<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

defined( 'ABSPATH' ) || exit;

use RuntimeException;

/**
 * Storage layer for the admin panel logo.
 *
 * Two-tier resolution, in order:
 *   1. Customer-uploaded file at
 *      `wp-content/uploads/reservas-aldealab/admin-logo.<ext>`. Survives
 *      plugin updates because WP never touches the uploads dir.
 *   2. Bundled fallback at `assets/admin/logo.<ext>` inside the plugin.
 *      Only relevant if a future release ships a default logo.
 *
 * Accepted extensions: `svg` and `png`. SVG wins over PNG when both are
 * present in the same tier (cleaner scaling on high-DPI displays).
 */
final class AdminLogoStorage {

    public const FILE_BASENAME = 'admin-logo';

    /**
     * @return array<int, string>
     */
    public static function allowedExtensions(): array {
        return array( 'svg', 'png' );
    }

    /**
     * Returns the URL of the active logo (uploads first, then bundled).
     */
    public static function resolveUrl(): ?string {
        foreach ( self::allowedExtensions() as $ext ) {
            $custom = self::customPath( $ext );
            if ( $custom !== null && is_file( $custom ) && is_readable( $custom ) ) {
                $url = self::customUrl( $ext );
                if ( $url !== null ) {
                    // Append mtime as a cache buster so reuploaded logos
                    // appear immediately even when the browser cached the
                    // previous file under the same URL.
                    return $url . '?v=' . (string) filemtime( $custom );
                }
            }
        }
        foreach ( self::allowedExtensions() as $ext ) {
            $bundled = RESERVAS_ALDEALAB_PATH . 'assets/admin/logo.' . $ext;
            if ( is_file( $bundled ) ) {
                return RESERVAS_ALDEALAB_URL . 'assets/admin/logo.' . $ext;
            }
        }
        return null;
    }

    /**
     * Reports the active logo plus where it lives, for the admin UI.
     *
     * @return array{url: ?string, source: 'uploads'|'packaged'|null, uploaded_at: ?string}
     */
    public static function status(): array {
        // Customer upload first.
        foreach ( self::allowedExtensions() as $ext ) {
            $custom = self::customPath( $ext );
            if ( $custom !== null && is_file( $custom ) && is_readable( $custom ) ) {
                $url = self::customUrl( $ext );
                if ( $url !== null ) {
                    return array(
                        'url'         => $url . '?v=' . (string) filemtime( $custom ),
                        'source'      => 'uploads',
                        'uploaded_at' => gmdate( 'c', (int) filemtime( $custom ) ),
                    );
                }
            }
        }
        // Bundled fallback.
        foreach ( self::allowedExtensions() as $ext ) {
            $bundled = RESERVAS_ALDEALAB_PATH . 'assets/admin/logo.' . $ext;
            if ( is_file( $bundled ) ) {
                return array(
                    'url'         => RESERVAS_ALDEALAB_URL . 'assets/admin/logo.' . $ext,
                    'source'      => 'packaged',
                    'uploaded_at' => null,
                );
            }
        }
        return array( 'url' => null, 'source' => null, 'uploaded_at' => null );
    }

