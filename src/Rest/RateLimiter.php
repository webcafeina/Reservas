<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest;

/**
 * Best-effort per-IP rate limiting backed by WP transients.
 *
 * Not a fortress: behind a reverse proxy REMOTE_ADDR may be the proxy's IP,
 * so this throttles buckets of users rather than individuals. Its real
 * purpose is to slow down the trivial "curl in a loop" case. Turnstile is
 * the actual anti-bot control.
 */
final class RateLimiter {

    public const DEFAULT_KEY_PREFIX = 'reservas_rl_';
    public const DEFAULT_LIMIT      = 10;
    public const DEFAULT_WINDOW     = 600; // seconds (10 min)

    private string $keyPrefix;
    private int $limit;
    private int $windowSeconds;

    public function __construct(
        int $limit = self::DEFAULT_LIMIT,
        int $windowSeconds = self::DEFAULT_WINDOW,
        string $keyPrefix = self::DEFAULT_KEY_PREFIX
    ) {
        $this->limit         = max( 1, $limit );
        $this->windowSeconds = max( 1, $windowSeconds );
        $this->keyPrefix     = $keyPrefix;
    }

    /**
     * Consume one token. Returns true if the caller may proceed, false if
     * the bucket is exhausted.
     */
    public function attempt( string $identifier ): bool {
        $key     = $this->keyFor( $identifier );
        $current = (int) get_transient( $key );
        if ( $current >= $this->limit ) {
            return false;
        }
        set_transient( $key, $current + 1, $this->windowSeconds );
        return true;
    }

    public function remaining( string $identifier ): int {
        $current = (int) get_transient( $this->keyFor( $identifier ) );
        $left    = $this->limit - $current;
        return $left < 0 ? 0 : $left;
    }

    public function reset( string $identifier ): void {
        delete_transient( $this->keyFor( $identifier ) );
    }

    /**
     * Resolve an IP from $_SERVER. Returns '0.0.0.0' if nothing usable.
     * Intentionally does NOT trust X-Forwarded-For by default: sites behind
     * a trusted proxy can set that up explicitly at the hosting layer.
     */
    public static function clientIp(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? (string) $_SERVER['REMOTE_ADDR']
            : '';
        $ip = filter_var( $ip, FILTER_VALIDATE_IP );
        return is_string( $ip ) && $ip !== '' ? $ip : '0.0.0.0';
    }

    private function keyFor( string $identifier ): string {
        // Hash the identifier so transient keys stay under WP's 172-char limit
        // even if $identifier is e.g. a long proxied-IP list.
        return $this->keyPrefix . substr( md5( $identifier ), 0, 16 );
    }
}
