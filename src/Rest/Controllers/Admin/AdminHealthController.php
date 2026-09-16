<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Admin\SettingsRegistrar;
use WebcafeinaReservas\Database\MigrationRunner;
use WebcafeinaReservas\Database\Schema;
use WebcafeinaReservas\Rest\RestApi;
use WebcafeinaReservas\Roles\RoleManager;
use WebcafeinaReservas\Services\BookingActionToken;
use WebcafeinaReservas\Services\EmailNotifier;
use WebcafeinaReservas\Services\PdfFillerPdftk;
use WebcafeinaReservas\Services\Sms\SmsProviderFactory;
use WebcafeinaReservas\Services\TurnstileVerifier;

/**
 * GET /admin/health — runtime health check of every plugin dependency.
 *
 * Returns a flat list of `Check` items grouped by category client-side.
 * Each check has one of four severities: `ok`, `warn`, `error`, `info`.
 * Live HTTP probes (Turnstile siteverify, Twilio API) actually call the
 * remote endpoints with short timeouts so credential issues surface
 * here rather than in production traffic.
 *
 * Gated by `manage_reservas`. No caching: the admin pulls "Actualizar"
 * when they want a fresh read.
 */
final class AdminHealthController {

    private const CAT_SYSTEM   = 'Sistema';
    private const CAT_DB       = 'Base de datos';
    private const CAT_FS       = 'Sistema de archivos';
    private const CAT_PDF      = 'PDF';
    private const CAT_NOTIF    = 'Notificaciones';
    private const CAT_TURN     = 'Anti-spam';
    private const CAT_SMS      = 'SMS';
    private const CAT_ROLES    = 'Roles y permisos';

