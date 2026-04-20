<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use WebcafeinaReservas\Models\Booking;

/**
 * Outcome of BookingService::create.
 *
 * - Happy path: success=true, booking set.
 * - Slot conflict: success=false, errorCode='conflict', availability set.
 * - Turnstile failure: success=false, errorCode='turnstile-failed'.
 * - Validation failure: success=false, errorCode describes the field.
 */
final class BookingResult {

    public bool $success;
    public ?Booking $booking;
    public ?AvailabilityResult $availability;
    public ?string $errorCode;
    public ?string $errorMessage;

    private function __construct(
        bool $success,
        ?Booking $booking,
        ?AvailabilityResult $availability,
        ?string $errorCode,
        ?string $errorMessage
    ) {
        $this->success      = $success;
        $this->booking      = $booking;
        $this->availability = $availability;
        $this->errorCode    = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    public static function ok( Booking $booking ): self {
        return new self( true, $booking, null, null, null );
    }

    public static function conflict( AvailabilityResult $availability ): self {
        return new self(
            false,
            null,
            $availability,
            'conflict',
            'El horario solicitado no está disponible.'
        );
    }

    public static function error( string $code, string $message ): self {
        return new self( false, null, null, $code, $message );
    }
}
