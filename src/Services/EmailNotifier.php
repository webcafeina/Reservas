<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

defined( 'ABSPATH' ) || exit;

use Throwable;
use WebcafeinaReservas\Models\Booking;
use WebcafeinaReservas\Models\Sala;
use WebcafeinaReservas\Models\UserProfile;
use WebcafeinaReservas\PostTypes\SalaCpt;
use WebcafeinaReservas\PostTypes\SalaMeta;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Repositories\EmailLogRepository;
use WebcafeinaReservas\Repositories\UserProfileRepository;
use WebcafeinaReservas\Roles\RoleManager;
use WebcafeinaReservas\Services\Sms\SmsProviderFactory;

/**
 * Transactional emails: booking confirmation (user + admin) and
 * cancellation (user). HTML via per-template PHP files under
 * src/Emails/templates/, rendered through layout.php.
 *
 * PDF generation is gated:
 *   - Sala is CPA               → always attach CPA template
 *   - User has usuario_alojado  → skip PDF + skip Sede instructions
 *   - Otherwise                 → attach generic Aldealab template
 *
 * Hook handlers:
 *   - `reservas_aldealab_send_notifications`  (async, scheduled from
 *     BookingService): send user + admin confirmation.
 *   - `reservas_aldealab_booking_cancelled`   (fired by admin controller):
 *     send cancellation email.
 */
final class EmailNotifier {

    public const HOOK_ASYNC     = 'reservas_aldealab_send_notifications';
    public const HOOK_CANCELLED = 'reservas_aldealab_booking_cancelled';

    public static function register(): void {
        add_action( self::HOOK_ASYNC, array( self::class, 'handleAsync' ), 10, 1 );
        add_action( self::HOOK_CANCELLED, array( self::class, 'handleCancelled' ), 10, 1 );
    }

    /**
     * Hook target: `reservas_aldealab_send_notifications`.
     */
    public static function handleAsync( int $bookingId ): void {
        $ctx = self::loadContext( $bookingId );
        if ( $ctx === null ) {
            return;
        }

        $attachPdf = self::shouldAttachPdf( $ctx['booking'], $ctx['sala'] );
        $pdfPath   = null;
        if ( $attachPdf ) {
            $pdfPath = self::tryGeneratePdfFile(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                $ctx['log']
            );
        }

        try {
            ( new self() )->sendUserConfirmation(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                $pdfPath,
                $attachPdf
            );
            ( new self() )->sendAdminNotification(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                $pdfPath
            );
            ( new self() )->maybeSendSms(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                'confirmation'
            );
        } finally {
            if ( $pdfPath !== null && is_file( $pdfPath ) ) {
                @unlink( $pdfPath );
            }
        }
    }

    /**
     * Hook target: `reservas_aldealab_booking_cancelled`.
     */
    public static function handleCancelled( int $bookingId ): void {
        $ctx = self::loadContext( $bookingId );
        if ( $ctx === null ) {
            return;
        }
        ( new self() )->sendCancellation( $ctx['booking'], $ctx['profile'], $ctx['sala'] );
        ( new self() )->maybeSendSms(
            $ctx['booking'],
            $ctx['profile'],
            $ctx['sala'],
            'cancellation'
        );
    }

    /**
     * Opt-in SMS dispatch. No-op when no provider is configured. Logs every
     * attempt to reservas_email_log with tipo='sms'.
     */
    public function maybeSendSms(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        string $kind
    ): void {
        $provider = SmsProviderFactory::fromSettings();
        if ( ! $provider->isConfigured() ) {
            return;
        }
        if ( $profile->movil === '' ) {
            return;
        }

        $body = $kind === 'cancellation'
            ? sprintf(
                /* translators: 1: sala name, 2: date */
                __( 'Tu reserva en "%1$s" el %2$s ha sido cancelada. Aldealab.', 'reservas-aldealab' ),
                $sala->title,
                PdfGenerator::isoToDdMmYyyy( $booking->fechaInicio )
            )
            : sprintf(
                /* translators: 1: sala name, 2: first date, 3: start time */
                __( 'Reserva recibida: %1$s el %2$s a las %3$s. Aldealab.', 'reservas-aldealab' ),
                $sala->title,
                PdfGenerator::isoToDdMmYyyy( $booking->fechaInicio ),
                substr( $booking->horaInicio, 0, 5 )
            );

        $result = $provider->send( $profile->movil, $body );
        self::log()->record(
            $booking->id,
            'sms-' . $kind,
            $profile->movil,
            'SMS ' . $result['provider'],
            $result['success'] ? 'enviado' : 'error',
            $result['error']
        );
    }

