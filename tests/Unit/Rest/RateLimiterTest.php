<?php

declare(strict_types=1);

namespace WebcafeinaReservas\Tests\Unit\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WebcafeinaReservas\Rest\RateLimiter;

/**
 * @covers \WebcafeinaReservas\Rest\RateLimiter
 */
final class RateLimiterTest extends TestCase {

    /** @var array<string, array{value:int, expires:int}> */
    private array $transients = array();

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $this->transients = array();

        Functions\when( 'get_transient' )->alias(
            function ( string $key ) {
                return $this->transients[ $key ]['value'] ?? 0;
            }
        );
        Functions\when( 'set_transient' )->alias(
            function ( string $key, $value, int $ttl ): bool {
                $this->transients[ $key ] = array(
                    'value'   => (int) $value,
                    'expires' => $ttl,
                );
                return true;
            }
        );
        Functions\when( 'delete_transient' )->alias(
            function ( string $key ): bool {
                unset( $this->transients[ $key ] );
                return true;
            }
        );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_attempts_under_limit_return_true(): void {
        $limiter = new RateLimiter( 3, 600 );
        self::assertTrue( $limiter->attempt( 'ip:1.2.3.4' ) );
        self::assertTrue( $limiter->attempt( 'ip:1.2.3.4' ) );
        self::assertTrue( $limiter->attempt( 'ip:1.2.3.4' ) );
    }

    public function test_attempt_over_limit_returns_false(): void {
        $limiter = new RateLimiter( 2, 600 );
        self::assertTrue( $limiter->attempt( 'ip:1.2.3.4' ) );
        self::assertTrue( $limiter->attempt( 'ip:1.2.3.4' ) );
        self::assertFalse( $limiter->attempt( 'ip:1.2.3.4' ) );
        self::assertFalse( $limiter->attempt( 'ip:1.2.3.4' ) );
    }

    public function test_different_identifiers_have_separate_buckets(): void {
        $limiter = new RateLimiter( 1, 600 );
        self::assertTrue( $limiter->attempt( 'ip:A' ) );
        self::assertFalse( $limiter->attempt( 'ip:A' ) );
        self::assertTrue( $limiter->attempt( 'ip:B' ) );
    }

    public function test_remaining_counts_down(): void {
        $limiter = new RateLimiter( 3, 600 );
        self::assertSame( 3, $limiter->remaining( 'ip:X' ) );
        $limiter->attempt( 'ip:X' );
        self::assertSame( 2, $limiter->remaining( 'ip:X' ) );
        $limiter->attempt( 'ip:X' );
        $limiter->attempt( 'ip:X' );
        self::assertSame( 0, $limiter->remaining( 'ip:X' ) );
    }

    public function test_reset_clears_bucket(): void {
        $limiter = new RateLimiter( 1, 600 );
        $limiter->attempt( 'ip:Y' );
        self::assertFalse( $limiter->attempt( 'ip:Y' ) );
        $limiter->reset( 'ip:Y' );
        self::assertTrue( $limiter->attempt( 'ip:Y' ) );
    }

    public function test_constructor_clamps_to_sensible_minimums(): void {
        // limit=0 and window=0 would be silly; constructor clamps to 1.
        $limiter = new RateLimiter( 0, 0 );
        self::assertTrue( $limiter->attempt( 'ip:Z' ) );
        self::assertFalse( $limiter->attempt( 'ip:Z' ) );
    }

    public function test_clientIp_returns_default_when_server_empty(): void {
        $saved = $_SERVER;
        unset( $_SERVER['REMOTE_ADDR'] );
        self::assertSame( '0.0.0.0', RateLimiter::clientIp() );
        $_SERVER = $saved;
    }

    public function test_clientIp_returns_validated_remote_addr(): void {
        $saved                   = $_SERVER;
        $_SERVER['REMOTE_ADDR']  = '198.51.100.42';
        self::assertSame( '198.51.100.42', RateLimiter::clientIp() );
        $_SERVER = $saved;
    }

    public function test_clientIp_returns_default_for_invalid_remote_addr(): void {
        $saved                  = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        self::assertSame( '0.0.0.0', RateLimiter::clientIp() );
        $_SERVER = $saved;
    }
}
