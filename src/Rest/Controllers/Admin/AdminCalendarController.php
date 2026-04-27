<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Models\BookingState;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Rest\RestApi;

/**
 * GET /admin/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Returns FullCalendar-shaped events for the requested date range —
 * one event per booking_date row (so recurring bookings expand
 * naturally) joined with sala title and solicitante name.
 *
 * Capped at 1500 events per response to avoid blowing up the year
 * view if the calendar gets crowded; the frontend should split into
 * smaller queries if it ever hits the limit.
 *
 * Gated by `manage_reservas`.
 */
final class AdminCalendarController {

    private const COLOR_BY_STATE = array(
        BookingState::PENDIENTE  => '#f59e0b',
        BookingState::CONFIRMADA => '#10b981',
        BookingState::CANCELADA  => '#9ca3af',
        BookingState::FINALIZADA => '#3b82f6',
    );

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/calendar',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'index' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
            )
        );
    }

    public function index( WP_REST_Request $request ): WP_REST_Response {
        $from = (string) $request->get_param( 'from' );
        $to   = (string) $request->get_param( 'to' );

        if ( ! self::isIsoDate( $from ) || ! self::isIsoDate( $to ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_range',
                    'message' => __( 'Parámetros from/to deben ser fechas ISO YYYY-MM-DD.', 'reservas-aldealab' ),
                ),
                400
            );
        }
        if ( $from > $to ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_range',
                    'message' => __( 'El rango de fechas es inverso (from > to).', 'reservas-aldealab' ),
                ),
                400
            );
        }

        global $wpdb;
        $repo = new BookingRepository( $wpdb );
        $rows = $repo->findEventsBetween( $from, $to );

        $events = array();
        foreach ( $rows as $row ) {
            $events[] = self::toFullCalendarEvent( $row );
            if ( count( $events ) >= 1500 ) {
                break;
            }
        }

        return new WP_REST_Response(
            array( 'events' => $events ),
            200
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function toFullCalendarEvent( array $row ): array {
        $bookingId = (int) ( $row['booking_id'] ?? 0 );
        $fecha     = (string) ( $row['fecha'] ?? '' );
        $hi        = self::trimSeconds( (string) ( $row['hora_inicio'] ?? '00:00:00' ) );
        $hf        = self::trimSeconds( (string) ( $row['hora_fin'] ?? '00:00:00' ) );
        $estado    = (string) ( $row['estado'] ?? BookingState::PENDIENTE );
        $sala      = (string) ( $row['sala_title'] ?? '' );
        $sol       = (string) ( $row['solicitante'] ?? '' );

        $title = $sala !== '' && $sol !== ''
            ? $sala . ' — ' . $sol
            : ( $sala !== '' ? $sala : $sol );

        $color = self::COLOR_BY_STATE[ $estado ] ?? '#6b7280';

        return array(
            'id'              => $bookingId . '-' . $fecha,
            'booking_id'      => $bookingId,
            'title'           => $title,
            'start'           => $fecha . 'T' . $hi,
            'end'             => $fecha . 'T' . $hf,
            'estado'          => $estado,
            'sala_title'      => $sala,
            'solicitante'     => $sol,
            'objeto'          => (string) ( $row['objeto'] ?? '' ),
            'backgroundColor' => $color,
            'borderColor'     => $color,
        );
    }

    private static function isIsoDate( string $value ): bool {
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) === 1;
    }

    private static function trimSeconds( string $time ): string {
        // FullCalendar accepts HH:MM or HH:MM:SS — keep as HH:MM:SS for safety.
        if ( preg_match( '/^\d{2}:\d{2}$/', $time ) === 1 ) {
            return $time . ':00';
        }
        return $time;
    }
}
