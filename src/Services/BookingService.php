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
 *  2. Expand RRULE → DateTimeImmutable[] (or wrap a single date).
 *  3. START TRANSACTION.
 *  4. checkAndLock availability — FOR UPDATE on booking_dates matching the
 *     sala+dates.
 *  5. Insert the booking's own profile row (never shared with other
 *     bookings, even with the same email), booking + booking_dates rows.
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

        // 3. Availability check + insert under a single transaction.
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

            // Inside the transaction and after the availability check: a
            // conflict leaves no orphan profile row behind.
            $profileId = $this->profiles->insert( $request->profile );

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

        // 4. Schedule async dispatch — non-blocking. Emails + PDF run later.
        //    Admin callers can set `suppressNotifications = true` to create
        //    a booking silently (e.g. when the user was already contacted
        //    through another channel).
        if ( ! $request->suppressNotifications && function_exists( 'wp_schedule_single_event' ) ) {
            wp_schedule_single_event( time(), self::ASYNC_HOOK, array( $bookingId ) );
        }

        return BookingResult::ok( $booking );
    }

    /**
     * Updates an existing booking with the same shape of `$request` accepted
     * by `create()`. Re-runs availability with `excludeBookingId` so the
     * booking doesn't conflict with itself, replaces the `booking_dates`
     * rows for the new expansion, and dispatches a "modified" async hook
     * with a snapshot of the original booking so the email handler can
     * build a diff for the solicitante.
     *
     * Turnstile is intentionally bypassed — admin endpoint.
     */
    public function update( int $bookingId, BookingRequest $request ): BookingResult {
        $original = $this->bookings->find( $bookingId );
        if ( $original === null ) {
            return BookingResult::error( 'not-found', 'La reserva no existe.' );
        }
        $originalSnapshot = $original->toArray();

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

        $this->checker->beginTransaction();
        try {
            if ( ! $request->forceOverride ) {
                $availability = $this->checker->checkAndLock(
                    $request->salaId,
                    $dates,
                    $request->horaInicio,
                    $request->horaFin,
                    $bookingId
                );
                if ( ! $availability->available ) {
                    $this->checker->rollback();
                    return BookingResult::conflict( $availability );
                }
            }

            // Update only this booking's own profile row. Bookings created
            // before 0.23.0 without a profile get a fresh row.
            if ( $original->profileId !== null && $original->profileId > 0 ) {
                $profileId = $original->profileId;
                $this->profiles->update( $profileId, $request->profile );
            } else {
                $profileId = $this->profiles->insert( $request->profile );
            }

            $updated                 = new Booking();
            $updated->id             = $bookingId;
            $updated->uuid           = $original->uuid;
            $updated->userId         = $original->userId;
            $updated->profileId      = $profileId;
            $updated->salaId         = $request->salaId;
            $updated->estado         = $request->initialState ?? $original->estado;
            $updated->horaInicio     = $this->normaliseTime( $request->horaInicio );
            $updated->horaFin        = $this->normaliseTime( $request->horaFin );
            $updated->rrule          = $request->rrule;
            $updated->fechaInicio    = $dates[0]->format( 'Y-m-d' );
            $updated->fechaFinSerie  = end( $dates )->format( 'Y-m-d' );
            $updated->objetoReserva  = $request->objetoReserva;
            $updated->notaAdmin      = $request->notaAdmin;

            $this->bookings->updateFullBooking( $updated, $dates );
            $updated->fechas = array_map(
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

        if ( ! $request->suppressNotifications && function_exists( 'wp_schedule_single_event' ) ) {
            wp_schedule_single_event(
                time(),
                'reservas_aldealab_booking_modified',
                array( $bookingId, $originalSnapshot )
            );
        }

        return BookingResult::ok( $updated );
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
