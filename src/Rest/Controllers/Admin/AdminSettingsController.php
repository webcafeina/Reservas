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
 * Secrets (Turnstile secret, Twilio auth token) are returned **masked** on
 * GET. PUT accepts the mask as "no change" so we don't clobber them when
 * the admin submits without touching the field.
 */
final class AdminSettingsController {

    private const MASK = '__redacted__';

    private const SECRET_KEYS = array(
        SettingsRegistrar::KEY_TURNSTILE_SECRET,
        SettingsRegistrar::KEY_TWILIO_TOKEN,
    );

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
        foreach ( self::SECRET_KEYS as $key ) {
            if ( ! empty( $settings[ $key ] ) ) {
                $settings[ $key ] = self::MASK;
            }
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

        // Preserve real secret values when the client sent the mask back.
        foreach ( self::SECRET_KEYS as $key ) {
            if ( isset( $raw[ $key ] ) && $raw[ $key ] === self::MASK ) {
                unset( $raw[ $key ] );
            }
        }

        $merged    = array_merge( $existing, $raw );
        $sanitized = SettingsRegistrar::sanitize( $merged );

        update_option( SettingsRegistrar::OPTION, $sanitized, false );

        // Re-mask for the response.
        foreach ( self::SECRET_KEYS as $key ) {
            if ( ! empty( $sanitized[ $key ] ) ) {
                $sanitized[ $key ] = self::MASK;
            }
        }
        return new WP_REST_Response( $sanitized, 200 );
    }
}
