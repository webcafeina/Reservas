<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

/**
 * Orchestrates booking creation end-to-end. Wire-up lands in Phase 5:
 *   1. Verify Turnstile token.
 *   2. Expand RRULE → dates.
 *   3. Open transaction.
 *   4. checkAndLock availability.
 *   5. Insert booking + booking_dates + cpa_items.
 *   6. Commit.
 *   7. wp_schedule_single_event → async email+PDF dispatch.
 *
 * This file exists so BookingService::class is resolvable today and
 * other phases can reference the class name in docblocks.
 */
final class BookingService {

    // Implementation in Phase 5.
}
