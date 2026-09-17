<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Admin;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Database\Schema;

/**
 * Shape of `reservas_aldealab_settings` (a single WP option with an array
 * payload). One option keeps the UI simple — the admin panel PUTs the whole
 * thing at once.
 */
final class SettingsRegistrar {

    public const OPTION = Schema::OPTION_SETTINGS;

    public const KEY_ADMIN_EMAILS       = 'admin_emails';
    public const KEY_TURNSTILE_SITE_KEY = 'turnstile_site_key';
    public const KEY_TURNSTILE_SECRET   = 'turnstile_secret';
    public const KEY_SEDE_URL           = 'sede_url';
    public const KEY_SEDE_TRAMITE_URL   = 'sede_tramite_url';
    public const KEY_PDFTK_PATH         = 'pdftk_path';
    public const KEY_VITE_DEV_URL       = 'vite_dev_url';
    public const KEY_EMAIL_INTRO_USER   = 'email_intro_user';
    public const KEY_EMAIL_INTRO_ADMIN  = 'email_intro_admin';
    public const KEY_DELETE_ON_UNINSTALL = 'delete_on_uninstall';

    public const KEY_SMS_PROVIDER        = 'sms_provider';
    public const KEY_TWILIO_SID          = 'twilio_account_sid';
    public const KEY_TWILIO_TOKEN        = 'twilio_auth_token';
    public const KEY_TWILIO_FROM         = 'twilio_from_number';

    public const KEY_FIRMA_TEXTO      = 'firma_texto';
    public const KEY_FIRMA_FORMULARIO = 'firma_formulario';
    public const KEY_FIRMA_PANEL      = 'firma_panel';

    /** Footer signature shown until the admin changes it (year fixed on purpose). */
    public const FIRMA_DEFECTO = 'Desarrollado con ❤️ y ☕ por Webcafeína | 2026';

    /** Max characters (not bytes) of the footer signature. */
    public const FIRMA_MAX = 120;

    public const FIRMA_EN_FORMULARIO = 'formulario';
    public const FIRMA_EN_PANEL      = 'panel';

    public static function register(): void {
        add_action( 'init', array( self::class, 'registerSetting' ) );
    }

    public static function registerSetting(): void {
        register_setting(
            'reservas_aldealab',
            self::OPTION,
            array(
                'type'              => 'object',
                'show_in_rest'      => false, // Gated through our REST controller.
                'default'           => self::defaults(),
                'sanitize_callback' => array( self::class, 'sanitize' ),
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return array(
            self::KEY_ADMIN_EMAILS        => array(),
            self::KEY_TURNSTILE_SITE_KEY  => '',
            self::KEY_TURNSTILE_SECRET    => '',
            self::KEY_SEDE_URL            => 'https://sede.caceres.es/',
            self::KEY_SEDE_TRAMITE_URL    => '',
            self::KEY_PDFTK_PATH          => '',
            self::KEY_VITE_DEV_URL        => '',
            self::KEY_EMAIL_INTRO_USER    => '',
            self::KEY_EMAIL_INTRO_ADMIN   => '',
            self::KEY_DELETE_ON_UNINSTALL => false,
            self::KEY_SMS_PROVIDER        => 'none',
            self::KEY_TWILIO_SID          => '',
            self::KEY_TWILIO_TOKEN        => '',
            self::KEY_TWILIO_FROM         => '',
            self::KEY_FIRMA_TEXTO         => self::FIRMA_DEFECTO,
            self::KEY_FIRMA_FORMULARIO    => true,
            self::KEY_FIRMA_PANEL         => true,
        );
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public static function sanitize( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return self::defaults();
        }
        $current = (array) get_option( self::OPTION, self::defaults() );
        $merged  = array_merge( self::defaults(), $current, $raw );

        // admin_emails: normalise to array of valid emails.
        $emails = $merged[ self::KEY_ADMIN_EMAILS ] ?? array();
        if ( is_string( $emails ) ) {
            $emails = array_map( 'trim', explode( ',', $emails ) );
        }
        if ( ! is_array( $emails ) ) {
            $emails = array();
        }
        $clean = array();
        foreach ( $emails as $e ) {
            $e = sanitize_email( (string) $e );
            if ( $e !== '' && is_email( $e ) ) {
                $clean[] = $e;
            }
        }
        $merged[ self::KEY_ADMIN_EMAILS ] = array_values( array_unique( $clean ) );

        // Scalar strings: trim and sanitize.
        foreach ( array(
            self::KEY_TURNSTILE_SITE_KEY,
            self::KEY_TURNSTILE_SECRET,
            self::KEY_PDFTK_PATH,
            self::KEY_EMAIL_INTRO_USER,
            self::KEY_EMAIL_INTRO_ADMIN,
            self::KEY_TWILIO_SID,
            self::KEY_TWILIO_TOKEN,
            self::KEY_TWILIO_FROM,
        ) as $k ) {
            $merged[ $k ] = sanitize_text_field( (string) ( $merged[ $k ] ?? '' ) );
        }

        // SMS provider: whitelist.
        $smsProvider = (string) ( $merged[ self::KEY_SMS_PROVIDER ] ?? 'none' );
        $merged[ self::KEY_SMS_PROVIDER ] = in_array( $smsProvider, array( 'none', 'twilio' ), true )
            ? $smsProvider
            : 'none';

        // URLs.
        foreach ( array(
            self::KEY_SEDE_URL,
            self::KEY_SEDE_TRAMITE_URL,
            self::KEY_VITE_DEV_URL,
        ) as $k ) {
            $url            = trim( (string) ( $merged[ $k ] ?? '' ) );
            $merged[ $k ]   = $url === '' ? '' : esc_url_raw( $url );
        }

        $merged[ self::KEY_DELETE_ON_UNINSTALL ] = ! empty( $merged[ self::KEY_DELETE_ON_UNINSTALL ] );

        // Footer signature: free text (empty = no signature) + one switch per screen.
        $firma = trim( sanitize_text_field( (string) ( $merged[ self::KEY_FIRMA_TEXTO ] ?? '' ) ) );
        $merged[ self::KEY_FIRMA_TEXTO ]      = mb_substr( $firma, 0, self::FIRMA_MAX );
        $merged[ self::KEY_FIRMA_FORMULARIO ] = ! empty( $merged[ self::KEY_FIRMA_FORMULARIO ] );
        $merged[ self::KEY_FIRMA_PANEL ]      = ! empty( $merged[ self::KEY_FIRMA_PANEL ] );

        return $merged;
    }

    /**
     * Footer signature for one screen (`FIRMA_EN_FORMULARIO` or
     * `FIRMA_EN_PANEL`), or null when it's switched off there or the text
     * is empty.
     */
    public static function firmaPara( string $donde ): ?string {
        $settings = self::get();
        $key      = $donde === self::FIRMA_EN_PANEL ? self::KEY_FIRMA_PANEL : self::KEY_FIRMA_FORMULARIO;
        if ( empty( $settings[ $key ] ) ) {
            return null;
        }
        $texto = trim( (string) ( $settings[ self::KEY_FIRMA_TEXTO ] ?? '' ) );
        return $texto === '' ? null : $texto;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array {
        $value = get_option( self::OPTION, self::defaults() );
        if ( ! is_array( $value ) ) {
            return self::defaults();
        }
        return array_merge( self::defaults(), $value );
    }
}
