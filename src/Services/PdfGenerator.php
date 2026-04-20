<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use RuntimeException;
use WebcafeinaReservas\Models\Booking;
use WebcafeinaReservas\Models\Sala;
use WebcafeinaReservas\Models\UserProfile;

/**
 * Picks a template and builds the AcroForm field map for a booking, then
 * delegates byte-level filling to {@see PdfFillerInterface}.
 *
 * Template selection is driven by Sala::$esCpa (set from the _es_cpa meta).
 *
 *   - CPA  → solicitud-cpa.pdf, plus the matching sub-space column (Plató TV,
 *            Sala de Control, TV Lab, Equipos móviles) determined by the
 *            sala's post_name.
 *   - Rest → solicitud-espacios-aldealab.pdf (generic template).
 */
final class PdfGenerator {

    public const TEMPLATE_ALDEALAB = 'solicitud-espacios-aldealab.pdf';
    public const TEMPLATE_CPA      = 'solicitud-cpa.pdf';

    private PdfFillerInterface $filler;
    private string $templatesDir;

    public function __construct( PdfFillerInterface $filler, ?string $templatesDir = null ) {
        $this->filler       = $filler;
        $this->templatesDir = $templatesDir ?? RESERVAS_ALDEALAB_PATH . 'assets/pdf-templates/';
    }

