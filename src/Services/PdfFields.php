<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

/**
 * AcroForm field-name constants for both templates.
 *
 * Names match `pdftk <template> dump_data_fields_utf8` output verbatim,
 * including spaces, diacritics and the `_2` suffixes used for duplicated
 * signer blocks. Full context in docs/decisions/001-campos-acroform.md.
 */
final class PdfFields {

    // -- Shared (both templates) --

    public const NIF                 = 'NIF';
    public const NOMBRE              = 'Nombre';
    public const DIRECCION_VIA       = 'Direccion_nombre';
    public const DIRECCION_NUMERO    = 'Direccion_numero';
    public const DIRECCION_LETRA     = 'Direccion_letra';
    public const DIRECCION_ESCALERA  = 'Direccion_escalera';
    public const DIRECCION_PISO      = 'Direccion_piso';
    public const DIRECCION_PUERTA    = 'Direccion_puerta';
    public const MUNICIPIO           = 'Municipio';
    public const PROVINCIA           = 'Provincia';
    public const CODIGO_POSTAL       = 'Codigo_postal';
    public const TELEFONO            = 'Telefono';
    public const MOVIL               = 'Movil';
    public const EMAIL               = 'Email';
    public const OBJETO              = 'Objeto';
    public const FDO                 = 'Fdo';
    public const FDO_2               = 'Fdo_2';

    // -- solicitud-espacios-aldealab.pdf specific --

    public const SALA     = 'Sala';
    public const FECHAS   = 'Fechas';
    public const HORAS    = 'Horas';
    public const FAX      = 'Fax';
    public const CACERES  = 'Cáceres';
    public const CACERES_2 = 'Cáceres_2';

    // -- solicitud-cpa.pdf specific (sub-espacios) --

    public const PLATOTV                 = 'Platotv';
    public const PLATOTV_FECHAS          = 'Fechas_platotv';
    public const PLATOTV_HORAS           = 'Horas_platotv';

    public const SALACONTROL             = 'Salacontrol';
    public const SALACONTROL_FECHAS      = 'Fechas_salacontrol';
    public const SALACONTROL_HORAS       = 'Horas_salacontrol';

    public const TVLAB                   = 'Tvlab';
    public const TVLAB_FECHAS            = 'Fechas_tvlab';
    public const TVLAB_HORAS             = 'Horas_tvlab';

    public const EQUIPOSMOVILES          = 'Equiposmoviles';
    public const EQUIPOSMOVILES_FECHAS   = 'Fechas_equiposmoviles';
    public const EQUIPOSMOVILES_HORAS    = 'Horas_equiposmoviles';

    // -- CPA signer block (duplicated across 2 pages) --

    public const CPA_NIF_CIF    = 'con NIF CIF';
    public const CPA_NIF_CIF_2  = 'con NIF CIF_2';
    public const CPA_INTERESADA   = 'interesada en alojarse en el edificio municipal';
    public const CPA_INTERESADA_2 = 'interesada en alojarse en el edificio municipal_2';

    /**
     * CPA sub-space slug (from the sala post_name) → field name prefix map.
     * Matches by case-insensitive substring; see PdfGenerator::resolveCpaPrefix.
     *
     * @return array<string, array{checkbox:string, fechas:string, horas:string}>
     */
    public static function cpaPrefixes(): array {
        return array(
            'plato'     => array(
                'checkbox' => self::PLATOTV,
                'fechas'   => self::PLATOTV_FECHAS,
                'horas'    => self::PLATOTV_HORAS,
            ),
            'control'   => array(
                'checkbox' => self::SALACONTROL,
                'fechas'   => self::SALACONTROL_FECHAS,
                'horas'    => self::SALACONTROL_HORAS,
            ),
            'tv-lab'    => array(
                'checkbox' => self::TVLAB,
                'fechas'   => self::TVLAB_FECHAS,
                'horas'    => self::TVLAB_HORAS,
            ),
            'tvlab'     => array(
                'checkbox' => self::TVLAB,
                'fechas'   => self::TVLAB_FECHAS,
                'horas'    => self::TVLAB_HORAS,
            ),
            'equipos'   => array(
                'checkbox' => self::EQUIPOSMOVILES,
                'fechas'   => self::EQUIPOSMOVILES_FECHAS,
                'horas'    => self::EQUIPOSMOVILES_HORAS,
            ),
        );
    }
}
