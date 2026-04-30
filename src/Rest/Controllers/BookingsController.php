<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Models\UserProfile;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Repositories\UserProfileRepository;
use WebcafeinaReservas\Rest\RateLimiter;
use WebcafeinaReservas\Rest\RestApi;
use WebcafeinaReservas\Services\AvailabilityChecker;
use WebcafeinaReservas\Services\BookingRequest;
use WebcafeinaReservas\Services\BookingService;
use WebcafeinaReservas\Services\RecurrenceExpander;
use WebcafeinaReservas\Services\TurnstileVerifier;
use WebcafeinaReservas\Support\Provincias;

/**
 * POST /bookings — create a new booking. Requires a valid Turnstile token
 * and is subject to per-IP rate limiting.
 */
final class BookingsController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/bookings',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'create' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );
    }

    public function create( WP_REST_Request $request ): WP_REST_Response {
        // Rate limit first — before any DB / external HTTP.
        $limiter = new RateLimiter();
        $ip      = RateLimiter::clientIp();
        if ( ! $limiter->attempt( 'bookings:' . $ip ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_rate_limited',
                    'message' => __( 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.', 'reservas-aldealab' ),
                ),
                429
            );
        }

        $payload = $this->extractRequest( $request, $ip );
        if ( $payload instanceof WP_REST_Response ) {
            return $payload;
        }

        global $wpdb;
        $expander  = new RecurrenceExpander();
        $checker   = new AvailabilityChecker( $wpdb );
        $bookings  = new BookingRepository( $wpdb );
        $profiles  = new UserProfileRepository( $wpdb );
        $turnstile = TurnstileVerifier::fromSettings();

        $service = new BookingService( $wpdb, $expander, $checker, $bookings, $profiles, $turnstile );
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

        $status = $result->errorCode === 'turnstile-failed' ? 403 : 422;
        return new WP_REST_Response(
            array(
                'code'    => 'rest_' . ( $result->errorCode ?? 'error' ),
                'message' => $result->errorMessage ?? __( 'Error al crear la reserva.', 'reservas-aldealab' ),
            ),
            $status
        );
    }

    /**
     * Parses the REST payload into a BookingRequest, or returns an error
     * response if validation fails.
     *
     * @return BookingRequest|WP_REST_Response
     */
    private function extractRequest( WP_REST_Request $request, string $ip ) {
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
        if ( ! Provincias::isValid( $profile->provincia ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_provincia',
                    'message' => __( 'La provincia no es válida. Selecciona una de la lista.', 'reservas-aldealab' ),
                ),
                400
            );
        }
        if ( $profile->empresa === null || trim( $profile->empresa ) === '' ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_empresa',
                    'message' => __( 'El campo empresa es obligatorio.', 'reservas-aldealab' ),
                ),
                400
            );
        }

        $payload                   = new BookingRequest();
        $payload->salaId           = $sala_id;
        $payload->horaInicio       = sanitize_text_field( $hora_inicio );
        $payload->horaFin          = sanitize_text_field( $hora_fin );
        $payload->fechaInicio      = sanitize_text_field( $fecha_inicio );
        $payload->fechaFinSerie    = $request->get_param( 'fecha_fin_serie' ) !== null
            ? sanitize_text_field( (string) $request->get_param( 'fecha_fin_serie' ) )
            : null;
        $payload->rrule            = $request->get_param( 'rrule' ) !== null
            ? sanitize_text_field( (string) $request->get_param( 'rrule' ) )
            : null;
        $excluded_raw              = $request->get_param( 'fechas_excluidas' );
        $payload->fechasExcluidas  = is_array( $excluded_raw )
            ? array_map( 'sanitize_text_field', $excluded_raw )
            : array();
        $payload->objetoReserva    = wp_kses_post( $objeto );
        $payload->profile          = $profile;
        $user                      = wp_get_current_user();
        $payload->userId           = ( isset( $user->ID ) && (int) $user->ID > 0 ) ? (int) $user->ID : null;
        $payload->turnstileToken   = $request->get_param( 'turnstile_token' ) !== null
            ? sanitize_text_field( (string) $request->get_param( 'turnstile_token' ) )
            : null;
        $payload->remoteIp         = $ip;

        $cpa_raw = $request->get_param( 'cpa_items' );
        if ( is_array( $cpa_raw ) ) {
            foreach ( $cpa_raw as $item ) {
                if ( is_array( $item ) && isset( $item['item_type'], $item['item_label'] ) ) {
                    $payload->cpaItems[] = array(
                        'item_type'  => sanitize_text_field( (string) $item['item_type'] ),
                        'item_label' => sanitize_text_field( (string) $item['item_label'] ),
                    );
                }
            }
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
            'telefono_fijo', 'movil', 'empresa',
        ) as $k ) {
            $out[ $k ] = isset( $raw[ $k ] ) ? sanitize_text_field( (string) $raw[ $k ] ) : '';
        }
        $out['email'] = isset( $raw['email'] ) ? sanitize_email( (string) $raw['email'] ) : '';
        return $out;
    }
}
