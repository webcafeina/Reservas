# Reservas Aldealab

Plugin de WordPress para gestionar reservas de salas en el edificio Aldealab
(Cáceres). Sustituye al legacy "Reservas de salas" de Webcafeína y es
**totalmente autónomo**: tablas propias, API REST propia, panel admin propio
y SPA pública en React. No depende de Motopress Hotel Booking ni de ningún
otro plugin de reservas.

Soporta reservas únicas y recurrentes (RFC 5545 RRULE), protege el formulario
público con Cloudflare Turnstile, genera PDFs oficiales del Ayuntamiento de
Cáceres rellenados automáticamente y envía las notificaciones por email al
solicitante y al gestor del espacio.

---

## 1. Resumen

- **8 pasos** de formulario (aforo → servicios → sala → fecha única o recurrente → horario → datos → resumen → éxito).
- **Recurrencias RFC 5545** con calendario visual y exclusión de fechas por clic.
- **Dos plantillas PDF** (Aldealab genérica / CPA específica) rellenadas vía `pdftk`.
- **Panel admin** React con dashboard, listado filtrable, editor y ajustes.
- **Multiidioma** (text domain `reservas-aldealab`).
- **Testado**: PHPUnit + Vitest. Cobertura crítica en `RecurrenceExpander`, `AvailabilityChecker` y `buildRrule`.

---

## 2. Requisitos

### En el servidor de producción

| Componente | Versión mínima | Notas |
|---|---|---|
| PHP | 7.4 | Testado en CI contra 7.4, 8.0, 8.1, 8.2. |
| WordPress | 6.0 | Probado hasta la última versión estable. |
| MySQL / MariaDB | 5.7 / 10.3 | `dbDelta` crea las 5 tablas del plugin. |
| `pdftk` o `pdftk-java` | cualquiera reciente | **Obligatorio** para generar los PDFs. Ver §9 si tu hosting no lo tiene. |
| Extensiones PHP | `json`, `pdo`, `mysqli` | Presentes por defecto en casi todos los hostings WP. |

### Solo para desarrollo

- Node.js ≥ 18 (para compilar el frontend con Vite).
- Composer 2.x.
- `wp-cli` (opcional, para regenerar el `.pot`).

---

## 3. Instalación en producción

1. **Descarga** el ZIP de la última release desde la página de Releases del repositorio: `reservas-aldealab-vX.Y.Z.zip`.
2. En WordPress, ve a **Plugins → Añadir nuevo → Subir plugin**, selecciona el ZIP y pulsa "Instalar ahora".
3. Pulsa **Activar plugin**. Durante la activación el plugin:
    - Comprueba que PHP ≥ 7.4 y WP ≥ 6.0. Si no, aborta y deja un mensaje claro.
    - Crea 5 tablas en tu base de datos (prefijo `{wp_prefix}reservas_`):
      - `reservas_bookings`, `reservas_booking_dates`, `reservas_user_profiles`, `reservas_booking_cpa_items`, `reservas_email_log`.
    - Crea 2 roles: `usuario_alojado` y `reservas_manager`.
    - Añade la capacidad `manage_reservas` al rol administrador.
4. **Verifica** en phpMyAdmin o similar que las 5 tablas existen. Si no, mira §9 Troubleshooting.

### Cómo verificar `pdftk`

Desde SSH en el servidor:

```bash
pdftk --version
# o si has instalado el fork Java:
pdftk-java --version
```

Si no aparece nada, instálalo:

```bash
# Debian / Ubuntu:
sudo apt install pdftk-java

# CentOS / RHEL:
sudo dnf install pdftk

# macOS (dev):
brew install pdftk-java
```

Si tu hosting gestionado no permite instalar binarios, lee §9 para las alternativas.

---

## 4. Configuración post-instalación

**El plugin no funciona hasta completar estos pasos.** Hazlos en orden.

### 4.1 Crear las salas (CPT)

