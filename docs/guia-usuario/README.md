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
| `01-paso-aforo.webp`               | Filtro de aforo y servicios marcados                                          |
| `02-paso-sala.webp`                | Catálogo de salas con una seleccionada                                        |
| `03-paso-fechas.webp`              | Conmutador "Fecha única / Recurrente" con un día elegido                      |
| `04-paso-recurrencia.webp`         | Patrón recurrente configurado y calendario con la serie                       |
| `05-paso-horario.webp`             | Hora inicio y fin rellenadas                                                  |
| `06-paso-perfil-logueado.webp`     | Paso 6 con campos prerellenados (usuario logueado)                            |
| `06-paso-perfil-invitado.webp`     | Paso 6 con campos vacíos (opcional, usuario invitado)                         |
| `07-paso-resumen.webp`             | Resumen completo + Cloudflare Turnstile                                       |
| `08-paso-exito.webp`               | Pantalla de éxito con número de reserva y descarga `.ics`                     |
| `09-email-confirmacion.webp`       | Email de confirmación recibido en una bandeja de entrada                      |
| `10-pdf-rellenado.webp`            | PDF adjunto abierto con los datos de la reserva rellenados                    |

**Resolución sugerida**: 1600 × … píxeles (alto libre). Formato WebP
con calidad 80-90 (compromiso típico para capturas de UI). Si las haces
más grandes no pasa nada, el documento las redimensiona; si las haces
mucho más pequeñas se verán pixeladas en A4.

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

## Acceder desde la web

El plugin ofrece dos formas de servir la guía. Elige la que mejor
encaje con cómo quieres mostrarla:

### Opción A — URL pública dedicada (recomendada)

```
https://<tu-sitio>/?reservas_guia=1
```

Esta URL devuelve la guía como **página completa, sin pasar por el
tema de WordPress**: no aparece la barra de admin, ni el header del
tema, ni el título de página, ni el footer. Solo la guía a pantalla
completa, con toda la maquetación cuidada del HTML.

Para enlazarla:

- Pégala directamente en el menú principal de WP (Apariencia →
  Menús → Enlace personalizado).
- O ponla como botón en el formulario de reservas, en un email, etc.

### Opción B — Shortcode dentro de una página WP

Si prefieres que la guía viva dentro de una página WordPress
estándar (con el header/footer del tema alrededor):

1. Crea una página: wp-admin → Páginas → Añadir nueva, llámala por
   ejemplo "Guía de uso de reservas".
2. Pega el shortcode en el contenido:
   ```
   [reservas_aldealab_guia]
   ```
3. Publica.

La guía se incrusta en un `<iframe>` con sus estilos aislados del
tema. Útil si quieres mantener el header/footer/menú de tu sitio
alrededor del documento.

> **Nota**: con esta opción aparecerán encima de la guía la barra de
> admin (si estás logueado) y el título de la página, porque son
> elementos que el tema añade fuera del iframe. Si quieres una
> visualización 100% limpia, usa la Opción A.

Cualquier actualización del HTML (cambios de texto, capturas nuevas)
se refleja al instante en ambas opciones tras un release.

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
