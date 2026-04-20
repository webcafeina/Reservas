<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

use DateTimeImmutable;
use DateTimeZone;
use WebcafeinaReservas\Models\Booking;
use WebcafeinaReservas\Models\Sala;
use WebcafeinaReservas\Models\UserProfile;

/**
 * Emits an RFC 5545 VCALENDAR for a booking. For recurring series we emit
 * the expanded dates as independent VEVENTs (one per day) rather than an
 * RRULE — safer across calendar clients, and the series may have
 * exclusions we've already applied.
 *
 * Output is raw bytes ready to stream with a `text/calendar` header.
 */
final class IcalGenerator {

    public const PROD_ID = '-//Webcafeína//Reservas Aldealab//ES';

    public function build( Booking $booking, UserProfile $profile, Sala $sala ): string {
        $now = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'Ymd\THis\Z' );

        $lines = array();
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:' . self::PROD_ID;
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';

        $dates = $booking->fechas !== array() ? $booking->fechas : array( $booking->fechaInicio );

        $summary     = 'Reserva — ' . $sala->title;
        $description = $this->foldMultiline( $booking->objetoReserva . "\\nSolicitante: " . $profile->fullName() );
        $location    = $sala->title;

        $hora_inicio = substr( $booking->horaInicio, 0, 8 );
        $hora_fin    = substr( $booking->horaFin, 0, 8 );

        foreach ( $dates as $idx => $iso ) {
            $dtStart = self::combineLocal( (string) $iso, $hora_inicio );
            $dtEnd   = self::combineLocal( (string) $iso, $hora_fin );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $booking->uuid . '-' . $idx . '@reservas-aldealab';
            $lines[] = 'DTSTAMP:' . $now;
            $lines[] = 'DTSTART:' . $dtStart;
            $lines[] = 'DTEND:' . $dtEnd;
            $lines[] = 'SUMMARY:' . $this->escape( $summary );
            $lines[] = 'LOCATION:' . $this->escape( $location );
            $lines[] = 'DESCRIPTION:' . $description;
            $lines[] = 'STATUS:' . ( $booking->estado === 'cancelada' ? 'CANCELLED' : 'CONFIRMED' );
            $lines[] = 'ORGANIZER;CN=Reservas Aldealab:mailto:' . $this->escape( $profile->email );
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545 mandates CRLF line endings and ≤ 75 octets per line (folded).
        $out = '';
        foreach ( $lines as $line ) {
            $out .= self::foldLine( $line ) . "\r\n";
        }
        return $out;
    }

    /**
     * Combines a YYYY-MM-DD date with an HH:MM:SS time into a local-TZ
     * DTSTART / DTEND string. Using local (floating) rather than UTC so
     * calendar clients show the booking at the wall-clock time the user
     * selected, regardless of their viewer TZ.
     */
    private static function combineLocal( string $isoDate, string $hms ): string {
        $date = preg_replace( '/-/', '', $isoDate ) ?? $isoDate;
        $time = preg_replace( '/:/', '', $hms ) ?? $hms;
        // Pad HMMSS to 6 digits if we only got HH:MM.
        $time = str_pad( (string) $time, 6, '0' );
        return $date . 'T' . substr( $time, 0, 6 );
    }

    private function escape( string $s ): string {
        // RFC 5545 §3.3.11: escape backslash, comma, semicolon, newline.
        return strtr(
            $s,
            array(
                '\\' => '\\\\',
                ','  => '\\,',
                ';'  => '\\;',
                "\n" => '\\n',
                "\r" => '',
            )
        );
    }

    private function foldMultiline( string $s ): string {
        // Same escaping but keep inline \\n markers for multi-line DESCRIPTION.
        return $this->escape( $s );
    }

    /**
     * Folds a logical line to ≤ 75 octets with a CRLF + space continuation,
     * as required by RFC 5545 §3.1. Works on byte count, not character count.
     */
    private static function foldLine( string $line ): string {
        if ( strlen( $line ) <= 75 ) {
            return $line;
        }
        $folded = '';
        $offset = 0;
        $len    = strlen( $line );
        while ( $offset < $len ) {
            $chunk = substr( $line, $offset, $offset === 0 ? 75 : 74 );
            $folded .= ( $offset === 0 ? '' : "\r\n " ) . $chunk;
            $offset += strlen( $chunk );
        }
        return $folded;
    }
}
