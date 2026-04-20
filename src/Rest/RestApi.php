<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Rest\Controllers\AvailabilityController;
use WebcafeinaReservas\Rest\Controllers\BookingsController;
use WebcafeinaReservas\Rest\Controllers\ProfileController;
use WebcafeinaReservas\Rest\Controllers\SpacesController;
use WebcafeinaReservas\Rest\Controllers\Admin\AdminBookingsController;
use WebcafeinaReservas\Rest\Controllers\Admin\AdminStatsController;
use WebcafeinaReservas\Roles\RoleManager;

/**
 * Registers all custom REST routes under `wp-json/reservas/v1/`.
 *
 * Controllers own their own registration — this class just composes them.
 */
final class RestApi {

    public const NAMESPACE = 'reservas/v1';

    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'registerRoutes' ) );
    }

    public static function registerRoutes(): void {
        ( new SpacesController() )->register();
        ( new AvailabilityController() )->register();
        ( new BookingsController() )->register();
        ( new ProfileController() )->register();
        ( new AdminBookingsController() )->register();
        ( new AdminStatsController() )->register();
    }

    /**
     * Shared permission_callback for admin endpoints.
     */
    public static function currentUserCanManage(): bool {
        return current_user_can( RoleManager::CAP_MANAGE );
    }
}
