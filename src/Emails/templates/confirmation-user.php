<?php
/**
 * User confirmation email body. Variables in scope:
 *   Booking $booking
 *   UserProfile $profile
 *   Sala $sala
 *   string $fechas_humano
 *   bool $incluye_sede
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
<h1 style="margin:0 0 16px 0;font-size:22px;font-weight:600;color:#0b5394;">
    <?php esc_html_e( 'Hemos recibido tu reserva', 'reservas-aldealab' ); ?>
</h1>

<p style="margin:0 0 16px 0;line-height:1.6;">
    <?php
    printf(
        /* translators: %s user's first name */
        esc_html__( 'Hola %s, gracias por tu solicitud. Aquí tienes el resumen de tu reserva:', 'reservas-aldealab' ),
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

<?php if ( $incluye_sede ) : ?>
    <h2 style="margin:24px 0 8px 0;font-size:16px;font-weight:600;color:#0b5394;">
        <?php esc_html_e( 'Próximos pasos: Sede Electrónica', 'reservas-aldealab' ); ?>
    </h2>
    <p style="margin:0 0 12px 0;line-height:1.6;">
        <?php esc_html_e( 'Adjunto a este correo encontrarás el PDF oficial con tus datos ya rellenados. Para formalizar la reserva, debes presentarlo en la Sede Electrónica del Ayuntamiento de Cáceres:', 'reservas-aldealab' ); ?>
    </p>
    <ol style="margin:0 0 16px 20px;line-height:1.6;">
        <li><?php esc_html_e( 'Descarga el PDF adjunto.', 'reservas-aldealab' ); ?></li>
        <li><?php esc_html_e( 'Fírmalo con certificado digital o Cl@ve.', 'reservas-aldealab' ); ?></li>
        <li><?php esc_html_e( 'Preséntalo en la Sede Electrónica del Ayuntamiento como "Solicitud de espacios municipales".', 'reservas-aldealab' ); ?></li>
    </ol>
<?php else : ?>
    <p style="margin:16px 0;line-height:1.6;">
        <?php esc_html_e( 'Al ser usuario ya alojado en el edificio, no necesitas presentar ninguna documentación adicional. Tu reserva quedará confirmada en breve.', 'reservas-aldealab' ); ?>
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
    <?php esc_html_e( 'Tu reserva está en estado "pendiente" hasta que un gestor la confirme. Te avisaremos por email cuando cambie el estado.', 'reservas-aldealab' ); ?>
</p>

<?php
$content_html = (string) ob_get_clean();
return $content_html;
