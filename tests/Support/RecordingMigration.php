<?php

declare(strict_types=1);

namespace WebcafeinaReservas\Tests\Support;

use WebcafeinaReservas\Database\MigrationInterface;

/**
 * Test double: a migration that records when up()/down() were invoked and by
 * whom. `$log` is static + keyed by version so the fixture files (which are
 * `require`d once and cached by PHP) can still be introspected per-test.
 */
final class RecordingMigration implements MigrationInterface {

    /**
     * @var array<string, array{up:int, down:int}>
     */
    public static array $log = array();

    private string $version;
    private string $description;

    public function __construct( string $version, string $description ) {
        $this->version     = $version;
        $this->description = $description;
    }

    public static function reset(): void {
        self::$log = array();
    }

    public function version(): string {
        return $this->version;
    }

    public function description(): string {
        return $this->description;
    }

    public function up(): void {
        if ( ! isset( self::$log[ $this->version ] ) ) {
            self::$log[ $this->version ] = array(
                'up'   => 0,
                'down' => 0,
            );
        }
        self::$log[ $this->version ]['up']++;
    }

    public function down(): void {
        if ( ! isset( self::$log[ $this->version ] ) ) {
            self::$log[ $this->version ] = array(
                'up'   => 0,
                'down' => 0,
            );
        }
        self::$log[ $this->version ]['down']++;
    }
}