    /**
     * Persists an uploaded logo. Validates the file before moving it into
     * the uploads dir and removes any previously stored logo with a
     * different extension so we never have stale orphans.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $upload
     * @return string Absolute path of the saved file.
     */
    public static function saveUpload( array $upload ): string {
        if ( ! isset( $upload['error'] ) || (int) $upload['error'] !== UPLOAD_ERR_OK ) {
            throw new RuntimeException( 'La subida falló: código ' . (int) ( $upload['error'] ?? -1 ) );
        }
        $size = (int) ( $upload['size'] ?? 0 );
        if ( $size <= 0 || $size > 2 * 1024 * 1024 ) {
            throw new RuntimeException( 'El logo debe pesar entre 1 byte y 2 MB.' );
        }
        $tmp = (string) ( $upload['tmp_name'] ?? '' );
        if ( ! is_uploaded_file( $tmp ) && ! is_file( $tmp ) ) {
            throw new RuntimeException( 'Archivo temporal no encontrado.' );
        }

        $name = (string) ( $upload['name'] ?? '' );
        $ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, self::allowedExtensions(), true ) ) {
            throw new RuntimeException( 'Solo se aceptan archivos .svg o .png.' );
        }

        // Magic-byte sanity check so a renamed PHP/HTML can't slip through.
        $handle = fopen( $tmp, 'rb' );
        if ( $handle === false ) {
            throw new RuntimeException( 'No se pudo abrir el archivo subido.' );
        }
        $head = (string) fread( $handle, 16 );
        fclose( $handle );
        if ( $ext === 'png' ) {
            // PNG files start with the 8-byte PNG signature: 89 50 4E 47 0D 0A 1A 0A.
            if ( substr( $head, 0, 8 ) !== "\x89PNG\r\n\x1A\n" ) {
                throw new RuntimeException( 'El archivo no es un PNG válido.' );
            }
        } else {
            // SVG: must look like XML and contain "<svg" within the first KB.
            // We only check the head for size/perf; the rest is trust.
            $headLower = strtolower( $head );
            if ( strpos( $headLower, '<?xml' ) !== 0 && strpos( $headLower, '<svg' ) === false ) {
                // Re-read a larger chunk for the <svg> sniff.
                $h = fopen( $tmp, 'rb' );
                $bigger = $h !== false ? strtolower( (string) fread( $h, 1024 ) ) : '';
                if ( $h !== false ) {
                    fclose( $h );
                }
                if ( strpos( $bigger, '<svg' ) === false ) {
                    throw new RuntimeException( 'El archivo no parece un SVG válido.' );
                }
            }
        }

        $dir = self::customDir();
        if ( $dir === null ) {
            throw new RuntimeException( 'El directorio de subidas no es escribible.' );
        }
        // Wipe any prior logo with a different extension so resolution stays
        // deterministic and we don't accumulate orphans.
        foreach ( self::allowedExtensions() as $other ) {
            if ( $other === $ext ) {
                continue;
            }
            $stale = $dir . '/' . self::FILE_BASENAME . '.' . $other;
            if ( is_file( $stale ) ) {
                @unlink( $stale ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
        }

        $target = $dir . '/' . self::FILE_BASENAME . '.' . $ext;
        if ( ! @move_uploaded_file( $tmp, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( ! @copy( $tmp, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                throw new RuntimeException( 'No se pudo guardar el logo en ' . $target );
            }
        }
        @chmod( $target, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        return $target;
    }

    /**
     * Removes the customer-uploaded logo (any extension). The bundled
     * fallback is left alone — it's part of the plugin code.
     */
    public static function deleteCustom(): bool {
        $deletedAny = false;
        foreach ( self::allowedExtensions() as $ext ) {
            $custom = self::customPath( $ext );
            if ( $custom !== null && is_file( $custom ) ) {
                if ( @unlink( $custom ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                    $deletedAny = true;
                }
            }
        }
        return $deletedAny;
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
        $dir = $basedir . '/reservas-aldealab';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
            return null;
        }
        return $dir;
    }

    private static function customPath( string $ext ): ?string {
        $uploads = wp_upload_dir();
        if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) ) {
            return null;
        }
        $basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
        if ( $basedir === '' ) {
            return null;
        }
        return $basedir . '/reservas-aldealab/' . self::FILE_BASENAME . '.' . $ext;
    }

    private static function customUrl( string $ext ): ?string {
        $uploads = wp_upload_dir();
        if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) ) {
            return null;
        }
        $baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
        if ( $baseurl === '' ) {
            return null;
        }
        return $baseurl . '/reservas-aldealab/' . self::FILE_BASENAME . '.' . $ext;
    }
}
