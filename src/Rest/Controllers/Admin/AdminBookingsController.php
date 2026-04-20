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
 * GET    /admin/bookings         — paginated list with filters.
 * GET    /admin/bookings/{id}    — one booking.
 * PATCH  /admin/bookings/{id}    — change estado / nota_admin.
 * DELETE /admin/bookings/{id}    — hard delete (booking + dates).
 *
 * All gated by `manage_reservas`.
 */
final class AdminBookingsController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/bookings',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'index' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
            )
        );

        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/bookings/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'show' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
                array(
                    'methods'             => 'PATCH',
                    'callback'            => array( $this, 'update' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array( $this, 'delete' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
            )
        );
    }

    public function index( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $repo = new BookingRepository( $wpdb );

        $filters = array(
            'estado'   => $request->get_param( 'estado' ) !== null
                ? sanitize_text_field( (string) $request->get_param( 'estado' ) )
                : null,
            'sala_id'  => $request->get_param( 'sala_id' ) !== null
                ? (int) $request->get_param( 'sala_id' )
                : null,
            'email'    => $request->get_param( 'email' ) !== null
                ? sanitize_email( (string) $request->get_param( 'email' ) )
                : null,
            'from'     => $request->get_param( 'from' ) !== null
                ? sanitize_text_field( (string) $request->get_param( 'from' ) )
                : null,
            'to'       => $request->get_param( 'to' ) !== null
                ? sanitize_text_field( (string) $request->get_param( 'to' ) )
                : null,
            'per_page' => $request->get_param( 'per_page' ) !== null ? (int) $request->get_param( 'per_page' ) : 20,
            'page'     => $request->get_param( 'page' ) !== null ? (int) $request->get_param( 'page' ) : 1,
        );

        $result = $repo->searchForAdmin( $filters );
        $items  = array_map(
            static function ( $b ) {
                return $b->toArray();
            },
            $result['items']
        );

        return new WP_REST_Response(
            array(
                'items'    => $items,
                'total'    => $result['total'],
                'page'     => $result['page'],
                'per_page' => $result['per_page'],
            ),
            200
        );
    }

    public function show( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $id      = (int) $request->get_param( 'id' );
        $repo    = new BookingRepository( $wpdb );
        $booking = $repo->find( $id );
        if ( $booking === null ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_not_found',
                    'message' => __( 'Reserva no encontrada.', 'reservas-aldealab' ),
                ),
                404
            );
        }
        return new WP_REST_Response( $booking->toArray(), 200 );
    }

    public function update( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $id   = (int) $request->get_param( 'id' );
        $repo = new BookingRepository( $wpdb );

        $booking = $repo->find( $id );
        if ( $booking === null ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_not_found',
                    'message' => __( 'Reserva no encontrada.', 'reservas-aldealab' ),
                ),
                404
            );
        }

        $estado = $request->get_param( 'estado' );
        if ( $estado !== null ) {
            $estado = sanitize_text_field( (string) $estado );
            if ( ! BookingState::isValid( $estado ) ) {
                return new WP_REST_Response(
                    array(
                        'code'    => 'rest_invalid_estado',
                        'message' => __( 'Estado no válido.', 'reservas-aldealab' ),
                    ),
                    400
                );
            }
            $repo->updateState( $id, $estado );
            if ( $estado === BookingState::CANCELADA ) {
                do_action( 'reservas_aldealab_booking_cancelled', $id );
            }
        }

        $note = $request->get_param( 'nota_admin' );
        if ( $note !== null ) {
            $repo->updateNotaAdmin( $id, sanitize_textarea_field( (string) $note ) );
        }

        $updated = $repo->find( $id );
        return new WP_REST_Response( $updated !== null ? $updated->toArray() : array(), 200 );
    }

    public function delete( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $id   = (int) $request->get_param( 'id' );
        $repo = new BookingRepository( $wpdb );

        if ( ! $repo->delete( $id ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_not_found',
                    'message' => __( 'Reserva no encontrada.', 'reservas-aldealab' ),
                ),
                404
            );
        }
        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }
}
