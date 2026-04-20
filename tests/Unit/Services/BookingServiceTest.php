<?php

declare(strict_types=1);

namespace WebcafeinaReservas\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use WebcafeinaReservas\Models\Booking;
use WebcafeinaReservas\Models\BookingState;
use WebcafeinaReservas\Models\UserProfile;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Repositories\UserProfileRepository;
use WebcafeinaReservas\Services\AvailabilityChecker;
use WebcafeinaReservas\Services\AvailabilityResult;
use WebcafeinaReservas\Services\BookingRequest;
use WebcafeinaReservas\Services\BookingResult;
use WebcafeinaReservas\Services\BookingService;
use WebcafeinaReservas\Services\RecurrenceExpander;
use WebcafeinaReservas\Services\TurnstileVerifier;
use wpdb;

/**
 * @covers \WebcafeinaReservas\Services\BookingService
 * @covers \WebcafeinaReservas\Services\BookingRequest
 * @covers \WebcafeinaReservas\Services\BookingResult
 */
final class BookingServiceTest extends TestCase {

    /** @var wpdb&\Mockery\MockInterface */
    private $wpdb;
    /** @var RecurrenceExpander&\Mockery\MockInterface */
    private $expander;
    /** @var AvailabilityChecker&\Mockery\MockInterface */
    private $checker;
    /** @var BookingRepository&\Mockery\MockInterface */
    private $bookings;
    /** @var UserProfileRepository&\Mockery\MockInterface */
    private $profiles;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $this->wpdb     = Mockery::mock( wpdb::class );
        $this->expander = Mockery::mock( RecurrenceExpander::class );
        $this->checker  = Mockery::mock( AvailabilityChecker::class );
        $this->bookings = Mockery::mock( BookingRepository::class );
        $this->profiles = Mockery::mock( UserProfileRepository::class );

        Functions\when( 'wp_generate_uuid4' )->justReturn( '00000000-0000-4000-8000-000000000001' );
        Functions\when( 'wp_schedule_single_event' )->justReturn( true );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function test_turnstile_failure_short_circuits_and_does_not_touch_repositories(): void {
        /** @var TurnstileVerifier&\Mockery\MockInterface $turnstile */
        $turnstile = Mockery::mock( TurnstileVerifier::class );
        $turnstile->shouldReceive( 'verify' )->once()->andReturn(
            array(
                'success'     => false,
                'error_codes' => array( 'invalid-input-response' ),
                'raw'         => null,
            )
        );

        $this->profiles->shouldNotReceive( 'upsert' );
        $this->checker->shouldNotReceive( 'beginTransaction' );
        $this->bookings->shouldNotReceive( 'create' );
        $this->expander->shouldNotReceive( 'expand' );

        $service = $this->makeService( $turnstile );
        $result  = $service->create( $this->makeRequest() );

        self::assertFalse( $result->success );
        self::assertSame( 'turnstile-failed', $result->errorCode );
    }

    public function test_conflict_rolls_back_and_returns_conflict_result(): void {
        $dates = array( new DateTimeImmutable( '2026-05-01' ) );

        $this->expander->shouldReceive( 'expandSingle' )->once()->andReturn( $dates );
        $this->profiles->shouldReceive( 'upsert' )->once()->andReturn( 7 );

        $this->checker->shouldReceive( 'beginTransaction' )->once();
        $this->checker->shouldReceive( 'checkAndLock' )->once()->andReturn(
            AvailabilityResult::conflicting(
                array(
                    array(
                        'fecha'       => '2026-05-01',
                        'booking_id'  => 99,
                        'hora_inicio' => '09:00:00',
                        'hora_fin'    => '10:00:00',
                    ),
                )
            )
        );
        $this->checker->shouldReceive( 'rollback' )->once();
        $this->checker->shouldNotReceive( 'commit' );
        $this->bookings->shouldNotReceive( 'create' );

        $service = $this->makeService( null );
        $result  = $service->create( $this->makeRequest() );

        self::assertFalse( $result->success );
        self::assertSame( 'conflict', $result->errorCode );
        self::assertNotNull( $result->availability );
        self::assertCount( 1, $result->availability->conflicts );
    }

