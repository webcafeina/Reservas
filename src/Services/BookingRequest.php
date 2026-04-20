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
}
