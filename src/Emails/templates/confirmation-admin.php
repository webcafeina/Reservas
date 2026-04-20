<?php
/**
 * Admin notification email body. Variables in scope:
 *   Booking $booking
 *   UserProfile $profile
 *   Sala $sala
 *   string $fechas_humano
 *   string $admin_url    (deep link to the booking in wp-admin)
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

/** @var \WebcafeinaReservas\Models\Booking $booking */
/** @var \WebcafeinaReservas\Models\UserProfile $profile */
/** @var \WebcafeinaReservas\Models\Sala $sala */
/** @var string $fechas_humano */
/** @var string $admin_url */

ob_start();
?>
<h1 style="margin:0 0 16px 0;font-size:22px;font-weight:600;color:#0b5394;">
    <?php esc_html_e( 'Nueva reserva recibida', 'reservas-aldealab' ); ?>
</h1>

<p style="margin:0 0 16px 0;line-height:1.6;">
    <?php
    printf(
        /* translators: %s sala title */
        esc_html__( 'Se ha creado una reserva pendiente para la sala "%s".', 'reservas-aldealab' ),
        esc_html( $sala->title )
    );
    ?>
</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:16px 0;">
    <tr>
        <td style="padding:10px 12px;background:#f5f6f8;width:170px;"><strong><?php esc_html_e( 'Solicitante', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;background:#f5f6f8;"><?php echo esc_html( $profile->fullName() ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;"><strong><?php esc_html_e( 'NIF', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;"><?php echo esc_html( $profile->nif ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;background:#f5f6f8;"><strong><?php esc_html_e( 'Email', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;background:#f5f6f8;"><a href="mailto:<?php echo esc_attr( $profile->email ); ?>"><?php echo esc_html( $profile->email ); ?></a></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;"><strong><?php esc_html_e( 'Móvil', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;"><?php echo esc_html( $profile->movil ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;background:#f5f6f8;"><strong><?php esc_html_e( 'Fecha(s)', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;background:#f5f6f8;"><?php echo esc_html( $fechas_humano ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;"><strong><?php esc_html_e( 'Horario', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;"><?php echo esc_html( substr( $booking->horaInicio, 0, 5 ) . ' – ' . substr( $booking->horaFin, 0, 5 ) ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;background:#f5f6f8;"><strong><?php esc_html_e( 'Objeto', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;background:#f5f6f8;"><?php echo esc_html( $booking->objetoReserva ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;"><strong><?php esc_html_e( 'Referencia', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;"><code><?php echo esc_html( '#' . ( $booking->id ?? 0 ) ); ?></code></td>
    </tr>
</table>

<p style="margin:24px 0 0 0;">
    <a
        href="<?php echo esc_url( $admin_url ); ?>"
        style="display:inline-block;background:#0b5394;color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;"
    >
        <?php esc_html_e( 'Revisar en el panel', 'reservas-aldealab' ); ?>
    </a>
</p>
<?php
$content_html = (string) ob_get_clean();
return $content_html;
