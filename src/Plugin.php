<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin orchestrator. Wires subsystems on `plugins_loaded`.
 *
 * Each subsystem (REST controllers, CPT registration, admin menu, etc.) owns
 * its own `register()` method; this class only composes them.
 */
final class Plugin {

    private static bool $booted = false;

    /**
     * Boot the plugin. Safe to call multiple times — idempotent.
     */
    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;

        load_plugin_textdomain(
            'reservas-aldealab',
            false,
            dirname( RESERVAS_ALDEALAB_BASENAME ) . '/languages'
        );

        // Run pending DB migrations on every admin pageload.
        add_action( 'admin_init', array( Database\MigrationRunner::class, 'maybeRun' ) );

        // Custom post type + taxonomies + meta.
        PostTypes\SalaCpt::register();
        if ( is_admin() ) {
            PostTypes\SalaMetabox::register();
        }

        // REST API (namespace reservas/v1).
        Rest\RestApi::register();

        // Subsystems wired in later phases:
        // - Admin\AdminMenu::register()
        // - Frontend\FormShortcode::register()
    }
}
