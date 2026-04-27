<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

defined( 'ABSPATH' ) || exit;

use Throwable;
use WebcafeinaReservas\Models\Booking;
use WebcafeinaReservas\Models\Sala;
use WebcafeinaReservas\Models\UserProfile;
use WebcafeinaReservas\PostTypes\SalaCpt;
use WebcafeinaReservas\PostTypes\SalaMeta;
use WebcafeinaReservas\Repositories\BookingRepository;
use WebcafeinaReservas\Repositories\EmailLogRepository;
use WebcafeinaReservas\Repositories\UserProfileRepository;
use WebcafeinaReservas\Roles\RoleManager;
use WebcafeinaReservas\Services\BookingActionToken;
use WebcafeinaReservas\Services\Sms\SmsProviderFactory;

/**
 * Transactional emails: booking confirmation (user + admin) and
 * cancellation (user). HTML via per-template PHP files under
 * src/Emails/templates/, rendered through layout.php.
 *
 * PDF generation is gated:
 *   - Sala is CPA               → always attach CPA template
 *   - User has usuario_alojado  → skip PDF + skip Sede instructions
 *   - Otherwise                 → attach generic Aldealab template
 *
 * Hook handlers:
 *   - `reservas_aldealab_send_notifications`  (async, scheduled from
 *     BookingService): send user + admin confirmation.
 *   - `reservas_aldealab_booking_cancelled`   (fired by admin controller):
 *     send cancellation email.
 */
final class EmailNotifier {

    public const HOOK_ASYNC               = 'reservas_aldealab_send_notifications';
    public const HOOK_CONFIRMED           = 'reservas_aldealab_booking_confirmed';
    public const HOOK_CANCELLED           = 'reservas_aldealab_booking_cancelled';
    public const HOOK_REVERTED_TO_PENDING = 'reservas_aldealab_booking_reverted_to_pending';

    public static function register(): void {
        add_action( self::HOOK_ASYNC, array( self::class, 'handleAsync' ), 10, 1 );
        add_action( self::HOOK_CONFIRMED, array( self::class, 'handleConfirmed' ), 10, 1 );
        add_action( self::HOOK_CANCELLED, array( self::class, 'handleCancelled' ), 10, 1 );
        add_action( self::HOOK_REVERTED_TO_PENDING, array( self::class, 'handleRevertedToPending' ), 10, 1 );
    }

    /**
     * Hook target: `reservas_aldealab_send_notifications`.
     */
    public static function handleAsync( int $bookingId ): void {
        $ctx = self::loadContext( $bookingId );
        if ( $ctx === null ) {
            return;
        }

        $attachPdf = self::shouldAttachPdf( $ctx['booking'], $ctx['sala'] );
        $pdfPath   = null;
        if ( $attachPdf ) {
            $pdfPath = self::tryGeneratePdfFile(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                $ctx['log']
            );
        }

        try {
            ( new self() )->sendUserConfirmation(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                $pdfPath,
                $attachPdf
            );
            ( new self() )->sendAdminNotification(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                $pdfPath
            );
            ( new self() )->maybeSendSms(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                'confirmation'
            );
        } finally {
            if ( $pdfPath !== null && is_file( $pdfPath ) ) {
                @unlink( $pdfPath );
            }
        }
    }

    /**
     * Hook target: `reservas_aldealab_booking_confirmed`. Fired when a
     * pending booking is accepted by an admin (panel PATCH or magic-link
     * "Aceptar" from email). Mirrors handleAsync — generates the PDF (if
     * applicable) and sends a single "Reserva aceptada" email to the
     * solicitante. Async (cron) so the admin's response isn't blocked
     * by PDF generation.
     */
    public static function handleConfirmed( int $bookingId ): void {
        $ctx = self::loadContext( $bookingId );
        if ( $ctx === null ) {
            return;
        }

        $attachPdf = self::shouldAttachPdf( $ctx['booking'], $ctx['sala'] );
        $pdfPath   = null;
        if ( $attachPdf ) {
            $pdfPath = self::tryGeneratePdfFile(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                $ctx['log']
            );
        }

        try {
            ( new self() )->sendUserAccepted(
                $ctx['booking'],
                $ctx['profile'],
                $ctx['sala'],
                $pdfPath,
                $attachPdf
            );
        } finally {
            if ( $pdfPath !== null && is_file( $pdfPath ) ) {
                @unlink( $pdfPath );
            }
        }
    }

