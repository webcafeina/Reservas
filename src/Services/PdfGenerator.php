<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use RuntimeException;

/**
 * Picks the template and builds the AcroForm field map for a booking, then
 * delegates the actual byte-level filling to a PdfFillerInterface impl.
 *
 * Real implementation lands in Phase 7 alongside the choice of engine
 * (FPDI vs FPDM). For now this class is a placeholder: it defines the
 * public surface BookingService will call into.
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
     * @param array<string, string> $fields AcroForm name → value.
     */
    public function generate( string $templateFile, array $fields ): string {
        $path = $this->templatesDir . ltrim( $templateFile, '/' );
        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            throw new RuntimeException( 'PDF template not found: ' . $path );
        }
        return $this->filler->fill( $path, $fields );
    }
}
