# ADR 001 — Campos AcroForm de las plantillas PDF legacy

- Estado: Aceptado
- Fecha: 2026-04-20
- Autor: Equipo Reservas Aldealab

## Contexto

El plugin debe rellenar dos plantillas PDF heredadas del plugin legacy y enviarlas al
solicitante junto con el email de confirmación cuando aplica (ver lógica en
`EmailNotifier` y `PdfGenerator`). Los archivos se han migrado con estos nombres:

- `assets/pdf-templates/solicitud-cpa.pdf` (41 campos AcroForm)
- `assets/pdf-templates/solicitud-espacios-aldealab.pdf` (34 campos AcroForm)

Las versiones `*_compressed.pdf` del repositorio de assets originales se han
descartado porque la recompresión rompe con frecuencia la tabla AcroForm.

## Nombres de campo (tal cual vienen en el PDF)

Los nombres se mantienen exactamente como los genera Acrobat — incluyendo los
sufijos `_2`, `_3`, espacios, y entidades HTML que aparecen al exportar con
`pdftk dump_data_fields` (ej. `&#211;` para `Ó`). El código PHP rellena estos
campos usando las constantes expuestas en `WebcafeinaReservas\Services\PdfFields`.

### solicitud-cpa.pdf

**Datos del solicitante (personales):**
- `NIF`
- `Nombre` — apellidos + nombre o razón social, separados por espacio
- `Direccion_nombre` — calle/plaza/avenida
- `Direccion_numero`
- `Direccion_letra`
- `Direccion_escalera`
- `Direccion_piso`
- `Direccion_puerta`
- `Municipio`
- `Provincia`
- `Codigo_postal`
- `Telefono`
- `Movil`
- `Email`

**Subespacios CPA (uno por sala-hija; el booking marca solo los relevantes):**
- Plató TV: `Platotv`, `Fechas_platotv`, `Horas_platotv`
- Sala de Control: `Salacontrol`, `Fechas_salacontrol`, `Horas_salacontrol`
- TV Lab: `Tvlab`, `Fechas_tvlab`, `Horas_tvlab`
- Equipos móviles: `Equiposmoviles`, `Fechas_equiposmoviles`, `Horas_equiposmoviles`

**Objeto de la reserva:**
- `Objeto`

**Bloque de firma (duplicado por el PDF en dos hojas; mantener paralelos):**
- Hoja 1: `Fdo`, `con NIF CIF`, `interesada en alojarse en el edificio municipal`, `undefined`
- Hoja 2: `Fdo_2`, `con NIF CIF_2`, `interesada en alojarse en el edificio municipal_2`, `undefined_2`, `undefined_3`

**Sección administrativa (NO rellenar desde el plugin — la firma la
Administración):**
- `DOCUMENTACIÓN A PRESENTAR Se presenta a rellenar por la AdministraciónRow1` .. `Row4`
- `AUTORIZACIONES DEL INTERESADO PARA ACCESO TELEMÁTICO A DATOS RELATIVOS AL CUMPLIMIENTO DE OBLIGACIONES CON AEAT y TGSS`

### solicitud-espacios-aldealab.pdf

**Datos del solicitante:**
- `NIF`, `Nombre`
- `Direccion_nombre`, `Direccion_numero`, `Direccion_letra`, `Direccion_escalera`, `Direccion_piso`, `Direccion_puerta`
- `Municipio`, `Provincia`, `Codigo_postal`
- `Telefono`, `Movil`, `Fax`, `Email`

**Reserva:**
- `Sala` — nombre legible de la sala
- `Objeto`
- `Fechas` — resumen textual (renderizado por `RecurrenceExpander::toHumanString`)
- `Horas` — `HH:MM - HH:MM`

**Firma (duplicada hoja 1 / hoja 2):**
- `Cáceres`, `Fdo`
- `Cáceres_2`, `Fdo_2`

**Sección administrativa (NO rellenar):**
- `DILIGENCIA para hacer constar que consultado el Calendario de Reservas de`
- `OBSERVACIONES 1` .. `OBSERVACIONES 5` (y sus `_2`)

## Decisión

1. Los nombres exactos de los campos se exponen como constantes PHP en
   `WebcafeinaReservas\Services\PdfFields` para evitar typos y facilitar
   refactors si algún día se regenera la plantilla.
2. Se rellenan los campos del solicitante, subespacios (solo CPA), objeto,
   firmas y fechas. La sección administrativa queda **vacía** — la rellena el
   Ayuntamiento tras recibir la solicitud vía Sede Electrónica.
3. Las firmas duplicadas (`Fdo` / `Fdo_2`, etc.) se rellenan con el mismo valor
   para que ambas copias del PDF muestren la información.
4. Los nombres con caracteres especiales (`Cáceres`, los que tienen espacios,
   los que vienen como entidades HTML al hacer `dump_data_fields`) se manejan
   como strings UTF-8 en PHP; FPDI los resuelve correctamente siempre que el
   archivo fuente esté codificado en UTF-8.

## Consecuencias

- Cualquier cambio en las plantillas PDF (p. ej. un diseño nuevo del
  Ayuntamiento) requiere regenerar este ADR y actualizar `PdfFields`.
- Si el flujo de CPA cambiase y una reserva pudiese implicar **varios**
  subespacios simultáneos, el código ya lo contempla: rellena solo los bloques
  cuya sala aparezca en la reserva y deja el resto en blanco.
- Si se detecta un campo con nombre cambiado en una versión futura del PDF, se
  diagnostica con `pdftk <file> dump_data_fields` y se actualiza este documento
  antes de editar código.
