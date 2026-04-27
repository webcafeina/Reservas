# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.3] — 2026-04-27

### Fixed

- **Fatal "Typed property must not be accessed before initialization"
  al confirmar reserva.** `Booking::$createdAt` y `Booking::$updatedAt`
  son propiedades typed nullable sin valor por defecto. El flujo de
  creación (`BookingService::create`) las dejaba sin inicializar — el
  INSERT en BD las puebla del lado de la base, pero el objeto en
  memoria nunca las recibe. Cuando `BookingsController` llamaba a
  `$result->booking->toArray()` para devolver el 201, PHP 7.4+ lanzaba
  el fatal y WordPress respondía con la página HTML "Ha habido un
  error crítico". La reserva quedaba creada y los emails llegaban
  porque el INSERT y el `wp_schedule_single_event` ya habían
  ejecutado, pero el usuario veía un error rojo confuso.

  Mismo patrón que el bug latente de `Booking::$notaAdmin` arreglado
  en v0.3.0 — ambas propiedades se quedaron por revisar. Ahora
  `createdAt` y `updatedAt` declaran `= null` por defecto en el
  modelo, así que nunca pueden volver a estar sin inicializar.

## [0.3.2] — 2026-04-27

### Fixed

- **Adjunto PDF en correos llegaba como `.tmp` en lugar de `.pdf`.**
  `EmailNotifier::tryGeneratePdfFile` usaba `wp_tempnam()`, que
  internamente arranca la extensión solicitada y fuerza `.tmp` al
  archivo de destino — el cliente de correo mostraba entonces un
  `.tmp` ilegible aunque por dentro era un PDF válido. Ahora se
  escribe directamente con `file_put_contents` en `get_temp_dir()`
  con un nombre significativo (`solicitud-aldealab-<id>-<rand>.pdf`
  o `solicitud-cpa-…` cuando la sala es CPA). La limpieza posterior
  (`@unlink` en el `finally` de `handleAsync`) sigue funcionando
  igual.

- **Falso positivo de "error de Cloudflare" al confirmar reserva.**
  El widget de Turnstile pasaba a estado rojo en el momento exacto
  de pulsar "Confirmar" porque Cloudflare reciclaba el challenge
  cuando el token se consumía. Visualmente parecía un fallo, pero el
  servidor verificaba el token (todavía válido) y creaba la reserva
  con normalidad. Ahora `Step7Resumen` desmonta el widget mientras
  la mutación está en vuelo — al usuario le aparece simplemente
  "Enviando…" hasta llegar a la pantalla de confirmación. Si el
  envío falla, el widget se remonta con un challenge fresco para
  reintentar.

## [0.3.1] — 2026-04-27

### Fixed

- **Compatibilidad con PHP 7.4 / 8.0 en `PdfTemplateStorage`.**
  Se reemplaza el literal octal `0o644` (sintaxis introducida en
  PHP 8.1) por `0644`. En servidores con PHP < 8.1 el archivo
  producía un `ParseError` al cargarse vía autoloader, lo que
  hacía que la pestaña "Plantillas PDF" del panel de admin
  devolviese un `500` con cuerpo vacío (el error de parseo ocurre
  antes de que WP pueda registrarlo en `debug.log`). Ambos
  literales tienen el mismo valor; solo cambia la sintaxis.

## [0.3.0] — 2026-04-21

### Added

