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
use WebcafeinaReservas\Repositories\UserProfileRepository;
use WebcafeinaReservas\Rest\RestApi;
use WebcafeinaReservas\Support\Provincias;

/**
 * GET  /user/profile — read logged-in user's stored profile (for form prefill).
 * PUT  /user/profile — update it.
 *
 * Both require the user to be logged in. Guests don't have persistent
 * profiles (those are created on booking via the email unique key).
 */
final class ProfileController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/user/profile',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'show' ),
                    'permission_callback' => array( $this, 'requireLoggedIn' ),
                ),
                array(
                    'methods'             => 'PUT',
                    'callback'            => array( $this, 'update' ),
                    'permission_callback' => array( $this, 'requireLoggedIn' ),
                ),
            )
        );
    }

    public function requireLoggedIn(): bool {
        return is_user_logged_in();
    }

    public function show( WP_REST_Request $request ): WP_REST_Response {
        $user = wp_get_current_user();
        $uid  = isset( $user->ID ) ? (int) $user->ID : 0;
        if ( $uid <= 0 ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_unauthorized',
                    'message' => __( 'Debes iniciar sesión.', 'reservas-aldealab' ),
                ),
                401
            );
        }

        global $wpdb;
        $repo    = new UserProfileRepository( $wpdb );
        $profile = $repo->findForUser( $uid );
        if ( $profile === null ) {
            return new WP_REST_Response( array( 'profile' => null ), 200 );
        }
        return new WP_REST_Response( array( 'profile' => $profile->toArray() ), 200 );
    }

    public function update( WP_REST_Request $request ): WP_REST_Response {
        $user = wp_get_current_user();
        $uid  = isset( $user->ID ) ? (int) $user->ID : 0;
        if ( $uid <= 0 ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_unauthorized',
                    'message' => __( 'Debes iniciar sesión.', 'reservas-aldealab' ),
                ),
                401
            );
        }

        $raw = $request->get_json_params();
        if ( ! is_array( $raw ) ) {
            $raw = $request->get_params();
        }
        if ( ! is_array( $raw ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_params',
                    'message' => __( 'Payload inválido.', 'reservas-aldealab' ),
                ),
                400
            );
        }

        $sanitized = array();
        foreach ( array(
            'nif', 'nombre', 'primer_apellido', 'segundo_apellido',
            'via', 'numero', 'letra', 'escalera', 'piso', 'puerta',
            'municipio', 'provincia', 'codigo_postal',
            'telefono_fijo', 'movil', 'empresa',
        ) as $k ) {
            $sanitized[ $k ] = isset( $raw[ $k ] ) ? sanitize_text_field( (string) $raw[ $k ] ) : '';
        }
        $sanitized['email'] = isset( $raw['email'] ) ? sanitize_email( (string) $raw['email'] ) : $user->user_email;
        $sanitized['user_id'] = $uid;

        $profile = UserProfile::fromArray( $sanitized );

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

        global $wpdb;
        $repo    = new UserProfileRepository( $wpdb );
        $id      = $repo->upsert( $profile );
        $profile->id = $id;

        return new WP_REST_Response( array( 'profile' => $profile->toArray() ), 200 );
    }
}
