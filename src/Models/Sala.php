<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Models;

defined( 'ABSPATH' ) || exit;

use WP_Post;
use WebcafeinaReservas\PostTypes\SalaCpt;
use WebcafeinaReservas\PostTypes\SalaMeta;

/**
 * Immutable DTO representing a sala post + its meta + its terms.
 *
 * Built via {@see Sala::fromPost()} so callers never have to deal with
 * WP_Post / get_post_meta / wp_get_post_terms shapes directly.
 */
final class Sala {

    public int $id;
    public string $slug;
    public string $title;
    public string $description;
    public string $excerpt;
    public ?string $featuredImageUrl;
    public int $aforoMin;
    public int $aforoMax;
    public bool $disponible;
    public bool $esCpa;

    /** @var array<int, array{id:int, name:string, slug:string}> */
    public array $edificios;

    /** @var array<int, array{id:int, name:string, slug:string}> */
    public array $servicios;

    /**
     * @param array<int, array{id:int, name:string, slug:string}> $edificios
     * @param array<int, array{id:int, name:string, slug:string}> $servicios
     */
    private function __construct(
        int $id,
        string $slug,
        string $title,
        string $description,
        string $excerpt,
        ?string $featuredImageUrl,
        int $aforoMin,
        int $aforoMax,
        bool $disponible,
        bool $esCpa,
        array $edificios,
        array $servicios
    ) {
        $this->id                = $id;
        $this->slug              = $slug;
        $this->title             = $title;
        $this->description       = $description;
        $this->excerpt           = $excerpt;
        $this->featuredImageUrl  = $featuredImageUrl;
        $this->aforoMin          = $aforoMin;
        $this->aforoMax          = $aforoMax;
        $this->disponible        = $disponible;
        $this->esCpa             = $esCpa;
        $this->edificios         = $edificios;
        $this->servicios         = $servicios;
    }

    public static function fromPost( WP_Post $post ): self {
        $featured = null;
        $thumb_id = get_post_thumbnail_id( $post->ID );
        if ( is_int( $thumb_id ) && $thumb_id > 0 ) {
            $url = wp_get_attachment_image_url( $thumb_id, 'large' );
            $featured = is_string( $url ) && $url !== '' ? $url : null;
        }

        $edificios = self::mapTerms( $post->ID, SalaCpt::TAX_EDIFICIO );
        $servicios = self::mapTerms( $post->ID, SalaCpt::TAX_SERVICIOS );

        return new self(
            (int) $post->ID,
            (string) $post->post_name,
            get_the_title( $post ),
            (string) $post->post_content,
            (string) $post->post_excerpt,
            $featured,
            (int) get_post_meta( $post->ID, SalaMeta::AFORO_MIN, true ),
            (int) get_post_meta( $post->ID, SalaMeta::AFORO_MAX, true ),
            SalaMeta::sanitizeBool( get_post_meta( $post->ID, SalaMeta::DISPONIBLE, true ) ),
            SalaMeta::sanitizeBool( get_post_meta( $post->ID, SalaMeta::ES_CPA, true ) ),
            $edificios,
            $servicios
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return array(
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'title'              => $this->title,
            'description'        => $this->description,
            'excerpt'            => $this->excerpt,
            'featured_image_url' => $this->featuredImageUrl,
            'aforo_min'          => $this->aforoMin,
            'aforo_max'          => $this->aforoMax,
            'disponible'         => $this->disponible,
            'es_cpa'             => $this->esCpa,
            'edificios'          => $this->edificios,
            'servicios'          => $this->servicios,
        );
    }

    /**
     * @return array<int, array{id:int, name:string, slug:string}>
     */
    private static function mapTerms( int $post_id, string $taxonomy ): array {
        $terms = wp_get_post_terms( $post_id, $taxonomy );
        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return array();
        }
        $out = array();
        foreach ( $terms as $term ) {
            if ( ! $term instanceof \WP_Term ) {
                continue;
            }
            $out[] = array(
                'id'   => (int) $term->term_id,
                'name' => (string) $term->name,
                'slug' => (string) $term->slug,
            );
        }
        return $out;
    }
}
