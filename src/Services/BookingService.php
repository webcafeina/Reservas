<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use DateTimeImmutable;
use Throwable;
use WebcafeinaReservas\Models\Booking;
use WebcafeinaReservas\Models\BookingState;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Repositories\UserProfileRepository;
use wpdb;

/**
 * Orchestrates booking creation end-to-end. Called from BookingsController
 * after request validation.
 *
 * Flow:
 *  1. Verify Turnstile token (if verifier configured).
 *  2. Upsert the user profile (email-keyed).
 *  3. Expand RRULE → DateTimeImmutable[] (or wrap a single date).
 *  4. START TRANSACTION.
 *  5. checkAndLock availability — FOR UPDATE on booking_dates matching the
 *     sala+dates.
 *  6. Insert booking + booking_dates rows.
 *  7. COMMIT.
 *  8. wp_schedule_single_event for async email + PDF dispatch.
 *
 * Any failure inside the transaction triggers ROLLBACK.
 */
final class BookingService {

    public const ASYNC_HOOK = 'reservas_aldealab_send_notifications';

    private wpdb $wpdb;
    private RecurrenceExpander $expander;
    private AvailabilityChecker $checker;
    private BookingRepository $bookings;
    private UserProfileRepository $profiles;
    private ?TurnstileVerifier $turnstile;

    public function __construct(
        wpdb $wpdb,
        RecurrenceExpander $expander,
        AvailabilityChecker $checker,
        BookingRepository $bookings,
        UserProfileRepository $profiles,
        ?TurnstileVerifier $turnstile = null
    ) {
        $this->wpdb      = $wpdb;
        $this->expander  = $expander;
        $this->checker   = $checker;
        $this->bookings  = $bookings;
        $this->profiles  = $profiles;
        $this->turnstile = $turnstile;
    }

    public function create( BookingRequest $request ): BookingResult {
        // 1. Turnstile verification.
        if ( $this->turnstile !== null ) {
            $tokenResult = $this->turnstile->verify(
                $request->turnstileToken ?? '',
                $request->remoteIp
            );
            if ( $tokenResult['success'] !== true ) {
                return BookingResult::error(
                    'turnstile-failed',
                    'La verificación anti-spam falló. Recarga la página e inténtalo de nuevo.'
                );
            }
        }

        // 2. Expand dates.
        try {
            $dates = $this->expandDates( $request );
        } catch ( \InvalidArgumentException $e ) {
            return BookingResult::error( 'invalid-recurrence', $e->getMessage() );
        }

        if ( $dates === array() ) {
            return BookingResult::error(
                'no-dates',
                'La recurrencia no produce ninguna fecha válida.'
            );
        }

        // 3. Upsert profile (outside the availability transaction — it has
        //    its own constraints and can commit independently).
        $profileId = $this->profiles->upsert( $request->profile );

        // 4. Availability check + insert under a single transaction.
        //    Admin callers may set `forceOverride = true` on the request to
        //    skip the slot-conflict check (still protected by the FOR
        //    UPDATE semantics of the inserts themselves).
        $this->checker->beginTransaction();
        try {
            if ( ! $request->forceOverride ) {
                $availability = $this->checker->checkAndLock(
                    $request->salaId,
                    $dates,
                    $request->horaInicio,
                    $request->horaFin
                );

                if ( ! $availability->available ) {
                    $this->checker->rollback();
                    return BookingResult::conflict( $availability );
                }
            }

            $booking               = new Booking();
            $booking->uuid         = $this->generateUuid();
            $booking->userId       = $request->userId;
            $booking->profileId    = $profileId;
            $booking->salaId       = $request->salaId;
            $booking->estado       = $request->initialState ?? BookingState::PENDIENTE;
            $booking->horaInicio   = $this->normaliseTime( $request->horaInicio );
            $booking->horaFin      = $this->normaliseTime( $request->horaFin );
            $booking->rrule        = $request->rrule;
            $booking->fechaInicio  = $dates[0]->format( 'Y-m-d' );
            $booking->fechaFinSerie = end( $dates )->format( 'Y-m-d' );
            $booking->objetoReserva = $request->objetoReserva;
            // Must be set explicitly — typed nullable property on Booking
            // throws if left uninitialized when BookingRepository reads it.
            $booking->notaAdmin    = $request->notaAdmin;

            $bookingId = $this->bookings->create( $booking, $dates );
            $booking->id = $bookingId;
            $booking->fechas = array_map(
                static function ( DateTimeImmutable $d ): string {
                    return $d->format( 'Y-m-d' );
                },
                $dates
            );

            $this->checker->commit();
        } catch ( Throwable $e ) {
            $this->checker->rollback();
            return BookingResult::error( 'db-error', $e->getMessage() );
        }

        // 5. Schedule async dispatch — non-blocking. Emails + PDF run later.
        //    Admin callers can set `suppressNotifications = true` to create
        //    a booking silently (e.g. when the user was already contacted
        //    through another channel).
        if ( ! $request->suppressNotifications && function_exists( 'wp_schedule_single_event' ) ) {
            wp_schedule_single_event( time(), self::ASYNC_HOOK, array( $bookingId ) );
        }

        return BookingResult::ok( $booking );
    }

    /**
     * @return array<int, DateTimeImmutable>
     */
    private function expandDates( BookingRequest $request ): array {
        $start = new DateTimeImmutable( $request->fechaInicio );

        if ( $request->rrule === null || $request->rrule === '' ) {
            return $this->expander->expandSingle( $start );
        }

        $bound = $request->fechaFinSerie !== null && $request->fechaFinSerie !== ''
            ? new DateTimeImmutable( $request->fechaFinSerie )
            : null;

        return $this->expander->expand(
            $request->rrule,
            $start,
            $bound,
            $request->fechasExcluidas
        );
    }

    private function normaliseTime( string $time ): string {
        // Normalise HH:MM → HH:MM:00 for the DB TIME column.
        if ( preg_match( '/^\d{2}:\d{2}$/', $time ) === 1 ) {
            return $time . ':00';
        }
        return $time;
    }

    private function generateUuid(): string {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }
        // Fallback for tests where WP isn't loaded.
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int( 0, 0xffff ), random_int( 0, 0xffff ),
            random_int( 0, 0xffff ),
            random_int( 0, 0x0fff ) | 0x4000,
            random_int( 0, 0x3fff ) | 0x8000,
            random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff )
        );
    }
}
