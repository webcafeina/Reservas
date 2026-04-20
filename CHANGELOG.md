# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
