# Repository Guidelines

## Project Structure & Module Organization
This repository is a Laravel 12 backend. Core application code lives in `app/`, with API controllers under `app/Http/Controllers/Api` and admin endpoints under `app/Http/Controllers/Api/Admin`. Business logic is grouped in `app/Services`, request validation in `app/Http/Requests`, and OpenAPI definitions in `app/OpenApi`.

Routes are defined in `routes/api.php`, `routes/web.php`, and `routes/console.php`. Database factories, migrations, and seeders live in `database/`. Tests are split into `tests/Feature` for HTTP and integration flows and `tests/Unit` for isolated logic. Frontend assets for Vite/Tailwind are in `resources/css` and `resources/js`. Project-specific notes belong in `docs/`.

## Build, Test, and Development Commands
Use Composer for backend tasks and npm for the Vite asset pipeline:

- `composer setup`: installs dependencies, creates `.env`, generates the app key, runs migrations, installs npm packages, and builds assets.
- `composer dev`: starts the Laravel server, queue listener, log tailing, and Vite dev server together.
- `composer test`: clears config and runs the PHPUnit suite through Laravel.
- `npm run dev`: starts the Vite dev server only.
- `npm run build`: builds production assets.

## Coding Style & Naming Conventions
Follow `.editorconfig`: UTF-8, LF line endings, spaces, and 4-space indentation for PHP. Format PHP with `./vendor/bin/pint` before opening a PR. Use PSR-4 class names that match file paths, for example `App\\Services\\Auth\\UserLoginService`. Keep controllers, requests, seeders, and tests in PascalCase. Use descriptive test methods such as `test_admin_can_create_product`. Every new service or API-facing endpoint must also be documented in Swagger/OpenAPI, keeping `app/OpenApi` and generated docs in sync with the implementation.

## Testing Guidelines
Write endpoint and permission coverage in `tests/Feature`; keep pure logic in `tests/Unit`. Name test files with the `*Test.php` suffix. Run `composer test` locally before pushing. When adding database-dependent behavior, cover both success and authorization/validation failure cases.

## Commit & Pull Request Guidelines
The current history uses short, imperative commit messages in lowercase Spanish, for example `commit inicial del backend en Laravel 12`. Keep commits focused and descriptive, ideally one concern per commit. PRs should include a short summary, linked issue if applicable, notes about migrations or env changes, and example request/response payloads for API changes.

## Security & Configuration Tips
Start from `.env.example`; never commit secrets. Review auth, Passport, permissions, and notification configuration when changing `config/`, `app/Notifications`, or `routes/api.php`. Regenerate API documentation outputs only from trusted source changes.