- **Administradores pueden crear reservas manualmente desde el panel.**
  Nuevo endpoint `POST /reservas/v1/admin/bookings` (gated por
  `manage_reservas`) y una nueva página en el admin React (`#/bookings/new`)
  con un botón de entrada desde BookingsList → "+ Crear reserva". La
  reserva pasa por la misma `Services\BookingService::create` que el
  formulario público, así que aparece idéntica en stats, listado, CSV
  export y emails — no hay dos "mundos" de reservas.

  La página admin soporta todo el flujo: selector de sala (mismas
  `SalaCard` del público), fecha + horario, **recurrencia completa**
  (freq / intervalo / byweekday / end con vista previa del calendario
  de ocurrencias y exclusiones), datos del solicitante validados con
  la misma `profileValidation` Zod.

  Tres opciones específicas de admin:
  - **Estado inicial** seleccionable (por defecto `confirmada`; también
    `pendiente` o `cancelada`).
  - **Forzar aunque haya solapamiento** — checkbox que salta la
    comprobación de disponibilidad cuando el solape es intencional.
  - **No notificar por email** — silencia el hook asíncrono de
    notificaciones (útil si ya se ha avisado al usuario por otro
    canal).
  - Además: campo `nota_admin` se guarda en la reserva desde la misma
    página.

  Ni Turnstile ni rate-limit se aplican a este endpoint: el cap
  `manage_reservas` es el gate.

### Fixed

- **Bug latente: `Booking::$notaAdmin` nunca se inicializaba en
  `BookingService::create`.** Era una typed nullable property sin
  default; en PHP 7.4+ habría lanzado `Typed property must not be
  accessed before initialization` al leerla en `BookingRepository`.
  Ahora se asigna explícitamente desde `BookingRequest::$notaAdmin`
  (que el flujo público deja en `null`). El fix ya estaba requerido
  para pasar la `nota_admin` desde el form admin.

## [0.2.14] — 2026-04-21

### Changed

- **Sala card meta ("Aforo máx." and "Edificio") now stack vertically**
  instead of sharing a row. `SalaCard.module.css .meta` flipped from
  `flex-direction: row + flex-wrap: wrap` to `flex-direction: column`.
  Tighter `gap: --ra-space-2` keeps the block compact.

## [0.2.13] — 2026-04-21

### Changed

- **Buttons and field labels in the public form now render at weight
  600 (semibold)** instead of 500 (medium). Affects:
  `Button.module.css`, `Field.module.css .label`, `Step1Aforo.module.css
  .servicesLegend`, and `Step4Recurrencia.module.css .groupLabel`. The
  back/next/confirm buttons, form field labels, and the "Servicios
  necesarios" / "Días de la semana" / "Vista previa" legends all read
  heavier now.

## [0.2.12] — 2026-04-21

### Changed

- **Public form typography now uses Gotham** (the corporate font served
  by the host site) so the booking form blends with the surrounding
  WordPress page. Override lives in `global.css` scoped to `#reservas-app`
  only — we override `--ra-font-family-sans` to `'Gotham', 'Gotham A',
  'Gotham HTF', 'Inter', system-ui, …`. If the theme stops serving
  Gotham on a given page, the stack falls back cleanly. Admin panel
  keeps its original Inter/system stack.

## [0.2.11] — 2026-04-21

### Changed

- **Step 6 (Tus datos) validation alert moved above the form.** A long
  personal-data form hid the "Completa los campos marcados antes de
  continuar" warning at the very bottom — most users never saw it. The
  alert now renders right under the subtitle so it's visible without
  scrolling.
- **Recurrence calendar months now each render inside a subtle outlined
  card** (`border`, `border-radius`, padding). Previously each month was
  an unframed column of cells, so when multiple months rendered side by
  side it was hard to tell where one ended and the next began.
- **Step 7 (Resumen) shows the recurrence rule in human language**
  instead of the raw RFC 5545 string. New helper
  `humanizeRrule(input)` maps `{freq: WEEKLY, interval: 2, byweekday:
  ['TH']}` → *"Semanal, cada 2 semanas, los jueves"*. The RRULE string
  is still what gets sent to the backend.
- **"Fin de la serie" in the resume now reflects the actual end rule**
  the user chose: `until` → *"Hasta el 30 de junio de 2026"*, `count`
  → *"Durante 10 ocurrencias"*, `never` → *"Sin fin (limitado a 1
  año)"*. Previously it always fell back to the generic "Según reglas"
  because the underlying `fechaFinSerie` state is never populated —
  the end lives inside `rruleInput.end`.

## [0.2.10] — 2026-04-21

### Changed

