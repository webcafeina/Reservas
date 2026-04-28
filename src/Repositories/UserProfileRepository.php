<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Repositories;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Database\Schema;
use WebcafeinaReservas\Models\UserProfile;
use wpdb;

/**
 * Persistence for reservas_user_profiles. `email` is the natural key:
 * a profile is upserted by email so guest bookings with the same email
 * consolidate under one record.
 */
final class UserProfileRepository {

    private wpdb $wpdb;

    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
    }

    public function findById( int $id ): ?UserProfile {
        if ( $id <= 0 ) {
            return null;
        }
        $table = Schema::userProfiles();
        $row   = $this->wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
            ARRAY_A
        );
        return is_array( $row ) ? UserProfile::fromArray( $row ) : null;
    }

    public function findForUser( int $userId ): ?UserProfile {
        if ( $userId <= 0 ) {
            return null;
        }
        $table = Schema::userProfiles();
        $row   = $this->wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $userId
            ),
            ARRAY_A
        );
        return is_array( $row ) ? UserProfile::fromArray( $row ) : null;
    }

    /**
     * Builds a UserProfile from the WP `wp_usermeta` keys used by the
     * Aldealab account. Used as a fallback for `ProfileController::show`
     * when the user is logged in but has never made a booking through
     * the plugin (no row in `reservas_user_profiles` yet) — pre-fills
     * the public booking form so the user doesn't re-key data they've
     * already saved in their account.
     *
     * Strategy is "fila del plugin gana": this method is only called if
     * `findForUser()` returned null. Once the user creates their first
     * booking, `upsert()` writes the row, and from then on the row is
     * the source of truth.
     *
     * Returns null when the user has none of the relevant metas (so the
     * caller can keep returning `{ profile: null }` and the form stays
     * empty for the user to fill manually).
     */
    public function buildFromUserMeta( int $userId ): ?UserProfile {
        if ( $userId <= 0 ) {
            return null;
        }

        // Field name on UserProfile (snake_case as used in fromArray)
        // mapped to the meta_key configured by the customer.
        $metaMap = array(
            'nif'              => 'nif_usuario',
            'nombre'           => 'nombre_usuario',
            'primer_apellido'  => 'apellido1_usuario',
            'segundo_apellido' => 'apellido2_usuario',
            'email'            => 'email_usuario',
            'movil'            => 'movil_usuario',
            'telefono_fijo'    => 'telefono_usuario',
            'empresa'          => 'empresa',
            'via'              => 'via_direccion_usuario',
            'numero'           => 'numero_direccion_usuario',
            'letra'            => 'letra_direccion_usuario',
            'escalera'         => 'escalera_direccion_usuario',
            'piso'             => 'piso_direccion_usuario',
            'puerta'           => 'puerta_direccion_usuario',
            'municipio'        => 'municipio_usuario',
            'provincia'        => 'provincia_usuario',
            'codigo_postal'    => 'cp_usuario',
        );

        $data    = array( 'user_id' => $userId );
        $hasAny  = false;
        foreach ( $metaMap as $field => $metaKey ) {
            $value = get_user_meta( $userId, $metaKey, true );
            if ( is_string( $value ) && $value !== '' ) {
                $data[ $field ] = $value;
                $hasAny         = true;
            }
        }

        // Email fallback: if `email_usuario` meta is empty, use the
        // WP user account email — that's always populated and is what
        // the user identifies themselves with.
        if ( ! isset( $data['email'] ) || $data['email'] === '' ) {
            $user = get_userdata( $userId );
            if ( $user instanceof \WP_User && $user->user_email !== '' ) {
                $data['email'] = (string) $user->user_email;
                $hasAny        = true;
            }
        }

        if ( ! $hasAny ) {
            return null;
        }

        return UserProfile::fromArray( $data );
    }

    public function findByEmail( string $email ): ?UserProfile {
        $email = trim( $email );
        if ( $email === '' ) {
            return null;
        }
        $table = Schema::userProfiles();
        $row   = $this->wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ),
            ARRAY_A
        );
        return is_array( $row ) ? UserProfile::fromArray( $row ) : null;
    }

    /**
     * Insert or update by email. Returns the profile id.
     */
    public function upsert( UserProfile $profile ): int {
        $existing = $this->findByEmail( $profile->email );

        $data = array(
            'user_id'          => $profile->userId,
            'nif'              => $profile->nif,
            'nombre'           => $profile->nombre,
            'primer_apellido'  => $profile->primerApellido,
            'segundo_apellido' => $profile->segundoApellido,
            'via'              => $profile->via,
            'numero'           => $profile->numero,
            'letra'            => $profile->letra,
            'escalera'         => $profile->escalera,
            'piso'             => $profile->piso,
            'puerta'           => $profile->puerta,
            'municipio'        => $profile->municipio,
            'provincia'        => $profile->provincia,
            'codigo_postal'    => $profile->codigoPostal,
            'telefono_fijo'    => $profile->telefonoFijo,
            'movil'            => $profile->movil,
            'email'            => $profile->email,
            'empresa'          => $profile->empresa,
        );

        $table = Schema::userProfiles();

        if ( $existing !== null && $existing->id !== null ) {
            $this->wpdb->update( $table, $data, array( 'id' => $existing->id ) );
            return (int) $existing->id;
        }

        $this->wpdb->insert( $table, $data );
        return (int) $this->wpdb->insert_id;
    }
}
