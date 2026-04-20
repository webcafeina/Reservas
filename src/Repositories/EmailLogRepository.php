<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Repositories;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Database\Schema;
use wpdb;

/**
 * Append-only log of outbound emails. Retained for audit + troubleshooting
 * ("the user swears they didn't get the email" — we can confirm/deny from
 * this table).
 */
final class EmailLogRepository {

    private wpdb $wpdb;

    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
    }

    public function record(
        ?int $bookingId,
        string $tipo,
        string $destinatario,
        string $asunto,
        string $estado,
        ?string $error = null
    ): void {
        $this->wpdb->insert(
            Schema::emailLog(),
            array(
                'booking_id'   => $bookingId,
                'tipo'         => $tipo,
                'destinatario' => $destinatario,
                'asunto'       => $asunto,
                'estado'       => $estado,
                'error'        => $error,
            )
        );
    }
}
