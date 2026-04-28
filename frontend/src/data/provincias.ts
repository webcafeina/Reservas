/**
 * Canonical list of Spanish provinces + Ceuta + Melilla. Bilingual
 * names use the regional/Castilian order separated by a slash. This
 * is exactly what gets rendered in the SelectField, what the user
 * picks, and what's persisted in `user_profiles.provincia`.
 *
 * Backend mirror: `src/Support/Provincias.php`. Keep both in sync.
 */

export const PROVINCIAS = [
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
] as const;

export type Provincia = (typeof PROVINCIAS)[number];

export function isProvincia(value: string): boolean {
    return (PROVINCIAS as readonly string[]).includes(value);
}
