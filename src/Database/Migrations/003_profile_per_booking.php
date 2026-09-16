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

    /**
     * Seconds of slack when matching a booking write against the profile's
     * `updated_at` (both come from separate statements of the same request).
     */
    private const WRITE_SLACK = 5;

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

        // 1. Report first: once the rows are split we can't tell which
        //    bookings used to share data.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT b.id, b.profile_id, b.sala_id, b.fecha_inicio, b.created_at, b.updated_at, "
            . "up.email, up.updated_at AS profile_updated_at "
            . "FROM {$bookings} b INNER JOIN {$profiles} up ON up.id = b.profile_id "
            . "WHERE b.profile_id IN ( "
            . "SELECT profile_id FROM {$bookings} WHERE profile_id IS NOT NULL "
            . "GROUP BY profile_id HAVING COUNT(*) > 1 ) "
            . 'ORDER BY b.profile_id ASC, b.id ASC',
            ARRAY_A
        );
        $groups = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $groups[ (int) $row['profile_id'] ][] = $row;
        }

        if ( $groups !== array() ) {
            $items = array();
            foreach ( $groups as $group ) {
                $owner = self::probableOwner( $group );
                $ids   = array_map(
                    static function ( array $r ): int {
                        return (int) $r['id'];
                    },
                    $group
                );
                foreach ( $group as $row ) {
                    $id      = (int) $row['id'];
                    $items[] = array(
                        'booking_id'     => $id,
                        'email'          => (string) $row['email'],
                        'sala_id'        => (int) $row['sala_id'],
                        'fecha_inicio'   => (string) $row['fecha_inicio'],
                        'compartida_con' => array_values( array_diff( $ids, array( $id ) ) ),
                        'revisar'        => $id !== $owner,
                    );
                }
            }
            // Don't clobber a previous report if the migration is re-run.
            if ( get_option( Schema::OPTION_SHARED_PROFILES_REPORT, null ) === null ) {
                add_option(
                    Schema::OPTION_SHARED_PROFILES_REPORT,
                    array(
                        'generated_at' => current_time( 'mysql' ),
                        'items'        => $items,
                    ),
                    '',
                    false
                );
            }
        }

        // 2. Email stops being unique (a copy per booking needs duplicates).
        if ( self::indexExists( $profiles, 'uq_email' ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query( "ALTER TABLE {$profiles} DROP INDEX uq_email" );
        }
        if ( ! self::indexExists( $profiles, 'idx_email' ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query( "ALTER TABLE {$profiles} ADD INDEX idx_email (email)" );
        }

        // 3. Split: the oldest booking keeps the row, the rest get a copy
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
        delete_option( Schema::OPTION_SHARED_PROFILES_REPORT );
    }

    /**
     * Booking whose write most likely left the current data in the shared
     * row: the one with the latest create/update at or before the profile's
     * `updated_at`. Every other booking of the group may show data typed for
     * a different booking. Returns 0 when no booking matches (all to review).
     *
     * @param array<int, array<string, mixed>> $group
     */
    private static function probableOwner( array $group ): int {
        $profileTs = strtotime( (string) $group[0]['profile_updated_at'] );
        if ( $profileTs === false ) {
            return 0;
        }
        $owner  = 0;
        $bestTs = PHP_INT_MIN;
        foreach ( $group as $row ) {
            foreach ( array( 'created_at', 'updated_at' ) as $col ) {
                $ts = strtotime( (string) $row[ $col ] );
                if ( $ts !== false && $ts <= $profileTs + self::WRITE_SLACK && $ts >= $bestTs ) {
                    $bestTs = $ts;
                    $owner  = (int) $row['id'];
                }
            }
        }
        return $owner;
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
