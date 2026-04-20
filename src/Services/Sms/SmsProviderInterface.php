<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services\Sms;

/**
 * Pluggable SMS provider. Implementations wrap a concrete backend
 * (Twilio, MessageBird, Vonage, a local SMPP gateway, …).
 */
interface SmsProviderInterface {

    /**
     * Returns true when the provider has enough config to actually send.
     * Called by EmailNotifier to decide whether to attempt SMS at all —
     * so a misconfigured Twilio doesn't fail the whole booking flow.
     */
    public function isConfigured(): bool;

    /**
     * Send an SMS. Returns an outcome record for logging.
     *
     * @return array{success: bool, error: string|null, provider: string}
     */
    public function send( string $toE164, string $message ): array;
}
