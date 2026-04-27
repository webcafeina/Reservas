<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Models;

/**
 * Persistent personal data for a booking requester. Survives individual
 * bookings so a logged-in user doesn't re-key their details every time.
 * Guest bookings create one of these too (user_id nullable).
 */
final class UserProfile {

    public ?int $id;
    public ?int $userId;
    public string $nif;
    public string $nombre;
    public string $primerApellido;
    public ?string $segundoApellido;
    public string $via;
    public string $numero;
    public ?string $letra;
    public ?string $escalera;
    public ?string $piso;
    public ?string $puerta;
    public string $municipio;
    public string $provincia;
    public string $codigoPostal;
    public ?string $telefonoFijo;
    public string $movil;
    public string $email;
    public ?string $empresa = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray( array $data ): self {
        $p = new self();
        $p->id              = isset( $data['id'] ) ? (int) $data['id'] : null;
        $p->userId          = isset( $data['user_id'] ) && $data['user_id'] !== null && $data['user_id'] !== ''
            ? (int) $data['user_id']
            : null;
        $p->nif             = (string) ( $data['nif'] ?? '' );
        $p->nombre          = (string) ( $data['nombre'] ?? '' );
        $p->primerApellido  = (string) ( $data['primer_apellido'] ?? '' );
        $p->segundoApellido = isset( $data['segundo_apellido'] ) && $data['segundo_apellido'] !== ''
            ? (string) $data['segundo_apellido']
            : null;
        $p->via             = (string) ( $data['via'] ?? '' );
        $p->numero          = (string) ( $data['numero'] ?? '' );
        $p->letra           = self::nullableString( $data, 'letra' );
        $p->escalera        = self::nullableString( $data, 'escalera' );
        $p->piso            = self::nullableString( $data, 'piso' );
        $p->puerta          = self::nullableString( $data, 'puerta' );
        $p->municipio       = (string) ( $data['municipio'] ?? '' );
        $p->provincia       = (string) ( $data['provincia'] ?? '' );
        $p->codigoPostal    = (string) ( $data['codigo_postal'] ?? '' );
        $p->telefonoFijo    = self::nullableString( $data, 'telefono_fijo' );
        $p->movil           = (string) ( $data['movil'] ?? '' );
        $p->email           = (string) ( $data['email'] ?? '' );
        $p->empresa         = self::nullableString( $data, 'empresa' );
        return $p;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return array(
            'id'               => $this->id,
            'user_id'          => $this->userId,
            'nif'              => $this->nif,
            'nombre'           => $this->nombre,
            'primer_apellido'  => $this->primerApellido,
            'segundo_apellido' => $this->segundoApellido,
            'via'              => $this->via,
            'numero'           => $this->numero,
            'letra'            => $this->letra,
            'escalera'         => $this->escalera,
            'piso'             => $this->piso,
            'puerta'           => $this->puerta,
            'municipio'        => $this->municipio,
            'provincia'        => $this->provincia,
            'codigo_postal'    => $this->codigoPostal,
            'telefono_fijo'    => $this->telefonoFijo,
            'movil'            => $this->movil,
            'email'            => $this->email,
            'empresa'          => $this->empresa,
        );
    }

    public function fullName(): string {
        $parts = array_filter(
            array( $this->nombre, $this->primerApellido, $this->segundoApellido ),
            static function ( ?string $s ): bool {
                return $s !== null && $s !== '';
            }
        );
        return trim( implode( ' ', $parts ) );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableString( array $data, string $key ): ?string {
        if ( ! isset( $data[ $key ] ) ) {
            return null;
        }
        $v = (string) $data[ $key ];
        return $v === '' ? null : $v;
    }
}