1. Menú **Reservas → Salas → Añadir nueva**.
2. Para cada sala rellena:
    - Título (nombre visible: p. ej. "Sala de Reuniones A").
    - Contenido y extracto (descripción pública).
    - **Imagen destacada** (se muestra en el catálogo del formulario).
    - Metabox "Detalles de la sala":
      - Aforo mínimo y máximo.
      - "Disponible para reserva" (marcado).
      - "Espacio CPA" — **solo** para las salas del Centro de Producciones Audiovisuales (Plató TV, Sala de Control, TV Lab, Equipos móviles). Para una sala ordinaria, déjalo desmarcado.
3. Crea las taxonomías **Edificios** (jerárquica) y **Servicios** (plana) según necesites, y asígnalas a cada sala.

> **Importante sobre CPA**: Plató TV, Sala de Control, TV Lab y Equipos móviles son cada uno una **sala independiente** con "Espacio CPA" marcado. El plugin detecta el sub-espacio por el slug / título para rellenar la columna correcta del PDF (busca substrings `plato`, `control`, `tv-lab` / `tvlab`, `equipos`). Usa slugs como `plato-tv`, `sala-de-control`, `tv-lab`, `equipos-moviles` para que la detección funcione sin configuración extra.

### 4.2 Ajustes del plugin

Ve a **Reservas → Ajustes**.

#### Emails del administrador

Lista de direcciones separadas por coma. Cada reserva nueva se notifica a todas. Deja al menos una.

#### Cloudflare Turnstile

1. Abre <https://dash.cloudflare.com/?to=/:account/turnstile> e inicia sesión.
2. Pulsa **"Add site"**.
    - Widget mode: **Managed** (recomendado).
    - Hostname: añade tu dominio.
    - Pre-clearance: no es necesario.
3. Copia la **Site key** (pública) y la **Secret key** (privada).
4. Pégalas en el formulario de ajustes del plugin y guarda.

> Si dejas ambas vacías, el plugin **omite la verificación Turnstile** y acepta todas las reservas. Esto es aceptable para pruebas en staging pero NO en producción pública.

#### Sede Electrónica

- URL general: `https://sede.caceres.es/` (valor por defecto).
- URL directa al trámite: opcional. Si la completas, se enlaza en el email de instrucciones al usuario.

#### Infraestructura

- **Ruta a `pdftk`**: déjalo vacío si está en el `PATH`. Si tu hosting requiere una ruta absoluta (p. ej. `/usr/local/bin/pdftk-java`), ponla aquí.
- **URL dev de Vite**: vacío en producción. Solo se usa en desarrollo local.

#### Textos de email

Dos cajas opcionales para personalizar la introducción del email al usuario y al administrador.

#### Desinstalación

"Eliminar todos los datos al desinstalar el plugin" → déjalo desmarcado salvo que sepas lo que haces. Con la casilla marcada, al borrar el plugin desde WordPress se eliminan las 5 tablas, los roles y los ajustes **sin posibilidad de recuperación**.

### 4.3 Insertar el formulario en una página

Crea (o edita) una página pública, "Reservas":

- **Editor Gutenberg**: añade un bloque "Shortcode" con el contenido:

    ```text
    [reservas_aldealab_formulario]
    ```

- **Editor clásico**: pega el shortcode tal cual en el contenido.

Publica la página. Cuando un visitante la abra, la SPA de React se monta automáticamente.

### 4.4 Prueba de humo

1. Abre la página pública en una ventana privada (sin sesión).
2. Completa una reserva de prueba con una sala cualquiera.
3. Verifica que recibes el email de confirmación (revisa la dirección del perfil).
4. Verifica que el administrador recibe su notificación.
5. Entra al panel (**Reservas → Panel**) y comprueba que la reserva aparece con estado `pendiente`.

---

## 5. Desarrollo local

### Requisitos

