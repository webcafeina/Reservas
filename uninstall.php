<?php
/**
 * WordPress calls this file when the site admin clicks "Delete" on the plugin.
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$autoload = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
    return;
}

require_once $autoload;

\WebcafeinaReservas\Uninstaller::uninstall();
