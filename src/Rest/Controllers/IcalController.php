<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Models\Sala;
use WebcafeinaReservas\PostTypes\SalaCpt;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Repositories\UserProfileRepository;
use WebcafeinaReservas\Rest\RestApi;
use WebcafeinaReservas\Services\IcalGenerator;

/**
 * GET /bookings/{uuid}/ical — returns the booking as text/calendar.
 *
 * UUID-based auth: the UUID is 128 bits of entropy, returned only to the
 * owner on successful creation and shown once in the confirmation email.
 * That's the same threat model as unsubscribe links in marketing emails,
 * which is fine for a non-sensitive iCal export.
 */
final class IcalController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/bookings/(?P<uuid>[0-9a-fA-F-]{36})/ical',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'download' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'uuid' => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );
    }

    public function download( WP_REST_Request $request ) {
        global $wpdb;
        $uuid    = (string) $request->get_param( 'uuid' );
        $bookings = new BookingRepository( $wpdb );
        $booking  = $bookings->findByUuid( $uuid );
        if ( $booking === null ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_not_found',
                    'message' => __( 'Reserva no encontrada.', 'reservas-aldealab' ),
                ),
                404
            );
        }

        $profile = $booking->profileId !== null
            ? ( new UserProfileRepository( $wpdb ) )->findById( (int) $booking->profileId )
            : null;
        if ( $profile === null ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_profile_missing',
                    'message' => __( 'Perfil asociado no encontrado.', 'reservas-aldealab' ),
                ),
                404
            );
        }

        $salaPost = get_post( $booking->salaId );
        if ( ! $salaPost instanceof \WP_Post || $salaPost->post_type !== SalaCpt::POST_TYPE ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_sala_missing',
                    'message' => __( 'Sala asociada no encontrada.', 'reservas-aldealab' ),
                ),
                404
            );
        }
        $sala = Sala::fromPost( $salaPost );

        $ics = ( new IcalGenerator() )->build( $booking, $profile, $sala );

        // Stream raw calendar bytes with the right headers.
        $filename = 'reserva-' . $booking->uuid . '.ics';
        $response = new WP_REST_Response( null, 200 );
        $response->header( 'Content-Type', 'text/calendar; charset=utf-8' );
        $response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
        $response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );

        // WP's REST infrastructure JSON-encodes data; to emit raw bytes we
        // register a late hook that replaces the body.
        add_filter(
            'rest_pre_serve_request',
            static function ( $served, $_response, $_request, $_server ) use ( $ics ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $ics;
                return true;
            },
            10,
            4
        );

        return $response;
    }
}
