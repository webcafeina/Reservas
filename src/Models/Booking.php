<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Models;

/**
 * One booking row — may expand to several booking_dates rows if recurring.
 */
final class Booking {

    public ?int $id;
    public string $uuid;
    public ?int $userId;
    public ?int $profileId;
    public int $salaId;
    public string $estado;
    public string $horaInicio;
    public string $horaFin;
    public ?string $rrule;
    public string $fechaInicio;
    public ?string $fechaFinSerie;
    public string $objetoReserva;
    public ?string $notaAdmin;
    // createdAt / updatedAt are populated by the DB on INSERT and only
    // read back when loading via Repository::fromArray. The create flow
    // never sets them, so we default to null to avoid PHP 7.4+'s
    // "Typed property must not be accessed before initialization" fatal
    // when toArray() is called on a freshly-built Booking.
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @var array<int, string> ISO date strings (YYYY-MM-DD) */
    public array $fechas = array();

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray( array $data ): self {
        $b = new self();
        $b->id             = isset( $data['id'] ) ? (int) $data['id'] : null;
        $b->uuid           = (string) ( $data['uuid'] ?? '' );
        $b->userId         = isset( $data['user_id'] ) && $data['user_id'] !== null && $data['user_id'] !== ''
            ? (int) $data['user_id']
            : null;
        $b->profileId      = isset( $data['profile_id'] ) && $data['profile_id'] !== null && $data['profile_id'] !== ''
            ? (int) $data['profile_id']
            : null;
        $b->salaId         = (int) ( $data['sala_id'] ?? 0 );
        $b->estado         = (string) ( $data['estado'] ?? BookingState::PENDIENTE );
        $b->horaInicio     = (string) ( $data['hora_inicio'] ?? '' );
        $b->horaFin        = (string) ( $data['hora_fin'] ?? '' );
        $b->rrule          = isset( $data['rrule'] ) && $data['rrule'] !== '' ? (string) $data['rrule'] : null;
        $b->fechaInicio    = (string) ( $data['fecha_inicio'] ?? '' );
        $b->fechaFinSerie  = isset( $data['fecha_fin_serie'] ) && $data['fecha_fin_serie'] !== ''
            ? (string) $data['fecha_fin_serie']
            : null;
        $b->objetoReserva  = (string) ( $data['objeto_reserva'] ?? '' );
        $b->notaAdmin      = isset( $data['nota_admin'] ) && $data['nota_admin'] !== ''
            ? (string) $data['nota_admin']
            : null;
        $b->createdAt      = isset( $data['created_at'] ) ? (string) $data['created_at'] : null;
        $b->updatedAt      = isset( $data['updated_at'] ) ? (string) $data['updated_at'] : null;
        if ( isset( $data['fechas'] ) && is_array( $data['fechas'] ) ) {
            $b->fechas = array_values( array_map( 'strval', $data['fechas'] ) );
        }
        return $b;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return array(
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'user_id'         => $this->userId,
            'profile_id'      => $this->profileId,
            'sala_id'         => $this->salaId,
            'estado'          => $this->estado,
            'hora_inicio'     => $this->horaInicio,
            'hora_fin'        => $this->horaFin,
            'rrule'           => $this->rrule,
            'fecha_inicio'    => $this->fechaInicio,
            'fecha_fin_serie' => $this->fechaFinSerie,
            'objeto_reserva'  => $this->objetoReserva,
            'nota_admin'      => $this->notaAdmin,
            'created_at'      => $this->createdAt,
            'updated_at'      => $this->updatedAt,
            'fechas'          => $this->fechas,
        );
    }
}
