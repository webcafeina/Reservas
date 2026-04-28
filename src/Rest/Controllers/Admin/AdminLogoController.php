<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

use Throwable;
use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Rest\RestApi;
use WebcafeinaReservas\Services\AdminLogoStorage;

/**
 * GET    /admin/logo  — current logo state (URL + source).
 * POST   /admin/logo  — multipart upload of a new logo (svg|png).
 * DELETE /admin/logo  — remove the customer-uploaded logo.
 *
 * All gated by `manage_reservas`.
 */
final class AdminLogoController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/logo',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'show' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'upload' ),
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

    public function show( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( AdminLogoStorage::status(), 200 );
    }

    public function upload( WP_REST_Request $request ): WP_REST_Response {
        $files  = $request->get_file_params();
        $upload = isset( $files['file'] ) ? $files['file'] : null;
        if ( ! is_array( $upload ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_missing_file',
                    'message' => __( 'Falta el archivo (campo "file").', 'reservas-aldealab' ),
                ),
                400
            );
        }
        try {
            AdminLogoStorage::saveUpload( $upload );
        } catch ( Throwable $e ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_upload_failed',
                    'message' => $e->getMessage(),
                ),
                422
            );
        }
        return new WP_REST_Response( AdminLogoStorage::status(), 201 );
    }

    public function delete( WP_REST_Request $request ): WP_REST_Response {
        $deleted = AdminLogoStorage::deleteCustom();
        $body    = AdminLogoStorage::status();
        $body['deleted'] = $deleted;
        return new WP_REST_Response( $body, 200 );
    }
}
