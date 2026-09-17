<?php

declare(strict_types=1);

namespace WebcafeinaReservas\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WebcafeinaReservas\Admin\SettingsRegistrar;

/**
 * @covers \WebcafeinaReservas\Admin\SettingsRegistrar
 */
final class SettingsRegistrarTest extends TestCase {

    /** @var mixed */
    private $stored;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $this->stored = false;
        Functions\when( 'get_option' )->alias(
            function ( string $name, $default = false ) {
                return $this->stored === false ? $default : $this->stored;
            }
        );
        Functions\when( 'sanitize_text_field' )->alias(
            static function ( string $value ): string {
                return trim( strip_tags( $value ) );
            }
        );
        Functions\when( 'sanitize_email' )->returnArg();
        Functions\when( 'is_email' )->justReturn( true );
        Functions\when( 'esc_url_raw' )->returnArg();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_signature_is_shown_everywhere_with_the_current_text_by_default(): void {
        self::assertSame( SettingsRegistrar::FIRMA_DEFECTO, SettingsRegistrar::firmaPara( SettingsRegistrar::FIRMA_EN_FORMULARIO ) );
        self::assertSame( SettingsRegistrar::FIRMA_DEFECTO, SettingsRegistrar::firmaPara( SettingsRegistrar::FIRMA_EN_PANEL ) );
    }

    public function test_existing_settings_without_signature_keys_keep_the_default(): void {
        // Installs from before 0.24.0 have an option without the firma_* keys.
        $this->stored = array( 'admin_emails' => array( 'a@example.test' ) );

        self::assertSame( SettingsRegistrar::FIRMA_DEFECTO, SettingsRegistrar::firmaPara( SettingsRegistrar::FIRMA_EN_FORMULARIO ) );
    }

    public function test_each_screen_has_its_own_switch(): void {
        $this->stored = SettingsRegistrar::sanitize(
            array(
                'firma_texto'      => 'Gestor de reservas de AldeaLab',
                'firma_formulario' => false,
                'firma_panel'      => true,
            )
        );

        self::assertNull( SettingsRegistrar::firmaPara( SettingsRegistrar::FIRMA_EN_FORMULARIO ) );
        self::assertSame( 'Gestor de reservas de AldeaLab', SettingsRegistrar::firmaPara( SettingsRegistrar::FIRMA_EN_PANEL ) );
    }

    public function test_empty_text_hides_the_signature(): void {
        $this->stored = SettingsRegistrar::sanitize( array( 'firma_texto' => '   ' ) );

        self::assertSame( '', $this->stored['firma_texto'] );
        self::assertNull( SettingsRegistrar::firmaPara( SettingsRegistrar::FIRMA_EN_FORMULARIO ) );
        self::assertNull( SettingsRegistrar::firmaPara( SettingsRegistrar::FIRMA_EN_PANEL ) );
    }

    public function test_text_is_cut_to_the_max_in_characters_not_bytes(): void {
        $long      = str_repeat( 'Café ☕ ', 30 );
        $sanitized = SettingsRegistrar::sanitize( array( 'firma_texto' => $long ) );

        self::assertSame( SettingsRegistrar::FIRMA_MAX, mb_strlen( $sanitized['firma_texto'] ) );
        self::assertSame( mb_substr( trim( $long ), 0, SettingsRegistrar::FIRMA_MAX ), $sanitized['firma_texto'] );
    }

    public function test_switches_are_stored_as_booleans(): void {
        $sanitized = SettingsRegistrar::sanitize( array( 'firma_formulario' => '0', 'firma_panel' => '1' ) );

        self::assertFalse( $sanitized['firma_formulario'] );
        self::assertTrue( $sanitized['firma_panel'] );
    }
}
