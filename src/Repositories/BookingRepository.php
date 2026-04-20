<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Repositories;

defined( 'ABSPATH' ) || exit;

use DateTimeInterface;
use InvalidArgumentException;
use WebcafeinaReservas\Database\Schema;
use WebcafeinaReservas\Models\Booking;
use WebcafeinaReservas\Models\BookingState;
use wpdb;

/**
 * Persistence for reservas_bookings and reservas_booking_dates.
 *
 * Writes do NOT open transactions themselves — the caller (BookingService)
 * is responsible for the transaction so it can combine this class with
 * AvailabilityChecker::checkAndLock() under the same BEGIN.
 */
final class BookingRepository {

    private wpdb $wpdb;

    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
    }

    /**
     * Insert the booking + its expanded dates. Returns the new booking id.
     *
     * @param array<int, DateTimeInterface> $dates
     */
    public function create( Booking $booking, array $dates ): int {
        if ( $dates === array() ) {
            throw new InvalidArgumentException( 'Booking must have at least one date.' );
        }

        $table_b = Schema::bookings();
        $table_d = Schema::bookingDates();

        $this->wpdb->insert(
            $table_b,
            array(
                'uuid'            => $booking->uuid,
                'user_id'         => $booking->userId,
                'profile_id'      => $booking->profileId,
                'sala_id'         => $booking->salaId,
                'estado'          => $booking->estado,
                'hora_inicio'     => $booking->horaInicio,
                'hora_fin'        => $booking->horaFin,
                'rrule'           => $booking->rrule,
                'fecha_inicio'    => $booking->fechaInicio,
                'fecha_fin_serie' => $booking->fechaFinSerie,
                'objeto_reserva'  => $booking->objetoReserva,
                'nota_admin'      => $booking->notaAdmin,
            )
        );
        $bookingId = (int) $this->wpdb->insert_id;
        if ( $bookingId <= 0 ) {
            throw new \RuntimeException( 'Failed to insert booking row.' );
        }

        foreach ( $dates as $d ) {
            $this->wpdb->insert(
                $table_d,
                array(
                    'booking_id'   => $bookingId,
                    'sala_id'      => $booking->salaId,
                    'fecha'        => $d->format( 'Y-m-d' ),
                    'estado_fecha' => 'activa',
                )
            );
        }

        return $bookingId;
    }

    public function find( int $id ): ?Booking {
        if ( $id <= 0 ) {
            return null;
        }
        $table = Schema::bookings();
        $row   = $this->wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
            ARRAY_A
        );
        if ( ! is_array( $row ) ) {
            return null;
        }
        $booking         = Booking::fromArray( $row );
        $booking->fechas = $this->loadDates( (int) $row['id'] );
        return $booking;
    }

    public function findByUuid( string $uuid ): ?Booking {
        if ( $uuid === '' ) {
            return null;
        }
        $table = Schema::bookings();
        $row   = $this->wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->prepare( "SELECT * FROM {$table} WHERE uuid = %s LIMIT 1", $uuid ),
            ARRAY_A
        );
        if ( ! is_array( $row ) ) {
            return null;
        }
        $booking         = Booking::fromArray( $row );
        $booking->fechas = $this->loadDates( (int) $row['id'] );
        return $booking;
    }

    /**
     * @param array{
     *     estado?: string|null,
     *     sala_id?: int|null,
     *     email?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     per_page?: int,
     *     page?: int,
     * } $filters
     *
     * @return array{items: array<int, Booking>, total: int, page: int, per_page: int}
     */
    public function searchForAdmin( array $filters ): array {
        $perPage = isset( $filters['per_page'] ) ? max( 1, min( 100, (int) $filters['per_page'] ) ) : 20;
        $page    = isset( $filters['page'] ) ? max( 1, (int) $filters['page'] ) : 1;
        $offset  = ( $page - 1 ) * $perPage;

        $table_b = Schema::bookings();
        $table_p = Schema::userProfiles();

        $where  = array( '1=1' );
        $params = array();

        if ( isset( $filters['estado'] ) && $filters['estado'] !== null && $filters['estado'] !== '' ) {
            $where[]  = 'b.estado = %s';
            $params[] = (string) $filters['estado'];
        }
        if ( isset( $filters['sala_id'] ) && $filters['sala_id'] !== null && (int) $filters['sala_id'] > 0 ) {
            $where[]  = 'b.sala_id = %d';
            $params[] = (int) $filters['sala_id'];
        }
        if ( isset( $filters['from'] ) && $filters['from'] !== null && $filters['from'] !== '' ) {
            $where[]  = 'b.fecha_inicio >= %s';
            $params[] = (string) $filters['from'];
        }
        if ( isset( $filters['to'] ) && $filters['to'] !== null && $filters['to'] !== '' ) {
            $where[]  = 'b.fecha_inicio <= %s';
            $params[] = (string) $filters['to'];
        }
        if ( isset( $filters['email'] ) && $filters['email'] !== null && $filters['email'] !== '' ) {
            $where[]  = 'p.email = %s';
            $params[] = (string) $filters['email'];
        }

        $where_sql = implode( ' AND ', $where );

        $sql_count =
            "SELECT COUNT(*) FROM {$table_b} b "
            . "LEFT JOIN {$table_p} p ON p.id = b.profile_id "
            . "WHERE {$where_sql}";

        $sql_items =
            "SELECT b.* FROM {$table_b} b "
            . "LEFT JOIN {$table_p} p ON p.id = b.profile_id "
            . "WHERE {$where_sql} "
            . 'ORDER BY b.created_at DESC '
            . 'LIMIT %d OFFSET %d';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total_raw = $this->wpdb->get_var(
            $params === array()
                ? $sql_count
                : $this->wpdb->prepare( $sql_count, $params )
        );
        $total = (int) $total_raw;

        $items_params = array_merge( $params, array( $perPage, $offset ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = (array) $this->wpdb->get_results(
            $this->wpdb->prepare( $sql_items, $items_params ),
            ARRAY_A
        );

        $items = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $booking         = Booking::fromArray( $row );
            $booking->fechas = $this->loadDates( (int) $row['id'] );
            $items[]         = $booking;
        }

        return array(
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        );
    }

    public function updateState( int $id, string $state ): bool {
        if ( $id <= 0 || ! BookingState::isValid( $state ) ) {
            return false;
        }
        $table   = Schema::bookings();
        $updated = $this->wpdb->update( $table, array( 'estado' => $state ), array( 'id' => $id ) );
        return $updated !== false;
    }

    public function updateNotaAdmin( int $id, ?string $note ): bool {
        if ( $id <= 0 ) {
            return false;
        }
        $table   = Schema::bookings();
        $updated = $this->wpdb->update( $table, array( 'nota_admin' => $note ), array( 'id' => $id ) );
        return $updated !== false;
    }

    public function delete( int $id ): bool {
        if ( $id <= 0 ) {
            return false;
        }
        $table_b = Schema::bookings();
        $table_d = Schema::bookingDates();

        // booking_dates.booking_id has no FK cascade at the DB level (WP DBs
        // are often MyISAM-compatible), so we delete children first manually.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$table_d} WHERE booking_id = %d", $id ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$table_b} WHERE id = %d", $id ) );

        return is_int( $result ) && $result > 0;
    }

    /**
     * @return array<int, string>
     */
    private function loadDates( int $bookingId ): array {
        $table = Schema::bookingDates();
        $rows  = (array) $this->wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->prepare(
                "SELECT fecha FROM {$table} WHERE booking_id = %d AND estado_fecha = 'activa' ORDER BY fecha ASC",
                $bookingId
            ),
            ARRAY_A
        );
        $dates = array();
        foreach ( $rows as $row ) {
            if ( is_array( $row ) && isset( $row['fecha'] ) ) {
                $dates[] = (string) $row['fecha'];
            }
        }
        return $dates;
    }
}
