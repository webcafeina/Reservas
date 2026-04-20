<?php
/**
 * Email layout wrapper. Expects:
 *   string $title
 *   string $content_html  (already-safe HTML — caller renders its body and
 *                          passes it in)
 *   string $header_image_url
 *
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

/** @var string $title */
/** @var string $content_html */
/** @var string $header_image_url */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $title ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#1b1f24;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f6f8;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(20,24,33,0.08);">
                    <?php if ( $header_image_url !== '' ) : ?>
                    <tr>
                        <td style="padding:0;">
                            <img
                                src="<?php echo esc_url( $header_image_url ); ?>"
                                alt="Aldealab"
                                width="600"
                                style="display:block;width:100%;max-width:600px;height:auto;"
                            />
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding:32px 32px 24px 32px;">
                            <?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#f5f6f8;color:#5a6370;font-size:12px;line-height:1.5;">
                            <?php esc_html_e( 'Este mensaje se ha generado automáticamente desde el sistema de reservas Aldealab. Por favor, no respondas directamente a este correo.', 'reservas-aldealab' ); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
