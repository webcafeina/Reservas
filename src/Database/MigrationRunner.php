<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Scaffold. Real implementation lands in Phase 2.
 *
 * Public surface:
 * - runAll(): execute every migration from current version → latest.
 * - maybeRun(): called on admin_init; runs only if DB version lags code.
 * - dropAll(): used by Uninstaller when the destructive-delete option is on.
 */
final class MigrationRunner {

    public static function runAll(): void {
        // Phase 2.
    }

    public static function maybeRun(): void {
        // Phase 2.
    }

    public static function dropAll(): void {
        // Phase 2.
    }
}
