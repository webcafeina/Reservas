<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Integration tests (WP-Browser / Codeception) use their own bootstrap;
 * this file is just for pure unit tests that don't load WordPress.
 */

declare(strict_types=1);

// Guard: make sure Composer autoload is present before we run any test.
$autoload = __DIR__ . '/../vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
    fwrite( STDERR, "Composer autoload not found. Run `composer install` first.\n" );
    exit( 1 );
}

require_once $autoload;

// Brain Monkey boots per-test in each test class's setUp()/tearDown().
// See https://brain-wp.github.io/BrainMonkey/docs/wordpress-setup.html
