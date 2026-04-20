<?php

declare(strict_types=1);

use WebcafeinaReservas\Database\MigrationInterface;
use WebcafeinaReservas\Tests\Support\RecordingMigration;

require_once __DIR__ . '/../../../Support/RecordingMigration.php';

return new RecordingMigration( '001', 'alpha' );
