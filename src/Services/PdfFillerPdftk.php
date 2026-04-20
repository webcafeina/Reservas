<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use RuntimeException;

/**
 * PdfFillerInterface implementation backed by the `pdftk` binary via
 * mikehaertl/php-pdftk.
 *
 * Requires `pdftk` (or the modern `pdftk-java` fork) to be installed on the
 * server. We probe for it at first use and cache the result.
 */
final class PdfFillerPdftk implements PdfFillerInterface {

    /**
     * @var string|null Absolute path to the binary, or null if unknown yet.
     */
    private ?string $binaryPath;

    public function __construct( ?string $binaryPath = null ) {
        $this->binaryPath = $binaryPath;
    }

    public function fill( string $templatePath, array $fields ): string {
        if ( ! is_file( $templatePath ) || ! is_readable( $templatePath ) ) {
            throw new RuntimeException( 'Plantilla PDF no encontrada o ilegible: ' . $templatePath );
        }

        if ( ! class_exists( \mikehaertl\pdftk\Pdf::class ) ) {
            throw new RuntimeException(
                'mikehaertl/php-pdftk no está instalado. Ejecuta `composer install`.'
            );
        }

        $options = array();
        if ( $this->binaryPath !== null ) {
            $options['command'] = $this->binaryPath;
        }

        $pdf = new \mikehaertl\pdftk\Pdf( $templatePath, $options );
        // need_appearances makes form fields render with a consistent font;
        // without it some viewers show empty cells.
        $pdf->fillForm( $fields )->needAppearances()->flatten();

        $bytes = $pdf->toString();
        if ( $bytes === false ) {
            $err = method_exists( $pdf, 'getError' ) ? (string) $pdf->getError() : 'desconocido';
            throw new RuntimeException( 'pdftk falló al rellenar el PDF: ' . $err );
        }
        return (string) $bytes;
    }

    /**
     * Best-effort check for the binary at boot time. Used by the admin
     * dashboard to warn when the dependency is missing.
     */
    public static function isAvailable( ?string $binaryPath = null ): bool {
        $cmd = $binaryPath ?? 'pdftk';
        // Escape and run `<cmd> --version`. If the return code is 0 we assume
        // it works. This deliberately does not use exec() if it's disabled —
        // fallback returns false in that case.
        if ( ! function_exists( 'shell_exec' ) ) {
            return false;
        }
        $safe   = escapeshellcmd( $cmd );
        $output = @shell_exec( $safe . ' --version 2>&1' );
        return is_string( $output ) && stripos( $output, 'pdftk' ) !== false;
    }
}
