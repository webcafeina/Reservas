<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode [reservas_aldealab_guia] — embeds the user-facing booking guide
 * inside any WP page. The guide itself is the standalone HTML file in
 * `docs/guia-usuario/guia-aldealab.html`, served through an `<iframe>` so
 * its embedded CSS doesn't bleed into the host page's theme.
 *
 * The guide HTML carries `[VERSIÓN]` and `[FECHA]` placeholders that the
 * release workflow substitutes at build time before zipping; the file
 * shipped inside the plugin therefore already shows the right metadata
 * without any runtime processing.
 */
final class UserGuideShortcode {

    public const TAG = 'reservas_aldealab_guia';

    private const GUIDE_REL = 'docs/guia-usuario/guia-aldealab.html';

    public static function register(): void {
        add_shortcode( self::TAG, array( self::class, 'render' ) );
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public static function render( $atts = array() ): string {
        unset( $atts );

        $path = RESERVAS_ALDEALAB_PATH . self::GUIDE_REL;
        if ( ! file_exists( $path ) ) {
            return '<p>' . esc_html__( 'La guía no está disponible en esta instalación.', 'reservas-aldealab' ) . '</p>';
        }

        $url   = plugins_url( self::GUIDE_REL, RESERVAS_ALDEALAB_FILE );
        $title = esc_attr__( 'Guía de uso del formulario de reservas', 'reservas-aldealab' );

        // Cache-buster tied to the file's mtime so theme/browser caches
        // don't keep serving a stale version after a plugin update.
        $cache_buster = (string) filemtime( $path );

        return sprintf(
            '<iframe src="%1$s?v=%2$s" title="%3$s" loading="lazy" class="reservas-aldealab-guia-frame" style="display:block;width:100%%;border:0;min-height:90vh;background:#fff"></iframe>',
            esc_url( $url ),
            esc_attr( $cache_buster ),
            $title
        );
    }
}
