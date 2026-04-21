# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
