# Guía de usuario — para PDF y para acceder desde la web

Este directorio contiene la guía que se reparte a los trabajadores
de Aldealab para que aprendan a reservar salas desde el formulario
público. Tiene dos formas de uso:

1. **Imprimir a PDF** desde el navegador para distribución por email
   o impresión.
2. **Embeberla en una página WordPress** con el shortcode
   `[reservas_aldealab_guia]` para que esté disponible en la web.

## Archivos

```
docs/guia-usuario/
├── guia-aldealab.html        ← documento principal (autocontenido)
├── README.md                  ← este archivo
└── assets/
    ├── logo-aldealab.png      ← cabecera de la portada
    └── capturas/              ← aquí van las capturas de pantalla
```

## Versión y fecha (automáticas)

El HTML lleva los marcadores `[VERSIÓN]` y `[FECHA]` en la portada.
**No hace falta tocarlos**: el workflow de release del plugin
(`release.yml`) ejecuta un `sed` antes de empaquetar el zip que los
sustituye por la versión real del tag y la fecha del despliegue. El
HTML que viaja dentro del zip distribuido siempre lleva los valores
correctos.

Si abres el HTML desde el repositorio (rama `develop`, sin haber
pasado por release) verás los placeholders literales — eso solo te
ocurre en desarrollo, los usuarios finales nunca lo ven.

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

## Imprimir a PDF (modo offline)

1. Abre `guia-aldealab.html` en **Chrome**, **Firefox**, **Edge** o
   **Safari**.
2. Pulsa <kbd>Cmd/Ctrl + P</kbd>.
3. En el diálogo de impresión:
   - **Destino**: "Guardar como PDF".
   - **Tamaño**: A4.
   - **Orientación**: Vertical.
   - **Márgenes**: "Ninguno" o "Predeterminado". Cualquiera de los
     dos funciona porque el CSS ya define márgenes propios con
     `@page`.
   - **Gráficos de fondo**: activado (importante — controla los
     colores de los callouts y la portada).
4. Pulsa "Guardar". Listo.

## Acceder desde la web (modo embed)

El plugin registra el shortcode `[reservas_aldealab_guia]`. Para
publicar la guía en la web:

1. En wp-admin → Páginas → Añadir nueva, crea una página llamada
   por ejemplo "Guía de uso de reservas".
2. Pega en el contenido el shortcode:
   ```
   [reservas_aldealab_guia]
   ```
3. Publica la página y enlázala desde donde quieras (menú principal,
   pie, etc.).

La guía se carga dentro de un `<iframe>` con sus propios estilos, sin
interferir con el tema. Cualquier actualización del HTML (cambios de
texto, capturas nuevas) se refleja al instante en la web sin tener
que volver a editar la página.

## Mantener actualizado

Si cambia el formulario (un paso nuevo, un campo, etc.):

1. Edita `guia-aldealab.html` directamente con un editor de texto.
   La URL `https://aldealab.es/reservas/` y el email
   `aldealab@ayto-caceres.es` ya están literales en el HTML — si
   alguno cambia, basta con buscarlos y reemplazarlos.
2. Si añades capturas nuevas, súbelas a `assets/capturas/` con el
   patrón de nombre `NN-descripcion.png` y haz el `<figure>`
   correspondiente en el HTML.
3. Haz un release del plugin con bump de versión — el workflow se
   encarga de estampar la nueva versión y fecha en la portada
   automáticamente.
