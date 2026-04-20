<?php

declare(strict_types=1);

namespace WebcafeinaReservas\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WebcafeinaReservas\Services\TurnstileVerifier;

/**
 * @covers \WebcafeinaReservas\Services\TurnstileVerifier
 */
final class TurnstileVerifierTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\when( 'is_wp_error' )->alias(
            static function ( $thing ): bool {
                return $thing instanceof \WP_Error;
            }
        );
        Functions\when( 'wp_remote_retrieve_response_code' )->alias(
            static function ( $response ): int {
                return (int) ( is_array( $response ) && isset( $response['response']['code'] )
                    ? $response['response']['code']
                    : 0 );
            }
        );
        Functions\when( 'wp_remote_retrieve_body' )->alias(
            static function ( $response ): string {
                return is_array( $response ) && isset( $response['body'] )
                    ? (string) $response['body']
                    : '';
            }
        );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_empty_token_is_rejected_without_http_call(): void {
        $called = false;
        Functions\when( 'wp_remote_post' )->alias(
            static function () use ( &$called ): array {
                $called = true;
                return array();
            }
        );

        $result = ( new TurnstileVerifier( 'secret-x' ) )->verify( '   ' );

        self::assertFalse( $called );
        self::assertFalse( $result['success'] );
        self::assertSame( array( 'missing-input-response' ), $result['error_codes'] );
    }

    public function test_successful_response(): void {
        Functions\when( 'wp_remote_post' )->alias(
            static function (): array {
                return array(
                    'response' => array( 'code' => 200 ),
                    'body'     => '{"success":true,"hostname":"example.com"}',
                );
            }
        );

        $result = ( new TurnstileVerifier( 'secret-x' ) )->verify( 'valid-token' );

        self::assertTrue( $result['success'] );
        self::assertSame( array(), $result['error_codes'] );
    }

    public function test_failed_response_carries_error_codes(): void {
        Functions\when( 'wp_remote_post' )->alias(
            static function (): array {
                return array(
                    'response' => array( 'code' => 200 ),
                    'body'     => '{"success":false,"error-codes":["invalid-input-response","timeout-or-duplicate"]}',
                );
            }
        );

        $result = ( new TurnstileVerifier( 'secret-x' ) )->verify( 'expired-token' );

        self::assertFalse( $result['success'] );
        self::assertSame(
            array( 'invalid-input-response', 'timeout-or-duplicate' ),
            $result['error_codes']
        );
    }

    public function test_network_error_maps_to_error_code(): void {
        Functions\when( 'wp_remote_post' )->alias(
            static function (): \WP_Error {
                return new \WP_Error( 'http_request_failed', 'Connection refused' );
            }
        );

        $result = ( new TurnstileVerifier( 'secret-x' ) )->verify( 'token' );

        self::assertFalse( $result['success'] );
        self::assertSame( array( 'network-error' ), $result['error_codes'] );
    }

    public function test_non_200_response_maps_to_http_code(): void {
        Functions\when( 'wp_remote_post' )->alias(
            static function (): array {
                return array(
                    'response' => array( 'code' => 503 ),
                    'body'     => '',
                );
            }
        );

        $result = ( new TurnstileVerifier( 'secret-x' ) )->verify( 'token' );

        self::assertFalse( $result['success'] );
        self::assertSame( array( 'http-503' ), $result['error_codes'] );
    }

    public function test_invalid_json_maps_to_error_code(): void {
        Functions\when( 'wp_remote_post' )->alias(
            static function (): array {
                return array(
                    'response' => array( 'code' => 200 ),
                    'body'     => 'not json',
                );
            }
        );

        $result = ( new TurnstileVerifier( 'secret-x' ) )->verify( 'token' );

        self::assertFalse( $result['success'] );
        self::assertSame( array( 'invalid-json' ), $result['error_codes'] );
    }

    public function test_fromSettings_returns_null_when_option_missing(): void {
        Functions\when( 'get_option' )->alias(
            static function ( string $name, $default = null ): array {
                return array();
            }
        );

        self::assertNull( TurnstileVerifier::fromSettings() );
    }

    public function test_fromSettings_returns_null_when_secret_empty(): void {
        Functions\when( 'get_option' )->alias(
            static function ( string $name, $default = null ): array {
                return array( 'turnstile_secret' => '' );
            }
        );

        self::assertNull( TurnstileVerifier::fromSettings() );
    }

    public function test_fromSettings_builds_verifier_with_stored_secret(): void {
        Functions\when( 'get_option' )->alias(
            static function ( string $name, $default = null ): array {
                return array( 'turnstile_secret' => 'top-secret' );
            }
        );

        $v = TurnstileVerifier::fromSettings();

        self::assertInstanceOf( TurnstileVerifier::class, $v );
    }
}
