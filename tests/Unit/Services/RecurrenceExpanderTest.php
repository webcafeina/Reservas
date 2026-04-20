<?php

declare(strict_types=1);

namespace WebcafeinaReservas\Tests\Unit\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WebcafeinaReservas\Services\RecurrenceExpander;

/**
 * @covers \WebcafeinaReservas\Services\RecurrenceExpander
 */
final class RecurrenceExpanderTest extends TestCase {

    private RecurrenceExpander $expander;

    protected function setUp(): void {
        parent::setUp();
        $this->expander = new RecurrenceExpander( new DateTimeZone( 'Europe/Madrid' ) );
    }

    public function test_expandSingle_returns_one_occurrence_at_midnight(): void {
        $date = new DateTimeImmutable( '2026-05-20 14:30:00', new DateTimeZone( 'Europe/Madrid' ) );

        $result = $this->expander->expandSingle( $date );

        self::assertCount( 1, $result );
        self::assertSame( '2026-05-20', $result[0]->format( 'Y-m-d' ) );
        self::assertSame( '00:00:00', $result[0]->format( 'H:i:s' ) );
    }

    public function test_expand_empty_rrule_throws(): void {
        $this->expectException( InvalidArgumentException::class );
        $this->expander->expand( '   ', new DateTimeImmutable( '2026-04-01' ) );
    }

    public function test_expand_safetyBound_before_startDate_throws(): void {
        $this->expectException( InvalidArgumentException::class );
        $this->expander->expand(
            'FREQ=DAILY;COUNT=3',
            new DateTimeImmutable( '2026-06-15' ),
            new DateTimeImmutable( '2026-06-01' )
        );
    }

    public function test_expand_weekly_by_weekday_until(): void {
        // Every Tuesday from 2026-04-07 (Tue) until 2026-06-30 inclusive.
        // April Tuesdays: 7, 14, 21, 28.
        // May Tuesdays: 5, 12, 19, 26.
        // June Tuesdays: 2, 9, 16, 23, 30.
        // Total: 13 occurrences.
        $result = $this->expander->expand(
            'FREQ=WEEKLY;BYDAY=TU;UNTIL=20260630T235959Z',
            new DateTimeImmutable( '2026-04-07' )
        );

        $dates = $this->toIsoDates( $result );

        self::assertContains( '2026-04-07', $dates );
        self::assertContains( '2026-04-14', $dates );
        self::assertContains( '2026-06-30', $dates );
        self::assertCount( 13, $dates );

        // Every date is a Tuesday.
        foreach ( $result as $d ) {
            self::assertSame( 'Tuesday', $d->format( 'l' ) );
        }
    }

    public function test_expand_respects_count(): void {
        $result = $this->expander->expand(
            'FREQ=WEEKLY;BYDAY=TU;COUNT=5',
            new DateTimeImmutable( '2026-04-07' )
        );

        self::assertCount( 5, $result );
        self::assertSame( '2026-04-07', $result[0]->format( 'Y-m-d' ) );
        self::assertSame( '2026-05-05', $result[4]->format( 'Y-m-d' ) );
    }

    public function test_expand_filters_excluded_dates(): void {
        // Tuesdays from 2026-04-07 to 2026-04-28, excluding 2026-04-14.
        $result = $this->expander->expand(
            'FREQ=WEEKLY;BYDAY=TU;UNTIL=20260428T235959Z',
            new DateTimeImmutable( '2026-04-07' ),
            null,
            array( '2026-04-14' )
        );

        $dates = $this->toIsoDates( $result );

        self::assertNotContains( '2026-04-14', $dates );
        self::assertContains( '2026-04-07', $dates );
        self::assertContains( '2026-04-21', $dates );
        self::assertContains( '2026-04-28', $dates );
        self::assertCount( 3, $dates );
    }

    public function test_expand_ignores_malformed_excluded_dates(): void {
        $result = $this->expander->expand(
            'FREQ=DAILY;COUNT=3',
            new DateTimeImmutable( '2026-04-01' ),
            null,
            array( 'not-a-date', '', '2026-04-02' )
        );

        $dates = $this->toIsoDates( $result );
        self::assertSame( array( '2026-04-01', '2026-04-03' ), $dates );
    }

    public function test_expand_monthly_by_setpos_third_monday(): void {
        // "Third Monday of the month" for 3 months starting Apr 2026.
        // April 2026: 3rd Monday = 20; May: 18; June: 15.
        $result = $this->expander->expand(
            'FREQ=MONTHLY;BYDAY=MO;BYSETPOS=3;COUNT=3',
            new DateTimeImmutable( '2026-04-01' )
        );

        $dates = $this->toIsoDates( $result );
        self::assertSame( array( '2026-04-20', '2026-05-18', '2026-06-15' ), $dates );
    }

    public function test_expand_daily_interval(): void {
        // Every other day, 5 times, starting 2026-04-01.
        $result = $this->expander->expand(
            'FREQ=DAILY;INTERVAL=2;COUNT=5',
            new DateTimeImmutable( '2026-04-01' )
        );

        $dates = $this->toIsoDates( $result );
        self::assertSame(
            array( '2026-04-01', '2026-04-03', '2026-04-05', '2026-04-07', '2026-04-09' ),
            $dates
        );
    }

    public function test_expand_yearly(): void {
        $result = $this->expander->expand(
            'FREQ=YEARLY;COUNT=3',
            new DateTimeImmutable( '2026-06-15' )
        );

        $dates = $this->toIsoDates( $result );
        self::assertSame( array( '2026-06-15', '2027-06-15', '2028-06-15' ), $dates );
    }

    public function test_expand_open_ended_rule_is_clamped_by_default_safety_bound(): void {
        // No UNTIL, no COUNT → must be clamped to DEFAULT_SAFETY_DAYS (365).
        $start  = new DateTimeImmutable( '2026-04-01' );
        $result = $this->expander->expand( 'FREQ=DAILY', $start );

        self::assertGreaterThan( 0, count( $result ) );
        self::assertLessThanOrEqual(
            RecurrenceExpander::DEFAULT_SAFETY_DAYS + 1,
            count( $result )
        );
        // Last date must be within safety window.
        $last = end( $result );
        self::assertInstanceOf( DateTimeImmutable::class, $last );
        $boundary = $start->modify( '+' . RecurrenceExpander::DEFAULT_SAFETY_DAYS . ' days' );
        self::assertLessThanOrEqual( $boundary, $last );
    }

    public function test_expand_honours_explicit_safety_bound(): void {
        $start  = new DateTimeImmutable( '2026-04-01' );
        $bound  = new DateTimeImmutable( '2026-04-10' );
        $result = $this->expander->expand( 'FREQ=DAILY', $start, $bound );

        $dates = $this->toIsoDates( $result );
        self::assertSame(
            array(
                '2026-04-01', '2026-04-02', '2026-04-03', '2026-04-04', '2026-04-05',
                '2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10',
            ),
            $dates
        );
    }

    public function test_expand_all_occurrences_have_zero_time(): void {
        $result = $this->expander->expand(
            'FREQ=WEEKLY;BYDAY=MO;COUNT=4',
            new DateTimeImmutable( '2026-04-06' )
        );

        foreach ( $result as $date ) {
            self::assertSame( '00:00:00', $date->format( 'H:i:s' ), $date->format( 'Y-m-d' ) );
        }
    }

    /**
     * @param array<int, DateTimeImmutable> $dates
     * @return array<int, string>
     */
    private function toIsoDates( array $dates ): array {
        return array_map(
            static function ( DateTimeImmutable $d ): string {
                return $d->format( 'Y-m-d' );
            },
            $dates
        );
    }
}
