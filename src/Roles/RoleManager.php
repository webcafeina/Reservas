<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Scaffold. Real implementation lands in Phase 2.
 *
 * Roles owned by this plugin:
 * - `usuario_alojado`: granted to tenants of the Aldealab building.
 * - `reservas_manager`: non-admin staff allowed into the reservations admin UI.
 */
final class RoleManager {

    public static function ensureRoles(): void {
        // Phase 2.
    }

    public static function removeRoles(): void {
        // Phase 2.
    }
}
