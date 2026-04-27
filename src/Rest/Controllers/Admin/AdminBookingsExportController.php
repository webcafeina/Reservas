<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Rest\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WebcafeinaReservas\Database\Schema;
use WebcafeinaReservas\Rest\RestApi;

/**
 * GET /admin/bookings/export.csv — streams the filtered bookings list as
 * a UTF-8 CSV with a BOM (so Excel opens it in the right encoding).
 *
 * Accepts the same filters as GET /admin/bookings (estado, sala_id, email,
 * from, to) but without pagination — the whole result set is streamed.
 * A hard cap of 10 000 rows prevents accidental dumps of huge tables.
 */
final class AdminBookingsExportController {

    private const MAX_ROWS = 10000;

    public function register(): void {
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/bookings/export',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'export' ),
                    'permission_callback' => array( RestApi::class, 'currentUserCanManage' ),
                ),
            )
        );
    }

    public function export( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $table_b  = Schema::bookings();
        $table_p  = Schema::userProfiles();
        $table_bd = Schema::bookingDates();

        $where  = array( '1=1' );
        $params = array();

        $estado = (string) $request->get_param( 'estado' );
        if ( $estado !== '' ) {
            $where[]  = 'b.estado = %s';
            $params[] = sanitize_text_field( $estado );
        }
        $sala = (int) $request->get_param( 'sala_id' );
        if ( $sala > 0 ) {
            $where[]  = 'b.sala_id = %d';
            $params[] = $sala;
        }
        $email = (string) $request->get_param( 'email' );
        if ( $email !== '' ) {
            $where[]  = 'p.email = %s';
            $params[] = sanitize_email( $email );
        }
        // Date filter operates on `booking_dates` (any active session falling
        // in the range), not just `fecha_inicio`. Mirrors the listing
        // semantics in BookingRepository::searchForAdmin so the CSV reflects
        // exactly what the admin sees in the panel.
        $from        = (string) $request->get_param( 'from' );
        $to          = (string) $request->get_param( 'to' );
        $hasFromDate = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) === 1;
        $hasToDate   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) === 1;
        if ( $hasFromDate || $hasToDate ) {
            $bd_conds = array( 'bd.booking_id = b.id', "bd.estado_fecha = 'activa'" );
            if ( $hasFromDate ) {
                $bd_conds[] = 'bd.fecha >= %s';
                $params[]   = $from;
            }
            if ( $hasToDate ) {
                $bd_conds[] = 'bd.fecha <= %s';
                $params[]   = $to;
            }
            $where[] = 'EXISTS (SELECT 1 FROM ' . $table_bd . ' bd WHERE ' . implode( ' AND ', $bd_conds ) . ')';
        }

        $where_sql = implode( ' AND ', $where );

        $sql =
            'SELECT b.id, b.uuid, b.estado, b.sala_id, b.fecha_inicio, b.fecha_fin_serie, '
            . 'b.hora_inicio, b.hora_fin, b.rrule, b.objeto_reserva, b.created_at, '
            . "p.email AS email, p.nombre, p.primer_apellido, p.segundo_apellido, p.movil, p.empresa "
            . "FROM {$table_b} b LEFT JOIN {$table_p} p ON p.id = b.profile_id "
            . "WHERE {$where_sql} "
            . 'ORDER BY b.created_at DESC '
            . 'LIMIT ' . self::MAX_ROWS;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = (array) $wpdb->get_results(
            $params === array() ? $sql : $wpdb->prepare( $sql, $params ),
            ARRAY_A
        );

        $filename = 'reservas-' . gmdate( 'Ymd-His' ) . '.csv';
        $csv      = self::toCsv( $rows );

        $response = new WP_REST_Response( null, 200 );
        $response->header( 'Content-Type', 'text/csv; charset=utf-8' );
        $response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
        $response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );

        add_filter(
            'rest_pre_serve_request',
            static function ( $served, $_response, $_request, $_server ) use ( $csv ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $csv;
                return true;
            },
            10,
            4
        );

        return $response;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function toCsv( array $rows ): string {
        $headers = array(
            'id', 'uuid', 'estado', 'sala_id',
            'fecha_inicio', 'fecha_fin_serie', 'hora_inicio', 'hora_fin', 'rrule',
            'objeto_reserva',
            'email', 'nombre', 'primer_apellido', 'segundo_apellido', 'movil', 'empresa',
            'created_at',
        );

        $fh = fopen( 'php://temp', 'r+' );
        if ( $fh === false ) {
            return '';
        }
        // UTF-8 BOM so Excel autodetects the charset.
        fwrite( $fh, "\xEF\xBB\xBF" );
        fputcsv( $fh, $headers );

        foreach ( $rows as $row ) {
            $line = array();
            foreach ( $headers as $h ) {
                $line[] = isset( $row[ $h ] ) ? (string) $row[ $h ] : '';
            }
            fputcsv( $fh, $line );
        }

        rewind( $fh );
        $out = (string) stream_get_contents( $fh );
        fclose( $fh );
        return $out;
    }
}
