<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin uninstall (from the WordPress "Delete" button).
 *
 * Data destruction here is destructive and permanent. We gate it behind a
 * site option (`reservas_aldealab_delete_data_on_uninstall`, default `false`)
 * so a careless uninstall doesn't wipe a year of reservations.
 *
 * The actual uninstall.php file at the plugin root delegates here.
 */
final class Uninstaller {

    public static function uninstall(): void {
        $delete = (bool) get_option( 'reservas_aldealab_delete_data_on_uninstall', false );
        if ( ! $delete ) {
            return;
        }

        // Drops tables and removes options. Implemented alongside DB migrations.
        Database\MigrationRunner::dropAll();

        Roles\RoleManager::removeRoles();

        delete_option( 'reservas_aldealab_db_version' );
        delete_option( 'reservas_aldealab_settings' );
        delete_option( 'reservas_aldealab_delete_data_on_uninstall' );
    }
}
