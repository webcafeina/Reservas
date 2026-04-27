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
use WebcafeinaReservas\Models\UserProfile;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Repositories\UserProfileRepository;
use WebcafeinaReservas\Rest\RestApi;
use WebcafeinaReservas\Services\AvailabilityChecker;
use WebcafeinaReservas\Services\BookingRequest;
use WebcafeinaReservas\Services\BookingService;
use WebcafeinaReservas\Services\EmailNotifier;
use WebcafeinaReservas\Services\RecurrenceExpander;

/**
 * GET    /admin/bookings         — paginated list with filters.
 * POST   /admin/bookings         — create a booking manually (admin-side).
 *                                  Reuses BookingService so stats, emails
 *                                  and exports stay unified with public
 *                                  bookings; skips Turnstile + rate-limit;
 *                                  accepts extra flags for `estado`,
 *                                  `force_override` and
 *                                  `suppress_notifications`.
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
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'create' ),
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
            $previousEstado = $booking->estado;
            $repo->updateState( $id, $estado );

            // Idempotency guard: only fire transition hooks when the
            // estado actually changes — re-saving the same state from
            // the panel must not re-send notifications.
            if ( $estado !== $previousEstado ) {
                if ( $estado === BookingState::CANCELADA ) {
                    do_action( 'reservas_aldealab_booking_cancelled', $id );
                }
                if ( $estado === BookingState::CONFIRMADA && function_exists( 'wp_schedule_single_event' ) ) {
                    // Async (cron) — PDF generation can take a few
                    // seconds and we don't want to block the admin's
                    // PATCH response.
                    wp_schedule_single_event( time(), EmailNotifier::HOOK_CONFIRMED, array( $id ) );
                }
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

    /**
     * POST /admin/bookings — create a manual booking from the admin panel.
     *
     * Reuses BookingService (the same code path as the public flow) so
     * stats, CSV exports and notifications stay unified. Differences vs.
     * the public `/bookings` endpoint:
     *   - no Turnstile verification (BookingService instantiated without
     *     a TurnstileVerifier),
     *   - no per-IP rate limiting,
     *   - accepts optional `estado` (default `confirmada`), `force_override`
     *     (default false) and `suppress_notifications` (default false)
     *     extras,
     *   - accepts `nota_admin` persisted on the booking row.
     */
    public function create( WP_REST_Request $request ): WP_REST_Response {
        $payload = $this->buildBookingRequest( $request );
        if ( $payload instanceof WP_REST_Response ) {
            return $payload;
        }

        global $wpdb;
        $expander = new RecurrenceExpander();
        $checker  = new AvailabilityChecker( $wpdb );
        $bookings = new BookingRepository( $wpdb );
        $profiles = new UserProfileRepository( $wpdb );

        // `turnstile = null` — admin-created bookings are trusted.
        $service = new BookingService( $wpdb, $expander, $checker, $bookings, $profiles, null );
        $result  = $service->create( $payload );

        if ( $result->success && $result->booking !== null ) {
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'booking' => $result->booking->toArray(),
                ),
                201
            );
        }

        if ( $result->errorCode === 'conflict' ) {
            return new WP_REST_Response(
                array(
                    'code'       => 'rest_conflict',
                    'message'    => $result->errorMessage,
                    'conflictos' => $result->availability !== null ? $result->availability->conflicts : array(),
                ),
                409
            );
        }

        return new WP_REST_Response(
            array(
                'code'    => 'rest_' . ( $result->errorCode ?? 'error' ),
                'message' => $result->errorMessage ?? __( 'Error al crear la reserva.', 'reservas-aldealab' ),
            ),
            422
        );
    }

    /**
     * Parses the admin create payload into a BookingRequest.
     *
     * @return BookingRequest|WP_REST_Response
     */
    private function buildBookingRequest( WP_REST_Request $request ) {
        $sala_id      = (int) $request->get_param( 'sala_id' );
        $hora_inicio  = (string) $request->get_param( 'hora_inicio' );
        $hora_fin     = (string) $request->get_param( 'hora_fin' );
        $fecha_inicio = (string) $request->get_param( 'fecha_inicio' );
        $objeto       = (string) $request->get_param( 'objeto_reserva' );
        $profile_raw  = $request->get_param( 'profile' );

        if ( $sala_id <= 0 || $hora_inicio === '' || $hora_fin === '' || $fecha_inicio === '' ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_params',
                    'message' => __( 'Faltan parámetros obligatorios.', 'reservas-aldealab' ),
                ),
                400
            );
        }
        if ( ! is_array( $profile_raw ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_params',
                    'message' => __( 'El campo profile es obligatorio.', 'reservas-aldealab' ),
                ),
                400
            );
        }

        $profile = UserProfile::fromArray( $this->sanitizeProfile( $profile_raw ) );
        if ( $profile->email === '' || ! is_email( $profile->email ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_email',
                    'message' => __( 'El email no es válido.', 'reservas-aldealab' ),
                ),
                400
            );
        }

        $payload                  = new BookingRequest();
        $payload->salaId          = $sala_id;
        $payload->horaInicio      = sanitize_text_field( $hora_inicio );
        $payload->horaFin         = sanitize_text_field( $hora_fin );
        $payload->fechaInicio     = sanitize_text_field( $fecha_inicio );
        $payload->fechaFinSerie   = $request->get_param( 'fecha_fin_serie' ) !== null
            ? sanitize_text_field( (string) $request->get_param( 'fecha_fin_serie' ) )
            : null;
        $payload->rrule           = $request->get_param( 'rrule' ) !== null
            ? sanitize_text_field( (string) $request->get_param( 'rrule' ) )
            : null;
        $excluded_raw             = $request->get_param( 'fechas_excluidas' );
        $payload->fechasExcluidas = is_array( $excluded_raw )
            ? array_map( 'sanitize_text_field', $excluded_raw )
            : array();
        $payload->objetoReserva   = wp_kses_post( $objeto );
        $payload->profile         = $profile;
        $user                     = wp_get_current_user();
        $payload->userId          = ( isset( $user->ID ) && (int) $user->ID > 0 ) ? (int) $user->ID : null;

        // Admin-only extras:
        $estado_raw = $request->get_param( 'estado' );
        if ( $estado_raw !== null ) {
            $estado = sanitize_text_field( (string) $estado_raw );
            if ( ! BookingState::isValid( $estado ) ) {
                return new WP_REST_Response(
                    array(
                        'code'    => 'rest_invalid_estado',
                        'message' => __( 'Estado no válido.', 'reservas-aldealab' ),
                    ),
                    400
                );
            }
            $payload->initialState = $estado;
        } else {
            // Admin flow default: manual bookings land already confirmed.
            $payload->initialState = BookingState::CONFIRMADA;
        }

        $payload->forceOverride         = (bool) $request->get_param( 'force_override' );
        $payload->suppressNotifications = (bool) $request->get_param( 'suppress_notifications' );

        $nota_raw = $request->get_param( 'nota_admin' );
        if ( $nota_raw !== null ) {
            $payload->notaAdmin = sanitize_textarea_field( (string) $nota_raw );
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function sanitizeProfile( array $raw ): array {
        $out = array();
        foreach ( array(
            'nif', 'nombre', 'primer_apellido', 'segundo_apellido',
            'via', 'numero', 'letra', 'escalera', 'piso', 'puerta',
            'municipio', 'provincia', 'codigo_postal',
            'telefono_fijo', 'movil',
        ) as $k ) {
            $out[ $k ] = isset( $raw[ $k ] ) ? sanitize_text_field( (string) $raw[ $k ] ) : '';
        }
        $out['email'] = isset( $raw['email'] ) ? sanitize_email( (string) $raw['email'] ) : '';
        return $out;
    }
}
