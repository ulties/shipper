# Shipper CLI - Agent Instructions

## Cursor Cloud specific instructions

Shipper is a PHP 8.3+ CLI tool built with Laravel Zero. It reads `shipper.yml` and performs plan/apply-style deployments to Ploi.io. There is no web UI, no local database, and no long-running service.

### Running in development

- Entry point: `php shipper` (from repo root)
- The `.env` file must exist (copy from `.env.example` if missing)
- Key commands are defined as Composer scripts in `composer.json`:
  - `composer test` — Pest test suite
  - `composer format:check` — Pint code style check
  - `composer format` — auto-fix code style
  - `composer analyse` — PHPStan level 9 static analysis
  - `composer build` — compile PHAR binary via Box

### Known test failures

All 29 feature tests pass without any external credentials. The `ApplyCommandTest` and `DestroyCommandTest` use mock providers injected via the application container.

### PHP and Composer

PHP 8.3 with extensions `mbstring`, `xml`, `ctype`, `curl`, `zip`, `dom` is required. Composer is the package manager; `composer install` populates `vendor/`.