    /**
     * Hook target: `reservas_aldealab_booking_reverted_to_pending`. Fired
     * when an admin moves a booking back to `pendiente` from any other
     * estado (e.g. confirmada → pendiente if they realised they need
     * more info, or cancelada → pendiente if they un-cancelled by
     * mistake). No PDF is attached because the booking is not yet a
     * formal commitment in this state.
     */
    public static function handleRevertedToPending( int $bookingId ): void {
        $ctx = self::loadContext( $bookingId );
        if ( $ctx === null ) {
            return;
        }
        ( new self() )->sendUserRevertedToPending(
            $ctx['booking'],
            $ctx['profile'],
            $ctx['sala']
        );
    }

    /**
     * Hook target: `reservas_aldealab_booking_cancelled`.
     */
    public static function handleCancelled( int $bookingId ): void {
        $ctx = self::loadContext( $bookingId );
        if ( $ctx === null ) {
            return;
        }
        ( new self() )->sendCancellation( $ctx['booking'], $ctx['profile'], $ctx['sala'] );
        ( new self() )->maybeSendSms(
            $ctx['booking'],
            $ctx['profile'],
            $ctx['sala'],
            'cancellation'
        );
    }

    /**
     * Opt-in SMS dispatch. No-op when no provider is configured. Logs every
     * attempt to reservas_email_log with tipo='sms'.
     */
    public function maybeSendSms(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        string $kind
    ): void {
        $provider = SmsProviderFactory::fromSettings();
        if ( ! $provider->isConfigured() ) {
            return;
        }
        if ( $profile->movil === '' ) {
            return;
        }

        $body = $kind === 'cancellation'
            ? sprintf(
                /* translators: 1: sala name, 2: date */
                __( 'Tu reserva en "%1$s" el %2$s ha sido cancelada. Aldealab.', 'reservas-aldealab' ),
                $sala->title,
                PdfGenerator::isoToDdMmYyyy( $booking->fechaInicio )
            )
            : sprintf(
                /* translators: 1: sala name, 2: first date, 3: start time */
                __( 'Reserva recibida: %1$s el %2$s a las %3$s. Aldealab.', 'reservas-aldealab' ),
                $sala->title,
                PdfGenerator::isoToDdMmYyyy( $booking->fechaInicio ),
                substr( $booking->horaInicio, 0, 5 )
            );

        $result = $provider->send( $profile->movil, $body );
        self::log()->record(
            $booking->id,
            'sms-' . $kind,
            $profile->movil,
            'SMS ' . $result['provider'],
            $result['success'] ? 'enviado' : 'error',
            $result['error']
        );
    }

    // ---------- Per-email senders ----------

    public function sendUserConfirmation(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        ?string $pdfPath,
        bool $includeSedeInstructions
    ): void {
        $title          = __( 'Confirmación de tu reserva en Aldealab', 'reservas-aldealab' );
        $fechas_humano  = self::formatDatesHuman( $booking );
        $header_url     = self::headerImageUrl();

        $incluye_sede = $includeSedeInstructions;

        $content_html = self::renderTemplate(
            'confirmation-user',
            compact( 'booking', 'profile', 'sala', 'fechas_humano', 'incluye_sede' )
        );
        $html = self::renderLayout( $title, $content_html, $header_url );

        $attachments = $pdfPath !== null ? array( $pdfPath ) : array();
        self::send(
            $profile->email,
            $title,
            $html,
            'usuario',
            $booking->id,
            $attachments
        );
    }

    public function sendAdminNotification(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        ?string $pdfPath
    ): void {
        $recipients = self::adminRecipients();
        if ( $recipients === array() ) {
            self::log()->record(
                $booking->id,
                'admin',
                '(ninguno)',
                'Nueva reserva',
                'omitido',
                'Sin destinatarios admin configurados.'
            );
            return;
        }

        $title         = sprintf(
            /* translators: %s sala title */
            __( 'Nueva reserva pendiente: %s', 'reservas-aldealab' ),
            $sala->title
        );
        $fechas_humano = self::formatDatesHuman( $booking );
        $admin_url     = self::adminDeepLink( $booking->id ?? 0 );
        $accept_url    = self::actionLink( $booking->id ?? 0, BookingActionToken::ACTION_ACCEPT );
        $reject_url    = self::actionLink( $booking->id ?? 0, BookingActionToken::ACTION_REJECT );
        $header_url    = self::headerImageUrl();

        $content_html = self::renderTemplate(
            'confirmation-admin',
            compact( 'booking', 'profile', 'sala', 'fechas_humano', 'admin_url', 'accept_url', 'reject_url' )
        );
        $html = self::renderLayout( $title, $content_html, $header_url );

        $attachments = $pdfPath !== null ? array( $pdfPath ) : array();
        foreach ( $recipients as $to ) {
            self::send( $to, $title, $html, 'admin', $booking->id, $attachments );
        }
    }

