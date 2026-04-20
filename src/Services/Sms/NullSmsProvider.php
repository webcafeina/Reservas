<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services\Sms;

/**
 * Default provider: does nothing. Returned by SmsProviderFactory when the
 * site hasn't opted into SMS notifications.
 */
final class NullSmsProvider implements SmsProviderInterface {

    public function isConfigured(): bool {
        return false;
    }

    public function send( string $toE164, string $message ): array {
        return array(
            'success'  => false,
            'error'    => 'sms-disabled',
            'provider' => 'null',
        );
    }
}
