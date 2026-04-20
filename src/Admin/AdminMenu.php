<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Admin;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Roles\RoleManager;

/**
 * Registers the top-level "Reservas" menu in wp-admin and renders the
 * container div that the React admin app mounts into.
 *
 * The actual UI is 100% React. PHP only renders the mount point + a fallback
 * message for users who disabled JavaScript.
 */
final class AdminMenu {

    public const SLUG = 'reservas-aldealab';

    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'registerMenus' ) );
    }

    public static function registerMenus(): void {
        add_menu_page(
            __( 'Reservas', 'reservas-aldealab' ),
            __( 'Reservas', 'reservas-aldealab' ),
            RoleManager::CAP_MANAGE,
            self::SLUG,
            array( self::class, 'renderPage' ),
            'dashicons-calendar-alt',
            25
        );

        // Quick link to the CPT list ("Salas") under the same menu.
        add_submenu_page(
            self::SLUG,
            __( 'Panel', 'reservas-aldealab' ),
            __( 'Panel', 'reservas-aldealab' ),
            RoleManager::CAP_MANAGE,
            self::SLUG,
            array( self::class, 'renderPage' )
        );
        add_submenu_page(
            self::SLUG,
            __( 'Salas', 'reservas-aldealab' ),
            __( 'Salas', 'reservas-aldealab' ),
            'edit_posts',
            'edit.php?post_type=sala'
        );
    }

    public static function renderPage(): void {
        ?>
        <div class="wrap">
            <div id="reservas-admin-app">
                <p><?php esc_html_e( 'Cargando panel…', 'reservas-aldealab' ); ?></p>
                <noscript>
                    <p>
                        <?php esc_html_e( 'El panel requiere JavaScript. Actívalo para continuar.', 'reservas-aldealab' ); ?>
                    </p>
                </noscript>
            </div>
        </div>
        <?php
    }
}