    public function sendUserAccepted(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        ?string $pdfPath,
        bool $includeSedeInstructions
    ): void {
        $title         = __( 'Tu reserva en Aldealab ha sido confirmada', 'reservas-aldealab' );
        $fechas_humano = self::formatDatesHuman( $booking );
        $header_url    = self::headerImageUrl();

        $incluye_sede = $includeSedeInstructions;

        $content_html = self::renderTemplate(
            'accepted-user',
            compact( 'booking', 'profile', 'sala', 'fechas_humano', 'incluye_sede' )
        );
        $html = self::renderLayout( $title, $content_html, $header_url );

        $attachments = $pdfPath !== null ? array( $pdfPath ) : array();
        self::send(
            $profile->email,
            $title,
            $html,
            'usuario-aceptada',
            $booking->id,
            $attachments
        );
    }

    public function sendUserRevertedToPending(
        Booking $booking,
        UserProfile $profile,
        Sala $sala
    ): void {
        $title         = __( 'Tu reserva está nuevamente en revisión', 'reservas-aldealab' );
        $fechas_humano = self::formatDatesHuman( $booking );
        $header_url    = self::headerImageUrl();

        $content_html = self::renderTemplate(
            'reverted-to-pending-user',
            compact( 'booking', 'profile', 'sala', 'fechas_humano' )
        );
        $html = self::renderLayout( $title, $content_html, $header_url );

        self::send(
            $profile->email,
            $title,
            $html,
            'usuario-revertida',
            $booking->id,
            array()
        );
    }

    public function sendCancellation(
        Booking $booking,
        UserProfile $profile,
        Sala $sala
    ): void {
        $title         = __( 'Tu reserva en Aldealab ha sido cancelada', 'reservas-aldealab' );
        $fechas_humano = self::formatDatesHuman( $booking );
        $header_url    = self::headerImageUrl();

        $content_html = self::renderTemplate(
            'cancellation-user',
            compact( 'booking', 'profile', 'sala', 'fechas_humano' )
        );
        $html = self::renderLayout( $title, $content_html, $header_url );

        self::send( $profile->email, $title, $html, 'cancelacion', $booking->id, array() );
    }

    // ---------- Helpers ----------

    /**
     * @return array{booking: Booking, profile: UserProfile, sala: Sala, log: EmailLogRepository}|null
     */
    private static function loadContext( int $bookingId ): ?array {
        global $wpdb;
        $bookings = new BookingRepository( $wpdb );
        $profiles = new UserProfileRepository( $wpdb );

        $booking = $bookings->find( $bookingId );
        if ( $booking === null || $booking->profileId === null ) {
            return null;
        }
        $profile = $profiles->findById( (int) $booking->profileId );
        if ( $profile === null ) {
            return null;
        }
        $salaPost = get_post( $booking->salaId );
        if ( ! $salaPost instanceof \WP_Post || $salaPost->post_type !== SalaCpt::POST_TYPE ) {
            return null;
        }
        $sala = \WebcafeinaReservas\Models\Sala::fromPost( $salaPost );

        return array(
            'booking' => $booking,
            'profile' => $profile,
            'sala'    => $sala,
            'log'     => new EmailLogRepository( $wpdb ),
        );
    }

    /**
     * Non-CPA sala + user has `usuario_alojado` role → no PDF. Otherwise, PDF.
     */
    public static function shouldAttachPdf( Booking $booking, Sala $sala ): bool {
        if ( $sala->esCpa ) {
            return true;
        }
        if ( $booking->userId === null ) {
            return true;
        }
        $user = get_userdata( (int) $booking->userId );
        if ( ! $user instanceof \WP_User ) {
            return true;
        }
        return ! in_array( RoleManager::ROLE_TENANT, (array) $user->roles, true );
    }