- **Tighter, more consistent step layout spacing.** In `StepFrame`:
  - Top action bar now leaves `2em` below itself before the body, giving
    the Back/Next controls a clear visual separation from step content.
  - Body's internal gap between sibling sections is `1em`
    (`display: flex; flex-direction: column; gap: 1em`). Applies to every
    step so e.g. the aforo block in Step 1 is cleanly separated from
    the services fieldset, and every subsequent step inherits the same
    rhythm without per-step tweaks.

## [0.2.9] — 2026-04-21

### Changed

- **Text scale rebalanced.** After bumping the form to px-based
  tokens in 0.2.7/0.2.8 some elements felt bulky. Tuned down:
  - StepFrame subtitle: sm (16px) instead of inheriting base (18px).
  - Buttons (Atrás / Siguiente / Confirmar): sm + tighter padding +
    min-height 40px (was 44px).
  - Step 1 service chips: xs (14px).
  - Sala card meta (Aforo / Edificio labels): xs + 12px for the
    uppercase `dt`. Service tag pills inside sala cards: 12px.
- **Sala cards now show "Aforo máx." with a single number** instead of
  the `min–max` range.

### Fixed

- **Selected sala card flipped to the gray neutral border on hover.**
  `.card:hover { border-color: --ra-color-border-strong }` was
  overriding the selected state because `.selected:hover` only reset
  transform. Added `border-color: var(--ra-color-primary)` to keep the
  brand blue while hovering.
- **Step 6 Perfil form was a hardcoded 2-column grid** via inline style,
  so on tablet/phone the fields were cramped. Moved to a CSS module
  with `@media (max-width: 700px)` collapsing to single column.
- **Public form padding scales down on tablet/mobile** (`#reservas-app`
  + StepFrame), and large titles (h2/h1) trim their size on narrow
  viewports so they don't wrap awkwardly.

## [0.2.8] — 2026-04-21

### Changed

- **Public form typography switched to px scale with 14px floor**
  (xs=14 / sm=16 / base=18 / lg=20 / xl=24 / 2xl=30 / 3xl=36). Previous
  rem values were at the mercy of the host theme's html font-size and
  still rendered small on sites that shrank the root. Admin panel tokens
  unchanged.
- **Public form brand blue is now `#05aae4`** for primary CTAs
  (Siguiente, Confirmar reserva), the selected sala card border, the
  occurrence calendar day background, and the progress bar. Done by
  overriding `--ra-color-primary` + `--ra-color-primary-hover` scoped to
  `#reservas-app` — admin panel primary stays `#0b5394`.
- **Back / Next buttons show directional arrows**: "← Atrás" and
  "Siguiente →" in all 7 step screens.
- **Selected sala card** now uses a 4px border (was 2px) and shrinks
  slightly via `transform: scale(0.97)` so the selection reads at a
  glance without reflowing the grid.
- **Step 6 "Tus datos" subtitle** unified to *"Introduce tus datos
  personales. Revisa que estén correctos."* for both logged-in and
  anonymous visitors.

## [0.2.7] — 2026-04-21

### Changed

- **Public form scaled up ~1.2x.** Overrode `--ra-font-size-*` and
  `--ra-step-width` / `--ra-container-max` scoped to `#reservas-app` so
  the embedded form reads larger than the wp-admin scale. Doesn't affect
  the admin panel.
- **Back/Next buttons moved to the top of each step.** Long steps (3
  fechas, 6 perfil…) no longer require scrolling to find the navigation.
  `StepFrame` renders the actions row above the body.
- **Dates in the Resumen step now display in Spanish human format**
  ("21 de abril de 2026") instead of `YYYY-MM-DD`. Internal storage
  unchanged. Implemented via `Intl.DateTimeFormat('es-ES', …)` parsing
  the iso string as UTC midnight to avoid timezone-driven off-by-one.
- **Progress bar fill + active step bullet are now solid `#05aae4`**
  (no gradient). Added `--ra-color-progress` token so future tweaks live
  in `tokens.css`.

### Fixed

