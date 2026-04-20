<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Models;

/**
 * Enum-ish container for booking states. Plain constants because the project
 * targets PHP 7.4 (no `enum`).
 */
final class BookingState {

    public const PENDIENTE   = 'pendiente';
    public const CONFIRMADA  = 'confirmada';
    public const CANCELADA   = 'cancelada';
    public const FINALIZADA  = 'finalizada';

    public const ALL = array(
        self::PENDIENTE,
        self::CONFIRMADA,
        self::CANCELADA,
        self::FINALIZADA,
    );

    public static function isValid( string $state ): bool {
        return in_array( $state, self::ALL, true );
    }
}