    /**
     * Generate the PDF to a temp file so we can attach it to wp_mail
     * (which takes file paths, not byte strings). Returns the path or null
     * on failure — a missing pdftk binary is not fatal; the email goes
     * without the PDF and we log the error.
     *
     * Note: we deliberately avoid wp_tempnam() here because it strips the
     * extension from the requested filename and forces ".tmp", which makes
     * mail clients show the attachment as ".tmp" instead of ".pdf".
     */
    private static function tryGeneratePdfFile(
        Booking $booking,
        UserProfile $profile,
        Sala $sala,
        EmailLogRepository $log
    ): ?string {
        try {
            $settings = (array) get_option( 'reservas_aldealab_settings', array() );
            $binary   = isset( $settings['pdftk_path'] ) ? (string) $settings['pdftk_path'] : null;
            $filler   = new PdfFillerPdftk( $binary !== '' ? $binary : null );
            $generator = new PdfGenerator( $filler );
            $bytes    = $generator->generateForBooking( $booking, $profile, $sala );

            $tempDir  = get_temp_dir();
            $prefix   = $sala->esCpa ? 'solicitud-cpa' : 'solicitud-aldealab';
            $basename = sprintf(
                '%s-%d-%s.pdf',
                $prefix,
                $booking->id ?? 0,
                wp_generate_password( 8, false )
            );
            $path = $tempDir . $basename;
            if ( file_put_contents( $path, $bytes ) === false ) {
                throw new \RuntimeException( 'No se pudo escribir PDF temporal en ' . $path );
            }
            return $path;
        } catch ( Throwable $e ) {
            $log->record(
                $booking->id,
                'pdf-error',
                $profile->email,
                'PDF no generado',
                'error',
                $e->getMessage()
            );
            return null;
        }
    }

    private static function send(
        string $to,
        string $subject,
        string $html,
        string $tipo,
        ?int $bookingId,
        array $attachments
    ): void {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        $sent = wp_mail( $to, $subject, $html, $headers, $attachments );

        self::log()->record(
            $bookingId,
            $tipo,
            $to,
            $subject,
            $sent ? 'enviado' : 'error',
            $sent ? null : 'wp_mail() devolvió false'
        );
    }

    private static function log(): EmailLogRepository {
        global $wpdb;
        return new EmailLogRepository( $wpdb );
    }

    /**
     * @return array<int, string>
     */
    private static function adminRecipients(): array {
        $settings = (array) get_option( 'reservas_aldealab_settings', array() );
        $list     = isset( $settings['admin_emails'] ) ? $settings['admin_emails'] : array();
        if ( is_string( $list ) ) {
            $list = array_map( 'trim', explode( ',', $list ) );
        }
        if ( ! is_array( $list ) ) {
            return array();
        }
        $out = array();
        foreach ( $list as $email ) {
            $email = sanitize_email( (string) $email );
            if ( $email !== '' && is_email( $email ) ) {
                $out[] = $email;
            }
        }
        return array_values( array_unique( $out ) );
    }

    private static function adminDeepLink( int $bookingId ): string {
        return admin_url( 'admin.php?page=reservas-aldealab&booking=' . $bookingId );
    }

    /**
     * Build a magic-link URL for a one-click accept/reject action from the
     * admin notification email. The token is HMAC-signed with wp_salt('auth')
     * and lives 7 days; see BookingActionToken.
     */
    private static function actionLink( int $bookingId, string $action ): string {
        $token = BookingActionToken::generate( $bookingId, $action );
        return add_query_arg(
            array(
                'reservas_action' => $action,
                'token'           => $token,
            ),
            home_url( '/' )
        );
    }

    private static function headerImageUrl(): string {
        return RESERVAS_ALDEALAB_URL . 'assets/email/header.png';
    }

    private static function formatDatesHuman( Booking $booking ): string {
        if ( $booking->fechas === array() ) {
            return PdfGenerator::isoToDdMmYyyy( $booking->fechaInicio );
        }
        $parts = array();
        foreach ( $booking->fechas as $iso ) {
            $parts[] = PdfGenerator::isoToDdMmYyyy( (string) $iso );
        }
        if ( count( $parts ) > 6 ) {
            $extra = count( $parts ) - 6;
            return implode( ', ', array_slice( $parts, 0, 6 ) )
                . sprintf( ' (+%d más)', $extra );
        }
        return implode( ', ', $parts );
    }

    /**
     * @param array<string, mixed> $vars
     */
    private static function renderTemplate( string $name, array $vars ): string {
        $path = __DIR__ . '/../Emails/templates/' . $name . '.php';
        if ( ! is_file( $path ) ) {
            return '';
        }
        extract( $vars, EXTR_SKIP );
        $content_html = require $path; // each template returns a string
        return is_string( $content_html ) ? $content_html : '';
    }

    private static function renderLayout(
        string $title,
        string $content_html,
        string $header_image_url
    ): string {
        $path = __DIR__ . '/../Emails/templates/layout.php';
        ob_start();
        // layout.php is an HTML document — it echoes. We buffer.
        include $path;
        return (string) ob_get_clean();
    }
}
