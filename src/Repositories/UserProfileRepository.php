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
