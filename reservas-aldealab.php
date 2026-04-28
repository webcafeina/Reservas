<?php
/**
 * Plugin Name:       Gestor de reservas de AldeaLab
 * Plugin URI:        https://webcafeina.com
 * Description:       Sistema autónomo de reservas de salas para Aldealab (Cáceres). Recurrencias RFC 5545, PDF oficial y notificaciones.
 * Version:           0.15.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Webcafeína
 * Author URI:        https://webcafeina.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       reservas-aldealab
 * Domain Path:       /languages
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'RESERVAS_ALDEALAB_VERSION', '0.15.0' );
define( 'RESERVAS_ALDEALAB_FILE', __FILE__ );
define( 'RESERVAS_ALDEALAB_PATH', plugin_dir_path( __FILE__ ) );
define( 'RESERVAS_ALDEALAB_URL', plugin_dir_url( __FILE__ ) );
define( 'RESERVAS_ALDEALAB_BASENAME', plugin_basename( __FILE__ ) );
define( 'RESERVAS_ALDEALAB_MIN_PHP', '7.4' );
define( 'RESERVAS_ALDEALAB_MIN_WP', '6.0' );

/**
 * Graceful fallback when vendor/autoload.php is missing (fresh checkout without
 * `composer install`). We surface an admin notice instead of fatal-ing.
 */
$reservas_aldealab_autoload = RESERVAS_ALDEALAB_PATH . 'vendor/autoload.php';
if ( ! file_exists( $reservas_aldealab_autoload ) ) {
    add_action(
        'admin_notices',
        static function (): void {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }
            echo '<div class="notice notice-error"><p><strong>Gestor de reservas de AldeaLab:</strong> ';
            esc_html_e(
                'Faltan dependencias de Composer. Ejecuta `composer install --no-dev --optimize-autoloader` en el directorio del plugin, o descarga el ZIP oficial de la release que ya las incluye.',
                'reservas-aldealab'
            );
            echo '</p></div>';
        }
    );
    return;
}

require_once $reservas_aldealab_autoload;

register_activation_hook( __FILE__, array( \WebcafeinaReservas\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WebcafeinaReservas\Deactivator::class, 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function (): void {
        \WebcafeinaReservas\Plugin::boot();
    }
);
