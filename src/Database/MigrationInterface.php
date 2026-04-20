<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Database;

defined( 'ABSPATH' ) || exit;

/**
 * One schema change. Versions are string-sortable (`001`, `002`, …) so the
 * runner can discover files lexicographically and run them in order.
 */
interface MigrationInterface {

    /**
     * Three-digit version string, e.g. '001'. Must be unique across migrations.
     */
    public function version(): string;

    /**
     * Short human-readable description for logs.
     */
    public function description(): string;

    /**
     * Forward migration. MUST be idempotent — `dbDelta` handles that for
     * CREATE TABLE; for custom SQL, guard with `IF NOT EXISTS` yourself.
     */
    public function up(): void;

    /**
     * Drop whatever this migration created. Called only by Uninstaller when
     * the destructive-delete option is enabled.
     */
    public function down(): void;
}