- **Turnstile widget stuck on "Verificando…"**. `useEffect` deps
  included `onVerify` / `onError` / `onExpire`, which Step7Resumen
  passed as inline arrows — every render produced fresh refs, the effect
  re-ran, the widget unmounted/remounted before the challenge could
  complete, and the user never got past the resume step. Refactored to
  keep callbacks in refs so the mount effect only restarts when
  `siteKey` / `theme` actually change.

## [0.2.6] — 2026-04-21

### Changed

- **Admin submenu reorganization** (under the "Reservas" top-level menu):
  - "Panel" renamed to **"Panel de control"**.
  - "Todas las salas" renamed to **"Salas reservables"** (via the CPT's
    `labels.menu_name`, which is what WP's `_add_post_type_submenus()`
    actually shows in the sidebar).
  - **"Añadir nueva" submenu removed.** New salas are created from the
    "Add New" button WP renders on the salas list page by default — the
    dedicated submenu was redundant.
  - Final order: **Panel de control → Salas reservables → Edificios →
    Servicios**. Enforced by splitting `AdminMenu::register` into two
    `admin_menu` hooks: priority 9 registers Panel (so it wins the
    parent-link click), WP core at priority 10 appends Salas reservables,
    priority 11 appends the taxonomy submenus.

## [0.2.5] — 2026-04-21

### Fixed

- **Admin nav active state + "Exportar CSV" button text still blue
  after v0.2.4.** The culprit turned out to be *our own*
  `#reservas-admin-app a { color: primary }` rule in
  `frontend/admin/styles/admin.css` — an ID selector with specificity
  (1,0,1) that beat every class-based override (`.nav .navActive`,
  `a.exportLink`, etc.). Wrapped the id in `:where()` so the default
  link color rule has zero specificity and component-level rules can
  win with normal class selectors. Generic prose links inside the admin
  (e.g. the Cloudflare Turnstile link in Settings) now inherit
  wp-admin's default link color instead of our primary, which is
  visually identical.

## [0.2.4] — 2026-04-21

### Fixed

- **Clicking the "Reservas" top-level menu now loads the Panel, not
  "Todas las salas".** WordPress uses the URL of the *first* submenu as
  the parent menu's click target. WP's core `_add_post_type_submenus()`
  runs on `admin_menu` at priority 10 and appends the CPT's "All items"
  submenu; our `AdminMenu::register` also ran at priority 10, so the
  CPT submenu landed first. Hooked our registration at priority 9 so
  the Panel is registered before WP's CPT submenu.
- **Admin nav active state ("Panel" / "Reservas" / "Ajustes") was
  invisible** (blue text on blue background). wp-admin ships
  `.wrap a { color: #2271b1 }` with specificity (0,1,1), which beat our
  `.navActive` rule at (0,1,0). Nested under `.nav .navActive` so the
  white `--ra-color-primary-contrast` wins.
- **"Exportar CSV" button text was also blue** for the same reason.
  Upgraded selectors to `a.exportLink` in both Dashboard.module.css and
  BookingsList.module.css.

## [0.2.3] — 2026-04-21

### Changed (BREAKING)

- **Prefixed CPT and taxonomy slugs to avoid collisions with CPT UI and
  other generic plugins**. Sites running CPT UI with a `sala` / `edificio`
  / `servicios` post type or taxonomy were silently deleting the auto-draft
  that WordPress creates when opening the new-sala editor, producing a
  `rest_post_invalid_id` 404 and the "Has intentado editar un elemento
  que no existe" notice. The plugin's internal names now are:
  - `post_type`: `sala` → `aldealab_sala`
  - `taxonomy` (hierarchical, edificios): `edificio` → `aldealab_edificio`
  - `taxonomy` (flat, servicios): `servicios_sala` → `aldealab_servicio`
  - REST base (CPT): `salas` → `aldealab-salas` (kebab for URLs)
  - REST base + rewrite slug (taxonomies): `aldealab-edificios` /
    `aldealab-edificio`, `aldealab-servicios` / `aldealab-servicio`.
