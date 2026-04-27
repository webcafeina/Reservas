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
 * GET /admin/stats — global KPIs for the admin Panel.
 *
 * Returns lifetime counts (no date filter):
 *   - by_state:    count of all bookings grouped by estado, past + future.
 *   - this_week:   bookings starting within the current ISO week.
 *   - upcoming:    confirmed bookings in the next 7 days from today.
 *
 * The CSV export endpoint (`/admin/bookings/export`) keeps its own
 * `from`/`to` filters — the date range that used to live on this stats
 * page is now scoped to the export module only.
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
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$bookings} WHERE estado = %s",
                    $state
                )
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

        return new WP_REST_Response(
            array(
                'by_state'  => $by_state,
                'this_week' => $this_week,
                'upcoming'  => $upcoming,
            ),
            200
        );
    }
}
