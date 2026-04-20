<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Admin\SettingsRegistrar;
use WebcafeinaReservas\Rest\RestApi;

/**
 * GET /admin/settings  — read plugin settings.
 * PUT /admin/settings  — merge-update plugin settings.
 *
 * Turnstile secret is returned **masked** on GET. PUT accepts the masked
 * string as "no change" so we don't clobber it when the admin submits
 * without touching the field.
 */
final class AdminSettingsController {

    private const MASK = '__redacted__';

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/settings',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'show' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
                array(
                    'methods'             => 'PUT',
                    'callback'            => array( $this, 'update' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
            )
        );
    }

    public function show( WP_REST_Request $request ): WP_REST_Response {
        $settings = SettingsRegistrar::get();
        if ( ! empty( $settings[ SettingsRegistrar::KEY_TURNSTILE_SECRET ] ) ) {
            $settings[ SettingsRegistrar::KEY_TURNSTILE_SECRET ] = self::MASK;
        }
        return new WP_REST_Response( $settings, 200 );
    }

    public function update( WP_REST_Request $request ): WP_REST_Response {
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

        $existing = SettingsRegistrar::get();

        // Preserve the real secret when the client sent the mask.
        if (
            isset( $raw[ SettingsRegistrar::KEY_TURNSTILE_SECRET ] )
            && $raw[ SettingsRegistrar::KEY_TURNSTILE_SECRET ] === self::MASK
        ) {
            unset( $raw[ SettingsRegistrar::KEY_TURNSTILE_SECRET ] );
        }

        $merged    = array_merge( $existing, $raw );
        $sanitized = SettingsRegistrar::sanitize( $merged );

        update_option( SettingsRegistrar::OPTION, $sanitized, false );

        // Re-mask for response.
        if ( ! empty( $sanitized[ SettingsRegistrar::KEY_TURNSTILE_SECRET ] ) ) {
            $sanitized[ SettingsRegistrar::KEY_TURNSTILE_SECRET ] = self::MASK;
        }
        return new WP_REST_Response( $sanitized, 200 );
    }
}
