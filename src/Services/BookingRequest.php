<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use WebcafeinaReservas\Models\UserProfile;

/**
 * Input DTO for BookingService::create. Controllers build one of these from
 * a sanitized REST payload.
 */
final class BookingRequest {

    public int $salaId;
    public string $horaInicio;
    public string $horaFin;
    public ?string $rrule;
    public string $fechaInicio;
    public ?string $fechaFinSerie;

    /** @var array<int, string> ISO date strings (YYYY-MM-DD) */
    public array $fechasExcluidas = array();

    public string $objetoReserva;
    public UserProfile $profile;
    public ?int $userId;
    public ?string $turnstileToken;
    public ?string $remoteIp;

    /** @var array<int, array{item_type:string, item_label:string}> */
    public array $cpaItems = array();

    /**
     * Admin-only: initial booking state. If null, BookingService falls back
     * to 'pendiente' (the public flow default). Admin requests typically set
     * this to 'confirmada' because an operator is creating the booking
     * intentionally and it should count immediately.
     */
    public ?string $initialState = null;

    /**
     * Admin-only: if true, skip the availability check (the FOR UPDATE
     * conflict detection in AvailabilityChecker). Allows an admin to
     * deliberately book a slot that already has another reservation.
     * Never set from the public controller.
     */
    public bool $forceOverride = false;

    /**
     * Admin-only: if true, skip the async `reservas_aldealab_send_notifications`
     * schedule so the booking is created silently (no user email, no admin
     * email, no SMS). The public controller always leaves this `false`.
     */
    public bool $suppressNotifications = false;

    /**
     * Optional admin note stored on the booking row. Public requests leave
     * this null; admin-created bookings may pass free text here.
     */
    public ?string $notaAdmin = null;
}
