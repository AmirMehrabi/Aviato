# Repository Guidelines

## Project Structure & Module Organization
This is a Laravel 13 application. Core code lives in `app/` (controllers, jobs, mail, middleware, requests), while routes are split across `routes/web.php`, `routes/api.php`, and `routes/console.php`. Blade views and UI fragments live in `resources/views/`, frontend code in `resources/js/`, and compiled public assets under `public/`. Database schema and fixtures are in `database/migrations/`, `database/seeders/`, and `database/factories/`. Tests are organized in `tests/Unit` and `tests/Feature`.

## Build, Test, and Development Commands
- `composer setup` installs PHP and JS dependencies, creates `.env`, generates the app key, runs migrations, and builds frontend assets.
- `composer dev` starts the full local stack: Laravel server, queue listener, log tailing, and Vite.
- `php artisan test` runs the PHPUnit suite.
- `composer test` clears config and then runs the test suite.
- `npm run dev` starts the Vite dev server.
- `npm run build` produces production frontend assets.
- `vendor/bin/pint` formats PHP code using Laravel Pint.

## Coding Style & Naming Conventions
Use 4-space indentation for PHP, Blade, and JS, and keep files UTF-8 with LF line endings. Follow Laravel conventions: classes in `App\...` use PascalCase, test methods use descriptive `test_*` names, and Blade templates use kebab- or descriptive directory names such as `resources/views/customer/monitoring/index.blade.php`. Prefer small, focused controllers and move business logic into jobs, services, or form requests when it grows.

## Testing Guidelines
The test suite uses PHPUnit with `tests/Unit` and `tests/Feature` defined in `phpunit.xml`. Tests run against SQLite in memory with queue, cache, mail, and session backed by array drivers, so keep tests isolated from external infrastructure. Name tests by behavior, for example `test_customer_can_queue_manual_backup()` or `test_customer_cannot_fetch_another_customer_vm_metrics()`. Add feature coverage for auth, billing, Proxmox workflows, and portal-specific redirects.

## Commit & Pull Request Guidelines
Git history here favors short, imperative summaries such as `Added a browser tab to the hero section` or `Fixed a minor issue`. Keep commits narrow and descriptive. Pull requests should include a concise summary, linked issue or task if available, test notes, and screenshots for UI changes. Call out any migrations, config changes, or external service assumptions.

## Security & Configuration Tips
Do not commit secrets or real portal credentials. Use `.env.example` as the baseline and verify changes against the correct admin/customer subdomain behavior in `config/portals.php` and `config/auth.php`. For billing, provisioning, and verification flows, prefer stubs or fixtures in tests over live service calls.
