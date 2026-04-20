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
            return;
        }

        $contents = file_get_contents( $manifest_path );
        if ( ! is_string( $contents ) ) {
            return;
        }
        $manifest = json_decode( $contents, true );
        if ( ! is_array( $manifest ) ) {
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

        if ( isset( $entry['css'] ) && is_array( $entry['css'] ) ) {
            foreach ( $entry['css'] as $idx => $css ) {
                wp_enqueue_style(
                    self::HANDLE_STYLE . '-' . $idx,
                    $base . (string) $css,
                    array(),
                    RESERVAS_ALDEALAB_VERSION
                );
            }
        }
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
        );

        wp_add_inline_script(
            self::HANDLE_SCRIPT,
            'window.ReservasAldealabAdmin = ' . wp_json_encode( $data ) . ';',
            'before'
        );
    }
}
