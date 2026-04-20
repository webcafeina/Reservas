<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Database\Schema;
use WebcafeinaReservas\Models\BookingState;
use WebcafeinaReservas\Rest\RestApi;

/**
 * GET /admin/stats — dashboard KPIs for the admin panel.
 *
 * Returns:
 *   - by_state:    count of bookings grouped by estado
 *   - this_week:   bookings starting within the current ISO week
 *   - upcoming:    confirmed bookings in the next 7 days
 *   - per_sala:    top 5 salas by booking count (last 30 days)
 */
final class AdminStatsController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/stats',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'stats' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
            )
        );
    }

    public function stats( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $bookings = Schema::bookings();

        $by_state = array();
        foreach ( BookingState::ALL as $state ) {
            $by_state[ $state ] = (int) $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings} WHERE estado = %s", $state )
            );
        }

        $week_start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
        $week_end   = gmdate( 'Y-m-d', strtotime( 'sunday this week' ) );
        $this_week  = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$bookings} WHERE fecha_inicio BETWEEN %s AND %s",
                $week_start,
                $week_end
            )
        );

        $today     = gmdate( 'Y-m-d' );
        $plus_week = gmdate( 'Y-m-d', strtotime( '+7 days' ) );
        $upcoming  = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$bookings} WHERE estado = %s AND fecha_inicio BETWEEN %s AND %s",
                BookingState::CONFIRMADA,
                $today,
                $plus_week
            )
        );

        $thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $per_sala        = (array) $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare(
                "SELECT sala_id, COUNT(*) AS total FROM {$bookings} "
                . 'WHERE fecha_inicio >= %s AND estado <> %s '
                . 'GROUP BY sala_id ORDER BY total DESC LIMIT 5',
                $thirty_days_ago,
                BookingState::CANCELADA
            ),
            ARRAY_A
        );

        $per_sala_out = array();
        foreach ( $per_sala as $row ) {
            if ( is_array( $row ) && isset( $row['sala_id'], $row['total'] ) ) {
                $per_sala_out[] = array(
                    'sala_id' => (int) $row['sala_id'],
                    'title'   => (string) get_the_title( (int) $row['sala_id'] ),
                    'total'   => (int) $row['total'],
                );
            }
        }

        return new WP_REST_Response(
            array(
                'by_state'  => $by_state,
                'this_week' => $this_week,
                'upcoming'  => $upcoming,
                'per_sala'  => $per_sala_out,
            ),
            200
        );
    }
}
