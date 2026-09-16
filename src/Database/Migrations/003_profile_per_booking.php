<?php
/**
 * One profile row per booking. Until 0.23.0 `user_profiles` was unique by
 * email and every booking with the same email pointed at the same row, so
 * editing the applicant data of one booking changed all of them. This
 * migration drops the unique index and gives each booking its own copy.
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
        return '003';
    }

    public function description(): string {
        return 'One user_profiles row per booking: drop unique email index and split shared rows.';
    }

    public function up(): void {
        global $wpdb;
        $profiles = Schema::userProfiles();
        $bookings = Schema::bookings();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT id, profile_id FROM {$bookings} "
            . "WHERE profile_id IN ( "
            . "SELECT profile_id FROM {$bookings} WHERE profile_id IS NOT NULL "
            . "GROUP BY profile_id HAVING COUNT(*) > 1 ) "
            . 'ORDER BY profile_id ASC, id ASC',
            ARRAY_A
        );
        $groups = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $groups[ (int) $row['profile_id'] ][] = $row;
        }

        // 1. Email stops being unique (a copy per booking needs duplicates).
        if ( self::indexExists( $profiles, 'uq_email' ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query( "ALTER TABLE {$profiles} DROP INDEX uq_email" );
        }
        if ( ! self::indexExists( $profiles, 'idx_email' ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query( "ALTER TABLE {$profiles} ADD INDEX idx_email (email)" );
        }

        // 2. Split: the oldest booking keeps the row, the rest get a copy
        //    (timestamps included, so the history stays readable).
        $columns = 'user_id, nif, nombre, primer_apellido, segundo_apellido, via, numero, letra, '
            . 'escalera, piso, puerta, municipio, provincia, codigo_postal, telefono_fijo, movil, '
            . 'email, empresa, created_at, updated_at';
        foreach ( $groups as $profileId => $group ) {
            foreach ( array_slice( $group, 1 ) as $row ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $copied = $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO {$profiles} ( {$columns} ) SELECT {$columns} FROM {$profiles} WHERE id = %d",
                        $profileId
                    )
                );
                if ( $copied !== 1 ) {
                    continue;
                }
                // Keep bookings.updated_at as is: it's not a real edit.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$bookings} SET profile_id = %d, updated_at = updated_at WHERE id = %d",
                        (int) $wpdb->insert_id,
                        (int) $row['id']
                    )
                );
            }
        }
    }

    public function down(): void {
        // The unique email index is not restored: with one row per booking
        // there are legitimate duplicates and ADD UNIQUE would fail. The
        // split rows are harmless for the old code (it upserts by email and
        // just picks one of them).
    }

    private static function indexExists( string $table, string $index ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
                $table,
                $index
            )
        );
        return (int) $count > 0;
    }
};
