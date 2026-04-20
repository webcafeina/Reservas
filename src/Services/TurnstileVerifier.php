<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

/**
 * Server-side verification of Cloudflare Turnstile tokens.
 *
 * Docs: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 *
 * The secret is read from the plugin settings at call time (not cached in a
 * constant) so admins can rotate it without redeploying.
 */
final class TurnstileVerifier {

    public const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private string $secret;

    public function __construct( string $secret ) {
        $this->secret = $secret;
    }

    /**
     * Factory that reads the secret from plugin settings. Returns null if no
     * secret is configured (callers should then reject the request with a
     * clear error rather than silently bypassing Turnstile).
     */
    public static function fromSettings(): ?self {
        $settings = get_option( 'reservas_aldealab_settings', array() );
        if ( ! is_array( $settings ) ) {
            return null;
        }
        $secret = isset( $settings['turnstile_secret'] ) ? (string) $settings['turnstile_secret'] : '';
        if ( $secret === '' ) {
            return null;
        }
        return new self( $secret );
    }

    /**
     * @return array{success: bool, error_codes: array<int, string>, raw: array<string, mixed>|null}
     */
    public function verify( string $token, ?string $remoteIp = null ): array {
        $token = trim( $token );
        if ( $token === '' ) {
            return array(
                'success'     => false,
                'error_codes' => array( 'missing-input-response' ),
                'raw'         => null,
            );
        }

        $body = array(
            'secret'   => $this->secret,
            'response' => $token,
        );
        if ( $remoteIp !== null && $remoteIp !== '' ) {
            $body['remoteip'] = $remoteIp;
        }

        $response = wp_remote_post(
            self::SITEVERIFY_URL,
            array(
                'timeout' => 5,
                'body'    => $body,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'     => false,
                'error_codes' => array( 'network-error' ),
                'raw'         => null,
            );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( $status !== 200 ) {
            return array(
                'success'     => false,
                'error_codes' => array( 'http-' . $status ),
                'raw'         => null,
            );
        }

        $raw_body = (string) wp_remote_retrieve_body( $response );
        $decoded  = json_decode( $raw_body, true );
        if ( ! is_array( $decoded ) ) {
            return array(
                'success'     => false,
                'error_codes' => array( 'invalid-json' ),
                'raw'         => null,
            );
        }

        $success     = isset( $decoded['success'] ) && $decoded['success'] === true;
        $error_codes = array();
        if ( isset( $decoded['error-codes'] ) && is_array( $decoded['error-codes'] ) ) {
            foreach ( $decoded['error-codes'] as $code ) {
                $error_codes[] = (string) $code;
            }
        }

        return array(
            'success'     => $success,
            'error_codes' => $error_codes,
            'raw'         => $decoded,
        );
    }
}