- Frontend `taxonomies.ts` updated to fetch from the new `rest_base` URLs.
- Public REST param names on `/reservas/v1/spaces` (`edificio`,
  `servicios`) and the JSON DTO property names (`edificios`,
  `servicios`) are **unchanged** — they were never slugs, just API
  conventions for the consumer.
- No data migration is needed for this release: sites that hit the CPT
  UI conflict couldn't create any salas in the first place. If you had
  salas under the old `sala` post type from an earlier working install,
  they'll need to be re-created or migrated at the DB layer
  (`UPDATE wp_posts SET post_type='aldealab_sala' WHERE post_type='sala'
  AND ...`); that scenario isn't automated here.

## [0.2.2] — 2026-04-21

### Fixed

- **"Añadir nueva" and taxonomy submenus missing under Reservas.** When
  v0.2.1 nested the `sala` CPT under the plugin's top-level menu via
  `show_in_menu => 'reservas-aldealab'`, WordPress's
  `_add_post_type_submenus()` only contributed the "All items" submenu —
  Add New and the Edificios / Servicios taxonomy admin pages are not
  auto-registered in that scenario. Consequence: users could list salas
  but not create them, and had no entry point to manage taxonomy terms.
  Also caused a spurious "Has intentado editar un elemento que no
  existe" error when navigating to the Add New flow from the admin bar.
  `AdminMenu::registerMenus` now registers the three missing submenus
  explicitly.

## [0.2.1] — 2026-04-21

### Fixed

- **Release ZIP was missing `assets/`**. The `rsync` filter in
  `.github/workflows/release.yml` excluded the `assets/` parent directory
  before descending into `assets/dist/`, `assets/pdf-templates/` and
  `assets/email/`, so the published ZIP shipped without the built Vite
  bundle, PDF templates and email templates. Effect: the admin panel
  stayed stuck on "Cargando panel…" and PDFs could never be generated.
  Added `--include='assets/'` and a verification step that fails the
  release build fast if any required path (`assets/dist/manifest.json`,
  `assets/pdf-templates/`, `assets/email/`, `vendor/autoload.php`) is
  missing from the assembled folder.
- **Administrator role could end up without `manage_reservas`** if the
  activation hook threw during DB migrations — the role step came after
  migrations and never ran. Symptom: top-level "Reservas" menu visible
  but the "Panel" submenu hidden, and clicking the parent jumped to the
  Salas CPT. Roles now run **before** migrations inside
  `Activator::activate`, and `RoleManager::ensureRoles` is also hooked on
  `admin_init` as an idempotent self-heal for plugins replaced via FTP
  without re-activation.
- **Duplicate "Salas" top-level menu.** The `sala` CPT declared
  `show_in_menu => true`, creating its own sidebar entry next to
  "Reservas" and leaving the manual "Salas" submenu redundant. Now hangs
  under `show_in_menu => 'reservas-aldealab'`; the CPT contributes "Todas
  las salas", "Añadir nueva", "Edificios" and "Servicios" automatically.
- **Shared Vite chunk CSS was never enqueued.** The design-tokens bundle
  (`_tokens-*.js`) that both public and admin entries import carried its
  own CSS, but both `AssetLoader`s only enqueued the entry's own CSS.
  Now each loader walks `entry.imports[].css` too.
- `AdminAssetLoader` now surfaces a WP admin error notice when
  `assets/dist/manifest.json` is missing, instead of silently skipping the
  enqueue (which previously produced the "Cargando panel…" symptom with
  zero signal).

## [0.2.0] — 2026-04-20

### Added

- **PDF template uploader** from the admin panel. New settings section lists
  both templates with their current source (custom upload vs. packaged),
  accepts multipart uploads with PDF magic-byte + size validation, and
  offers a "revert to packaged" button. Uploads are stored under
  `wp-content/uploads/reservas-aldealab/pdf-templates/` with a deny-all
  `.htaccess`, so they survive plugin upgrades. New REST surface:
  GET/POST/DELETE `/admin/pdf-templates[/{key}]`.
- **iCal export**. New service `IcalGenerator` emits RFC 5545 VCALENDAR
  with one VEVENT per expanded date. Public endpoint
  `GET /bookings/{uuid}/ical` (UUID-gated). iCal download button appears
  in the success step of the public SPA and a CTA button in the user
  confirmation email.