    private const FIX_SETTINGS = '#/settings';

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/health',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'index' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
            )
        );
    }

    public function index( WP_REST_Request $request ): WP_REST_Response {
        $settings = SettingsRegistrar::get();
        $checks   = array();

        foreach ( $this->systemChecks() as $c )       { $checks[] = $c; }
        foreach ( $this->databaseChecks() as $c )     { $checks[] = $c; }
        foreach ( $this->filesystemChecks() as $c )   { $checks[] = $c; }
        foreach ( $this->pdfChecks( $settings ) as $c )         { $checks[] = $c; }
        foreach ( $this->notificationChecks() as $c ) { $checks[] = $c; }
        foreach ( $this->turnstileChecks( $settings ) as $c )   { $checks[] = $c; }
        foreach ( $this->smsChecks( $settings ) as $c )         { $checks[] = $c; }
        foreach ( $this->roleChecks() as $c )         { $checks[] = $c; }

        $summary = array( 'ok' => 0, 'warn' => 0, 'error' => 0, 'info' => 0 );
        foreach ( $checks as $c ) {
            $sev = (string) ( $c['severity'] ?? 'ok' );
            if ( isset( $summary[ $sev ] ) ) {
                ++$summary[ $sev ];
            }
        }

        return new WP_REST_Response(
            array(
                'summary' => $summary,
                'checks'  => $checks,
            ),
            200
        );
    }

    // ---------- Category check builders ----------

    /** @return array<int, array<string, mixed>> */
    private function systemChecks(): array {
        $out = array();

        $phpVersion = PHP_VERSION;
        if ( PHP_VERSION_ID < 70400 ) {
            $out[] = self::check(
                'php-min', self::CAT_SYSTEM, 'Versión de PHP',
                'error',
                sprintf( 'PHP %s — el plugin requiere 7.4 o superior.', $phpVersion ),
                null
            );
        } elseif ( PHP_VERSION_ID < 80000 ) {
            $out[] = self::check(
                'php-min', self::CAT_SYSTEM, 'Versión de PHP',
                'warn',
                sprintf( 'PHP %s — funciona pero recomendamos 8.0+ por rendimiento y soporte.', $phpVersion ),
                null
            );
        } else {
            $out[] = self::check(
                'php-min', self::CAT_SYSTEM, 'Versión de PHP',
                'ok',
                sprintf( 'PHP %s', $phpVersion ),
                null
            );
        }

        $wpVersion = (string) get_bloginfo( 'version' );
        if ( version_compare( $wpVersion, '6.0', '<' ) ) {
            $out[] = self::check(
                'wp-min', self::CAT_SYSTEM, 'Versión de WordPress',
                'error',
                sprintf( 'WP %s — el plugin requiere 6.0 o superior.', $wpVersion ),
                null
            );
        } else {
            $out[] = self::check(
                'wp-min', self::CAT_SYSTEM, 'Versión de WordPress',
                'ok',
                sprintf( 'WordPress %s', $wpVersion ),
                null
            );
        }

        if ( ! function_exists( 'shell_exec' ) ) {
            $out[] = self::check(
                'shell-exec', self::CAT_SYSTEM, 'shell_exec disponible',
                'error',
                '`shell_exec` está deshabilitado. Sin esta función no se puede invocar `pdftk` para generar los PDFs adjuntos.',
                null
            );
        } else {
            $out[] = self::check(
                'shell-exec', self::CAT_SYSTEM, 'shell_exec disponible',
                'ok',
                '`shell_exec` activo.',
                null
            );
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function databaseChecks(): array {
        global $wpdb;
        $out = array();

        $tables = array(
            'bookings'        => Schema::bookings(),
            'booking_dates'   => Schema::bookingDates(),
            'user_profiles'   => Schema::userProfiles(),
            'cpa_items'       => Schema::cpaItems(),
            'email_log'       => Schema::emailLog(),
        );
        $missing = array();
        foreach ( $tables as $name => $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                $missing[] = $name;
            }
        }
        if ( $missing === array() ) {
            $out[] = self::check(
                'db-tables', self::CAT_DB, 'Tablas del plugin',
                'ok',
                sprintf( '%d/%d tablas presentes.', count( $tables ), count( $tables ) ),
                null
            );
        } else {
            $out[] = self::check(
                'db-tables', self::CAT_DB, 'Tablas del plugin',
                'error',
                sprintf( 'Faltan %d tablas: %s. Reactiva el plugin para forzar las migraciones.', count( $missing ), implode( ', ', $missing ) ),
                null
            );
        }

        $current = MigrationRunner::getDbVersion();
        $latest  = MigrationRunner::latestAvailableVersion(
            RESERVAS_ALDEALAB_PATH . 'src/Database/Migrations'
        );
        if ( $latest === null ) {
            $out[] = self::check(
                'db-version', self::CAT_DB, 'Versión del esquema',
                'warn',
                'No se han descubierto migraciones en disco — instalación incompleta.',
                null
            );
        } elseif ( $current === '' ) {
            $out[] = self::check(
                'db-version', self::CAT_DB, 'Versión del esquema',
                'warn',
                sprintf( 'Sin versión registrada (esperada: %s). Recarga cualquier página de admin para ejecutar las migraciones.', $latest ),
                null
            );
        } elseif ( $current !== $latest ) {
            $out[] = self::check(
                'db-version', self::CAT_DB, 'Versión del esquema',
                'warn',
                sprintf( 'BD en %s pero la última migración es %s. Recarga cualquier página de admin para aplicar las pendientes.', $current, $latest ),
                null
            );
        } else {
            $out[] = self::check(
                'db-version', self::CAT_DB, 'Versión del esquema',
                'ok',
                sprintf( 'En %s.', $current ),
                null
            );
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function filesystemChecks(): array {
        $out = array();

        $manifest = RESERVAS_ALDEALAB_PATH . 'assets/dist/.vite/manifest.json';
        if ( is_file( $manifest ) ) {
            $out[] = self::check(
                'vite-manifest', self::CAT_FS, 'Bundle frontend (Vite)',
                'ok',
                'Manifiesto encontrado.',
                null
            );
        } else {
            $out[] = self::check(
                'vite-manifest', self::CAT_FS, 'Bundle frontend (Vite)',
                'error',
                'Falta `assets/dist/.vite/manifest.json`. El plugin no podrá cargar los bundles JS/CSS — reinstala el zip oficial generado por el workflow de release.',
                null
            );
        }

        $packaged = array(
            'solicitud-espacios-aldealab.pdf',
            'solicitud-cpa.pdf',
        );
        $missingTpl = array();
        foreach ( $packaged as $f ) {
            if ( ! is_file( RESERVAS_ALDEALAB_PATH . 'assets/pdf-templates/' . $f ) ) {
                $missingTpl[] = $f;
            }
        }
        if ( $missingTpl === array() ) {
            $out[] = self::check(
                'pdf-packaged', self::CAT_FS, 'Plantillas PDF empaquetadas',
                'ok',
                'Plantillas Aldealab y CPA presentes.',
                null
            );
        } else {
            $out[] = self::check(
                'pdf-packaged', self::CAT_FS, 'Plantillas PDF empaquetadas',
                'error',
                sprintf( 'Faltan: %s', implode( ', ', $missingTpl ) ),
                null
            );
        }

        $uploads = wp_upload_dir();
        $base    = is_array( $uploads ) && isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
        $custom  = $base !== '' ? $base . '/reservas-aldealab/pdf-templates' : '';
        if ( $custom === '' ) {
            $out[] = self::check(
                'uploads-writable', self::CAT_FS, 'Plantillas PDF personalizadas',
                'warn',
                'No se pudo resolver la carpeta `wp-content/uploads/`.',
                self::FIX_SETTINGS
            );
        } elseif ( ! is_dir( $custom ) ) {
            $out[] = self::check(
                'uploads-writable', self::CAT_FS, 'Plantillas PDF personalizadas',
                'info',
                'Aún no se ha subido ninguna plantilla personalizada — se usarán las empaquetadas.',
                self::FIX_SETTINGS
            );
        } elseif ( ! is_writable( $custom ) ) {
            $out[] = self::check(
                'uploads-writable', self::CAT_FS, 'Plantillas PDF personalizadas',
                'warn',
                sprintf( '`%s` no es escribible. No se podrán subir plantillas nuevas.', $custom ),
                self::FIX_SETTINGS
            );
        } else {
            $out[] = self::check(
                'uploads-writable', self::CAT_FS, 'Plantillas PDF personalizadas',
                'ok',
                'Carpeta de subidas escribible.',
                self::FIX_SETTINGS
            );
        }

        $tmp = get_temp_dir();
        if ( ! is_writable( $tmp ) ) {
            $out[] = self::check(
                'temp-writable', self::CAT_FS, 'Directorio temporal',
                'error',
                sprintf( '`%s` no es escribible. Los emails con PDF adjunto no se podrán generar.', $tmp ),
                null
            );
        } else {
            $out[] = self::check(
                'temp-writable', self::CAT_FS, 'Directorio temporal',
                'ok',
                sprintf( 'Escribible en `%s`.', rtrim( $tmp, '/' ) ),
                null
            );
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    private function pdfChecks( array $settings ): array {
        $out = array();

        $configured = isset( $settings[ SettingsRegistrar::KEY_PDFTK_PATH ] )
            ? (string) $settings[ SettingsRegistrar::KEY_PDFTK_PATH ]
            : '';
        $binaryPath = $configured !== '' ? $configured : null;
        $available  = PdfFillerPdftk::isAvailable( $binaryPath );

        if ( $available ) {
            $out[] = self::check(
                'pdftk-binary', self::CAT_PDF, 'Binario pdftk',
                'ok',
                $binaryPath !== null
                    ? sprintf( 'Detectado en `%s`.', $binaryPath )
                    : 'Detectado en el `$PATH` del sistema.',
                self::FIX_SETTINGS
            );
        } else {
            $out[] = self::check(
                'pdftk-binary', self::CAT_PDF, 'Binario pdftk',
                'error',
                $binaryPath !== null
                    ? sprintf( 'No se ejecuta `%s --version`. Revisa la ruta o pide al sysadmin que instale `pdftk-java`.', $binaryPath )
                    : '`pdftk` no está en el `$PATH`. Pide al sysadmin que instale `pdftk-java` o configura una ruta absoluta.',
                self::FIX_SETTINGS
            );
        }

        if ( function_exists( 'shell_exec' ) ) {
            $javaOut = (string) ( @shell_exec( 'java -version 2>&1' ) ?? '' );
            if ( stripos( $javaOut, 'version' ) !== false ) {
                $firstLine = trim( strtok( $javaOut, "\n" ) ?: '' );
                $out[] = self::check(
                    'java-runtime', self::CAT_PDF, 'Java runtime (necesario para pdftk-java)',
                    'ok',
                    $firstLine !== '' ? $firstLine : 'Java detectado.',
                    null
                );
            } else {
                $out[] = self::check(
                    'java-runtime', self::CAT_PDF, 'Java runtime (necesario para pdftk-java)',
                    'warn',
                    'No se detecta `java`. Si el binario instalado es `pdftk-java` necesitará Java; si es `pdftk` clásico no aplica.',
                    null
                );
            }
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function notificationChecks(): array {
        $out        = array();
        $recipients = EmailNotifier::adminRecipients();
        if ( $recipients === array() ) {
            $out[] = self::check(
                'admin-emails', self::CAT_NOTIF, 'Emails de administradores',
                'error',
                'No hay ningún email configurado para notificaciones de admin. Las nuevas reservas se crearán pero nadie recibirá aviso.',
                self::FIX_SETTINGS
            );
        } else {
            $out[] = self::check(
                'admin-emails', self::CAT_NOTIF, 'Emails de administradores',
                'ok',
                sprintf( '%d destinatario%s configurado%s.', count( $recipients ), count( $recipients ) === 1 ? '' : 's', count( $recipients ) === 1 ? '' : 's' ),
                self::FIX_SETTINGS
            );
        }

        // Sanity check: BookingActionToken needs wp_salt('auth') which is
        // present on every WP install. We just confirm it's not empty so a
        // misconfigured install (very rare) would surface here.
        $token = BookingActionToken::generate( 0, BookingActionToken::ACTION_ACCEPT );
        if ( $token === '' ) {
            $out[] = self::check(
                'action-tokens', self::CAT_NOTIF, 'Magic links del email admin',
                'error',
                'No se pueden firmar tokens — `wp_salt(\'auth\')` está vacío. Revisa `wp-config.php`.',
                null
            );
        } else {
            $out[] = self::check(
                'action-tokens', self::CAT_NOTIF, 'Magic links del email admin',
                'ok',
                'Tokens HMAC firmados correctamente.',
                null
            );
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    private function turnstileChecks( array $settings ): array {
        $out  = array();
        $key  = (string) ( $settings[ SettingsRegistrar::KEY_TURNSTILE_SITE_KEY ] ?? '' );
        $sec  = (string) ( $settings[ SettingsRegistrar::KEY_TURNSTILE_SECRET ] ?? '' );

        if ( $key === '' && $sec === '' ) {
            $out[] = self::check(
                'turnstile-config', self::CAT_TURN, 'Cloudflare Turnstile',
                'warn',
                'Sin configurar. El formulario público no estará protegido contra spam.',
                self::FIX_SETTINGS
            );
            return $out;
        }
        if ( $key === '' || $sec === '' ) {
            $out[] = self::check(
                'turnstile-config', self::CAT_TURN, 'Cloudflare Turnstile',
                'error',
                'Configuración incompleta — falta ' . ( $key === '' ? 'site key' : 'secret' ) . '. Ambos campos son obligatorios.',
                self::FIX_SETTINGS
            );
            return $out;
        }

        $out[] = self::check(
            'turnstile-config', self::CAT_TURN, 'Cloudflare Turnstile',
            'ok',
            'Site key + secret configurados.',
            self::FIX_SETTINGS
        );

        // Live ping: post a known-bad token to siteverify. A reachable
        // endpoint with a recognised secret returns 200 + JSON containing
        // error-codes 'invalid-input-response' (or similar). Anything else
        // — network error, 5xx, missing-input-secret — is a real problem.
        $response = wp_remote_post(
            TurnstileVerifier::SITEVERIFY_URL,
            array(
                'timeout' => 5,
                'body'    => array(
                    'secret'   => $sec,
                    'response' => 'health-check-probe',
                ),
            )
        );
        if ( is_wp_error( $response ) ) {
            $out[] = self::check(
                'turnstile-reachable', self::CAT_TURN, 'Turnstile siteverify alcanzable',
                'error',
                sprintf( 'Error de red: %s', $response->get_error_message() ),
                null
            );
            return $out;
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            $out[] = self::check(
                'turnstile-reachable', self::CAT_TURN, 'Turnstile siteverify alcanzable',
                'error',
                sprintf( 'HTTP %d desde Cloudflare. Verifica que el secret es válido.', $code ),
                self::FIX_SETTINGS
            );
            return $out;
        }
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) {
            $out[] = self::check(
                'turnstile-reachable', self::CAT_TURN, 'Turnstile siteverify alcanzable',
                'error',
                'Respuesta no JSON desde Cloudflare.',
                null
            );
            return $out;
        }
        $errors = isset( $body['error-codes'] ) && is_array( $body['error-codes'] )
            ? array_map( 'strval', $body['error-codes'] )
            : array();
        if ( in_array( 'invalid-input-secret', $errors, true ) ) {
            $out[] = self::check(
                'turnstile-reachable', self::CAT_TURN, 'Turnstile siteverify alcanzable',
                'error',
                'Cloudflare rechaza el secret (`invalid-input-secret`). Genera uno nuevo en el panel de Cloudflare.',
                self::FIX_SETTINGS
            );
            return $out;
        }
        $out[] = self::check(
            'turnstile-reachable', self::CAT_TURN, 'Turnstile siteverify alcanzable',
            'ok',
            'Cloudflare responde correctamente.',
            null
        );

        return $out;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    private function smsChecks( array $settings ): array {
        $out      = array();
        $provider = (string) ( $settings[ SettingsRegistrar::KEY_SMS_PROVIDER ] ?? 'none' );

        if ( $provider === 'none' ) {
            $out[] = self::check(
                'sms-provider', self::CAT_SMS, 'Envío de SMS',
                'info',
                'Deshabilitado intencionalmente.',
                self::FIX_SETTINGS
            );
            return $out;
        }

        $sms = SmsProviderFactory::fromSettings();
        if ( ! $sms->isConfigured() ) {
            $out[] = self::check(
                'sms-provider', self::CAT_SMS, 'Envío de SMS',
                'error',
                sprintf( 'Provider "%s" seleccionado pero faltan credenciales.', $provider ),
                self::FIX_SETTINGS
            );
            return $out;
        }
        $out[] = self::check(
            'sms-provider', self::CAT_SMS, 'Envío de SMS',
            'ok',
            sprintf( 'Provider "%s" configurado.', $provider ),
            self::FIX_SETTINGS
        );

        if ( $provider === 'twilio' ) {
            $sid   = (string) ( $settings[ SettingsRegistrar::KEY_TWILIO_SID ] ?? '' );
            $token = (string) ( $settings[ SettingsRegistrar::KEY_TWILIO_TOKEN ] ?? '' );
            $url   = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '.json';
            $resp  = wp_remote_get(
                $url,
                array(
                    'timeout' => 5,
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
                    ),
                )
            );
            if ( is_wp_error( $resp ) ) {
                $out[] = self::check(
                    'twilio-reachable', self::CAT_SMS, 'API de Twilio alcanzable',
                    'warn',
                    sprintf( 'Error de red: %s', $resp->get_error_message() ),
                    null
                );
            } else {
                $code = (int) wp_remote_retrieve_response_code( $resp );
                if ( $code === 200 ) {
                    $out[] = self::check(
                        'twilio-reachable', self::CAT_SMS, 'API de Twilio alcanzable',
                        'ok',
                        'Twilio responde con la cuenta verificada.',
                        null
                    );
                } elseif ( $code === 401 ) {
                    $out[] = self::check(
                        'twilio-reachable', self::CAT_SMS, 'API de Twilio alcanzable',
                        'error',
                        'Twilio rechaza las credenciales (401). Revisa el SID y el auth token.',
                        self::FIX_SETTINGS
                    );
                } else {
                    $out[] = self::check(
                        'twilio-reachable', self::CAT_SMS, 'API de Twilio alcanzable',
                        'warn',
                        sprintf( 'HTTP %d desde Twilio.', $code ),
                        null
                    );
                }
            }
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function roleChecks(): array {
        $out   = array();
        $roles = wp_roles();
        $found = array();
        if ( isset( $roles->roles ) && is_array( $roles->roles ) ) {
            foreach ( $roles->roles as $slug => $data ) {
                if ( isset( $data['capabilities'][ RoleManager::CAP_MANAGE ] ) && $data['capabilities'][ RoleManager::CAP_MANAGE ] === true ) {
                    $found[] = (string) $slug;
                }
            }
        }
        if ( $found === array() ) {
            $out[] = self::check(
                'cap-manage', self::CAT_ROLES, 'Capability `manage_reservas`',
                'error',
                'Ningún rol tiene la capability `manage_reservas`. Recarga cualquier página de admin para que `RoleManager::ensureRoles()` la restaure.',
                null
            );
        } else {
            $out[] = self::check(
                'cap-manage', self::CAT_ROLES, 'Capability `manage_reservas`',
                'ok',
                sprintf( 'Asignada a: %s.', implode( ', ', $found ) ),
                null
            );
        }
        return $out;
    }

    // ---------- Helpers ----------

    /**
     * @return array{
     *   id: string,
     *   category: string,
     *   label: string,
     *   severity: string,
     *   message: string,
     *   fix_url: ?string
     * }
     */
    private static function check(
        string $id,
        string $category,
        string $label,
        string $severity,
        string $message,
        ?string $fixUrl
    ): array {
        return array(
            'id'       => $id,
            'category' => $category,
            'label'    => $label,
            'severity' => $severity,
            'message'  => $message,
            'fix_url'  => $fixUrl,
        );
    }
}