    /**
     * Low-level: generate a PDF given a template filename and a precomputed
     * field map. Used by tests and by {@see self::generateForBooking}.
     *
     * @param array<string, string> $fields
     */
    public function generate( string $templateFile, array $fields ): string {
        $path = $this->templatesDir . ltrim( $templateFile, '/' );
        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            throw new RuntimeException( 'PDF template not found: ' . $path );
        }
        return $this->filler->fill( $path, $fields );
    }

    /**
     * High-level: generate the PDF for a specific booking + profile + sala.
     * Returns the raw PDF bytes. Caller attaches them to an email or streams
     * them to HTTP.
     */
    public function generateForBooking(
        Booking $booking,
        UserProfile $profile,
        Sala $sala
    ): string {
        if ( $sala->esCpa ) {
            $template = self::TEMPLATE_CPA;
            $fields   = $this->buildCpaFields( $booking, $profile, $sala );
        } else {
            $template = self::TEMPLATE_ALDEALAB;
            $fields   = $this->buildAldealabFields( $booking, $profile, $sala );
        }
        return $this->generate( $template, $fields );
    }

    /**
     * @return array<string, string>
     */
    private function buildAldealabFields( Booking $booking, UserProfile $profile, Sala $sala ): array {
        $dates = $this->formatDates( $booking );
        $horas = $this->formatHours( $booking );

        $fields = array(
            PdfFields::NIF                => $profile->nif,
            PdfFields::NOMBRE             => $this->fullName( $profile ),
            PdfFields::DIRECCION_VIA      => $profile->via,
            PdfFields::DIRECCION_NUMERO   => $profile->numero,
            PdfFields::DIRECCION_LETRA    => $profile->letra ?? '',
            PdfFields::DIRECCION_ESCALERA => $profile->escalera ?? '',
            PdfFields::DIRECCION_PISO     => $profile->piso ?? '',
            PdfFields::DIRECCION_PUERTA   => $profile->puerta ?? '',
            PdfFields::MUNICIPIO          => $profile->municipio,
            PdfFields::PROVINCIA          => $profile->provincia,
            PdfFields::CODIGO_POSTAL      => $profile->codigoPostal,
            PdfFields::TELEFONO           => $profile->telefonoFijo ?? '',
            PdfFields::MOVIL              => $profile->movil,
            PdfFields::EMAIL              => $profile->email,
            PdfFields::FAX                => '',
            PdfFields::SALA               => $sala->title,
            PdfFields::FECHAS             => $dates,
            PdfFields::HORAS              => $horas,
            PdfFields::OBJETO             => $booking->objetoReserva,
            PdfFields::CACERES            => $this->todayHuman(),
            PdfFields::CACERES_2          => $this->todayHuman(),
            PdfFields::FDO                => $this->fullName( $profile ),
            PdfFields::FDO_2              => $this->fullName( $profile ),
        );

        return $this->removeEmpties( $fields );
    }

    /**
     * @return array<string, string>
     */
    private function buildCpaFields( Booking $booking, UserProfile $profile, Sala $sala ): array {
        $dates = $this->formatDates( $booking );
        $horas = $this->formatHours( $booking );

        $fields = array(
            PdfFields::NIF                => $profile->nif,
            PdfFields::NOMBRE             => $this->fullName( $profile ),
            PdfFields::DIRECCION_VIA      => $profile->via,
            PdfFields::DIRECCION_NUMERO   => $profile->numero,
            PdfFields::DIRECCION_LETRA    => $profile->letra ?? '',
            PdfFields::DIRECCION_ESCALERA => $profile->escalera ?? '',
            PdfFields::DIRECCION_PISO     => $profile->piso ?? '',
            PdfFields::DIRECCION_PUERTA   => $profile->puerta ?? '',
            PdfFields::MUNICIPIO          => $profile->municipio,
            PdfFields::PROVINCIA          => $profile->provincia,
            PdfFields::CODIGO_POSTAL      => $profile->codigoPostal,
            PdfFields::TELEFONO           => $profile->telefonoFijo ?? '',
            PdfFields::MOVIL              => $profile->movil,
            PdfFields::EMAIL              => $profile->email,
            PdfFields::OBJETO             => $booking->objetoReserva,

            // Signer blocks — duplicated across the two CPA pages.
            PdfFields::FDO                => $this->fullName( $profile ),
            PdfFields::FDO_2              => $this->fullName( $profile ),
            PdfFields::CPA_NIF_CIF        => $profile->nif,
            PdfFields::CPA_NIF_CIF_2      => $profile->nif,
            PdfFields::CPA_INTERESADA     => $this->fullName( $profile ),
            PdfFields::CPA_INTERESADA_2   => $this->fullName( $profile ),
        );

        // Mark the sub-space this booking belongs to.
        $prefix = self::resolveCpaPrefix( $sala->slug, $sala->title );
        if ( $prefix !== null ) {
            $fields[ $prefix['checkbox'] ] = 'X';
            $fields[ $prefix['fechas'] ]   = $dates;
            $fields[ $prefix['horas'] ]    = $horas;
        }

        return $this->removeEmpties( $fields );
    }

    /**
     * Public so it can be reused (and unit-tested) without constructing a
     * full PdfGenerator.
     *
     * @return array{checkbox:string, fechas:string, horas:string}|null
     */
    public static function resolveCpaPrefix( string $slug, string $title = '' ): ?array {
        $haystack = strtolower( $slug . ' ' . $title );
        foreach ( PdfFields::cpaPrefixes() as $needle => $block ) {
            if ( strpos( $haystack, $needle ) !== false ) {
                return $block;
            }
        }
        return null;
    }

    private function fullName( UserProfile $profile ): string {
        return $profile->fullName();
    }

    private function formatDates( Booking $booking ): string {
        if ( $booking->fechas === array() ) {
            return $booking->fechaInicio;
        }
        $formatted = array();
        foreach ( $booking->fechas as $iso ) {
            $formatted[] = self::isoToDdMmYyyy( $iso );
        }
        // Keep it compact: first 6 dates, then "(+N más)" if we overflow.
        if ( count( $formatted ) > 6 ) {
            $extra = count( $formatted ) - 6;
            return implode( ', ', array_slice( $formatted, 0, 6 ) )
                . sprintf( ' (+%d más)', $extra );
        }
        return implode( ', ', $formatted );
    }

    private function formatHours( Booking $booking ): string {
        $inicio = substr( $booking->horaInicio, 0, 5 );
        $fin    = substr( $booking->horaFin, 0, 5 );
        return $inicio . ' - ' . $fin;
    }

    private function todayHuman(): string {
        // Non-localised to avoid relying on setlocale(); the template is in
        // Spanish and the day/month formats are unambiguous.
        $now = current_time( 'j' ) . ' de ' . self::monthNameEs( (int) current_time( 'n' ) )
            . ' de ' . current_time( 'Y' );
        return $now;
    }

    public static function isoToDdMmYyyy( string $iso ): string {
        $m = array();
        if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m ) === 1 ) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return $iso;
    }

    public static function monthNameEs( int $n ): string {
        $months = array(
            1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        );
        return $months[ $n ] ?? '';
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, string>
     */
    private function removeEmpties( array $fields ): array {
        $out = array();
        foreach ( $fields as $k => $v ) {
            if ( $v !== '' ) {
                $out[ $k ] = $v;
            }
        }
        return $out;
    }
}
