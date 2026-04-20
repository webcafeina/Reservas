<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services\Sms;

use WebcafeinaReservas\Admin\SettingsRegistrar;

/**
 * Resolves the active SMS provider from plugin settings. Always returns a
 * non-null implementation — callers should check `isConfigured()` before
 * actually sending.
 *
 * Extending with a new provider is a two-file change:
 *   1. Implement SmsProviderInterface.
 *   2. Add a branch here keyed by settings['sms_provider'].
 */
final class SmsProviderFactory {

    public static function fromSettings(): SmsProviderInterface {
        $settings = SettingsRegistrar::get();
        $provider = isset( $settings['sms_provider'] ) ? (string) $settings['sms_provider'] : '';

        switch ( $provider ) {
            case 'twilio':
                return new TwilioSmsProvider(
                    (string) ( $settings['twilio_account_sid'] ?? '' ),
                    (string) ( $settings['twilio_auth_token'] ?? '' ),
                    (string) ( $settings['twilio_from_number'] ?? '' )
                );
            case 'none':
            default:
                return new NullSmsProvider();
        }
    }
}
