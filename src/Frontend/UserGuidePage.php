<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Public, theme-less endpoint that serves the user guide as a full-page
 * HTML document. URL: `https://<site>/?reservas_guia=1`.
 *
 * Hooks into `template_redirect`, which fires *before* the theme renders
 * its header / admin bar / breadcrumbs. We read the static HTML shipped
 * inside the plugin, rewrite relative `assets/...` paths to absolute
 * `plugins_url()` paths so the logo and capturas resolve regardless of
 * the page URL, and `exit` — WordPress never gets to load the theme,
 * so the user sees only the guide.
 *
 * This is a complement to the `[reservas_aldealab_guia]` shortcode:
 * the shortcode embeds the guide *inside* a host page (with its theme
 * around), this endpoint shows it standalone.
 */
final class UserGuidePage {

    public const QUERY_VAR = 'reservas_guia';

    private const GUIDE_REL = 'docs/guia-usuario/guia-aldealab.html';

    public static function register(): void {
        add_action( 'template_redirect', array( self::class, 'maybeServe' ) );
    }

    public static function maybeServe(): void {
        if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
            return;
        }
        // Only act when the query var is truthy. Treats `?reservas_guia=0`
        // and empty string as "do nothing" so accidental link rewrites
        // don't trigger the endpoint.
        $val = sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) );
        if ( $val === '' || $val === '0' ) {
            return;
        }

        $path = RESERVAS_ALDEALAB_PATH . self::GUIDE_REL;
        if ( ! file_exists( $path ) ) {
            status_header( 404 );
            wp_die(
                esc_html__( 'La guía no está disponible en esta instalación.', 'reservas-aldealab' ),
                esc_html__( 'Guía no encontrada', 'reservas-aldealab' ),
                array( 'response' => 404 )
            );
        }

        $html = file_get_contents( $path );
        if ( $html === false ) {
            status_header( 500 );
            wp_die(
                esc_html__( 'No se pudo leer el documento de la guía.', 'reservas-aldealab' )
            );
        }

        // Rewrite relative asset paths so the logo and capturas load
        // regardless of the URL the visitor reached. The HTML in the
        // repo references everything as `assets/...` (relative).
        $assets_url = plugins_url( 'docs/guia-usuario/assets/', RESERVAS_ALDEALAB_FILE );
        $html       = str_replace( 'src="assets/', 'src="' . esc_url( $assets_url ), $html );
        $html       = str_replace( "src='assets/", "src='" . esc_url( $assets_url ), $html );
        $html       = str_replace( 'href="assets/', 'href="' . esc_url( $assets_url ), $html );

        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $html;
        exit;
    }
}
