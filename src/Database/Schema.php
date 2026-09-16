<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Centralised table-name accessor. Everywhere else in the codebase uses these
 * constants / methods — never `$wpdb->prefix . 'reservas_bookings'` inline.
 *
 * Why: the prefix is per-site, and multisite changes it per subsite, so
 * resolving it lazily (not at class-load) is safer than defining constants.
 */
final class Schema {

    public const PREFIX = 'reservas_';

    public const TABLE_BOOKINGS        = 'bookings';
    public const TABLE_BOOKING_DATES   = 'booking_dates';
    public const TABLE_USER_PROFILES   = 'user_profiles';
    public const TABLE_CPA_ITEMS       = 'booking_cpa_items';
    public const TABLE_EMAIL_LOG       = 'email_log';

    public const OPTION_DB_VERSION = 'reservas_aldealab_db_version';
    public const OPTION_SETTINGS   = 'reservas_aldealab_settings';

    public static function tableName( string $baseName ): string {
        global $wpdb;
        return $wpdb->prefix . self::PREFIX . $baseName;
    }

    public static function bookings(): string {
        return self::tableName( self::TABLE_BOOKINGS );
    }

    public static function bookingDates(): string {
        return self::tableName( self::TABLE_BOOKING_DATES );
    }

    public static function userProfiles(): string {
        return self::tableName( self::TABLE_USER_PROFILES );
    }

    public static function cpaItems(): string {
        return self::tableName( self::TABLE_CPA_ITEMS );
    }

    public static function emailLog(): string {
        return self::tableName( self::TABLE_EMAIL_LOG );
    }

    /**
     * All plugin tables, in an order safe for dropping (children first to
     * avoid FK-style dangling references in logs, even though we don't use
     * hard FKs).
     *
     * @return array<int, string> Fully-qualified table names.
     */
    public static function allTablesDropOrder(): array {
        return array(
            self::emailLog(),
            self::cpaItems(),
            self::bookingDates(),
            self::bookings(),
            self::userProfiles(),
        );
    }
}
