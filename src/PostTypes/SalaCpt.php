<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `sala` CPT and its two taxonomies (edificio, servicios_sala).
 *
 * CPT owns: title, editor, thumbnail, custom-fields, revisions, excerpt.
 * REST: exposed under wp/v2/salas (plus plugin-specific endpoints in
 * the reservas/v1 namespace; see Rest\RestApi).
 */
final class SalaCpt {

    public const POST_TYPE        = 'sala';
    public const TAX_EDIFICIO     = 'edificio';
    public const TAX_SERVICIOS    = 'servicios_sala';
    public const REST_BASE        = 'salas';

    public static function register(): void {
        add_action( 'init', array( self::class, 'registerPostType' ), 10 );
        add_action( 'init', array( self::class, 'registerTaxonomies' ), 11 );
        add_action( 'init', array( SalaMeta::class, 'register' ), 12 );
    }

    public static function registerPostType(): void {
        $labels = array(
            'name'                  => _x( 'Salas', 'post type general name', 'reservas-aldealab' ),
            'singular_name'         => _x( 'Sala', 'post type singular name', 'reservas-aldealab' ),
            'menu_name'             => _x( 'Salas', 'admin menu', 'reservas-aldealab' ),
            'name_admin_bar'        => _x( 'Sala', 'add new on admin bar', 'reservas-aldealab' ),
            'add_new'               => _x( 'Añadir nueva', 'sala', 'reservas-aldealab' ),
            'add_new_item'          => __( 'Añadir nueva sala', 'reservas-aldealab' ),
            'new_item'              => __( 'Nueva sala', 'reservas-aldealab' ),
            'edit_item'             => __( 'Editar sala', 'reservas-aldealab' ),
            'view_item'             => __( 'Ver sala', 'reservas-aldealab' ),
            'all_items'             => __( 'Todas las salas', 'reservas-aldealab' ),
            'search_items'          => __( 'Buscar salas', 'reservas-aldealab' ),
            'not_found'             => __( 'No hay salas.', 'reservas-aldealab' ),
            'not_found_in_trash'    => __( 'No hay salas en la papelera.', 'reservas-aldealab' ),
            'featured_image'        => __( 'Imagen de la sala', 'reservas-aldealab' ),
            'set_featured_image'    => __( 'Establecer imagen de la sala', 'reservas-aldealab' ),
            'remove_featured_image' => __( 'Quitar imagen de la sala', 'reservas-aldealab' ),
            'use_featured_image'    => __( 'Usar como imagen de la sala', 'reservas-aldealab' ),
            'archives'              => __( 'Archivo de salas', 'reservas-aldealab' ),
            'filter_items_list'     => __( 'Filtrar lista de salas', 'reservas-aldealab' ),
            'items_list_navigation' => __( 'Navegación de la lista de salas', 'reservas-aldealab' ),
            'items_list'            => __( 'Lista de salas', 'reservas-aldealab' ),
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __( 'Salas reservables del edificio Aldealab.', 'reservas-aldealab' ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            // Hang under the plugin's "Reservas" top-level menu (slug in
            // Admin\AdminMenu::SLUG) instead of creating a duplicate top-level.
            'show_in_menu'       => 'reservas-aldealab',
            'show_in_rest'       => true,
            'rest_base'          => self::REST_BASE,
            'query_var'          => true,
            'rewrite'            => array(
                'slug'       => self::REST_BASE,
                'with_front' => false,
            ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields',
                'revisions',
            ),
            'taxonomies'         => array( self::TAX_EDIFICIO, self::TAX_SERVICIOS ),
        );

        register_post_type( self::POST_TYPE, $args );
    }

    public static function registerTaxonomies(): void {
        register_taxonomy(
            self::TAX_EDIFICIO,
            array( self::POST_TYPE ),
            array(
                'labels'            => array(
                    'name'          => _x( 'Edificios', 'taxonomy general name', 'reservas-aldealab' ),
                    'singular_name' => _x( 'Edificio', 'taxonomy singular name', 'reservas-aldealab' ),
                    'search_items'  => __( 'Buscar edificios', 'reservas-aldealab' ),
                    'all_items'     => __( 'Todos los edificios', 'reservas-aldealab' ),
                    'edit_item'     => __( 'Editar edificio', 'reservas-aldealab' ),
                    'update_item'   => __( 'Actualizar edificio', 'reservas-aldealab' ),
                    'add_new_item'  => __( 'Añadir edificio', 'reservas-aldealab' ),
                    'new_item_name' => __( 'Nombre del edificio', 'reservas-aldealab' ),
                    'menu_name'     => __( 'Edificios', 'reservas-aldealab' ),
                ),
                'hierarchical'      => true,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'rest_base'         => 'edificios',
                'rewrite'           => array( 'slug' => 'edificio' ),
            )
        );

        register_taxonomy(
            self::TAX_SERVICIOS,
            array( self::POST_TYPE ),
            array(
                'labels'            => array(
                    'name'          => _x( 'Servicios', 'taxonomy general name', 'reservas-aldealab' ),
                    'singular_name' => _x( 'Servicio', 'taxonomy singular name', 'reservas-aldealab' ),
                    'search_items'  => __( 'Buscar servicios', 'reservas-aldealab' ),
                    'all_items'     => __( 'Todos los servicios', 'reservas-aldealab' ),
                    'edit_item'     => __( 'Editar servicio', 'reservas-aldealab' ),
                    'update_item'   => __( 'Actualizar servicio', 'reservas-aldealab' ),
                    'add_new_item'  => __( 'Añadir servicio', 'reservas-aldealab' ),
                    'new_item_name' => __( 'Nombre del servicio', 'reservas-aldealab' ),
                    'menu_name'     => __( 'Servicios', 'reservas-aldealab' ),
                ),
                'hierarchical'      => false,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'rest_base'         => 'servicios',
                'rewrite'           => array( 'slug' => 'servicio' ),
            )
        );
    }
}
