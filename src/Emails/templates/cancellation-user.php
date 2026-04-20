<?php
/**
 * Cancellation email body (sent to the user when admin cancels).
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

/** @var \WebcafeinaReservas\Models\Booking $booking */
/** @var \WebcafeinaReservas\Models\UserProfile $profile */
/** @var \WebcafeinaReservas\Models\Sala $sala */
/** @var string $fechas_humano */

ob_start();
?>
<h1 style="margin:0 0 16px 0;font-size:22px;font-weight:600;color:#c0392b;">
    <?php esc_html_e( 'Tu reserva ha sido cancelada', 'reservas-aldealab' ); ?>
</h1>

<p style="margin:0 0 16px 0;line-height:1.6;">
    <?php
    printf(
        /* translators: %s user's first name */
        esc_html__( 'Hola %s, te informamos de que tu reserva ha sido cancelada por un gestor.', 'reservas-aldealab' ),
        esc_html( $profile->nombre )
    );
    ?>
</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:16px 0;">
    <tr>
        <td style="padding:12px;background:#f5f6f8;"><strong><?php esc_html_e( 'Sala', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;background:#f5f6f8;"><?php echo esc_html( $sala->title ); ?></td>
    </tr>
    <tr>
        <td style="padding:12px;"><strong><?php esc_html_e( 'Fecha(s)', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;"><?php echo esc_html( $fechas_humano ); ?></td>
    </tr>
    <tr>
        <td style="padding:12px;background:#f5f6f8;"><strong><?php esc_html_e( 'Horario', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;background:#f5f6f8;">
            <?php echo esc_html( substr( $booking->horaInicio, 0, 5 ) . ' – ' . substr( $booking->horaFin, 0, 5 ) ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding:12px;"><strong><?php esc_html_e( 'Nº de reserva', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;"><code><?php echo esc_html( '#' . ( $booking->id ?? 0 ) ); ?></code></td>
    </tr>
</table>

<p style="margin:16px 0;line-height:1.6;">
    <?php esc_html_e( 'Si la cancelación no te parece correcta, contacta con el gestor del espacio.', 'reservas-aldealab' ); ?>
</p>
<?php
$content_html = (string) ob_get_clean();
return $content_html;
