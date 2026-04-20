<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Repositories;

defined( 'ABSPATH' ) || exit;

use WP_Post;
use WP_Query;
use WebcafeinaReservas\Models\Sala;
use WebcafeinaReservas\PostTypes\SalaCpt;
use WebcafeinaReservas\PostTypes\SalaMeta;

/**
 * Read-only access to salas. Writes go through the WP editor / REST / the
 * admin React app — never through this class.
 *
 * Filters supported by {@see self::search()}:
 * - aforo_min / aforo_max: sala fits a party of this size.
 * - servicios: array of term IDs. Sala must have ALL of them (AND).
 * - edificio: single term ID.
 * - disponible: defaults to null (return all); pass true to filter to
 *   only bookable salas.
 */
final class SalaRepository {

    /**
     * @param array{
     *     aforo_min?: int|null,
     *     aforo_max?: int|null,
     *     servicios?: array<int, int>,
     *     edificio?: int|null,
     *     disponible?: bool|null,
     *     per_page?: int,
     *     page?: int,
     * } $filters
     *
     * @return array<int, Sala>
     */
    public function search( array $filters = array() ): array {
        $args = array(
            'post_type'      => SalaCpt::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => isset( $filters['per_page'] ) ? (int) $filters['per_page'] : -1,
            'paged'          => isset( $filters['page'] ) ? max( 1, (int) $filters['page'] ) : 1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => false,
        );

        $meta_query = array();

        if ( isset( $filters['aforo_min'] ) && $filters['aforo_min'] !== null ) {
            // Sala accepts this party size if its max >= requested min.
            $meta_query[] = array(
                'key'     => SalaMeta::AFORO_MAX,
                'value'   => (int) $filters['aforo_min'],
                'type'    => 'NUMERIC',
                'compare' => '>=',
            );
        }

        if ( isset( $filters['aforo_max'] ) && $filters['aforo_max'] !== null ) {
            // Sala's min must fit (min <= requested max).
            $meta_query[] = array(
                'key'     => SalaMeta::AFORO_MIN,
                'value'   => (int) $filters['aforo_max'],
                'type'    => 'NUMERIC',
                'compare' => '<=',
            );
        }

        if ( isset( $filters['disponible'] ) && $filters['disponible'] !== null ) {
            $meta_query[] = array(
                'key'     => SalaMeta::DISPONIBLE,
                'value'   => $filters['disponible'] ? '1' : '',
                'compare' => $filters['disponible'] ? '=' : '!=',
            );
        }

        if ( $meta_query !== array() ) {
            $args['meta_query'] = $meta_query;
        }

        $tax_query = array();

        if ( isset( $filters['edificio'] ) && $filters['edificio'] !== null ) {
            $tax_query[] = array(
                'taxonomy' => SalaCpt::TAX_EDIFICIO,
                'field'    => 'term_id',
                'terms'    => array( (int) $filters['edificio'] ),
            );
        }

        if ( isset( $filters['servicios'] ) && $filters['servicios'] !== array() ) {
            $tax_query[] = array(
                'taxonomy' => SalaCpt::TAX_SERVICIOS,
                'field'    => 'term_id',
                'terms'    => array_map( 'intval', $filters['servicios'] ),
                'operator' => 'AND',
            );
        }

        if ( $tax_query !== array() ) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query( $args );

        $out = array();
        foreach ( $query->posts as $post ) {
            if ( $post instanceof WP_Post ) {
                $out[] = Sala::fromPost( $post );
            }
        }
        return $out;
    }

    public function find( int $id ): ?Sala {
        if ( $id <= 0 ) {
            return null;
        }
        $post = get_post( $id );
        if ( ! $post instanceof WP_Post || $post->post_type !== SalaCpt::POST_TYPE ) {
            return null;
        }
        if ( $post->post_status !== 'publish' ) {
            return null;
        }
        return Sala::fromPost( $post );
    }
}
