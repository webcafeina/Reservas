<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Central registry of meta keys used by the `sala` CPT. One constant per key,
 * all exposed over REST so the SPA and admin app can read/write them.
 *
 * `_es_cpa` is the flag that drives:
 *   (a) the PDF template chosen by PdfGenerator (CPA template vs Aldealab
 *       generic template), and
 *   (b) whether the confirmation email includes Sede Electrónica instructions.
 */
final class SalaMeta {

    public const AFORO_MIN   = '_aforo_min';
    public const AFORO_MAX   = '_aforo_max';
    public const DISPONIBLE  = '_disponible';
    public const ES_CPA      = '_es_cpa';

    public const ALL = array(
        self::AFORO_MIN,
        self::AFORO_MAX,
        self::DISPONIBLE,
        self::ES_CPA,
    );

    /**
     * Hook target: `init`.
     */
    public static function register(): void {
        register_post_meta(
            SalaCpt::POST_TYPE,
            self::AFORO_MIN,
            array(
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'default'           => 0,
                'sanitize_callback' => 'absint',
                'auth_callback'     => static function (): bool {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_post_meta(
            SalaCpt::POST_TYPE,
            self::AFORO_MAX,
            array(
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'default'           => 0,
                'sanitize_callback' => 'absint',
                'auth_callback'     => static function (): bool {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_post_meta(
            SalaCpt::POST_TYPE,
            self::DISPONIBLE,
            array(
                'type'              => 'boolean',
                'single'            => true,
                'show_in_rest'      => true,
                'default'           => true,
                'sanitize_callback' => array( self::class, 'sanitizeBool' ),
                'auth_callback'     => static function (): bool {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_post_meta(
            SalaCpt::POST_TYPE,
            self::ES_CPA,
            array(
                'type'              => 'boolean',
                'single'            => true,
                'show_in_rest'      => true,
                'default'           => false,
                'sanitize_callback' => array( self::class, 'sanitizeBool' ),
                'auth_callback'     => static function (): bool {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    /**
     * Normalises truthy/falsy values from REST ("0", "false", 0, etc.)
     * into a real PHP bool — WP's default casting is unpredictable here.
     *
     * @param mixed $value
     */
    public static function sanitizeBool( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_string( $value ) ) {
            $lower = strtolower( trim( $value ) );
            if ( in_array( $lower, array( 'false', '0', '', 'no', 'off' ), true ) ) {
                return false;
            }
            return true;
        }
        return (bool) $value;
    }
}
