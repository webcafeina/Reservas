<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical list of Spanish provinces (50) plus the autonomous cities
 * Ceuta and Melilla. Bilingual names follow the regional/Castilian
 * order separated by a slash, matching how they're rendered in the
 * panel dropdown and how they're persisted in the DB.
 *
 * Used by the REST controllers to reject any `profile.provincia`
 * value that doesn't match this closed list. Front-end mirror lives
 * at `frontend/src/data/provincias.ts` — keep both in sync if a name
 * ever changes officially.
 */
final class Provincias {

    /** @var array<int, string> */
    public const ALL = array(
        'A Coruña/La Coruña',
        'Alacant/Alicante',
        'Albacete',
        'Almería',
        'Araba/Álava',
        'Asturias',
        'Ávila',
        'Badajoz',
        'Barcelona',
        'Bizkaia/Vizcaya',
        'Burgos',
        'Cáceres',
        'Cádiz',
        'Cantabria',
        'Castelló/Castellón',
        'Ceuta',
        'Ciudad Real',
        'Córdoba',
        'Cuenca',
        'Girona/Gerona',
        'Gipuzkoa/Guipúzcoa',
        'Granada',
        'Guadalajara',
        'Huelva',
        'Huesca',
        'Illes Balears/Islas Baleares',
        'Jaén',
        'La Rioja',
        'Las Palmas',
        'León',
        'Lleida/Lérida',
        'Lugo',
        'Madrid',
        'Málaga',
        'Melilla',
        'Murcia',
        'Navarra',
        'Ourense/Orense',
        'Palencia',
        'Pontevedra',
        'Salamanca',
        'Santa Cruz de Tenerife',
        'Segovia',
        'Sevilla',
        'Soria',
        'Tarragona',
        'Teruel',
        'Toledo',
        'València/Valencia',
        'Valladolid',
        'Zamora',
        'Zaragoza',
    );

    public static function isValid( string $value ): bool {
        return in_array( $value, self::ALL, true );
    }
}
