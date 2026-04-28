<?php
/**
 * Body of the email sent to the solicitante when an admin edits an
 * existing booking. Variables in scope:
 *   Booking $booking
 *   UserProfile $profile
 *   Sala $sala
 *   string $fechas_humano
 *   bool $incluye_sede
 *   bool $tiene_pdf
 *   array<string, array{label: string, before: string, after: string}> $diff
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

/** @var \WebcafeinaReservas\Models\Booking $booking */
/** @var \WebcafeinaReservas\Models\UserProfile $profile */
/** @var \WebcafeinaReservas\Models\Sala $sala */
/** @var string $fechas_humano */
/** @var bool $incluye_sede */
/** @var bool $tiene_pdf */
/** @var array<string, array{label: string, before: string, after: string}> $diff */

ob_start();
?>
<h1 style="margin:0 0 16px 0;font-size:22px;font-weight:600;color:#0b5394;">
    <?php esc_html_e( 'Tu reserva ha sido modificada', 'reservas-aldealab' ); ?>
</h1>

<p style="margin:0 0 16px 0;line-height:1.6;">
    <?php
    printf(
        /* translators: %s user's first name */
        esc_html__( 'Hola %s, un gestor ha actualizado los datos de tu reserva. Estos son los cambios:', 'reservas-aldealab' ),
        esc_html( $profile->nombre )
    );
    ?>
</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
    <thead>
        <tr>
            <th style="text-align:left;padding:10px 12px;background:#0b5394;color:#ffffff;font-weight:600;width:32%;">
                <?php esc_html_e( 'Campo', 'reservas-aldealab' ); ?>
            </th>
            <th style="text-align:left;padding:10px 12px;background:#0b5394;color:#ffffff;font-weight:600;">
                <?php esc_html_e( 'Antes', 'reservas-aldealab' ); ?>
            </th>
            <th style="text-align:left;padding:10px 12px;background:#0b5394;color:#ffffff;font-weight:600;">
                <?php esc_html_e( 'Ahora', 'reservas-aldealab' ); ?>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 0; foreach ( $diff as $row ) : $i++; ?>
            <tr style="background:<?php echo $i % 2 === 0 ? '#ffffff' : '#f5f6f8'; ?>;">
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-weight:600;">
                    <?php echo esc_html( $row['label'] ); ?>
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#666666;text-decoration:line-through;">
                    <?php echo esc_html( $row['before'] ); ?>
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#0c5d2c;font-weight:600;">
                    <?php echo esc_html( $row['after'] ); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2 style="margin:24px 0 8px 0;font-size:16px;font-weight:600;color:#0b5394;">
    <?php esc_html_e( 'Resumen actualizado', 'reservas-aldealab' ); ?>
</h2>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:8px 0 16px 0;">
    <tr>
        <td style="padding:10px 12px;background:#f5f6f8;width:170px;"><strong><?php esc_html_e( 'Sala', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;background:#f5f6f8;"><?php echo esc_html( $sala->title ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;"><strong><?php esc_html_e( 'Fecha(s)', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;"><?php echo esc_html( $fechas_humano ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;background:#f5f6f8;"><strong><?php esc_html_e( 'Horario', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;background:#f5f6f8;"><?php echo esc_html( substr( $booking->horaInicio, 0, 5 ) . ' – ' . substr( $booking->horaFin, 0, 5 ) ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;"><strong><?php esc_html_e( 'Objeto', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;"><?php echo esc_html( $booking->objetoReserva ); ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px;background:#f5f6f8;"><strong><?php esc_html_e( 'Nº de reserva', 'reservas-aldealab' ); ?></strong></td>
        <td style="padding:10px 12px;background:#f5f6f8;"><code style="font-family:monospace;font-size:13px;"><?php echo esc_html( '#' . ( $booking->id ?? 0 ) . ' / ' . $booking->uuid ); ?></code></td>
    </tr>
</table>

<?php if ( $tiene_pdf && $incluye_sede ) : ?>
    <p style="margin:16px 0;line-height:1.6;">
        <?php esc_html_e( 'Adjunto encontrarás el PDF oficial actualizado con los datos nuevos. Si ya habías presentado el anterior en la Sede Electrónica, sustitúyelo por este.', 'reservas-aldealab' ); ?>
    </p>
<?php endif; ?>

<p style="margin:16px 0;color:#5a6370;font-size:14px;">
    <?php esc_html_e( 'Si no esperabas este cambio, contacta con el centro lo antes posible.', 'reservas-aldealab' ); ?>
</p>

<?php
$content_html = (string) ob_get_clean();
return $content_html;
