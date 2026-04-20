<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation.
 *
 * Keep this side-effect-light: a deactivation is usually temporary, so we
 * DO NOT drop tables or delete user data here. See Uninstaller for that.
 */
final class Deactivator {

    public static function deactivate(): void {
        // Clear any scheduled single events our plugin enqueued.
        wp_clear_scheduled_hook( 'reservas_aldealab_send_notifications' );

        flush_rewrite_rules();
    }
}
