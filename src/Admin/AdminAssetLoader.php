<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Admin;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Rest\RestApi;

/**
 * Enqueues the admin-specific Vite bundle (entry: `admin/main.tsx`).
 *
 * Loaded only on the plugin's admin pages (`toplevel_page_reservas-aldealab`),
 * never on the public site nor on other wp-admin screens.
 */
final class AdminAssetLoader {

    public const HANDLE_SCRIPT = 'reservas-aldealab-admin';
    public const HANDLE_STYLE  = 'reservas-aldealab-admin-style';

    public static function register(): void {
        add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
    }

    public static function enqueue( string $hook_suffix ): void {
        if ( strpos( $hook_suffix, AdminMenu::SLUG ) === false ) {
            return;
        }

        $dev_url = self::devServerUrl();
        if ( $dev_url !== null ) {
            self::enqueueDev( $dev_url );
        } else {
            self::enqueueProduction();
        }

        self::localize();
    }

    private static function devServerUrl(): ?string {
        $settings = SettingsRegistrar::get();
        $url      = (string) ( $settings[ SettingsRegistrar::KEY_VITE_DEV_URL ] ?? '' );
        return $url === '' ? null : rtrim( $url, '/' );
    }

    private static function enqueueDev( string $devUrl ): void {
        wp_enqueue_script(
            'reservas-vite-client-admin',
            $devUrl . '/@vite/client',
            array(),
            (string) time(),
            false
        );
        wp_enqueue_script(
            self::HANDLE_SCRIPT,
            $devUrl . '/admin/main.tsx',
            array( 'wp-element' ),
            (string) time(),
            true
        );
        add_filter( 'script_loader_tag', array( self::class, 'scriptAsModule' ), 10, 3 );
    }

    private static function enqueueProduction(): void {
        $manifest_path = RESERVAS_ALDEALAB_PATH . 'assets/dist/.vite/manifest.json';
        if ( ! file_exists( $manifest_path ) ) {
            $manifest_path = RESERVAS_ALDEALAB_PATH . 'assets/dist/manifest.json';
        }
        if ( ! file_exists( $manifest_path ) ) {
            self::noticeMissingBuild();
            return;
        }

        $contents = file_get_contents( $manifest_path );
        if ( ! is_string( $contents ) ) {
            self::noticeMissingBuild();
            return;
        }
        $manifest = json_decode( $contents, true );
        if ( ! is_array( $manifest ) ) {
            self::noticeMissingBuild();
            return;
        }

        $entry = null;
        foreach ( array( 'admin/main.tsx', 'admin', 'frontend/admin/main.tsx' ) as $candidate ) {
            if ( isset( $manifest[ $candidate ] ) && is_array( $manifest[ $candidate ] ) ) {
                $entry = $manifest[ $candidate ];
                break;
            }
        }
        if ( $entry === null ) {
            self::noticeMissingBuild();
            return;
        }

        $base = RESERVAS_ALDEALAB_URL . 'assets/dist/';

        if ( isset( $entry['file'] ) ) {
            wp_enqueue_script(
                self::HANDLE_SCRIPT,
                $base . (string) $entry['file'],
                array( 'wp-element' ),
                RESERVAS_ALDEALAB_VERSION,
                true
            );
            add_filter( 'script_loader_tag', array( self::class, 'scriptAsModule' ), 10, 3 );
        }

        // Entry's own CSS + CSS from any imported chunk (Vite splits shared
        // design tokens into their own chunk that also carries CSS).
        $css_files = array();
        if ( isset( $entry['css'] ) && is_array( $entry['css'] ) ) {
            $css_files = array_merge( $css_files, $entry['css'] );
        }
        if ( isset( $entry['imports'] ) && is_array( $entry['imports'] ) ) {
            foreach ( $entry['imports'] as $imported_key ) {
                if ( isset( $manifest[ $imported_key ]['css'] ) && is_array( $manifest[ $imported_key ]['css'] ) ) {
                    $css_files = array_merge( $css_files, $manifest[ $imported_key ]['css'] );
                }
            }
        }
        foreach ( array_values( array_unique( $css_files ) ) as $idx => $css ) {
            wp_enqueue_style(
                self::HANDLE_STYLE . '-' . $idx,
                $base . (string) $css,
                array(),
                RESERVAS_ALDEALAB_VERSION
            );
        }
    }

    private static function noticeMissingBuild(): void {
        add_action(
            'admin_notices',
            static function (): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                    return;
                }
                echo '<div class="notice notice-error"><p><strong>Gestor de reservas de AldeaLab:</strong> ';
                esc_html_e(
                    'No se encontró assets/dist/manifest.json. El panel no puede cargar. Instala el ZIP oficial de la release (no uses "Download ZIP" del repo) o ejecuta `npm run build`.',
                    'reservas-aldealab'
                );
                echo '</p></div>';
            }
        );
    }

    public static function scriptAsModule( string $tag, string $handle, string $src ): string {
        if ( $handle !== self::HANDLE_SCRIPT && $handle !== 'reservas-vite-client-admin' ) {
            return $tag;
        }
        if ( strpos( $tag, ' type=' ) !== false ) {
            return $tag;
        }
        return str_replace( ' src=', ' type="module" src=', $tag );
    }

    private static function localize(): void {
        $data = array(
            'restBase'   => esc_url_raw( rest_url( RestApi::NAMESPACE ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'locale'     => determine_locale(),
            'adminUrl'   => esc_url_raw( admin_url( 'admin.php?page=' . AdminMenu::SLUG ) ),
            'logoUrl'    => self::resolveLogoUrl(),
        );

        wp_add_inline_script(
            self::HANDLE_SCRIPT,
            'window.ReservasAldealabAdmin = ' . wp_json_encode( $data ) . ';',
            'before'
        );
    }

    /**
     * Resolves the URL of the active admin logo. Delegated to
     * `AdminLogoStorage` which checks the customer-uploaded file in
     * `wp-content/uploads/reservas-aldealab/` first (survives plugin
     * updates) and falls back to a logo bundled in `assets/admin/`.
     * Returns null when neither exists.
     */
    private static function resolveLogoUrl(): ?string {
        return \WebcafeinaReservas\Services\AdminLogoStorage::resolveUrl();
    }
}
