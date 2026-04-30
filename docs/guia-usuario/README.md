# Guía de usuario — instrucciones para imprimirla a PDF

Este directorio contiene la guía que se reparte a los trabajadores
de Aldealab para que aprendan a reservar salas desde el formulario
público. El entregable está pensado para imprimirse a PDF desde el
navegador.

## Archivos

```
docs/guia-usuario/
├── guia-aldealab.html        ← documento principal
├── README.md                  ← este archivo
└── assets/
    ├── logo-aldealab.png      ← cabecera de la portada
    └── capturas/              ← aquí van las capturas de pantalla
```

## Antes de imprimir: rellenar los huecos

Abre `guia-aldealab.html` en un editor de texto y sustituye estos
marcadores (resaltados en amarillo cuando se ve el documento):

| Marcador                       | Sustitúyelo por…                                    |
| ------------------------------ | --------------------------------------------------- |
| `[INSERTAR URL]`               | URL pública de la página de reservas                |
| `[INSERTAR EMAIL DE CONTACTO]` | Email al que escribir si hay dudas (sale 3 veces)   |
| `[VERSIÓN]`                    | Versión del documento (ej. `1.0`) — solo en portada |
| `[FECHA]`                      | Fecha de la versión (ej. `Mayo 2026`) — solo en portada |

Truco rápido: usa "Buscar y reemplazar" en cualquier editor (VS Code,
Sublime, Notepad++, incluso TextEdit). Cada marcador es único, sin
ambigüedad.

## Capturas de pantalla

El documento tiene huecos preparados para 11 capturas. Mientras no
existan, cada hueco muestra un patrón rayado que indica "[Captura
pendiente]". En cuanto añadas el archivo correspondiente, aparece
en su sitio.

Crea las capturas con estos nombres exactos y mételos en
`assets/capturas/`:

| Archivo                            | Qué debe verse                                                                |
| ---------------------------------- | ----------------------------------------------------------------------------- |
| `01-paso-aforo.png`                | Filtro de aforo y servicios marcados                                          |
| `02-paso-sala.png`                 | Catálogo de salas con una seleccionada                                        |
| `03-paso-fechas.png`               | Conmutador "Fecha única / Recurrente" con un día elegido                      |
| `04-paso-recurrencia.png`          | Patrón recurrente configurado y calendario con la serie                       |
| `05-paso-horario.png`              | Hora inicio y fin rellenadas                                                  |
| `06-paso-perfil-logueado.png`      | Paso 6 con campos prerellenados (usuario logueado)                            |
| `06-paso-perfil-invitado.png`      | Paso 6 con campos vacíos (opcional, usuario invitado)                         |
| `07-paso-resumen.png`              | Resumen completo + Cloudflare Turnstile                                       |
| `08-paso-exito.png`                | Pantalla de éxito con número de reserva y descarga `.ics`                     |
| `09-email-confirmacion.png`        | Email de confirmación recibido en una bandeja de entrada                      |
| `10-email-aceptacion.png`          | Email "¡Reserva confirmada!" recibido                                         |

**Resolución sugerida**: 1600 × … píxeles (alto libre). Formato PNG.
Si las haces más grandes no pasa nada, el documento las redimensiona;
si las haces mucho más pequeñas se verán pixeladas en A4.

## Imprimir a PDF

1. Abre `guia-aldealab.html` en **Chrome**, **Firefox**, **Edge** o
   **Safari**.
2. Pulsa <kbd>Cmd/Ctrl + P</kbd>.
3. En el diálogo de impresión:
   - **Destino**: "Guardar como PDF".
   - **Tamaño**: A4.
   - **Orientación**: Vertical.
   - **Márgenes**: "Ninguno" o "Predeterminado". Cualquiera de los dos
     funciona porque el CSS ya define márgenes propios con `@page`.
   - **Gráficos de fondo**: activado (importante — controla los
     colores de los callouts y la portada).
4. Pulsa "Guardar". Listo.

## Mantener actualizado

Si en el futuro cambia el formulario (un paso nuevo, un campo, etc.),
actualiza el HTML directamente y vuelve a generar el PDF. El logo y
los colores ya quedan resueltos por el CSS embebido — no hace falta
tocar nada de estilo para versiones nuevas.
