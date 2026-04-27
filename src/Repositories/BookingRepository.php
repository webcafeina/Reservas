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
use WebcafeinaReservas\Models\UserProfile;
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
        global $wpdb;
        $table_b  = Schema::bookings();
        $table_up = Schema::userProfiles();
        $table_p  = $wpdb->posts;

        $sql = "SELECT b.*, p.post_title AS sala_title, "
            . self::profileSelectColumns()
            . "FROM {$table_b} b "
            . "LEFT JOIN {$table_p} p ON p.ID = b.sala_id "
            . "LEFT JOIN {$table_up} up ON up.id = b.profile_id "
            . 'WHERE b.id = %d LIMIT 1';

        $row = $this->wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->prepare( $sql, $id ),
            ARRAY_A
        );
        if ( ! is_array( $row ) ) {
            return null;
        }

        $booking         = $this->hydrateBookingFromJoinedRow( $row );
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

        global $wpdb;
        $table_b   = Schema::bookings();
        $table_up  = Schema::userProfiles();
        $table_post = $wpdb->posts;

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
            $where[]  = 'up.email = %s';
            $params[] = (string) $filters['email'];
        }

        $where_sql = implode( ' AND ', $where );

        $sql_count =
            "SELECT COUNT(*) FROM {$table_b} b "
            . "LEFT JOIN {$table_up} up ON up.id = b.profile_id "
            . "WHERE {$where_sql}";

        $sql_items =
            'SELECT b.*, p.post_title AS sala_title, '
            . self::profileSelectColumns()
            . "FROM {$table_b} b "
            . "LEFT JOIN {$table_post} p ON p.ID = b.sala_id "
            . "LEFT JOIN {$table_up} up ON up.id = b.profile_id "
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
            $booking         = $this->hydrateBookingFromJoinedRow( $row );
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
     * Calendar feed: one row per booking_date in [$from, $to]. Joined with
     * the booking, sala (post title) and profile so the controller can build
     * FullCalendar events without N+1 queries.
     *
     * Optional filters:
     *   - $salaId: restrict to a single sala.
     *   - $estado: restrict to a single booking estado.
     *
     * Result shape — array<int, array{
     *   booking_id: int,
     *   fecha: string,           // YYYY-MM-DD
     *   estado: string,
     *   hora_inicio: string,
     *   hora_fin: string,
     *   sala_id: int,
     *   sala_title: string,
     *   solicitante: string,
     *   objeto: string,
     * }>
     *
     * @return array<int, array<string, mixed>>
     */
    public function findEventsBetween(
        string $from,
        string $to,
        ?int $salaId = null,
        ?string $estado = null
    ): array {
        global $wpdb;
        $table_b  = Schema::bookings();
        $table_d  = Schema::bookingDates();
        $table_up = Schema::userProfiles();
        $table_p  = $wpdb->posts;

        $where  = array(
            'bd.fecha >= %s',
            'bd.fecha <= %s',
            "bd.estado_fecha = 'activa'",
        );
        $params = array( $from, $to );

        if ( $salaId !== null && $salaId > 0 ) {
            $where[]  = 'b.sala_id = %d';
            $params[] = $salaId;
        }
        if ( $estado !== null && $estado !== '' && BookingState::isValid( $estado ) ) {
            $where[]  = 'b.estado = %s';
            $params[] = $estado;
        }

        $where_sql = implode( ' AND ', $where );

        $sql =
            'SELECT bd.booking_id, bd.fecha, b.estado, b.hora_inicio, b.hora_fin, '
            . 'b.objeto_reserva, b.sala_id, '
            . 'p.post_title AS sala_title, '
            . 'up.nombre, up.primer_apellido, up.segundo_apellido '
            . "FROM {$table_d} bd "
            . "INNER JOIN {$table_b} b ON b.id = bd.booking_id "
            . "LEFT JOIN {$table_p} p ON p.ID = b.sala_id "
            . "LEFT JOIN {$table_up} up ON up.id = b.profile_id "
            . "WHERE {$where_sql} "
            . 'ORDER BY bd.fecha ASC, b.hora_inicio ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = (array) $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, $params ),
            ARRAY_A
        );

        $out = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $solicitante = trim(
                ( (string) ( $row['nombre'] ?? '' ) ) . ' '
                . ( (string) ( $row['primer_apellido'] ?? '' ) ) . ' '
                . ( (string) ( $row['segundo_apellido'] ?? '' ) )
            );
            $out[] = array(
                'booking_id'  => (int) ( $row['booking_id'] ?? 0 ),
                'fecha'       => (string) ( $row['fecha'] ?? '' ),
                'estado'      => (string) ( $row['estado'] ?? '' ),
                'hora_inicio' => (string) ( $row['hora_inicio'] ?? '' ),
                'hora_fin'    => (string) ( $row['hora_fin'] ?? '' ),
                'sala_id'     => (int) ( $row['sala_id'] ?? 0 ),
                'sala_title'  => (string) ( $row['sala_title'] ?? '' ),
                'solicitante' => $solicitante,
                'objeto'      => (string) ( $row['objeto_reserva'] ?? '' ),
            );
        }
        return $out;
    }

    /**
     * Aliased SELECT columns for the user_profiles JOIN. Prefixes every
     * column with `up_` so we can disambiguate booking vs profile rows
     * after the JOIN (e.g. both have `id`, `created_at`, `updated_at`).
     */
    private static function profileSelectColumns(): string {
        $cols = array(
            'id', 'user_id', 'nif', 'nombre', 'primer_apellido', 'segundo_apellido',
            'via', 'numero', 'letra', 'escalera', 'piso', 'puerta',
            'municipio', 'provincia', 'codigo_postal',
            'telefono_fijo', 'movil', 'email', 'empresa',
        );
        $aliased = array();
        foreach ( $cols as $c ) {
            $aliased[] = "up.{$c} AS up_{$c}";
        }
        return implode( ', ', $aliased ) . ' ';
    }

    /**
     * Build a Booking out of a row that JOINed posts (`sala_title`) and
     * user_profiles (columns prefixed `up_*`). Sets `salaTitle` and the
     * full `profile` so the controller can serialise everything in a
     * single response.
     *
     * @param array<string, mixed> $row
     */
    private function hydrateBookingFromJoinedRow( array $row ): Booking {
        $booking = Booking::fromArray( $row );

        if ( isset( $row['sala_title'] ) && $row['sala_title'] !== '' ) {
            $booking->salaTitle = (string) $row['sala_title'];
        }

        if ( isset( $row['up_id'] ) && $row['up_id'] !== null && $row['up_id'] !== '' ) {
            $profileData = array();
            foreach ( $row as $k => $v ) {
                if ( is_string( $k ) && strpos( $k, 'up_' ) === 0 ) {
                    $profileData[ substr( $k, 3 ) ] = $v;
                }
            }
            $booking->profile = UserProfile::fromArray( $profileData );
        }

        return $booking;
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
