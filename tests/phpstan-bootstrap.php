<?php
/**
 * PHPStan bootstrap: declares constants that WordPress would normally provide
 * so static analysis doesn't flag them as undefined.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wp/' );
}
if ( ! defined( 'RESERVAS_ALDEALAB_VERSION' ) ) {
    define( 'RESERVAS_ALDEALAB_VERSION', '0.1.0' );
}
if ( ! defined( 'RESERVAS_ALDEALAB_FILE' ) ) {
    define( 'RESERVAS_ALDEALAB_FILE', '/tmp/reservas-aldealab.php' );
}
if ( ! defined( 'RESERVAS_ALDEALAB_PATH' ) ) {
    define( 'RESERVAS_ALDEALAB_PATH', '/tmp/' );
}
if ( ! defined( 'RESERVAS_ALDEALAB_URL' ) ) {
    define( 'RESERVAS_ALDEALAB_URL', 'https://example.test/' );
}
if ( ! defined( 'RESERVAS_ALDEALAB_MIN_PHP' ) ) {
    define( 'RESERVAS_ALDEALAB_MIN_PHP', '7.4' );
}
if ( ! defined( 'RESERVAS_ALDEALAB_MIN_WP' ) ) {
    define( 'RESERVAS_ALDEALAB_MIN_WP', '6.0' );
}
