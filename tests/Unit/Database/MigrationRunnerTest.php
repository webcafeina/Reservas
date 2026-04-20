<?php

declare(strict_types=1);

namespace WebcafeinaReservas\Tests\Unit\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WebcafeinaReservas\Database\MigrationRunner;
use WebcafeinaReservas\Database\Schema;
use WebcafeinaReservas\Tests\Support\RecordingMigration;

/**
 * @covers \WebcafeinaReservas\Database\MigrationRunner
 */
final class MigrationRunnerTest extends TestCase {

    private const FIXTURES = __DIR__ . '/fixtures/migrations';

    /**
     * @var string Tracks the "current DB version" across get/update_option stubs.
     */
    private string $storedVersion = '';

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        RecordingMigration::reset();
        $this->storedVersion = '';

        // get_option(OPTION_DB_VERSION, '') → $this->storedVersion
        Functions\when( 'get_option' )->alias(
            function ( string $name, $default = '' ) {
                if ( $name === Schema::OPTION_DB_VERSION ) {
                    return $this->storedVersion;
                }
                return $default;
            }
        );

        // update_option(OPTION_DB_VERSION, $value, $autoload) → sets storedVersion
        Functions\when( 'update_option' )->alias(
            function ( string $name, $value, $autoload = null ): bool {
                if ( $name === Schema::OPTION_DB_VERSION ) {
                    $this->storedVersion = (string) $value;
                }
                return true;
            }
        );

        Functions\when( 'delete_option' )->alias(
            function ( string $name ): bool {
                if ( $name === Schema::OPTION_DB_VERSION ) {
                    $this->storedVersion = '';
                }
                return true;
            }
        );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_discoverMigrations_returns_sorted_list(): void {
        $migrations = MigrationRunner::discoverMigrations( self::FIXTURES );

        self::assertCount( 3, $migrations );
        self::assertSame( '001', $migrations[0]->version() );
        self::assertSame( '002', $migrations[1]->version() );
        self::assertSame( '003', $migrations[2]->version() );
    }

    public function test_discoverMigrations_on_empty_directory_returns_empty(): void {
        $empty = sys_get_temp_dir() . '/reservas-test-' . uniqid();
        mkdir( $empty );

        try {
            self::assertSame( array(), MigrationRunner::discoverMigrations( $empty ) );
        } finally {
            rmdir( $empty );
        }
    }

    public function test_latestAvailableVersion_returns_highest(): void {
        self::assertSame( '003', MigrationRunner::latestAvailableVersion( self::FIXTURES ) );
    }

    public function test_latestAvailableVersion_is_null_for_empty_dir(): void {
        $empty = sys_get_temp_dir() . '/reservas-test-' . uniqid();
        mkdir( $empty );

        try {
            self::assertNull( MigrationRunner::latestAvailableVersion( $empty ) );
        } finally {
            rmdir( $empty );
        }
    }

    public function test_runFromDirectory_executes_all_when_version_is_empty(): void {
        self::assertSame( '', $this->storedVersion );

        MigrationRunner::runFromDirectory( self::FIXTURES );

        self::assertSame( '003', $this->storedVersion );
        self::assertSame( 1, RecordingMigration::$log['001']['up'] );
        self::assertSame( 1, RecordingMigration::$log['002']['up'] );
        self::assertSame( 1, RecordingMigration::$log['003']['up'] );
    }

    public function test_runFromDirectory_is_idempotent_when_up_to_date(): void {
        $this->storedVersion = '003';

        MigrationRunner::runFromDirectory( self::FIXTURES );

        self::assertSame( '003', $this->storedVersion );
        self::assertSame( array(), RecordingMigration::$log );
    }

    public function test_runFromDirectory_skips_already_applied_and_runs_new_ones(): void {
        $this->storedVersion = '001';

        MigrationRunner::runFromDirectory( self::FIXTURES );

        self::assertSame( '003', $this->storedVersion );
        self::assertArrayNotHasKey( '001', RecordingMigration::$log );
        self::assertSame( 1, RecordingMigration::$log['002']['up'] );
        self::assertSame( 1, RecordingMigration::$log['003']['up'] );
    }

    public function test_second_invocation_does_not_rerun_anything(): void {
        MigrationRunner::runFromDirectory( self::FIXTURES );
        RecordingMigration::reset();

        MigrationRunner::runFromDirectory( self::FIXTURES );

        self::assertSame( array(), RecordingMigration::$log );
        self::assertSame( '003', $this->storedVersion );
    }

    public function test_getDbVersion_returns_stored_value(): void {
        $this->storedVersion = '042';
        self::assertSame( '042', MigrationRunner::getDbVersion() );
    }

    public function test_setDbVersion_writes_option(): void {
        MigrationRunner::setDbVersion( '007' );
        self::assertSame( '007', $this->storedVersion );
    }
}
