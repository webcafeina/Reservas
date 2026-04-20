<?php
/**
 * Initial schema: bookings, booking_dates, user_profiles, booking_cpa_items,
 * email_log. Returns a MigrationInterface; loaded dynamically by the runner.
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

use WebcafeinaReservas\Database\MigrationInterface;
use WebcafeinaReservas\Database\Schema;

defined( 'ABSPATH' ) || exit;

return new class implements MigrationInterface {

    public function version(): string {
        return '001';
    }

    public function description(): string {
        return 'Initial schema: bookings, booking_dates, user_profiles, cpa_items, email_log.';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $bookings       = Schema::bookings();
        $booking_dates  = Schema::bookingDates();
        $user_profiles  = Schema::userProfiles();
        $cpa_items      = Schema::cpaItems();
        $email_log      = Schema::emailLog();

        // Bookings: one row per reservation, even if recurring.
        // dbDelta formatting: 2 spaces after PRIMARY KEY, each KEY on its own line.
        $sql_bookings = "CREATE TABLE {$bookings} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            profile_id BIGINT UNSIGNED NULL,
            sala_id BIGINT UNSIGNED NOT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            hora_inicio TIME NOT NULL,
            hora_fin TIME NOT NULL,
            rrule TEXT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin_serie DATE NULL,
            objeto_reserva TEXT NOT NULL,
            nota_admin TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_uuid (uuid),
            KEY idx_sala (sala_id),
            KEY idx_estado (estado),
            KEY idx_user (user_id),
            KEY idx_profile (profile_id),
            KEY idx_fecha_inicio (fecha_inicio)
        ) {$charset_collate};";

        // Expanded dates: one row per occurrence, enabling fast availability lookups.
        $sql_booking_dates = "CREATE TABLE {$booking_dates} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT UNSIGNED NOT NULL,
            sala_id BIGINT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            estado_fecha VARCHAR(20) NOT NULL DEFAULT 'activa',
            PRIMARY KEY  (id),
            KEY idx_sala_fecha (sala_id, fecha),
            KEY idx_booking (booking_id),
            KEY idx_estado_fecha (estado_fecha)
        ) {$charset_collate};";

        // Personal data, re-usable across bookings. user_id nullable for guests.
        $sql_user_profiles = "CREATE TABLE {$user_profiles} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            nif VARCHAR(20) NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            primer_apellido VARCHAR(100) NOT NULL,
            segundo_apellido VARCHAR(100) NULL,
            via VARCHAR(150) NOT NULL,
            numero VARCHAR(10) NOT NULL,
            letra VARCHAR(5) NULL,
            escalera VARCHAR(10) NULL,
            piso VARCHAR(10) NULL,
            puerta VARCHAR(10) NULL,
            municipio VARCHAR(100) NOT NULL,
            provincia VARCHAR(100) NOT NULL,
            codigo_postal VARCHAR(10) NOT NULL,
            telefono_fijo VARCHAR(20) NULL,
            movil VARCHAR(20) NOT NULL,
            email VARCHAR(150) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_email (email),
            KEY idx_user (user_id),
            KEY idx_nif (nif)
        ) {$charset_collate};";

        // CPA sub-items (only for CPA bookings: extra optional equipment).
        $sql_cpa_items = "CREATE TABLE {$cpa_items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT UNSIGNED NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_label VARCHAR(150) NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_booking (booking_id)
        ) {$charset_collate};";

        // Email delivery log: one row per outbound email.
        $sql_email_log = "CREATE TABLE {$email_log} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT UNSIGNED NULL,
            tipo VARCHAR(30) NOT NULL,
            destinatario VARCHAR(150) NOT NULL,
            asunto VARCHAR(200) NOT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'enviado',
            error TEXT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_booking (booking_id),
            KEY idx_tipo (tipo),
            KEY idx_estado_sent (estado, sent_at)
        ) {$charset_collate};";

        dbDelta( $sql_user_profiles );
        dbDelta( $sql_bookings );
        dbDelta( $sql_booking_dates );
        dbDelta( $sql_cpa_items );
        dbDelta( $sql_email_log );
    }

    public function down(): void {
        global $wpdb;
        foreach ( Schema::allTablesDropOrder() as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }
};
