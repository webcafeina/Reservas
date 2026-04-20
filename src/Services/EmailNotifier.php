<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

/**
 * Sends transactional emails (admin copy + user copy + cancellation) and
 * records every attempt in the reservas_email_log table.
 *
 * Real implementation lands in Phase 7 along with the PDF generator and
 * HTML templates. This stub keeps BookingService's references resolvable.
 */
final class EmailNotifier {

    public function sendBookingConfirmation( int $bookingId ): void {
        // Phase 7.
    }

    public function sendAdminNotification( int $bookingId ): void {
        // Phase 7.
    }

    public function sendCancellation( int $bookingId ): void {
        // Phase 7.
    }
}
