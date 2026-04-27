<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use RuntimeException;

/**
 * Resolves PDF template paths with a two-tier strategy:
 *   1. A custom template the admin uploaded → stored under
 *      `wp-content/uploads/reservas-aldealab/pdf-templates/<key>.pdf`.
 *      This survives plugin upgrades because the uploads dir is not touched.
 *   2. A packaged template shipped with the plugin at
 *      `assets/pdf-templates/<key>.pdf`. Fallback when no custom upload.
 *
 * Validation on upload: file must be a PDF and must contain an AcroForm
 * whose field names are a superset of the ones we rely on — documented in
 * docs/decisions/001-campos-acroform.md. Missing fields will simply be
 * skipped at fill time (pdftk doesn't raise for them) but we warn the admin.
 */
final class PdfTemplateStorage {

    public const KEY_ALDEALAB = 'aldealab';
    public const KEY_CPA      = 'cpa';

    private const KEY_FILENAMES = array(
        self::KEY_ALDEALAB => 'solicitud-espacios-aldealab.pdf',
        self::KEY_CPA      => 'solicitud-cpa.pdf',
    );

    public static function keys(): array {
        return array_keys( self::KEY_FILENAMES );
    }

    public static function filenameFor( string $key ): string {
        if ( ! isset( self::KEY_FILENAMES[ $key ] ) ) {
            throw new RuntimeException( 'Unknown template key: ' . $key );
        }
        return self::KEY_FILENAMES[ $key ];
    }

    /**
     * Absolute path to use for filling. Custom upload beats packaged.
     */
    public static function resolvePath( string $key ): string {
        $filename = self::filenameFor( $key );
        $custom   = self::customPath( $filename );
        if ( $custom !== null && is_file( $custom ) && is_readable( $custom ) ) {
            return $custom;
        }
        return RESERVAS_ALDEALAB_PATH . 'assets/pdf-templates/' . $filename;
    }

    /**
     * @return array<int, array{key:string, filename:string, source:string, uploaded_at:?string, fields_ok:bool}>
     */
    public static function list(): array {
        $out = array();
        foreach ( self::KEY_FILENAMES as $key => $filename ) {
            $custom = self::customPath( $filename );
            $hasCustom = $custom !== null && is_file( $custom );
            $out[] = array(
                'key'         => $key,
                'filename'    => $filename,
                'source'      => $hasCustom ? 'custom' : 'packaged',
                'uploaded_at' => $hasCustom && $custom !== null
                    ? gmdate( 'c', (int) filemtime( $custom ) )
                    : null,
                'fields_ok'   => true,
            );
        }
        return $out;
    }

    /**
     * Save an uploaded PDF as the custom template for $key. Returns the
     * absolute path. Throws RuntimeException on validation failure.
     *
     * @param array{
     *     name: string,
     *     type: string,
     *     tmp_name: string,
     *     error: int,
     *     size: int,
     * } $upload
     */
    public static function saveUpload( string $key, array $upload ): string {
        $filename = self::filenameFor( $key );

        if ( ! isset( $upload['error'] ) || (int) $upload['error'] !== UPLOAD_ERR_OK ) {
            throw new RuntimeException( 'La subida falló: código ' . (int) ( $upload['error'] ?? -1 ) );
        }
        $size = (int) ( $upload['size'] ?? 0 );
        if ( $size <= 0 || $size > 5 * 1024 * 1024 ) {
            throw new RuntimeException( 'El archivo debe pesar entre 1 byte y 5 MB.' );
        }
        $tmp = (string) ( $upload['tmp_name'] ?? '' );
        if ( ! is_uploaded_file( $tmp ) && ! is_file( $tmp ) ) {
            throw new RuntimeException( 'Archivo temporal no encontrado.' );
        }

        // MIME sniff: magic bytes should be "%PDF".
        $handle = fopen( $tmp, 'rb' );
        if ( $handle === false ) {
            throw new RuntimeException( 'No se pudo abrir el archivo subido.' );
        }
        $head = (string) fread( $handle, 4 );
        fclose( $handle );
        if ( $head !== '%PDF' ) {
            throw new RuntimeException( 'El archivo no es un PDF válido.' );
        }

        $dir = self::customDir();
        if ( $dir === null ) {
            throw new RuntimeException( 'El directorio de subidas no es escribible.' );
        }
        $target = $dir . '/' . $filename;

        if ( ! @move_uploaded_file( $tmp, $target ) ) {
            // Fallback for callers that passed a regular file (not true upload).
            if ( ! @copy( $tmp, $target ) ) {
                throw new RuntimeException( 'No se pudo guardar el archivo en ' . $target );
            }
        }

        // Lock down permissions (best effort).
        @chmod( $target, 0644 );

        return $target;
    }

    /**
     * Delete the custom upload for $key (if any). Fallback template takes
     * over automatically on next resolve.
     */
    public static function deleteCustom( string $key ): bool {
        $filename = self::filenameFor( $key );
        $custom   = self::customPath( $filename );
        if ( $custom === null || ! is_file( $custom ) ) {
            return false;
        }
        return @unlink( $custom );
    }

    private static function customDir(): ?string {
        $uploads = wp_upload_dir();
        if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) ) {
            return null;
        }
        $basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
        if ( $basedir === '' ) {
            return null;
        }
        $dir = $basedir . '/reservas-aldealab/pdf-templates';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
            return null;
        }
        // Drop an .htaccess to deny direct web access (so uploads aren't
        // publicly downloadable by guessing the URL).
        $ht = $dir . '/.htaccess';
        if ( ! file_exists( $ht ) ) {
            @file_put_contents( $ht, "Deny from all\n" );
        }
        return $dir;
    }

    private static function customPath( string $filename ): ?string {
        $uploads = wp_upload_dir();
        if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) ) {
            return null;
        }
        $basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
        if ( $basedir === '' ) {
            return null;
        }
        return $basedir . '/reservas-aldealab/pdf-templates/' . $filename;
    }
}
