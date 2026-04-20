<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Classic metabox for editing sala meta (aforo, disponibilidad, es_cpa).
 *
 * Works in both the Classic and Gutenberg editors. In Gutenberg it renders
 * at the bottom under "Meta Boxes"; acceptable here because these fields
 * don't need a rich-text UI and the plugin targets staff who rarely edit
 * salas. The REST surface (show_in_rest on the meta keys) is what the SPA
 * and public API actually consume.
 */
final class SalaMetabox {

    private const METABOX_ID    = 'reservas_sala_details';
    private const NONCE_FIELD   = 'reservas_sala_nonce';
    private const NONCE_ACTION  = 'reservas_sala_save_meta';

    public static function register(): void {
        add_action( 'add_meta_boxes', array( self::class, 'addMetabox' ) );
        add_action( 'save_post_' . SalaCpt::POST_TYPE, array( self::class, 'save' ), 10, 2 );
    }

    public static function addMetabox(): void {
        add_meta_box(
            self::METABOX_ID,
            __( 'Detalles de la sala', 'reservas-aldealab' ),
            array( self::class, 'render' ),
            SalaCpt::POST_TYPE,
            'side',
            'high'
        );
    }

    public static function render( \WP_Post $post ): void {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

        $aforo_min  = (int) get_post_meta( $post->ID, SalaMeta::AFORO_MIN, true );
        $aforo_max  = (int) get_post_meta( $post->ID, SalaMeta::AFORO_MAX, true );
        $disponible = get_post_meta( $post->ID, SalaMeta::DISPONIBLE, true );
        $es_cpa     = get_post_meta( $post->ID, SalaMeta::ES_CPA, true );

        // New posts: both flags default to their "safe" values.
        if ( $disponible === '' ) {
            $disponible = true;
        } else {
            $disponible = SalaMeta::sanitizeBool( $disponible );
        }
        $es_cpa = SalaMeta::sanitizeBool( $es_cpa );

        ?>
        <p>
            <label for="reservas-aforo-min">
                <strong><?php esc_html_e( 'Aforo mínimo', 'reservas-aldealab' ); ?></strong>
            </label>
            <input
                type="number"
                id="reservas-aforo-min"
                name="<?php echo esc_attr( SalaMeta::AFORO_MIN ); ?>"
                value="<?php echo esc_attr( (string) $aforo_min ); ?>"
                min="0"
                step="1"
                class="widefat"
            />
        </p>
        <p>
            <label for="reservas-aforo-max">
                <strong><?php esc_html_e( 'Aforo máximo', 'reservas-aldealab' ); ?></strong>
            </label>
            <input
                type="number"
                id="reservas-aforo-max"
                name="<?php echo esc_attr( SalaMeta::AFORO_MAX ); ?>"
                value="<?php echo esc_attr( (string) $aforo_max ); ?>"
                min="0"
                step="1"
                class="widefat"
            />
        </p>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="<?php echo esc_attr( SalaMeta::DISPONIBLE ); ?>"
                    value="1"
                    <?php checked( $disponible ); ?>
                />
                <strong><?php esc_html_e( 'Disponible para reserva', 'reservas-aldealab' ); ?></strong>
            </label>
            <br />
            <em class="description">
                <?php esc_html_e( 'Desmarca para ocultar esta sala en el formulario público sin eliminarla.', 'reservas-aldealab' ); ?>
            </em>
        </p>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="<?php echo esc_attr( SalaMeta::ES_CPA ); ?>"
                    value="1"
                    <?php checked( $es_cpa ); ?>
                />
                <strong><?php esc_html_e( 'Espacio CPA', 'reservas-aldealab' ); ?></strong>
            </label>
            <br />
            <em class="description">
                <?php esc_html_e( 'Marca esta casilla si la sala pertenece al Centro de Producciones Audiovisuales. Las reservas usarán la plantilla PDF específica y las instrucciones de Sede Electrónica.', 'reservas-aldealab' ); ?>
            </em>
        </p>
        <?php
    }

    public static function save( int $post_id, \WP_Post $post ): void {
        // Guard: nonce, autosave, permissions, post type.
        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
            return;
        }
        $nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( $post->post_type !== SalaCpt::POST_TYPE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $aforo_min = isset( $_POST[ SalaMeta::AFORO_MIN ] )
            ? absint( wp_unslash( (string) $_POST[ SalaMeta::AFORO_MIN ] ) )
            : 0;
        $aforo_max = isset( $_POST[ SalaMeta::AFORO_MAX ] )
            ? absint( wp_unslash( (string) $_POST[ SalaMeta::AFORO_MAX ] ) )
            : 0;

        // Checkboxes: present in $_POST only when checked.
        $disponible = isset( $_POST[ SalaMeta::DISPONIBLE ] );
        $es_cpa     = isset( $_POST[ SalaMeta::ES_CPA ] );

        update_post_meta( $post_id, SalaMeta::AFORO_MIN, $aforo_min );
        update_post_meta( $post_id, SalaMeta::AFORO_MAX, $aforo_max );
        update_post_meta( $post_id, SalaMeta::DISPONIBLE, $disponible );
        update_post_meta( $post_id, SalaMeta::ES_CPA, $es_cpa );
    }
}
