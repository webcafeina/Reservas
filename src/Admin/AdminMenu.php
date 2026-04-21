<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Admin;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\PostTypes\SalaCpt;
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
        // Priority 9 (before WP's default 10) so our "Panel" submenu is
        // registered BEFORE WP's `_add_post_type_submenus()` appends the
        // CPT's "Todas las salas". WP uses the URL of the first submenu as
        // the top-level menu's click target — we want Panel to be first.
        add_action( 'admin_menu', array( self::class, 'registerMenus' ), 9 );
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

        // Rename the auto-generated first submenu from "Reservas" to "Panel".
        add_submenu_page(
            self::SLUG,
            __( 'Panel', 'reservas-aldealab' ),
            __( 'Panel', 'reservas-aldealab' ),
            RoleManager::CAP_MANAGE,
            self::SLUG,
            array( self::class, 'renderPage' )
        );

        // When a CPT uses `show_in_menu => '<parent-slug>'`, WP's
        // `_add_post_type_submenus()` only contributes the "All items"
        // submenu — Add New and taxonomy admin pages are NOT auto-registered.
        // We add them explicitly to keep the menu complete.
        add_submenu_page(
            self::SLUG,
            __( 'Añadir nueva sala', 'reservas-aldealab' ),
            __( 'Añadir nueva', 'reservas-aldealab' ),
            'edit_posts',
            'post-new.php?post_type=' . SalaCpt::POST_TYPE
        );
        add_submenu_page(
            self::SLUG,
            __( 'Edificios', 'reservas-aldealab' ),
            __( 'Edificios', 'reservas-aldealab' ),
            'manage_categories',
            'edit-tags.php?taxonomy=' . SalaCpt::TAX_EDIFICIO . '&post_type=' . SalaCpt::POST_TYPE
        );
        add_submenu_page(
            self::SLUG,
            __( 'Servicios', 'reservas-aldealab' ),
            __( 'Servicios', 'reservas-aldealab' ),
            'manage_categories',
            'edit-tags.php?taxonomy=' . SalaCpt::TAX_SERVICIOS . '&post_type=' . SalaCpt::POST_TYPE
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
