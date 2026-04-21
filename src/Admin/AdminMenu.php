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
        // Order of submenus under "Reservas" is enforced via hook priority:
        //   Priority 9  — we add the top-level menu + "Panel de control".
        //   Priority 10 — WP core's `_add_post_type_submenus()` appends the
        //                 CPT's own submenu ("Salas reservables", label set
        //                 in SalaCpt labels.menu_name).
        //   Priority 11 — we append the taxonomy submenus (Edificios,
        //                 Servicios).
        // Result in the sidebar: Panel de control → Salas reservables →
        // Edificios → Servicios, which is what the client asked for.
        add_action( 'admin_menu', array( self::class, 'registerPanel' ), 9 );
        add_action( 'admin_menu', array( self::class, 'registerTaxonomySubmenus' ), 11 );
    }

    public static function registerPanel(): void {
        add_menu_page(
            __( 'Reservas', 'reservas-aldealab' ),
            __( 'Reservas', 'reservas-aldealab' ),
            RoleManager::CAP_MANAGE,
            self::SLUG,
            array( self::class, 'renderPage' ),
            'dashicons-calendar-alt',
            25
        );

        // Rename the auto-generated first submenu from "Reservas" to
        // "Panel de control". Because this add_submenu_page uses the parent
        // slug as its own slug, WP merges it with the auto-generated entry.
        add_submenu_page(
            self::SLUG,
            __( 'Panel de control', 'reservas-aldealab' ),
            __( 'Panel de control', 'reservas-aldealab' ),
            RoleManager::CAP_MANAGE,
            self::SLUG,
            array( self::class, 'renderPage' )
        );
    }

    public static function registerTaxonomySubmenus(): void {
        // WP's `_add_post_type_submenus()` only contributes the CPT's "All
        // items" submenu when `show_in_menu` is a parent slug — taxonomy
        // admin pages are NOT auto-registered. We add them explicitly.
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
