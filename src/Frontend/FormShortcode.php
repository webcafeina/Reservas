<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode [reservas_aldealab_formulario] — renders the mount div for the
 * React SPA. Script + styles come from AssetLoader (which only enqueues
 * when it detects this shortcode in the page content).
 */
final class FormShortcode {

    public const TAG = 'reservas_aldealab_formulario';

    public static function register(): void {
        add_shortcode( self::TAG, array( self::class, 'render' ) );
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public static function render( $atts = array() ): string {
        unset( $atts );

        // Just the mount point. React hydrates from here.
        $fallback = esc_html__(
            'Cargando formulario de reservas…',
            'reservas-aldealab'
        );
        return sprintf(
            '<div id="reservas-app"><noscript>%s</noscript><p class="reservas-app-loading">%s</p></div>',
            esc_html__( 'Activa JavaScript para usar el formulario de reservas.', 'reservas-aldealab' ),
            $fallback
        );
    }
}
