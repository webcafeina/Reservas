<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation.
 *
 * Responsibilities: environment checks, DB migrations, role creation.
 * Anything here must be idempotent.
 */
final class Activator {

    public static function activate(): void {
        self::guardEnvironment();
        Database\MigrationRunner::runAll();
        Roles\RoleManager::ensureRoles();
        flush_rewrite_rules();
    }

    /**
     * Abort activation if PHP or WP version is below minimum.
     */
    private static function guardEnvironment(): void {
        global $wp_version;

        if ( version_compare( PHP_VERSION, RESERVAS_ALDEALAB_MIN_PHP, '<' ) ) {
            deactivate_plugins( RESERVAS_ALDEALAB_BASENAME );
            wp_die(
                esc_html(
                    sprintf(
                        /* translators: %1$s current PHP version, %2$s required PHP version */
                        __( 'Reservas Aldealab requiere PHP %2$s o superior. Tienes %1$s.', 'reservas-aldealab' ),
                        PHP_VERSION,
                        RESERVAS_ALDEALAB_MIN_PHP
                    )
                ),
                esc_html__( 'Reservas Aldealab: PHP incompatible', 'reservas-aldealab' ),
                array( 'back_link' => true )
            );
        }

        if ( isset( $wp_version ) && version_compare( $wp_version, RESERVAS_ALDEALAB_MIN_WP, '<' ) ) {
            deactivate_plugins( RESERVAS_ALDEALAB_BASENAME );
            wp_die(
                esc_html(
                    sprintf(
                        /* translators: %1$s current WP version, %2$s required WP version */
                        __( 'Reservas Aldealab requiere WordPress %2$s o superior. Tienes %1$s.', 'reservas-aldealab' ),
                        $wp_version,
                        RESERVAS_ALDEALAB_MIN_WP
                    )
                ),
                esc_html__( 'Reservas Aldealab: WordPress incompatible', 'reservas-aldealab' ),
                array( 'back_link' => true )
            );
        }
    }
}
