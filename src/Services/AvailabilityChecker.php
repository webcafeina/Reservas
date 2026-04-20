<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use DateTimeInterface;
use InvalidArgumentException;
use WebcafeinaReservas\Database\Schema;
use wpdb;

/**
 * Reads booking_dates + bookings to answer: "is this sala free on these
 * dates between these hours?".
 *
 * Two public entry points:
 *   - check()        : plain read; safe outside a transaction, but two racing
 *                      writers could both see "available" and both insert.
 *   - checkAndLock() : same query with FOR UPDATE; only valid inside a
 *                      transaction. Blocks concurrent writers on the matching
 *                      booking_dates rows.
 *
 * The caller (BookingService) is responsible for opening the transaction,
 * calling checkAndLock, writing the new booking + its dates, and committing.
 *
 * Conflict detection: two time ranges [a, b) and [c, d) overlap iff
 * a < d AND b > c. We apply that at the SQL level so MySQL does the work.
 */
final class AvailabilityChecker {

    /**
     * Booking states that still occupy a slot.
     */
    private const BLOCKING_STATES = array( 'pendiente', 'confirmada' );

    private wpdb $wpdb;

    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
    }

    /**
     * @param array<int, DateTimeInterface> $dates
     */
    public function check(
        int $salaId,
        array $dates,
        string $horaInicio,
        string $horaFin,
        ?int $excludeBookingId = null
    ): AvailabilityResult {
        return $this->run( $salaId, $dates, $horaInicio, $horaFin, $excludeBookingId, false );
    }

    /**
     * Locking variant. Must be called inside a transaction; otherwise the
     * row lock is released immediately and provides no protection.
     *
     * @param array<int, DateTimeInterface> $dates
     */
    public function checkAndLock(
        int $salaId,
        array $dates,
        string $horaInicio,
        string $horaFin,
        ?int $excludeBookingId = null
    ): AvailabilityResult {
        return $this->run( $salaId, $dates, $horaInicio, $horaFin, $excludeBookingId, true );
    }

    public function beginTransaction(): void {
        $this->wpdb->query( 'START TRANSACTION' );
    }

    public function commit(): void {
        $this->wpdb->query( 'COMMIT' );
    }

    public function rollback(): void {
        $this->wpdb->query( 'ROLLBACK' );
    }

    /**
     * @param array<int, DateTimeInterface> $dates
     */
    private function run(
        int $salaId,
        array $dates,
        string $horaInicio,
        string $horaFin,
        ?int $excludeBookingId,
        bool $lock
    ): AvailabilityResult {
        if ( $salaId <= 0 ) {
            throw new InvalidArgumentException( 'salaId must be a positive integer.' );
        }
        if ( $dates === array() ) {
            return AvailabilityResult::available();
        }
        $this->assertTime( $horaInicio, 'horaInicio' );
        $this->assertTime( $horaFin, 'horaFin' );
        if ( strcmp( $horaInicio, $horaFin ) >= 0 ) {
            throw new InvalidArgumentException( 'horaInicio must be strictly before horaFin.' );
        }

        $isoDates = array();
        foreach ( $dates as $d ) {
            $isoDates[ $d->format( 'Y-m-d' ) ] = true;
        }
        $isoDates = array_keys( $isoDates );

        $placeholders = implode( ', ', array_fill( 0, count( $isoDates ), '%s' ) );

        $bookings      = Schema::bookings();
        $booking_dates = Schema::bookingDates();

        $state_placeholders = implode(
            ', ',
            array_fill( 0, count( self::BLOCKING_STATES ), '%s' )
        );

        $sql =
            "SELECT bd.fecha AS fecha, bd.booking_id AS booking_id, "
            . "b.hora_inicio AS hora_inicio, b.hora_fin AS hora_fin "
            . "FROM {$booking_dates} bd "
            . "INNER JOIN {$bookings} b ON b.id = bd.booking_id "
            . "WHERE bd.sala_id = %d "
            . "AND bd.estado_fecha = 'activa' "
            . "AND b.estado IN ({$state_placeholders}) "
            . "AND bd.fecha IN ({$placeholders}) "
            . 'AND b.hora_inicio < %s '
            . 'AND b.hora_fin > %s';

        $params   = array( $salaId );
        $params   = array_merge( $params, self::BLOCKING_STATES );
        $params   = array_merge( $params, $isoDates );
        $params[] = $horaFin;
        $params[] = $horaInicio;

        if ( $excludeBookingId !== null && $excludeBookingId > 0 ) {
            $sql     .= ' AND bd.booking_id <> %d';
            $params[] = $excludeBookingId;
        }

        if ( $lock ) {
            $sql .= ' FOR UPDATE';
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare( $sql, $params );
        /** @var array<int, array{fecha:string, booking_id:string|int, hora_inicio:string, hora_fin:string}> $rows */
        $rows = (array) $this->wpdb->get_results( $prepared, ARRAY_A );

        if ( $rows === array() ) {
            return AvailabilityResult::available();
        }

        $conflicts = array();
        foreach ( $rows as $row ) {
            $conflicts[] = array(
                'fecha'       => (string) $row['fecha'],
                'booking_id'  => (int) $row['booking_id'],
                'hora_inicio' => (string) $row['hora_inicio'],
                'hora_fin'    => (string) $row['hora_fin'],
            );
        }

        return AvailabilityResult::conflicting( $conflicts );
    }

    private function assertTime( string $time, string $name ): void {
        if ( preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time ) !== 1 ) {
            throw new InvalidArgumentException( $name . ' must be HH:MM or HH:MM:SS.' );
        }
    }
}