    public function test_happy_path_single_date_inserts_commits_and_schedules_async(): void {
        $dates = array( new DateTimeImmutable( '2026-05-01' ) );
        $this->expander->shouldReceive( 'expandSingle' )->once()->andReturn( $dates );
        $this->profiles->shouldReceive( 'upsert' )->once()->andReturn( 7 );

        $this->checker->shouldReceive( 'beginTransaction' )->once();
        $this->checker->shouldReceive( 'checkAndLock' )->once()->andReturn(
            AvailabilityResult::available()
        );
        $this->checker->shouldReceive( 'commit' )->once();
        $this->checker->shouldNotReceive( 'rollback' );

        $capturedBooking = null;
        $this->bookings->shouldReceive( 'create' )->once()->andReturnUsing(
            function ( Booking $booking, array $dates ) use ( &$capturedBooking ): int {
                $capturedBooking = $booking;
                return 42;
            }
        );

        $scheduled = false;
        Functions\when( 'wp_schedule_single_event' )->alias(
            function ( int $time, string $hook, array $args ) use ( &$scheduled ): bool {
                $scheduled = ( $hook === BookingService::ASYNC_HOOK && $args === array( 42 ) );
                return true;
            }
        );

        $service = $this->makeService( null );
        $result  = $service->create( $this->makeRequest() );

        self::assertTrue( $result->success );
        self::assertNotNull( $result->booking );
        self::assertSame( 42, $result->booking->id );
        self::assertSame( BookingState::PENDIENTE, $result->booking->estado );
        self::assertSame( '2026-05-01', $result->booking->fechaInicio );
        self::assertSame( '2026-05-01', $result->booking->fechaFinSerie );
        self::assertSame( '09:00:00', $result->booking->horaInicio );
        self::assertSame( '10:00:00', $result->booking->horaFin );
        self::assertSame( array( '2026-05-01' ), $result->booking->fechas );

        self::assertNotNull( $capturedBooking );
        self::assertSame( 7, $capturedBooking->profileId );

        self::assertTrue( $scheduled );
    }

    public function test_happy_path_recurring_passes_rrule_to_expander_and_persists_series(): void {
        $dates = array(
            new DateTimeImmutable( '2026-04-07' ),
            new DateTimeImmutable( '2026-04-14' ),
            new DateTimeImmutable( '2026-04-21' ),
            new DateTimeImmutable( '2026-04-28' ),
        );
        $this->expander->shouldReceive( 'expand' )
            ->once()
            ->with(
                'FREQ=WEEKLY;BYDAY=TU;UNTIL=20260430T000000Z',
                Mockery::type( DateTimeImmutable::class ),
                Mockery::type( DateTimeImmutable::class ),
                array( '2026-04-14' )
            )
            ->andReturn( $dates );

        $this->profiles->shouldReceive( 'upsert' )->once()->andReturn( 1 );

        $this->checker->shouldReceive( 'beginTransaction' )->once();
        $this->checker->shouldReceive( 'checkAndLock' )->once()->andReturn(
            AvailabilityResult::available()
        );
        $this->checker->shouldReceive( 'commit' )->once();

        $passedDates = null;
        $this->bookings->shouldReceive( 'create' )->once()->andReturnUsing(
            function ( Booking $b, array $passed ) use ( &$passedDates ): int {
                $passedDates = $passed;
                return 17;
            }
        );

        $req                  = $this->makeRequest();
        $req->rrule           = 'FREQ=WEEKLY;BYDAY=TU;UNTIL=20260430T000000Z';
        $req->fechaFinSerie   = '2026-04-30';
        $req->fechasExcluidas = array( '2026-04-14' );

        $service = $this->makeService( null );
        $result  = $service->create( $req );

        self::assertTrue( $result->success );
        self::assertNotNull( $result->booking );
        self::assertSame( '2026-04-07', $result->booking->fechaInicio );
        self::assertSame( '2026-04-28', $result->booking->fechaFinSerie );
        self::assertCount( 4, $passedDates );
    }

