<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Creates / removes the two roles owned by this plugin.
 *
 * - `usuario_alojado`: tenants of the Aldealab building. If the booking user
 *   has this role *and* the sala is not CPA, they don't receive the Sede
 *   Electrónica PDF (they already have presented the paperwork to be tenants).
 * - `reservas_manager`: non-admin staff allowed into the reservations panel.
 *
 * Capabilities:
 * - `manage_reservas` — the single gate checked by every admin REST endpoint
 *   and the admin menu page.
 */
final class RoleManager {

    public const ROLE_TENANT  = 'usuario_alojado';
    public const ROLE_MANAGER = 'reservas_manager';

    /**
     * Role slugs treated as "tenant of the Aldealab building" by the
     * plugin. The canonical one (`usuario_alojado`) is created and
     * removed by the plugin itself. Aliases (e.g. `usuario-alojado`,
     * with a hyphen) may be created by other systems for the same
     * audience; we accept any of them when deciding whether to skip
     * the PDF attachment.
     *
     * @var list<string>
     */
    public const TENANT_ROLES = array(
        'usuario_alojado',
        'usuario-alojado',
    );

    public const CAP_MANAGE = 'manage_reservas';

    public static function ensureRoles(): void {
        // Tenants: read-only WP access; the plugin uses the role *itself* as
        // the signal, no extra capabilities needed.
        if ( get_role( self::ROLE_TENANT ) === null ) {
            add_role(
                self::ROLE_TENANT,
                __( 'Usuario alojado', 'reservas-aldealab' ),
                array(
                    'read' => true,
                )
            );
        }

        // Managers: full control over the plugin (panel + salas CPT +
        // Edificios/Servicios taxonomies), but no access to the rest of
        // wp-admin (no users, no other plugins, no settings outside the
        // plugin's own page). The CPT uses capability_type=post, so we
        // need the matching post-management caps. `manage_categories` is
        // the standard cap for managing taxonomies under the same type.
        $managerCaps = array(
            'read'                   => true,
            self::CAP_MANAGE         => true,
            'upload_files'           => true,
            // Salas (CPT capability_type=post)
            'edit_posts'             => true,
            'edit_others_posts'      => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
            'delete_posts'           => true,
            'delete_others_posts'    => true,
            'delete_published_posts' => true,
            'read_private_posts'     => true,
            // Edificios + Servicios taxonomies
            'manage_categories'      => true,
        );

        $manager = get_role( self::ROLE_MANAGER );
        if ( $manager === null ) {
            add_role(
                self::ROLE_MANAGER,
                __( 'Gestor de Reservas', 'reservas-aldealab' ),
                $managerCaps
            );
        } else {
            // Self-heal existing role: add any cap that's missing without
            // touching unrelated caps that may have been added by other
            // plugins or by hand. Idempotent on every admin pageload.
            foreach ( array_keys( $managerCaps ) as $cap ) {
                if ( ! $manager->has_cap( $cap ) ) {
                    $manager->add_cap( $cap );
                }
            }
        }

        // Administrators always get manage_reservas, even if they never
        // touched this plugin before. Idempotent.
        $admin = get_role( 'administrator' );
        if ( $admin !== null && ! $admin->has_cap( self::CAP_MANAGE ) ) {
            $admin->add_cap( self::CAP_MANAGE );
        }
    }

    public static function removeRoles(): void {
        remove_role( self::ROLE_TENANT );
        remove_role( self::ROLE_MANAGER );

        $admin = get_role( 'administrator' );
        if ( $admin !== null && $admin->has_cap( self::CAP_MANAGE ) ) {
            $admin->remove_cap( self::CAP_MANAGE );
        }
    }
}
