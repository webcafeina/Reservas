# ADR 002 — Motor de relleno de PDFs

- Estado: Aceptado
- Fecha: 2026-04-20
- Autor: Equipo Reservas Aldealab

## Contexto

Necesitamos rellenar los campos AcroForm de dos PDFs oficiales (`solicitud-cpa.pdf`
y `solicitud-espacios-aldealab.pdf`) con los datos de la reserva, y enviar el
resultado por email.

El briefing sugería evaluar `setasign/fpdi` como evolución moderna de FPDM
(lo que usaba el legacy). Tras revisar ambas opciones y el resto del
ecosistema PHP:

### Opciones evaluadas

1. **FPDM** (legacy) — librería monofichero, sin mantenimiento desde hace años,
   no está en Packagist con licencia clara. Funcionaba con los PDFs legacy
   pero arrastra bugs conocidos (codificación UTF-8, campos con `_2` duplicados).
2. **setasign/fpdi** + **setasign/fpdf** — excelente para IMPORTAR y
   superponer contenido en PDFs, pero no rellena AcroForms nativamente. Para
   lograrlo habría que conocer las coordenadas `X/Y` exactas de cada campo en
   la plantilla y renderizar texto encima. Es frágil: un rediseño de la
   plantilla por parte del Ayuntamiento rompe todos los layouts.
3. **setasign/FPDI-FillableForm** — soporte oficial de AcroForms, pero
   **licencia comercial**. Descartado.
4. **mikehaertl/php-pdftk** — wrapper PHP del binario `pdftk` (o `pdftk-java`).
   Rellena AcroForms con nombres de campo, ignora coordenadas, preserva la
   plantilla bit-a-bit. Licencia MIT. Requiere el binario `pdftk` instalado
   en el servidor.

## Decisión

Usamos **`mikehaertl/php-pdftk`** como `PdfFillerInterface` por defecto.

**Razones:**

- Es la única vía fiable y libre de licencias para rellenar AcroForms por
  nombre de campo en PHP.
- Preserva el diseño original del Ayuntamiento — cualquier cambio visual en
  la plantilla PDF se propaga automáticamente sin tocar código.
- Es el mismo motor que usan herramientas como LibreOffice en modo headless
  y que recomienda Adobe en su guía de scripting para AcroForms.

**Contrapartida:** exige tener instalado el binario `pdftk` o `pdftk-java` en
el servidor. La inmensa mayoría de hostings WordPress lo incluyen o pueden
instalarlo vía paquete de sistema (`apt install pdftk-java` en Debian/Ubuntu,
`brew install pdftk-java` en macOS dev).

## Implementación

- `\WebcafeinaReservas\Services\PdfFillerPdftk` implementa `PdfFillerInterface`.
  Detecta al construirse si el binario está disponible; si no, lanza una
  excepción clara al rellenar.
- `\WebcafeinaReservas\Services\PdfGenerator` expone `generate(templateFile,
  fields)` y elige plantilla según el flag `_es_cpa` de la sala.
- Los nombres exactos de los campos AcroForm están en
  `\WebcafeinaReservas\Services\PdfFields` como constantes (una por campo),
  derivados de `docs/decisions/001-campos-acroform.md`.
- El README incluirá instrucciones de instalación del binario + verificación
  (`pdftk --version`).

## Fallback

Si el binario no está disponible en el servidor:
- El email al usuario y al admin se envía igualmente, con una nota que
  indica "no se pudo generar el PDF de solicitud, contacte con el
  administrador para recibirla por otro canal".
- Se loguea un error en `reservas_email_log` con tipo `pdf-error`.
- No se interrumpe la reserva — los datos ya están guardados, el PDF es
  un anexo, no la reserva en sí.

## Consecuencias

- Documentamos la dependencia del binario en la sección "Requisitos" del
  `README.md` y en el screen de activación del plugin.
- Si en el futuro aparece una alternativa pura-PHP que rellene AcroForms
  con licencia libre y mantenida, basta con añadir una segunda implementación
  de `PdfFillerInterface` y cambiar el factory.
