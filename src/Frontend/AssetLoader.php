<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Frontend;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Rest\RestApi;

/**
 * Enqueues the Vite-built frontend bundle.
 *
 * Production: reads `assets/dist/manifest.json`, enqueues the hashed JS +
 * CSS files that Vite emitted for `frontend/src/main.tsx`.
 *
 * Development: if the plugin setting `vite_dev_url` is filled in (e.g.
 * http://localhost:5173), we enqueue the Vite dev server entry directly
 * — HMR for free during local dev.
 */
final class AssetLoader {

    public const HANDLE_SCRIPT = 'reservas-aldealab-app';
    public const HANDLE_STYLE  = 'reservas-aldealab-app-style';

    public static function register(): void {
        add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
    }

    public static function enqueue(): void {
        // Only enqueue on pages that contain the shortcode. WP sets the
        // global $post; is_singular() plus has_shortcode gets us there.
        if ( ! self::shouldEnqueue() ) {
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

    private static function shouldEnqueue(): bool {
        global $post;
        if ( ! is_singular() || ! $post instanceof \WP_Post ) {
            return false;
        }
        return has_shortcode( $post->post_content, FormShortcode::TAG );
    }

    private static function devServerUrl(): ?string {
        $settings = (array) get_option( 'reservas_aldealab_settings', array() );
        $url      = isset( $settings['vite_dev_url'] ) ? trim( (string) $settings['vite_dev_url'] ) : '';
        if ( $url === '' ) {
            return null;
        }
        return rtrim( $url, '/' );
    }

    private static function enqueueDev( string $devUrl ): void {
        // Vite client for HMR.
        wp_enqueue_script(
            'reservas-vite-client',
            $devUrl . '/@vite/client',
            array(),
            (string) time(),
            false
        );
        wp_enqueue_script(
            self::HANDLE_SCRIPT,
            $devUrl . '/src/main.tsx',
            array(),
            (string) time(),
            true
        );
        // Dev scripts are ES modules.
        add_filter(
            'script_loader_tag',
            array( self::class, 'scriptAsModule' ),
            10,
            3
        );
    }

    private static function enqueueProduction(): void {
        $manifest_path = RESERVAS_ALDEALAB_PATH . 'assets/dist/.vite/manifest.json';
        if ( ! file_exists( $manifest_path ) ) {
            // Vite 4 manifest path was at root; Vite 5 moved it to .vite/.
            $manifest_path = RESERVAS_ALDEALAB_PATH . 'assets/dist/manifest.json';
        }
        if ( ! file_exists( $manifest_path ) ) {
            // No build yet — surface a clear notice to admins.
            if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
                add_action(
                    'wp_footer',
                    static function (): void {
                        echo '<!-- Reservas Aldealab: assets/dist/manifest.json no encontrado. Ejecuta `npm run build`. -->';
                    }
                );
            }
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

        // The entry name matches Vite's input key: 'app' (configured in vite.config.ts)
        // but Vite keys entries by relative input path — either 'app' or
        // 'src/main.tsx' depending on the config style used. Try both.
        $entry = null;
        foreach ( array( 'src/main.tsx', 'app', 'frontend/src/main.tsx' ) as $candidate ) {
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
                array(),
                RESERVAS_ALDEALAB_VERSION,
                true
            );
            add_filter(
                'script_loader_tag',
                array( self::class, 'scriptAsModule' ),
                10,
                3
            );
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

    /**
     * Adds `type="module"` attribute to our script tags.
     */
    public static function scriptAsModule( string $tag, string $handle, string $src ): string {
        if ( $handle !== self::HANDLE_SCRIPT && $handle !== 'reservas-vite-client' ) {
            return $tag;
        }
        if ( strpos( $tag, ' type=' ) !== false ) {
            return $tag;
        }
        return str_replace( ' src=', ' type="module" src=', $tag );
    }

    private static function localize(): void {
        $settings = (array) get_option( 'reservas_aldealab_settings', array() );
        $site_key = isset( $settings['turnstile_site_key'] ) ? (string) $settings['turnstile_site_key'] : '';

        $data = array(
            'restBase'         => esc_url_raw( rest_url( RestApi::NAMESPACE ) ),
            'nonce'            => wp_create_nonce( 'wp_rest' ),
            'turnstileSiteKey' => $site_key,
            'locale'           => determine_locale(),
            'isLoggedIn'       => is_user_logged_in(),
        );

        wp_add_inline_script(
            self::HANDLE_SCRIPT,
            'window.ReservasAldealab = ' . wp_json_encode( $data ) . ';',
            'before'
        );
    }
}
