<?php

declare(strict_types=1);

namespace WebcafeinaReservas\Tests\Unit\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use WebcafeinaReservas\Services\AvailabilityChecker;
use WebcafeinaReservas\Services\AvailabilityResult;
use wpdb;

/**
 * @covers \WebcafeinaReservas\Services\AvailabilityChecker
 * @covers \WebcafeinaReservas\Services\AvailabilityResult
 */
final class AvailabilityCheckerTest extends TestCase {

    /** @var wpdb&\Mockery\LegacyMockInterface */
    private $wpdb;

    private AvailabilityChecker $checker;

    protected function setUp(): void {
        parent::setUp();
        $this->wpdb         = Mockery::mock( wpdb::class );
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb']    = $this->wpdb;
        $this->checker      = new AvailabilityChecker( $this->wpdb );
    }

    protected function tearDown(): void {
        Mockery::close();
        unset( $GLOBALS['wpdb'] );
        parent::tearDown();
    }

    public function test_invalid_sala_id_throws(): void {
        $this->expectException( InvalidArgumentException::class );
        $this->checker->check( 0, array( new DateTimeImmutable( '2026-05-01' ) ), '09:00', '10:00' );
    }

    public function test_invalid_time_throws(): void {
        $this->expectException( InvalidArgumentException::class );
        $this->checker->check( 1, array( new DateTimeImmutable( '2026-05-01' ) ), '25:00', '10:00' );
    }

    public function test_start_after_end_throws(): void {
        $this->expectException( InvalidArgumentException::class );
        $this->checker->check( 1, array( new DateTimeImmutable( '2026-05-01' ) ), '10:00', '09:00' );
    }

    public function test_empty_date_array_returns_available_without_querying(): void {
        $this->wpdb->shouldNotReceive( 'prepare' );
        $this->wpdb->shouldNotReceive( 'get_results' );

        $result = $this->checker->check( 42, array(), '09:00', '10:00' );

        self::assertTrue( $result->available );
        self::assertSame( array(), $result->conflicts );
    }

    public function test_no_conflicts_returns_available(): void {
        $this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'SELECT …' );
        $this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

        $result = $this->checker->check(
            42,
            array( new DateTimeImmutable( '2026-05-01' ), new DateTimeImmutable( '2026-05-08' ) ),
            '09:00',
            '10:00'
        );

        self::assertInstanceOf( AvailabilityResult::class, $result );
        self::assertTrue( $result->available );
        self::assertSame( array(), $result->conflicts );
    }

    public function test_conflicts_are_returned_normalised(): void {
        $this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'SELECT …' );
        $this->wpdb->shouldReceive( 'get_results' )->once()->andReturn(
            array(
                array(
                    'fecha'       => '2026-05-01',
                    'booking_id'  => '17',
                    'hora_inicio' => '09:30:00',
                    'hora_fin'    => '10:30:00',
                ),
            )
        );

        $result = $this->checker->check(
            42,
            array( new DateTimeImmutable( '2026-05-01' ) ),
            '09:00',
            '10:00'
        );

        self::assertFalse( $result->available );
        self::assertCount( 1, $result->conflicts );
        self::assertSame( 17, $result->conflicts[0]['booking_id'] );
        self::assertSame( '2026-05-01', $result->conflicts[0]['fecha'] );
    }

    public function test_duplicate_dates_are_collapsed_before_query(): void {
        $captured = null;
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturnUsing(
                static function ( string $sql, array $params ) use ( &$captured ): string {
                    $captured = $params;
                    return 'SELECT …';
                }
            );
        $this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

        $this->checker->check(
            42,
            array(
                new DateTimeImmutable( '2026-05-01' ),
                new DateTimeImmutable( '2026-05-01 23:59:00' ),
                new DateTimeImmutable( '2026-05-02' ),
            ),
            '09:00',
            '10:00'
        );

        self::assertIsArray( $captured );
        // First param = sala_id, then 2 blocking states, then unique dates (2), then end + start.
        self::assertContains( '2026-05-01', $captured );
        self::assertContains( '2026-05-02', $captured );
        $dateOccurrences = array_filter(
            $captured,
            static function ( $v ): bool {
                return is_string( $v ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) === 1;
            }
        );
        self::assertCount( 2, $dateOccurrences );
    }

    public function test_checkAndLock_appends_for_update(): void {
        $capturedSql = null;
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturnUsing(
                static function ( string $sql ) use ( &$capturedSql ): string {
                    $capturedSql = $sql;
                    return $sql;
                }
            );
        $this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

        $this->checker->checkAndLock(
            42,
            array( new DateTimeImmutable( '2026-05-01' ) ),
            '09:00',
            '10:00'
        );

        self::assertIsString( $capturedSql );
        self::assertStringContainsString( 'FOR UPDATE', $capturedSql );
    }

    public function test_excludeBookingId_adds_filter(): void {
        $capturedSql = null;
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturnUsing(
                static function ( string $sql, array $params ) use ( &$capturedSql ): string {
                    $capturedSql = $sql;
                    return $sql;
                }
            );
        $this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

        $this->checker->check(
            42,
            array( new DateTimeImmutable( '2026-05-01' ) ),
            '09:00',
            '10:00',
            99
        );

        self::assertIsString( $capturedSql );
        self::assertStringContainsString( 'bd.booking_id <> %d', $capturedSql );
    }

    public function test_overlap_predicate_uses_half_open_intervals(): void {
        $capturedSql = null;
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturnUsing(
                static function ( string $sql, array $params ) use ( &$capturedSql ): string {
                    $capturedSql = $sql;
                    return $sql;
                }
            );
        $this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

        $this->checker->check(
            42,
            array( new DateTimeImmutable( '2026-05-01' ) ),
            '09:00',
            '10:00'
        );

        self::assertIsString( $capturedSql );
        self::assertStringContainsString( 'b.hora_inicio < %s', $capturedSql );
        self::assertStringContainsString( 'b.hora_fin > %s', $capturedSql );
    }

    public function test_transaction_helpers_issue_expected_sql(): void {
        $this->wpdb->shouldReceive( 'query' )->once()->with( 'START TRANSACTION' )->andReturn( 1 );
        $this->wpdb->shouldReceive( 'query' )->once()->with( 'COMMIT' )->andReturn( 1 );
        $this->wpdb->shouldReceive( 'query' )->once()->with( 'ROLLBACK' )->andReturn( 1 );

        $this->checker->beginTransaction();
        $this->checker->commit();
        $this->checker->rollback();

        self::assertTrue( true );
    }

    public function test_available_factory_returns_available_result(): void {
        $r = AvailabilityResult::available();
        self::assertTrue( $r->available );
        self::assertSame( array(), $r->conflicts );
        self::assertSame(
            array(
                'disponible' => true,
                'conflictos' => array(),
            ),
            $r->toArray()
        );
    }

    public function test_conflicting_factory_carries_conflicts(): void {
        $r = AvailabilityResult::conflicting(
            array(
                array(
                    'fecha'       => '2026-05-01',
                    'booking_id'  => 1,
                    'hora_inicio' => '09:00:00',
                    'hora_fin'    => '10:00:00',
                ),
            )
        );
        self::assertFalse( $r->available );
        self::assertCount( 1, $r->conflicts );
    }
}