    // ---------- Per-email senders ----------

    public function sendUserConfirmation(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        ?string $pdfPath,
        bool $includeSedeInstructions
    ): void {
        $title          = __( 'Confirmación de tu reserva en Aldealab', 'reservas-aldealab' );
        $fechas_humano  = self::formatDatesHuman( $booking );
        $header_url     = self::headerImageUrl();

        $incluye_sede = $includeSedeInstructions;

        $content_html = self::renderTemplate(
            'confirmation-user',
            compact( 'booking', 'profile', 'sala', 'fechas_humano', 'incluye_sede' )
        );
        $html = self::renderLayout( $title, $content_html, $header_url );

        $attachments = $pdfPath !== null ? array( $pdfPath ) : array();
        self::send(
            $profile->email,
            $title,
            $html,
            'usuario',
            $booking->id,
            $attachments
        );
    }

    public function sendAdminNotification(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        ?string $pdfPath
    ): void {
        $recipients = self::adminRecipients();
        if ( $recipients === array() ) {
            self::log()->record(
                $booking->id,
                'admin',
                '(ninguno)',
                'Nueva reserva',
                'omitido',
                'Sin destinatarios admin configurados.'
            );
            return;
        }

        $title         = sprintf(
            /* translators: %s sala title */
            __( 'Nueva reserva: %s', 'reservas-aldealab' ),
            $sala->title
        );
        $fechas_humano = self::formatDatesHuman( $booking );
        $admin_url     = self::adminDeepLink( $booking->id ?? 0 );
        $header_url    = self::headerImageUrl();

        $content_html = self::renderTemplate(
            'confirmation-admin',
            compact( 'booking', 'profile', 'sala', 'fechas_humano', 'admin_url' )
        );
        $html = self::renderLayout( $title, $content_html, $header_url );

        $attachments = $pdfPath !== null ? array( $pdfPath ) : array();
        foreach ( $recipients as $to ) {
            self::send( $to, $title, $html, 'admin', $booking->id, $attachments );
        }
    }

    public function sendCancellation(
        Booking $booking,
        UserProfile $profile,
        Sala $sala
    ): void {
        $title         = __( 'Tu reserva en Aldealab ha sido cancelada', 'reservas-aldealab' );
        $fechas_humano = self::formatDatesHuman( $booking );
        $header_url    = self::headerImageUrl();

        $content_html = self::renderTemplate(
            'cancellation-user',
            compact( 'booking', 'profile', 'sala', 'fechas_humano' )
        );
        $html = self::renderLayout( $title, $content_html, $header_url );

        self::send( $profile->email, $title, $html, 'cancelacion', $booking->id, array() );
    }

    // ---------- Helpers ----------

    /**
     * @return array{booking: Booking, profile: UserProfile, sala: Sala, log: EmailLogRepository}|null
     */
    private static function loadContext( int $bookingId ): ?array {
        global $wpdb;
        $bookings = new BookingRepository( $wpdb );
        $profiles = new UserProfileRepository( $wpdb );

        $booking = $bookings->find( $bookingId );
        if ( $booking === null || $booking->profileId === null ) {
            return null;
        }
        $profile = $profiles->findById( (int) $booking->profileId );
        if ( $profile === null ) {
            return null;
        }
        $salaPost = get_post( $booking->salaId );
        if ( ! $salaPost instanceof \WP_Post || $salaPost->post_type !== SalaCpt::POST_TYPE ) {
            return null;
        }
        $sala = \WebcafeinaReservas\Models\Sala::fromPost( $salaPost );

        return array(
            'booking' => $booking,
            'profile' => $profile,
            'sala'    => $sala,
            'log'     => new EmailLogRepository( $wpdb ),
        );
    }

    /**
     * Non-CPA sala + user has `usuario_alojado` role → no PDF. Otherwise, PDF.
     */
    public static function shouldAttachPdf( Booking $booking, Sala $sala ): bool {
        if ( $sala->esCpa ) {
            return true;
        }
        if ( $booking->userId === null ) {
            return true;
        }
        $user = get_userdata( (int) $booking->userId );
        if ( ! $user instanceof \WP_User ) {
            return true;
        }
        return ! in_array( RoleManager::ROLE_TENANT, (array) $user->roles, true );
    }

