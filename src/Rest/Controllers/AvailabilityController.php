<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Rest\RestApi;
use WebcafeinaReservas\Services\AvailabilityChecker;
use WebcafeinaReservas\Services\RecurrenceExpander;

/**
 * POST /availability — check whether a sala is free on the given dates/time
 * without consuming the slot. Read-only, no Turnstile, no rate limit.
 */
final class AvailabilityController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/availability',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'check' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );
    }

    public function check( WP_REST_Request $request ): WP_REST_Response {
        $sala_id       = (int) $request->get_param( 'sala_id' );
        $fecha_inicio  = (string) $request->get_param( 'fecha_inicio' );
        $fecha_fin     = (string) ( $request->get_param( 'fecha_fin_serie' ) ?? '' );
        $hora_inicio   = (string) $request->get_param( 'hora_inicio' );
        $hora_fin      = (string) $request->get_param( 'hora_fin' );
        $rrule         = (string) ( $request->get_param( 'rrule' ) ?? '' );
        $excluded_raw  = $request->get_param( 'fechas_excluidas' );
        $excluded      = is_array( $excluded_raw ) ? array_map( 'strval', $excluded_raw ) : array();

        if ( $sala_id <= 0 || $fecha_inicio === '' || $hora_inicio === '' || $hora_fin === '' ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_params',
                    'message' => __( 'Faltan parámetros obligatorios.', 'reservas-aldealab' ),
                ),
                400
            );
        }

        try {
            $expander = new RecurrenceExpander();
            $start    = new DateTimeImmutable( $fecha_inicio );
            $dates    = $rrule === ''
                ? $expander->expandSingle( $start )
                : $expander->expand(
                    $rrule,
                    $start,
                    $fecha_fin !== '' ? new DateTimeImmutable( $fecha_fin ) : null,
                    $excluded
                );
        } catch ( \Exception $e ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_recurrence',
                    'message' => $e->getMessage(),
                ),
                400
            );
        }

        global $wpdb;
        $checker = new AvailabilityChecker( $wpdb );
        try {
            $result = $checker->check( $sala_id, $dates, $hora_inicio, $hora_fin );
        } catch ( \InvalidArgumentException $e ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_params',
                    'message' => $e->getMessage(),
                ),
                400
            );
        }

        return new WP_REST_Response(
            array(
                'disponible'       => $result->available,
                'conflictos'       => $result->conflicts,
                'fechas_evaluadas' => array_map(
                    static function ( DateTimeImmutable $d ): string {
                        return $d->format( 'Y-m-d' );
                    },
                    $dates
                ),
            ),
            200
        );
    }
}
