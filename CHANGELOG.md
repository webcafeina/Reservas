# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
