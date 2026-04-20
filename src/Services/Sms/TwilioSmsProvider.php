<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services\Sms;

/**
 * Twilio SMS provider. Uses the official REST API v2010.
 *
 * Endpoint: https://api.twilio.com/2010-04-01/Accounts/{SID}/Messages.json
 * Basic auth: SID + auth token.
 * Body: `To`, `From`, `Body`.
 *
 * Docs: https://www.twilio.com/docs/sms/api/message-resource
 */
final class TwilioSmsProvider implements SmsProviderInterface {

    private string $accountSid;
    private string $authToken;
    private string $fromNumber;

    public function __construct( string $accountSid, string $authToken, string $fromNumber ) {
        $this->accountSid = trim( $accountSid );
        $this->authToken  = trim( $authToken );
        $this->fromNumber = trim( $fromNumber );
    }

    public function isConfigured(): bool {
        return $this->accountSid !== '' && $this->authToken !== '' && $this->fromNumber !== '';
    }

    public function send( string $toE164, string $message ): array {
        if ( ! $this->isConfigured() ) {
            return array(
                'success'  => false,
                'error'    => 'twilio-not-configured',
                'provider' => 'twilio',
            );
        }
        $to = $this->normalisePhone( $toE164 );
        if ( $to === null ) {
            return array(
                'success'  => false,
                'error'    => 'invalid-phone',
                'provider' => 'twilio',
            );
        }

        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            rawurlencode( $this->accountSid )
        );

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 10,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $this->accountSid . ':' . $this->authToken ),
                ),
                'body'    => array(
                    'To'   => $to,
                    'From' => $this->fromNumber,
                    'Body' => $this->truncate( $message, 1600 ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'  => false,
                'error'    => 'network: ' . $response->get_error_message(),
                'provider' => 'twilio',
            );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( $status >= 200 && $status < 300 ) {
            return array(
                'success'  => true,
                'error'    => null,
                'provider' => 'twilio',
            );
        }

        $body   = (string) wp_remote_retrieve_body( $response );
        $parsed = json_decode( $body, true );
        $msg    = is_array( $parsed ) && isset( $parsed['message'] )
            ? (string) $parsed['message']
            : 'HTTP ' . $status;

        return array(
            'success'  => false,
            'error'    => 'twilio: ' . $msg,
            'provider' => 'twilio',
        );
    }

    /**
     * Best-effort E.164 normalisation. If the number already starts with '+',
     * trust it; otherwise, if it looks Spanish (9 digits), prefix +34.
     */
    private function normalisePhone( string $raw ): ?string {
        $trim = trim( preg_replace( '/[\s\-]/', '', $raw ) ?? '' );
        if ( $trim === '' ) {
            return null;
        }
        if ( strpos( $trim, '+' ) === 0 ) {
            return $trim;
        }
        if ( preg_match( '/^\d{9}$/', $trim ) === 1 ) {
            return '+34' . $trim;
        }
        if ( preg_match( '/^\d{10,15}$/', $trim ) === 1 ) {
            return '+' . $trim;
        }
        return null;
    }

    private function truncate( string $s, int $max ): string {
        if ( strlen( $s ) <= $max ) {
            return $s;
        }
        return substr( $s, 0, $max - 1 ) . '…';
    }
}
