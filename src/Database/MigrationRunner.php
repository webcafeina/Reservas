<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers migrations from `src/Database/Migrations/` (each file returns a
 * MigrationInterface) and runs them in version order.
 *
 * Version tracking lives in a single WP option (see Schema::OPTION_DB_VERSION).
 * On every admin pageload `maybeRun()` compares option vs. latest-discovered
 * and executes anything new. Idempotent by design: once a version is recorded
 * it never runs again.
 */
final class MigrationRunner {

    /**
     * Called by `register_activation_hook` via Activator.
     */
    public static function runAll(): void {
        self::runFromDirectory( self::defaultMigrationsPath() );
    }

    /**
     * Called on `admin_init`. Cheap no-op when already up to date.
     */
    public static function maybeRun(): void {
        $current = self::getDbVersion();
        $latest  = self::latestAvailableVersion( self::defaultMigrationsPath() );

        if ( $latest === null || $current === $latest ) {
            return;
        }
        self::runFromDirectory( self::defaultMigrationsPath() );
    }

    /**
     * Used by Uninstaller. Calls `down()` on every migration in reverse order
     * and resets the stored version.
     */
    public static function dropAll(): void {
        $migrations = self::discoverMigrations( self::defaultMigrationsPath() );
        foreach ( array_reverse( $migrations ) as $migration ) {
            $migration->down();
        }
        delete_option( Schema::OPTION_DB_VERSION );
    }

    /**
     * Executes every migration newer than the currently stored version.
     * Extracted for testability.
     */
    public static function runFromDirectory( string $dir ): void {
        $current    = self::getDbVersion();
        $migrations = self::discoverMigrations( $dir );

        foreach ( $migrations as $migration ) {
            if ( $current !== '' && strcmp( $migration->version(), $current ) <= 0 ) {
                continue;
            }
            $migration->up();
            self::setDbVersion( $migration->version() );
            $current = $migration->version();
        }
    }

    /**
     * Reads and returns migrations from a directory, sorted by version string.
     *
     * @return array<int, MigrationInterface>
     */
    public static function discoverMigrations( string $dir ): array {
        if ( ! is_dir( $dir ) ) {
            return array();
        }

        $files = glob( rtrim( $dir, '/' ) . '/*.php' );
        if ( $files === false || $files === array() ) {
            return array();
        }

        sort( $files, SORT_STRING );

        $migrations = array();
        foreach ( $files as $file ) {
            /** @var mixed $migration */
            $migration = require $file;
            if ( $migration instanceof MigrationInterface ) {
                $migrations[] = $migration;
            }
        }

        // Belt-and-suspenders sort by declared version in case filenames ever lie.
        usort(
            $migrations,
            static function ( MigrationInterface $a, MigrationInterface $b ): int {
                return strcmp( $a->version(), $b->version() );
            }
        );

        return $migrations;
    }

    public static function latestAvailableVersion( string $dir ): ?string {
        $migrations = self::discoverMigrations( $dir );
        if ( $migrations === array() ) {
            return null;
        }
        return end( $migrations )->version();
    }

    public static function getDbVersion(): string {
        $value = get_option( Schema::OPTION_DB_VERSION, '' );
        return is_string( $value ) ? $value : '';
    }

    public static function setDbVersion( string $version ): void {
        update_option( Schema::OPTION_DB_VERSION, $version, false );
    }

    private static function defaultMigrationsPath(): string {
        return RESERVAS_ALDEALAB_PATH . 'src/Database/Migrations';
    }
}
