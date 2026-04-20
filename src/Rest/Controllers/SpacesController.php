<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Repositories\SalaRepository;
use WebcafeinaReservas\Rest\RestApi;

/**
 * GET  /spaces           — list salas with filters.
 * GET  /spaces/{id}      — single sala.
 *
 * Public (no auth): reading the catalog is anonymous. We do NOT expose
 * admin-only fields; the DTO is what's returned verbatim.
 */
final class SpacesController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/spaces',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'index' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'aforo_min'  => array(
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'aforo_max'  => array(
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'edificio'   => array(
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'servicios'  => array(
                            'type'     => 'array',
                            'required' => false,
                            'items'    => array( 'type' => 'integer' ),
                        ),
                        'disponible' => array(
                            'type'     => 'boolean',
                            'required' => false,
                        ),
                        'per_page'   => array(
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                        'page'       => array(
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            RestApi::NAMESPACE,
            '/spaces/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'show' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'id' => array(
                            'type'              => 'integer',
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );
    }

    public function index( WP_REST_Request $request ): WP_REST_Response {
        $filters = array(
            'aforo_min'  => $request->get_param( 'aforo_min' ) !== null ? (int) $request->get_param( 'aforo_min' ) : null,
            'aforo_max'  => $request->get_param( 'aforo_max' ) !== null ? (int) $request->get_param( 'aforo_max' ) : null,
            'edificio'   => $request->get_param( 'edificio' ) !== null ? (int) $request->get_param( 'edificio' ) : null,
            'servicios'  => is_array( $request->get_param( 'servicios' ) )
                ? array_map( 'intval', $request->get_param( 'servicios' ) )
                : array(),
            'disponible' => $request->get_param( 'disponible' ) !== null
                ? (bool) $request->get_param( 'disponible' )
                : null,
            'per_page'   => $request->get_param( 'per_page' ) !== null ? (int) $request->get_param( 'per_page' ) : 20,
            'page'       => $request->get_param( 'page' ) !== null ? (int) $request->get_param( 'page' ) : 1,
        );

        $salas = ( new SalaRepository() )->search( $filters );
        $data  = array_map(
            static function ( $sala ) {
                return $sala->toArray();
            },
            $salas
        );

        return new WP_REST_Response(
            array(
                'items' => $data,
                'total' => count( $data ),
            ),
            200
        );
    }

    public function show( WP_REST_Request $request ): WP_REST_Response {
        $id   = (int) $request->get_param( 'id' );
        $sala = ( new SalaRepository() )->find( $id );
        if ( $sala === null ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_not_found',
                    'message' => __( 'Sala no encontrada.', 'reservas-aldealab' ),
                ),
                404
            );
        }
        return new WP_REST_Response( $sala->toArray(), 200 );
    }
}
