<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Frontend;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Models\BookingState;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Services\BookingActionToken;
use WebcafeinaReservas\Services\EmailNotifier;
use WebcafeinaReservas\Services\PdfGenerator;

/**
 * Public handler for the magic-link buttons in the admin notification email
 * (Aceptar / Rechazar). Reads ?reservas_action=<accept|reject>&token=<HMAC>
 * from the URL, verifies the token, and shows a confirmation page.
 *
 * Two-step UX (intentional): GET shows a confirmation page with a form that
 * POSTs back to the same URL. This stops mail-client pre-fetchers (Outlook,
 * Defender, antivirus link-scanners) from triggering the action behind the
 * recipient's back — only the actual user click on the [Confirmar] button
 * mutates state.
 */
final class BookingActionHandler {

    public const QUERY_VAR = 'reservas_action';

    public static function register(): void {
        // Priority 5: run before most theme/plugin init that may emit output.
        add_action( 'init', array( self::class, 'maybeHandle' ), 5 );
    }

    public static function maybeHandle(): void {
        if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $action = sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token  = isset( $_GET['token'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ? sanitize_text_field( wp_unslash( (string) $_GET['token'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            : '';

        $verified = BookingActionToken::verify( $token );
        if ( ! $verified['valid'] || $verified['action'] !== $action ) {
            self::renderError(
                __( 'El enlace no es válido o ha caducado.', 'reservas-aldealab' ),
                $verified['error'] ?? 'unknown'
            );
        }

        global $wpdb;
        $repo    = new BookingRepository( $wpdb );
        $booking = $repo->find( $verified['booking_id'] );
        if ( $booking === null ) {
            self::renderError(
                __( 'La reserva no existe o ya ha sido eliminada.', 'reservas-aldealab' ),
                'not-found'
            );
        }

        if ( $booking->estado !== BookingState::PENDIENTE ) {
            self::renderInfo(
                __( 'Reserva ya procesada', 'reservas-aldealab' ),
                sprintf(
                    /* translators: 1: id, 2: estado */
                    __( 'La reserva #%1$d ya está en estado "%2$s". No es necesaria ninguna acción adicional.', 'reservas-aldealab' ),
                    $booking->id,
                    $booking->estado
                )
            );
        }

        $method = isset( $_SERVER['REQUEST_METHOD'] )
            ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) )
            : 'GET';
        if ( $method === 'POST' ) {
            // Re-verify token + state on POST so a stale form can't act.
            $confirmedAction = isset( $_POST['confirm_action'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                ? sanitize_key( wp_unslash( (string) $_POST['confirm_action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                : '';
            if ( $confirmedAction !== $action ) {
                self::renderError(
                    __( 'La confirmación no coincide con el enlace.', 'reservas-aldealab' ),
                    'mismatch'
                );
            }

            $newState = $action === BookingActionToken::ACTION_ACCEPT
                ? BookingState::CONFIRMADA
                : BookingState::CANCELADA;
            $repo->updateState( $booking->id, $newState );

            if ( $newState === BookingState::CANCELADA ) {
                do_action( 'reservas_aldealab_booking_cancelled', $booking->id );
            }
            if ( $newState === BookingState::CONFIRMADA && function_exists( 'wp_schedule_single_event' ) ) {
                // Async (cron) — PDF generation should not block the
                // confirmation page render that the admin sees right
                // after pressing "Sí, aceptar reserva".
                wp_schedule_single_event( time(), EmailNotifier::HOOK_CONFIRMED, array( $booking->id ) );
            }

            self::renderSuccess(
                $action === BookingActionToken::ACTION_ACCEPT
                    ? __( 'Reserva aceptada', 'reservas-aldealab' )
                    : __( 'Reserva rechazada', 'reservas-aldealab' ),
                sprintf(
                    /* translators: 1: id */
                    $action === BookingActionToken::ACTION_ACCEPT
                        ? __( 'La reserva #%d ha quedado confirmada. Se ha registrado tu decisión.', 'reservas-aldealab' )
                        : __( 'La reserva #%d ha sido cancelada. Se ha enviado al usuario un email avisándole de la cancelación.', 'reservas-aldealab' ),
                    $booking->id
                )
            );
        }

        // GET → confirmation page.
        self::renderConfirmation( $booking, $action, $token );
    }

    /**
     * @param \WebcafeinaReservas\Models\Booking $booking
     */
    private static function renderConfirmation( $booking, string $action, string $token ): void {
        $isAccept = $action === BookingActionToken::ACTION_ACCEPT;
        $title    = $isAccept
            ? __( 'Confirmar aceptación de reserva', 'reservas-aldealab' )
            : __( 'Confirmar rechazo de reserva', 'reservas-aldealab' );

        $intro = $isAccept
            ? __( 'Vas a marcar esta reserva como <strong>confirmada</strong>. El solicitante quedará informado de que la sala está reservada para él.', 'reservas-aldealab' )
            : __( 'Vas a <strong>rechazar y cancelar</strong> esta reserva. Se enviará al solicitante un email avisándole de que la cancelación.', 'reservas-aldealab' );

        $btnLabel = $isAccept
            ? __( 'Sí, aceptar reserva', 'reservas-aldealab' )
            : __( 'Sí, rechazar reserva', 'reservas-aldealab' );

        $btnColor = $isAccept ? '#107a3a' : '#b3261e';
        $fechaHum = PdfGenerator::isoToDdMmYyyy( $booking->fechaInicio );
        $horario  = substr( $booking->horaInicio, 0, 5 ) . ' – ' . substr( $booking->horaFin, 0, 5 );

        $action_url = esc_url(
            add_query_arg(
                array(
                    self::QUERY_VAR => $action,
                    'token'         => $token,
                ),
                home_url( '/' )
            )
        );

        self::renderShell(
            $title,
            function () use ( $title, $intro, $btnLabel, $btnColor, $fechaHum, $horario, $booking, $action_url, $action ) {
                ?>
                <h1 style="margin:0 0 16px 0;font-size:22px;color:#0b5394;"><?php echo esc_html( $title ); ?></h1>
                <p style="margin:0 0 16px 0;line-height:1.6;"><?php echo wp_kses( $intro, array( 'strong' => array() ) ); ?></p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
                    <tr>
                        <td style="padding:8px 12px;background:#f5f6f8;width:140px;"><strong><?php esc_html_e( 'Reserva', 'reservas-aldealab' ); ?></strong></td>
                        <td style="padding:8px 12px;background:#f5f6f8;">#<?php echo (int) $booking->id; ?></td>
                    </tr>
                    <tr>
                        <td style="padding:8px 12px;"><strong><?php esc_html_e( 'Fecha', 'reservas-aldealab' ); ?></strong></td>
                        <td style="padding:8px 12px;"><?php echo esc_html( $fechaHum ); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:8px 12px;background:#f5f6f8;"><strong><?php esc_html_e( 'Horario', 'reservas-aldealab' ); ?></strong></td>
                        <td style="padding:8px 12px;background:#f5f6f8;"><?php echo esc_html( $horario ); ?></td>
                    </tr>
                </table>

                <form method="post" action="<?php echo esc_attr( $action_url ); ?>" style="margin-top:24px;">
                    <input type="hidden" name="confirm_action" value="<?php echo esc_attr( $action ); ?>" />
                    <button
                        type="submit"
                        style="display:inline-block;background:<?php echo esc_attr( $btnColor ); ?>;color:#ffffff;padding:12px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:15px;"
                    >
                        <?php echo esc_html( $btnLabel ); ?>
                    </button>
                </form>
                <p style="margin-top:12px;font-size:13px;color:#666;">
                    <?php esc_html_e( 'Si has llegado aquí por error, simplemente cierra esta pestaña — no se hará ningún cambio.', 'reservas-aldealab' ); ?>
                </p>
                <?php
            }
        );
    }

    private static function renderSuccess( string $title, string $message ): void {
        self::renderShell(
            $title,
            function () use ( $title, $message ) {
                ?>
                <h1 style="margin:0 0 16px 0;font-size:22px;color:#107a3a;"><?php echo esc_html( $title ); ?></h1>
                <p style="margin:0 0 16px 0;line-height:1.6;"><?php echo esc_html( $message ); ?></p>
                <p style="margin-top:24px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=reservas-aldealab' ) ); ?>"
                       style="display:inline-block;background:#0b5394;color:#ffffff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;">
                        <?php esc_html_e( 'Ir al panel', 'reservas-aldealab' ); ?>
                    </a>
                </p>
                <?php
            }
        );
    }

    private static function renderInfo( string $title, string $message ): void {
        self::renderShell(
            $title,
            function () use ( $title, $message ) {
                ?>
                <h1 style="margin:0 0 16px 0;font-size:22px;color:#0b5394;"><?php echo esc_html( $title ); ?></h1>
                <p style="margin:0 0 16px 0;line-height:1.6;"><?php echo esc_html( $message ); ?></p>
                <p style="margin-top:24px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=reservas-aldealab' ) ); ?>"
                       style="display:inline-block;background:#0b5394;color:#ffffff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;">
                        <?php esc_html_e( 'Ir al panel', 'reservas-aldealab' ); ?>
                    </a>
                </p>
                <?php
            }
        );
    }

    private static function renderError( string $message, string $code ): void {
        $title = __( 'Enlace no válido', 'reservas-aldealab' );
        self::renderShell(
            $title,
            function () use ( $title, $message, $code ) {
                ?>
                <h1 style="margin:0 0 16px 0;font-size:22px;color:#b3261e;"><?php echo esc_html( $title ); ?></h1>
                <p style="margin:0 0 16px 0;line-height:1.6;"><?php echo esc_html( $message ); ?></p>
                <p style="margin-top:8px;font-size:12px;color:#999;">
                    <?php
                    printf(
                        /* translators: %s machine error code */
                        esc_html__( 'Código: %s', 'reservas-aldealab' ),
                        esc_html( $code )
                    );
                    ?>
                </p>
                <p style="margin-top:24px;">
                    <?php esc_html_e( 'Inicia sesión en el panel de administración para gestionar la reserva manualmente.', 'reservas-aldealab' ); ?>
                </p>
                <?php
            },
            $code === 'not-found' ? 404 : 410
        );
    }

    /**
     * @param callable $bodyFn
     */
    private static function renderShell( string $title, callable $bodyFn, int $status = 200 ): void {
        status_header( $status );
        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );
        ?>
<!doctype html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="robots" content="noindex,nofollow" />
<title><?php echo esc_html( $title ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background:#f5f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#222;">
<main style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
<?php $bodyFn(); ?>
</main>
</body>
</html>
        <?php
        exit;
    }
}
