# Clínica Vida Salud — bootstrap

PHP 8.3+ / Laravel 13, PostgreSQL only. The accepted architecture is a modular
monolith with server-rendered Blade, HTML, CSS and JavaScript. Future modules:
Identity, Patients, MedicalStaff, Schedules and Appointments under `app/Modules`.
The current slice registers module routes and serves a public clinic landing page;
no authentication or business operations are implemented.

## Modular foundation

| Surface | Responsibility |
| --- | --- |
| `bootstrap/providers.php` | Registers `ModuleServiceProvider` with Laravel. |
| `app/Providers/ModuleServiceProvider.php` | Explicitly loads existing `routes/web.php` files for the five approved modules using Laravel's cache-aware route loader. |
| `app/Modules/Appointments/routes/web.php` | Owns the public `GET /` route, named `home`, with `web` middleware. Booking is not implemented. |
| `resources/views/layouts/clinic.blade.php` | Shared semantic Blade shell, skip link and implementation status. |
| `resources/views/home.blade.php` | Public foundation status; no patient or medical data. |
| `public/css/clinic.css` | Local responsive stylesheet, served directly without a frontend build. |

Only Appointments has a module directory because it currently owns a route.
Add other module directories only when implementing their approved behavior; the
provider already recognizes their exact names. Module route files must explicitly
apply their middleware (such as `web`) and choose unique route names and URLs.
`routes/web.php` is reserved for future cross-module routes; it does not duplicate
`/`. Laravel's existing `/up` health endpoint remains unchanged and is not a
PostgreSQL readiness check.

No JavaScript is needed for this static page. Node/npm and Vite are not required to
render it. When interactive features justify bundling, migrate the stylesheet to
`resources` and use Blade's Vite integration with the existing frontend pipeline;
do not add a mandatory build to this initial slice.

Check the foundation without environment files or a database:

```sh
vendor/bin/phpunit --do-not-cache-result tests/Feature/ModuleFoundationTest.php
vendor/bin/phpunit --do-not-cache-result tests/Feature/ExampleTest.php
vendor/bin/phpunit --do-not-cache-result
```

These tests cover root/module registration, semantic HTML, local asset references,
unavailable business endpoints and preservation of `/up`. Browser-based visual
and keyboard checks remain pending; HTTP tests do not establish visual fidelity.

## Safe local bootstrap

Prerequisites: PHP with Laravel's extensions and `pdo_pgsql`, and Composer. Node/npm
is needed only for future bundled frontend assets. Check the driver without connecting to a database:

```sh
php -v
composer -V
php -r 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);'
composer install --no-scripts --no-interaction
composer validate --strict --no-check-publish
vendor/bin/phpunit --do-not-cache-result
```

Dependencies and Laravel Boost have been installed. Boost's generated guidelines
are in `CLAUDE.md`; read those as well as `AGENTS.md` before changes.
`--no-scripts` is the guarded bootstrap path: it avoids application hooks while
configuration is still pending. The unchanged package discovery/asset publication
hooks may run only once environment provisioning is approved and ready.

### Environment provisioning (manual, not performed)

`.env` and `.env.example` remain guard-blocked: do not read, copy or generate them
with bootstrap commands. An authorized operator must provision process environment
variables through their local secret manager or, later, deployment configuration.
Supply a unique secret `APP_KEY` generated outside this repository; never use the
public, fixed PHPUnit key outside tests. Set `APP_ENV=local`, `APP_DEBUG=true` only
locally, and an appropriate `APP_URL`. Use `APP_DEBUG=false` for production.

Set `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT` (normally 5432), `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD` and the appropriate `DB_SSLMODE`. Keep credentials out
of source control, command transcripts and shared logs. No SQLite fallback exists;
`AppServiceProvider` restricts the booted connection list to PostgreSQL after Laravel
merges its framework defaults. Laravel's PostgreSQL connector still requires the
real PDO driver. The boot-level regression runs without opening connections:

```sh
vendor/bin/phpunit --do-not-cache-result tests/Feature/PostgresConfigurationTest.php
```

Local defaults are `SESSION_DRIVER=file`, `CACHE_STORE=file`,
`QUEUE_CONNECTION=sync`, `QUEUE_FAILED_DRIVER=null`: no sessions/cache/jobs tables
are needed. Files are local to one instance; durable queues, shared storage,
batching and failed-job retention require a later infrastructure/schema decision.
Tests use array sessions/cache, sync queues and PostgreSQL configuration; the current
tests do not connect to a database and never load environment files. Any future
DB tests require an explicitly authorized, isolated PostgreSQL database, not SQLite.

After manual provisioning, `composer run setup` retains dependency installation and
frontend install/build only; it does not copy environment files, generate keys,
create databases or migrate. `composer run dev` and `composer run test` retain their
upstream behavior and require a provisioned environment. No frontend build or
long-running development server was started in this slice.

## Database and delivery boundary

The PostgreSQL service is **unverified**; no database functionality is proven.
No database was created and no migration ran. The upstream business/infrastructure
migration files remain unexecuted: do not run them during bootstrap. Reconcile
future migrations with the report's 17-table schema before explicit authorization.
Dokploy configuration/deployment, authentication and business workflows are later
work, not part of this bootstrap.