    public function test_invalid_rrule_returns_invalid_recurrence_error(): void {
        $this->expander->shouldReceive( 'expand' )->once()->andThrow(
            new InvalidArgumentException( 'Bad RRULE' )
        );
        $this->profiles->shouldNotReceive( 'upsert' );
        $this->checker->shouldNotReceive( 'beginTransaction' );

        $req        = $this->makeRequest();
        $req->rrule = 'GIBBERISH';

        $service = $this->makeService( null );
        $result  = $service->create( $req );

        self::assertFalse( $result->success );
        self::assertSame( 'invalid-recurrence', $result->errorCode );
    }

    public function test_empty_expanded_dates_returns_no_dates_error(): void {
        $this->expander->shouldReceive( 'expandSingle' )->once()->andReturn( array() );
        $this->profiles->shouldNotReceive( 'upsert' );

        $service = $this->makeService( null );
        $result  = $service->create( $this->makeRequest() );

        self::assertFalse( $result->success );
        self::assertSame( 'no-dates', $result->errorCode );
    }

    public function test_repository_exception_rolls_back(): void {
        $this->expander->shouldReceive( 'expandSingle' )
            ->once()
            ->andReturn( array( new DateTimeImmutable( '2026-05-01' ) ) );
        $this->profiles->shouldReceive( 'upsert' )->once()->andReturn( 1 );
        $this->checker->shouldReceive( 'beginTransaction' )->once();
        $this->checker->shouldReceive( 'checkAndLock' )->once()->andReturn( AvailabilityResult::available() );
        $this->bookings->shouldReceive( 'create' )->once()->andThrow( new \RuntimeException( 'duplicate key' ) );
        $this->checker->shouldReceive( 'rollback' )->once();
        $this->checker->shouldNotReceive( 'commit' );

        $service = $this->makeService( null );
        $result  = $service->create( $this->makeRequest() );

        self::assertFalse( $result->success );
        self::assertSame( 'db-error', $result->errorCode );
    }

    public function test_time_without_seconds_is_padded_with_zero_seconds(): void {
        $this->expander->shouldReceive( 'expandSingle' )
            ->once()
            ->andReturn( array( new DateTimeImmutable( '2026-05-01' ) ) );
        $this->profiles->shouldReceive( 'upsert' )->once()->andReturn( 1 );
        $this->checker->shouldReceive( 'beginTransaction' )->once();
        $this->checker->shouldReceive( 'checkAndLock' )->once()->andReturn( AvailabilityResult::available() );
        $this->checker->shouldReceive( 'commit' )->once();
        $this->bookings->shouldReceive( 'create' )->once()->andReturn( 1 );

        $req             = $this->makeRequest();
        $req->horaInicio = '14:30';
        $req->horaFin    = '15:45';

        $service = $this->makeService( null );
        $result  = $service->create( $req );

        self::assertTrue( $result->success );
        self::assertSame( '14:30:00', $result->booking->horaInicio );
        self::assertSame( '15:45:00', $result->booking->horaFin );
    }

    private function makeService( ?TurnstileVerifier $turnstile ): BookingService {
        return new BookingService(
            $this->wpdb,
            $this->expander,
            $this->checker,
            $this->bookings,
            $this->profiles,
            $turnstile
        );
    }

    private function makeRequest(): BookingRequest {
        $profile                 = new UserProfile();
        $profile->email          = 'user@example.test';
        $profile->nif            = '00000000A';
        $profile->nombre         = 'Ana';
        $profile->primerApellido = 'Pérez';
        $profile->movil          = '600000000';
        $profile->via            = 'Gran Vía';
        $profile->numero         = '1';
        $profile->municipio      = 'Cáceres';
        $profile->provincia      = 'Cáceres';
        $profile->codigoPostal   = '10001';

        $req                 = new BookingRequest();
        $req->salaId         = 12;
        $req->horaInicio     = '09:00:00';
        $req->horaFin        = '10:00:00';
        $req->fechaInicio    = '2026-05-01';
        $req->rrule          = null;
        $req->fechaFinSerie  = null;
        $req->objetoReserva  = 'Reunión de prueba';
        $req->profile        = $profile;
        $req->userId         = null;
        $req->turnstileToken = 'fake-token';
        $req->remoteIp       = '127.0.0.1';

        return $req;
    }
}
