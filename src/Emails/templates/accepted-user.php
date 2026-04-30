<?php
/**
 * Body of the email sent to the solicitante when an admin confirms a
 * previously-pending booking. Variables in scope:
 *   Booking $booking
 *   UserProfile $profile
 *   Sala $sala
 *   string $fechas_humano
 *   bool $incluye_sede
 *   string $ical_url
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

/** @var \WebcafeinaReservas\Models\Booking $booking */
/** @var \WebcafeinaReservas\Models\UserProfile $profile */
/** @var \WebcafeinaReservas\Models\Sala $sala */
/** @var string $fechas_humano */
/** @var bool $incluye_sede */
/** @var string $ical_url */

ob_start();
?>
<h1 style="margin:0 0 16px 0;font-size:22px;font-weight:600;color:#107a3a;">
    <?php esc_html_e( '¡Tu reserva ha sido confirmada!', 'reservas-aldealab' ); ?>
</h1>

<p style="margin:0 0 16px 0;line-height:1.6;">
    <?php
    printf(
        /* translators: %s user's first name */
        esc_html__( 'Hola %s, te confirmamos que un gestor ha aceptado tu reserva. Estos son los datos:', 'reservas-aldealab' ),
        esc_html( $profile->nombre )
    );
    ?>
</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:16px 0;">
    <tr>
        <td style="padding:12px;background:#f5f6f8;border-radius:6px 6px 0 0;"><strong><?php esc_html_e( 'Sala', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;background:#f5f6f8;border-radius:0 6px 0 0;"><?php echo esc_html( $sala->title ); ?></td>
    </tr>
    <tr>
        <td style="padding:12px;border-bottom:1px solid #e5e7eb;"><strong><?php esc_html_e( 'Fecha(s)', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;border-bottom:1px solid #e5e7eb;"><?php echo esc_html( $fechas_humano ); ?></td>
    </tr>
    <tr>
        <td style="padding:12px;border-bottom:1px solid #e5e7eb;"><strong><?php esc_html_e( 'Horario', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;border-bottom:1px solid #e5e7eb;">
            <?php echo esc_html( substr( $booking->horaInicio, 0, 5 ) . ' – ' . substr( $booking->horaFin, 0, 5 ) ); ?>
        </td>
    </tr>
    <tr>
        <td style="padding:12px;border-bottom:1px solid #e5e7eb;"><strong><?php esc_html_e( 'Objeto', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;border-bottom:1px solid #e5e7eb;"><?php echo esc_html( $booking->objetoReserva ); ?></td>
    </tr>
    <tr>
        <td style="padding:12px;"><strong><?php esc_html_e( 'Nº de reserva', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:12px;"><code style="font-family:monospace;font-size:13px;"><?php echo esc_html( '#' . ( $booking->id ?? 0 ) . ' / ' . $booking->uuid ); ?></code></td>
    </tr>
</table>

<div style="background:#e7f5ec;border-left:4px solid #107a3a;padding:12px 16px;margin:16px 0;border-radius:4px;">
    <p style="margin:0;line-height:1.6;font-weight:600;color:#0c5d2c;">
        <?php esc_html_e( 'Tu reserva está ahora en estado "confirmada".', 'reservas-aldealab' ); ?>
    </p>
    <p style="margin:6px 0 0 0;line-height:1.5;font-size:13px;color:#0c5d2c;">
        <?php esc_html_e( 'Si finalmente no puedes asistir, ponte en contacto con el centro lo antes posible para liberar el espacio.', 'reservas-aldealab' ); ?>
    </p>
</div>

<?php if ( $incluye_sede ) : ?>
    <p style="margin:16px 0;line-height:1.6;">
        <?php esc_html_e( 'Adjunto encontrarás de nuevo el PDF oficial con tus datos rellenados, por si lo necesitas para tus trámites.', 'reservas-aldealab' ); ?>
    </p>
<?php endif; ?>

<p style="margin:20px 0 8px 0;">
    <a
        href="<?php echo esc_url( $ical_url ); ?>"
        style="display:inline-block;padding:10px 20px;background:#ffffff;color:#0b5394;border:1px solid #0b5394;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;"
    >
        <?php esc_html_e( 'Añadir al calendario (.ics)', 'reservas-aldealab' ); ?>
    </a>
</p>

<p style="margin:16px 0;color:#5a6370;font-size:14px;">
    <?php esc_html_e( 'Si crees que esta confirmación te ha llegado por error, responde a este correo para avisarnos.', 'reservas-aldealab' ); ?>
</p>

<?php
$content_html = (string) ob_get_clean();
return $content_html;