- **SMS notifications (opt-in)**. Pluggable `SmsProviderInterface` with a
  null provider by default and a concrete Twilio implementation
  (`TwilioSmsProvider`) that uses the Twilio REST API. EmailNotifier
  dispatches an SMS after the email when the provider is configured and
  the profile has a mobile number, for both confirmation and cancellation
  flows. Settings UI adds a provider selector and Twilio SID / token /
  from-number fields. Auth token is masked on GET.
- **Stats with custom date ranges**. `GET /admin/stats` now accepts
  `from` / `to` (YYYY-MM-DD) query params. Dashboard gets a range picker
  with "Last month / quarter / year" presets.
- **CSV export of bookings**. `GET /admin/bookings/export` streams a
  UTF-8 CSV (BOM for Excel) with the same filter set as the bookings
  list, capped at 10 000 rows. Export buttons in Dashboard (by range)
  and BookingsList (with current filters).
- Small Webcafeína footer in both the public SPA and the admin panel.

### Changed

- `AdminSettingsController` masks both Turnstile secret and Twilio auth
  token on GET, accepts the mask as "no change" on PUT.
- `PdfGenerator` resolves template paths through `PdfTemplateStorage`
  so admin uploads win over packaged templates.

## [0.1.0] — 2026-04-20

Initial release. Complete rewrite from scratch of the legacy
"Reservas de salas" plugin, now standalone and no longer dependent on
Motopress Hotel Booking.

### Added

- Autonomous database schema: 5 tables (`bookings`, `booking_dates`,
  `user_profiles`, `booking_cpa_items`, `email_log`) with versioned
  migrations.
- Custom post type `sala` with `edificio` (hierarchical) and
  `servicios_sala` (flat) taxonomies; metabox for aforo, disponibilidad
  and CPA flag; all fields REST-exposed.
- Two plugin roles: `usuario_alojado` (tenants) and `reservas_manager`
  (staff), plus a `manage_reservas` capability.
- REST API under `wp-json/reservas/v1/` covering public space listing,
  availability check, booking creation (with Turnstile + rate limit),
  user profile management, and full admin CRUD + stats + settings.
- Domain services: `RecurrenceExpander` (wraps simshaun/recurr),
  `AvailabilityChecker` with `SELECT ... FOR UPDATE`, `BookingService`
  orchestration with transactional integrity, `TurnstileVerifier`,
  `PdfGenerator` with pdftk backend, `EmailNotifier` with async hooks.
- React SPA in 8 steps (aforo/services → sala → fechas → recurrencia →
  horario → datos → resumen → éxito). Visual occurrence calendar with
  click-to-exclude, services chip selector, session-storage persistence.
- React admin panel with dashboard KPIs, filterable bookings list,
  booking editor (state + internal note), and full settings form.
- PDF generation with `mikehaertl/php-pdftk`: fills AcroForm templates
  (CPA or generic Aldealab) with booking and profile data, attaches
  result to confirmation emails.
- HTML email notifications (user confirmation + admin notification +
  user cancellation) with per-template layout and email log table.
- Cloudflare Turnstile integration (front + server verification).
- Cloudflare-style rate limiting on POST /bookings (transient-backed).
- Internationalization: POT template and `bin/make-pot.sh` regenerator.
- GitHub Actions CI (PHP 7.4–8.2 × WP 6.0/latest, PHPCS, PHPStan L6,
  PHPUnit, ESLint, Vitest, Vite build) and release workflow that
  packages a production ZIP on `v*` tags.
- Comprehensive README covering install, configuration, development,
  release, troubleshooting, and migration from the legacy plugin.

### Architecture Decision Records

- `docs/decisions/001-campos-acroform.md` — AcroForm field names.
- `docs/decisions/002-motor-pdf.md` — choice of php-pdftk over FPDI.
- `docs/decisions/003-admin-bundle-separado.md` — separate Vite entry
  for the admin panel.
