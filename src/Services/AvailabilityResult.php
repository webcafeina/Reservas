<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

/**
 * Plain result bag returned by AvailabilityChecker.
 *
 * @final
 */
final class AvailabilityResult {

    public bool $available;

    /**
     * @var array<int, array{
     *     fecha:string,
     *     booking_id:int,
     *     hora_inicio:string,
     *     hora_fin:string,
     * }>
     */
    public array $conflicts;

    /**
     * @param array<int, array{
     *     fecha:string,
     *     booking_id:int,
     *     hora_inicio:string,
     *     hora_fin:string,
     * }> $conflicts
     */
    private function __construct( bool $available, array $conflicts ) {
        $this->available = $available;
        $this->conflicts = $conflicts;
    }

    public static function available(): self {
        return new self( true, array() );
    }

    /**
     * @param array<int, array{
     *     fecha:string,
     *     booking_id:int,
     *     hora_inicio:string,
     *     hora_fin:string,
     * }> $conflicts
     */
    public static function conflicting( array $conflicts ): self {
        return new self( false, $conflicts );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return array(
            'disponible' => $this->available,
            'conflictos' => $this->conflicts,
        );
    }
}
