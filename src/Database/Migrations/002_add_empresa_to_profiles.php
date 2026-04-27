<?php
/**
 * Add an optional `empresa` (company) column to the user_profiles table.
 * Returns a MigrationInterface; loaded dynamically by the runner.
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

use WebcafeinaReservas\Database\MigrationInterface;
use WebcafeinaReservas\Database\Schema;

defined( 'ABSPATH' ) || exit;

return new class implements MigrationInterface {

    public function version(): string {
        return '002';
    }

    public function description(): string {
        return 'Add optional `empresa` (company) column to user_profiles.';
    }

    public function up(): void {
        global $wpdb;
        $table = Schema::userProfiles();

        // Idempotency: avoid `ALTER TABLE ADD COLUMN` failing if the column
        // is already present (manual SQL re-runs, partial restores, etc.).
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                $table,
                'empresa'
            )
        );
        if ( (int) $exists > 0 ) {
            return;
        }

        // VARCHAR(255) NULL — same length as the other free-text columns of
        // the table. Nullable because the field is optional in the form.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN empresa VARCHAR(255) NULL DEFAULT NULL AFTER email" );
    }

    public function down(): void {
        global $wpdb;
        $table = Schema::userProfiles();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                $table,
                'empresa'
            )
        );
        if ( (int) $exists === 0 ) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "ALTER TABLE {$table} DROP COLUMN empresa" );
    }
};