- PHP 7.4+ (ideal: la misma versión del servidor de producción).
- Composer 2.x.
- Node 18+.
- WordPress local — recomendamos [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) o [Local](https://localwp.com/).

### Pasos

```bash
# 1. Clonar y entrar
git clone <url> reservas-aldealab
cd reservas-aldealab

# 2. Dependencias PHP
composer install

# 3. Dependencias JS
npm install

# 4. En una terminal: Vite dev server (SPA pública)
npm run dev            # escucha en http://localhost:5173

# 5. En otra terminal: Vite dev server del admin (mismo puerto, mismo proceso — la config
#    ya incluye ambos entries).

# 6. Arranca WordPress local.
#    Si usas wp-env:
npx wp-env start
#    Enlaza el plugin al contenedor:
#    (wp-env ya monta el directorio actual; solo asegúrate de copiar o symlink a
#    wp-content/plugins/reservas-aldealab si tu setup no lo hace).

# 7. Activa el plugin en wp-admin.

# 8. En Reservas → Ajustes → "URL dev de Vite", pon:
#    http://localhost:5173
#    A partir de ese momento, la SPA se sirve directamente desde Vite con HMR.
```

### Scripts disponibles

```bash
# PHP
composer run lint         # PHPCS con WordPress Coding Standards
composer run lint:fix     # Autofix con phpcbf
composer run stan         # PHPStan nivel 6
composer run test         # PHPUnit todo
composer run test:unit    # Solo suite Unit

# JS
npm run lint              # ESLint
npm run lint:fix          # ESLint --fix
npm run typecheck         # tsc --noEmit
npm test                  # Vitest run
npm run test:watch        # Vitest watch mode
npm run format            # Prettier --write
npm run build             # Build de producción a assets/dist/
```

---

## 6. Build de producción

Para empaquetar manualmente (normalmente lo hace el workflow de release):

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
# Copia a un directorio clean los ficheros que van al zip:
rsync -a \
    --include='reservas-aldealab.php' --include='uninstall.php' \
    --include='README.md' --include='CHANGELOG.md' --include='LICENSE' \
    --include='composer.json' --include='composer.lock' \
    --include='src/***' --include='vendor/***' \
    --include='assets/pdf-templates/***' --include='assets/email/***' \
    --include='assets/dist/***' --include='languages/***' \
    --exclude='*' ./ /tmp/reservas-aldealab/
cd /tmp && zip -r reservas-aldealab-vX.Y.Z.zip reservas-aldealab
```

El workflow `.github/workflows/release.yml` ejecuta exactamente estos pasos al crear un tag `v*`.

---

## 7. Migración desde el plugin legacy

El antiguo plugin "Reservas de salas" de Webcafeína (v1.1.21) debe quedar **desactivado pero NO eliminado** durante al menos 30 días tras el despliegue del nuevo. Esto protege las reservas históricas por si hay que volver atrás.

### Datos que deja atrás el legacy

- Tablas `{prefix}motopress_` de Motopress Hotel Booking: no las toques; siguen siendo del plugin base de Motopress.
- Opciones con prefijo `webcafeina_reservas_` o similares: puedes auditarlas en phpMyAdmin. El nuevo plugin no las lee ni las toca.
- Archivos PDF temporales en `/wp-content/uploads/temp/`: ver §9.

### Qué hacer tras 30 días sin incidencias

1. Haz un volcado de seguridad de la base de datos.
2. En **Plugins**, elimina el plugin legacy (no solo desactivar — "Eliminar").
3. WP dispara el `uninstall.php` del legacy, que limpia sus opciones.
4. Las tablas de Motopress permanecen — si ya no usas Motopress, desactívalo también.

El nuevo plugin **no importa** automáticamente las reservas históricas del legacy. Si necesitas esa migración de datos, pide un script a medida: el schema legacy tenía funciones de recurrencia duplicadas en 4 sitios y no es una migración 1-a-1.

---

## 8. Multiidioma

El text domain es `reservas-aldealab` y el idioma base es español (`es_ES`).

### Regenerar el `.pot`

```bash
# Requiere wp-cli instalado.
bin/make-pot.sh
# Escribe languages/reservas-aldealab.pot
```

### Añadir una traducción

1. Duplica el `.pot` como `.po` con el código de idioma:

    ```bash
    cp languages/reservas-aldealab.pot languages/reservas-aldealab-en_US.po
    ```

2. Edita con [Poedit](https://poedit.net/) o similar.
3. Poedit al guardar genera también el `.mo` compilado.
4. Sube ambos archivos al servidor.

WordPress detecta automáticamente los `.mo` que estén en `languages/` cuando la locale del sitio coincide.

---

## 9. Troubleshooting

### El formulario no carga (pantalla en blanco)

1. Abre la consola del navegador (F12).
2. Busca errores de carga de JS o CSS.
3. Si ves "404" para `assets/dist/...`:
    - Verifica que el plugin venga del ZIP de release (no del repositorio sin compilar).
    - Si es un clone, ejecuta `npm run build`.
4. Si la consola muestra `window.ReservasAldealab is undefined`:
    - El shortcode no está en la página actual.

### Turnstile no aparece

- Comprueba que la Site key está bien copiada en Ajustes.
- Confirma que el dominio de la página coincide con el hostname configurado en Cloudflare.
- En la consola del navegador, busca errores `challenges.cloudflare.com`. Si hay bloqueo por CSP o por un plugin de privacidad, añade `challenges.cloudflare.com` a la allowlist.

### El PDF no se adjunta al email

1. En **Reservas → Panel**, abre la reserva y mira `reservas_email_log` (vía phpMyAdmin):
    - Si hay una entrada con `tipo='pdf-error'`, la columna `error` explica la causa.
2. Causas más comunes:
    - **`pdftk` no encontrado**: instálalo (§2) o pon la ruta absoluta en Ajustes.
    - **Permisos de escritura**: WordPress debe poder escribir en su directorio temporal (`get_temp_dir()`).
    - **Plantilla PDF corrupta**: no sobreescribas los PDFs de `assets/pdf-templates/` sin replicar la tabla AcroForm. Si regeneras las plantillas desde Word/Acrobat, revisa que los nombres de campo coincidan con los de `docs/decisions/001-campos-acroform.md`.

### Los emails no llegan

- WordPress usa `wp_mail()`, que por defecto intenta SMTP del servidor. Muchos hostings lo tienen deshabilitado.
- Instala un plugin como **WP Mail SMTP** y configúralo con SendGrid, Mailgun o el SMTP que uses.
- Después, crea una reserva de prueba y revisa `reservas_email_log`: `estado` debe ser `enviado`.

### Nonce expired / 403 en la página pública

- Suele pasar tras dejar el formulario abierto varias horas. El nonce caduca a las 12 h.
- Recarga la página.

### Dos usuarios reservaron el mismo hueco

- No debería ocurrir: usamos `SELECT ... FOR UPDATE` en la comprobación previa.
- Si aparece, verifica que tu MySQL/MariaDB soporta InnoDB con bloqueos a nivel de fila (lo habitual). Las tablas son InnoDB por defecto al usar `dbDelta`.

### El legacy sigue enviando emails duplicados

- Desactiva el plugin legacy (**Plugins → Desactivar**). No lo elimines todavía — ver §7.

---

## 10. Testing y CI

### Local

```bash
composer run test     # PHPUnit unit + integration (las integration requieren WP-Browser)
npm test              # Vitest
```

### Cobertura crítica

- `RecurrenceExpander`: 12 tests. Variantes RFC 5545 de diaria/semanal/mensual/anual.
- `AvailabilityChecker`: 13 tests. Conflicto SQL, dedupe, `FOR UPDATE`, transacciones.
- `BookingService`: 8 tests. Orquestación con Turnstile + rollback + async.
- `buildRrule` / `expandOccurrences` (frontend): 13 + 12 tests.
- `bookingStore` (frontend): 7 tests de persistencia en sessionStorage.

### CI automático

`.github/workflows/ci.yml` corre en cada push a `main` y `develop` + en cada pull request:

- **PHP matrix** 7.4 / 8.0 / 8.1 / 8.2 × WP 6.0 / latest.
- Lint PHP (PHPCS WPCS) + PHPStan nivel 6 + PHPUnit.
- Lint JS (ESLint + Prettier) + typecheck + Vitest + Vite build.

### Release automático

`.github/workflows/release.yml` se dispara al crear un tag `v*`:

```bash
git tag v0.1.0
git push origin v0.1.0
```

Empaqueta el ZIP de producción y lo publica como GitHub Release.

---

## 11. Roadmap y limitaciones conocidas

### Ya entregado en v0.2

- **Uploader de plantillas PDF** desde **Reservas → Ajustes → Plantillas PDF**. Cada plantilla indica si está en modo "empaquetada" o "personalizada", muestra la fecha de subida y ofrece un botón para revertir. Se valida el tipo MIME por magic bytes (`%PDF`) y se limita a 5 MB. Los ficheros se guardan en `wp-content/uploads/reservas-aldealab/pdf-templates/` con `.htaccess deny from all`, por lo que sobreviven a actualizaciones del plugin.
- **Exportación iCal** (`.ics`) por reserva: botón en el paso final del formulario público y enlace en el email de confirmación. Endpoint `GET /bookings/{uuid}/ical` protegido por UUID.
- **Notificaciones SMS opcionales** con `TwilioSmsProvider` listo para usar. Se activa desde **Ajustes → Notificaciones SMS** eligiendo "Twilio" y configurando SID + token + número emisor. Si se deja en "Ninguno" el plugin no envía SMS. Extender a otros proveedores (Vonage, MessageBird…) requiere implementar `SmsProviderInterface`.
- **Panel de estadísticas con rangos personalizados**: `from` / `to` en `GET /admin/stats`, selector de fechas en el dashboard con presets rápidos.
- **Exportación CSV de reservas** vía `GET /admin/bookings/export`, respeta los filtros activos, BOM UTF-8 para que Excel abra bien el encoding. Botones desde Dashboard y desde el listado.

### Limitaciones actuales

- **Importación de reservas del plugin legacy** no existe. Hacer una migración requiere un script a medida.
- **Rate limiter sin soporte de proxy** — usa `REMOTE_ADDR`. Si el sitio está detrás de Cloudflare u otro proxy y todos los visitantes comparten IP, el rate limiter limita a todos juntos. Añadir soporte de `CF-Connecting-IP` o `X-Forwarded-For` es trivial si hace falta; pidelo.
- **Tests de integración REST** (WP-Browser / Codeception) están pendientes. La cobertura actual está en unit tests con mocks.
- **Validación profunda de AcroForm** al subir plantillas PDF: hoy se valida el tipo MIME y el tamaño, pero no que los nombres de campo coincidan con los esperados por `PdfFields`. Si el Ayuntamiento publica una plantilla con campos renombrados, pdftk los ignorará silenciosamente. Queda como mejora futura.

### Próximos hitos sugeridos

- v0.3: Migrador automático de reservas históricas del plugin legacy.
- v0.4: Validación profunda de AcroForm en el uploader (comparar campos con los de `PdfFields`).
- v0.5: Soporte nativo de proxy inverso en el rate limiter y tests de integración REST con WP-Browser.

---

## Licencia

GPL-2.0-or-later. Ver `LICENSE`.

## Autor

[Webcafeína](https://webcafeina.com) — `info@webcafeina.com`

## Agradecimientos

- `simshaun/recurr` para la expansión de reglas RRULE en backend.
- `mikehaertl/php-pdftk` como wrapper del binario `pdftk`.
- WordPress Coding Standards, PHPStan y el ecosistema de herramientas que mantienen la calidad del código.
