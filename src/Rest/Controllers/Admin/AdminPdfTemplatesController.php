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
use WebcafeinaReservas\Services\PdfTemplateStorage;

/**
 * GET    /admin/pdf-templates         — list both templates with their source.
 * POST   /admin/pdf-templates/{key}   — multipart upload of a replacement PDF.
 * DELETE /admin/pdf-templates/{key}   — revert to the packaged default.
 *
 * All gated by `manage_reservas`.
 */
final class AdminPdfTemplatesController {

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/pdf-templates',
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
            '/admin/pdf-templates/(?P<key>[a-z0-9-]+)',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'upload' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array( $this, 'revert' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
            )
        );
    }

    public function index( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response(
            array( 'items' => PdfTemplateStorage::list() ),
            200
        );
    }

    public function upload( WP_REST_Request $request ): WP_REST_Response {
        $key = (string) $request->get_param( 'key' );
        if ( ! in_array( $key, PdfTemplateStorage::keys(), true ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_key',
                    'message' => __( 'Clave de plantilla desconocida.', 'reservas-aldealab' ),
                ),
                400
            );
        }

        $files = $request->get_file_params();
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
            $path = PdfTemplateStorage::saveUpload( $key, $upload );
        } catch ( Throwable $e ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_upload_failed',
                    'message' => $e->getMessage(),
                ),
                422
            );
        }

        return new WP_REST_Response(
            array(
                'success'     => true,
                'key'         => $key,
                'path'        => $path,
                'source'      => 'custom',
                'uploaded_at' => gmdate( 'c' ),
            ),
            201
        );
    }

    public function revert( WP_REST_Request $request ): WP_REST_Response {
        $key = (string) $request->get_param( 'key' );
        if ( ! in_array( $key, PdfTemplateStorage::keys(), true ) ) {
            return new WP_REST_Response(
                array(
                    'code'    => 'rest_invalid_key',
                    'message' => __( 'Clave de plantilla desconocida.', 'reservas-aldealab' ),
                ),
                400
            );
        }
        $deleted = PdfTemplateStorage::deleteCustom( $key );
        return new WP_REST_Response(
            array(
                'success' => true,
                'reverted' => $deleted,
                'key'     => $key,
                'source'  => 'packaged',
            ),
            200
        );
    }
}