    /**
     * Generate the PDF to a temp file so we can attach it to wp_mail
     * (which takes file paths, not byte strings). Returns the path or null
     * on failure — a missing pdftk binary is not fatal; the email goes
     * without the PDF and we log the error.
     */
    private static function tryGeneratePdfFile(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        EmailLogRepository $log
    ): ?string {
        try {
            $settings = (array) get_option( 'reservas_aldealab_settings', array() );
            $binary   = isset( $settings['pdftk_path'] ) ? (string) $settings['pdftk_path'] : null;
            $filler   = new PdfFillerPdftk( $binary !== '' ? $binary : null );
            $generator = new PdfGenerator( $filler );
            $bytes    = $generator->generateForBooking( $booking, $profile, $sala );

            $path = wp_tempnam( 'reservas-aldealab-booking-' . ( $booking->id ?? 0 ) . '.pdf' );
            if ( ! is_string( $path ) || $path === '' ) {
                throw new \RuntimeException( 'No se pudo crear archivo temporal.' );
            }
            file_put_contents( $path, $bytes );
            return $path;
        } catch ( Throwable $e ) {
            $log->record(
                $booking->id,
                'pdf-error',
                $profile->email,
                'PDF no generado',
                'error',
                $e->getMessage()
            );
            return null;
        }
    }

    private static function send(
        string $to,
        string $subject,
        string $html,
        string $tipo,
        ?int $bookingId,
        array $attachments
    ): void {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        $sent = wp_mail( $to, $subject, $html, $headers, $attachments );

        self::log()->record(
            $bookingId,
            $tipo,
            $to,
            $subject,
            $sent ? 'enviado' : 'error',
            $sent ? null : 'wp_mail() devolvió false'
        );
    }

    private static function log(): EmailLogRepository {
        global $wpdb;
        return new EmailLogRepository( $wpdb );
    }

    /**
     * @return array<int, string>
     */
    private static function adminRecipients(): array {
        $settings = (array) get_option( 'reservas_aldealab_settings', array() );
        $list     = isset( $settings['admin_emails'] ) ? $settings['admin_emails'] : array();
        if ( is_string( $list ) ) {
            $list = array_map( 'trim', explode( ',', $list ) );
        }
        if ( ! is_array( $list ) ) {
            return array();
        }
        $out = array();
        foreach ( $list as $email ) {
            $email = sanitize_email( (string) $email );
            if ( $email !== '' && is_email( $email ) ) {
                $out[] = $email;
            }
        }
        return array_values( array_unique( $out ) );
    }

    private static function adminDeepLink( int $bookingId ): string {
        return admin_url( 'admin.php?page=reservas-aldealab&booking=' . $bookingId );
    }

    private static function headerImageUrl(): string {
        return RESERVAS_ALDEALAB_URL . 'assets/email/header.png';
    }

    private static function formatDatesHuman( Booking $booking ): string {
        if ( $booking->fechas === array() ) {
            return PdfGenerator::isoToDdMmYyyy( $booking->fechaInicio );
        }
        $parts = array();
        foreach ( $booking->fechas as $iso ) {
            $parts[] = PdfGenerator::isoToDdMmYyyy( (string) $iso );
        }
        if ( count( $parts ) > 6 ) {
            $extra = count( $parts ) - 6;
            return implode( ', ', array_slice( $parts, 0, 6 ) )
                . sprintf( ' (+%d más)', $extra );
        }
        return implode( ', ', $parts );
    }

    /**
     * @param array<string, mixed> $vars
     */
    private static function renderTemplate( string $name, array $vars ): string {
        $path = __DIR__ . '/../Emails/templates/' . $name . '.php';
        if ( ! is_file( $path ) ) {
            return '';
        }
        extract( $vars, EXTR_SKIP );
        $content_html = require $path; // each template returns a string
        return is_string( $content_html ) ? $content_html : '';
    }

    private static function renderLayout(
        string $title,
        string $content_html,
        string $header_image_url
    ): string {
        $path = __DIR__ . '/../Emails/templates/layout.php';
        ob_start();
        // layout.php is an HTML document — it echoes. We buffer.
        include $path;
        return (string) ob_get_clean();
    }
}
