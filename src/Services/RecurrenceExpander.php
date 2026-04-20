<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;

/**
 * Expands an RFC 5545 RRULE into a list of concrete date occurrences.
 *
 * Wrapper around simshaun/recurr. Keeps the recurr API at arm's length so:
 *   - The rest of the codebase doesn't import recurr classes directly.
 *   - A safety upper bound is always enforced (never expand infinite rules).
 *   - Excluded dates go through one consistent normalisation path.
 *
 * All returned DateTimes are at midnight UTC on their calendar day: the
 * booking's time-of-day lives on the `bookings` row, not on each occurrence.
 */
final class RecurrenceExpander {

    /**
     * Default guardrail. If an RRULE has no UNTIL and no COUNT, we refuse to
     * expand beyond this many days from `$startDate`.
     */
    public const DEFAULT_SAFETY_DAYS = 365;

    private const MAX_OCCURRENCES = 366 * 2;

    private DateTimeZone $tz;

    public function __construct( ?DateTimeZone $tz = null ) {
        $this->tz = $tz ?? new DateTimeZone( 'UTC' );
    }

    /**
     * @param string                 $rrule          e.g. "FREQ=WEEKLY;BYDAY=TU;UNTIL=20260630T000000Z"
     * @param DateTimeInterface      $startDate      first occurrence / DTSTART
     * @param DateTimeInterface|null $safetyBound    hard upper bound; defaults
     *                                               to $startDate + DEFAULT_SAFETY_DAYS.
     * @param array<int, string>     $excludedDates  'YYYY-MM-DD' strings; any
     *                                               occurrence that falls on
     *                                               one of these is dropped.
     *
     * @return array<int, DateTimeImmutable>
     */
    public function expand(
        string $rrule,
        DateTimeInterface $startDate,
        ?DateTimeInterface $safetyBound = null,
        array $excludedDates = array()
    ): array {
        $rrule = trim( $rrule );
        if ( $rrule === '' ) {
            throw new InvalidArgumentException( 'RRULE cannot be empty. Use expandSingle() for non-recurring bookings.' );
        }

        $start = $this->toImmutable( $startDate );
        $bound = $safetyBound !== null
            ? $this->toImmutable( $safetyBound )
            : $start->modify( '+' . self::DEFAULT_SAFETY_DAYS . ' days' );

        if ( $bound < $start ) {
            throw new InvalidArgumentException( 'safetyBound must be after startDate.' );
        }

        $rule = new Rule( $rrule, $this->toMutable( $start ), null, $this->tz->getName() );

        // Recurr handles excluded dates via setExDates (Ymd format).
        $excludedMap = array();
        foreach ( $excludedDates as $ex ) {
            $exDate = $this->normaliseExcludedDate( (string) $ex );
            if ( $exDate !== null ) {
                $excludedMap[ $exDate->format( 'Y-m-d' ) ] = true;
            }
        }
        if ( $excludedMap !== array() ) {
            $rule->setExDates( array_keys( $excludedMap ) );
        }

        $transformer = new ArrayTransformer();
        $constraint  = new BetweenConstraint(
            $this->toMutable( $start ),
            $this->toMutable( $bound ),
            true
        );

        $collection = $transformer->transform( $rule, $constraint );

        $out   = array();
        $count = 0;

        foreach ( $collection as $occurrence ) {
            if ( $count++ >= self::MAX_OCCURRENCES ) {
                break;
            }
            $date = $this->toImmutable( $occurrence->getStart() )->setTime( 0, 0, 0 );
            $key  = $date->format( 'Y-m-d' );
            if ( isset( $excludedMap[ $key ] ) ) {
                continue;
            }
            $out[] = $date;
        }

        return $out;
    }

    /**
     * Non-recurring booking convenience: returns a single-element array
     * containing the given date at midnight.
     *
     * @return array<int, DateTimeImmutable>
     */
    public function expandSingle( DateTimeInterface $date ): array {
        return array( $this->toImmutable( $date )->setTime( 0, 0, 0 ) );
    }

    private function toImmutable( DateTimeInterface $d ): DateTimeImmutable {
        if ( $d instanceof DateTimeImmutable ) {
            return $d;
        }
        // createFromInterface() is PHP 8.0+; this path works in 7.4.
        return new DateTimeImmutable( $d->format( 'Y-m-d H:i:s.u' ), $d->getTimezone() );
    }

    private function toMutable( DateTimeInterface $d ): \DateTime {
        if ( $d instanceof \DateTime ) {
            return clone $d;
        }
        return new \DateTime( $d->format( 'Y-m-d H:i:s.u' ), $d->getTimezone() );
    }

    private function normaliseExcludedDate( string $input ): ?DateTimeImmutable {
        $input = trim( $input );
        if ( $input === '' ) {
            return null;
        }
        try {
            return ( new DateTimeImmutable( $input, $this->tz ) )->setTime( 0, 0, 0 );
        } catch ( \Exception $e ) {
            return null;
        }
    }
}
