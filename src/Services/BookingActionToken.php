<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

defined( 'ABSPATH' ) || exit;

/**
 * HMAC-SHA256 signed tokens for one-click admin actions from email
 * (accept / reject a pending booking).
 *
 * Encoded format (base64-url): "<bookingId>|<action>|<expiryUnixTs>|<signature>"
 *
 * The secret is wp_salt('auth') — rotates with WP secret keys without
 * us having to manage a separate setting.
 */
final class BookingActionToken {

    public const ACTION_ACCEPT = 'accept';
    public const ACTION_REJECT = 'reject';
    public const TTL_SECONDS   = 7 * DAY_IN_SECONDS;

    public static function isValidAction( string $action ): bool {
        return in_array( $action, array( self::ACTION_ACCEPT, self::ACTION_REJECT ), true );
    }

    public static function generate( int $bookingId, string $action ): string {
        $expiry  = time() + self::TTL_SECONDS;
        $payload = $bookingId . '|' . $action . '|' . $expiry;
        $sig     = hash_hmac( 'sha256', $payload, self::secret() );
        return self::base64UrlEncode( $payload . '|' . $sig );
    }

    /**
     * @return array{valid: bool, booking_id: int, action: string, error: ?string}
     */
    public static function verify( string $token ): array {
        $invalid = array(
            'valid'      => false,
            'booking_id' => 0,
            'action'     => '',
            'error'      => 'malformed',
        );

        $decoded = self::base64UrlDecode( $token );
        if ( $decoded === '' ) {
            return $invalid;
        }
        $parts = explode( '|', $decoded );
        if ( count( $parts ) !== 4 ) {
            return $invalid;
        }
        list( $idStr, $action, $expiryStr, $sig ) = $parts;
        $payload  = $idStr . '|' . $action . '|' . $expiryStr;
        $expected = hash_hmac( 'sha256', $payload, self::secret() );
        if ( ! hash_equals( $expected, $sig ) ) {
            $invalid['error'] = 'signature';
            return $invalid;
        }
        $expiry = (int) $expiryStr;
        if ( $expiry < time() ) {
            $invalid['error'] = 'expired';
            return $invalid;
        }
        if ( ! self::isValidAction( $action ) ) {
            $invalid['error'] = 'action';
            return $invalid;
        }
        return array(
            'valid'      => true,
            'booking_id' => (int) $idStr,
            'action'     => $action,
            'error'      => null,
        );
    }

    private static function secret(): string {
        return wp_salt( 'auth' );
    }

    private static function base64UrlEncode( string $s ): string {
        return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
    }

    private static function base64UrlDecode( string $s ): string {
        $b64 = strtr( $s, '-_', '+/' );
        $pad = strlen( $b64 ) % 4;
        if ( $pad > 0 ) {
            $b64 .= str_repeat( '=', 4 - $pad );
        }
        $decoded = base64_decode( $b64, true );
        return $decoded === false ? '' : $decoded;
    }
}
