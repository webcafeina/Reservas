<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Repositories;

defined( 'ABSPATH' ) || exit;

use WebcafeinaReservas\Database\Schema;
use RuntimeException;
use WebcafeinaReservas\Models\UserProfile;
use wpdb;

/**
 * Persistence for reservas_user_profiles. Each booking owns its own row
 * (`bookings.profile_id`): the personal data is a snapshot of what was
 * entered for that booking, so editing one booking never changes another
 * one with the same email. Until 0.23.0 rows were upserted by email and
 * shared between bookings — migration 003 split them.
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
     * booking, `insert()` writes a row, and from then on the row is
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

    /**
     * Inserts a new profile row and returns its id. Every booking gets its
     * own row (see class docblock), so this is what BookingService::create()
     * calls.
     *
     * @throws RuntimeException When the insert fails, so a caller inside a
     *                          transaction rolls back.
     */
    public function insert( UserProfile $profile ): int {
        $ok = $this->wpdb->insert( Schema::userProfiles(), self::toRow( $profile ) );
        if ( $ok === false ) {
            throw new RuntimeException( 'No se pudieron guardar los datos del solicitante: ' . $this->wpdb->last_error );
        }
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Overwrites the profile row `$id`. Only for the row owned by a single
     * booking (or the standalone row of a WP user) — never look a row up by
     * email to update it, other bookings may share that email.
     *
     * @throws RuntimeException When the update fails.
     */
    public function update( int $id, UserProfile $profile ): void {
        $ok = $this->wpdb->update( Schema::userProfiles(), self::toRow( $profile ), array( 'id' => $id ) );
        if ( $ok === false ) {
            throw new RuntimeException( 'No se pudieron actualizar los datos del solicitante: ' . $this->wpdb->last_error );
        }
    }

    /**
     * Saves the data a logged-in user edits from the public form
     * (PUT /user/profile). Updates the user's latest row only when no
     * booking points at it; otherwise inserts a new row, so the personal
     * data stored on past bookings stays as it was. Returns the row id.
     */
    public function saveForUser( UserProfile $profile ): int {
        $userId = $profile->userId ?? 0;
        if ( $userId > 0 ) {
            $table    = Schema::userProfiles();
            $bookings = Schema::bookings();
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $id = (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT up.id FROM {$table} up "
                    . "WHERE up.user_id = %d "
                    . "AND NOT EXISTS ( SELECT 1 FROM {$bookings} b WHERE b.profile_id = up.id ) "
                    . 'ORDER BY up.id DESC LIMIT 1',
                    $userId
                )
            );
            if ( $id > 0 ) {
                $this->update( $id, $profile );
                return $id;
            }
        }
        return $this->insert( $profile );
    }

    /**
     * @return array<string, mixed>
     */
    private static function toRow( UserProfile $profile ): array {
        return array(
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
    }
}
