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

## Production container (Dokploy)

The root `Dockerfile` uses PHP 8.3/Apache Bookworm and Composer 2. It installs
`pdo_pgsql` and the application extensions, installs locked production dependencies
with `--no-dev --no-scripts`, and serves only `/var/www/html/public` on port **80**.
Apache rewrite rules route requests through the existing public entrypoint; the
stylesheet at `/css/clinic.css` needs no Node/Vite build. PHP errors go to stderr,
not HTTP responses. There is no debug, phpinfo or database administration endpoint.

### Source upload contract

Commit the reviewed Dockerfile, `.dockerignore`, `deploy/` configuration and
application source before preparing a ZIP from `git archive` of that commit.
Uncommitted/untracked files are not included. The upload must contain the project
files at its root, including `composer.lock`, `artisan`, `app/`, `bootstrap/`,
`config/`, `database/`, `public/` (including `.htaccess`), `resources/`, `routes/`
and `deploy/`. No separate packaging script or frontend artifact is needed.

`.dockerignore` is default-deny: only those application/build inputs are admitted.
It keeps environment files, credentials, keys, logs, host caches, `.git`, `vendor`,
`node_modules`, tests, ODD documents and agent configuration out of the build
context/image. Docker ignore rules do **not** filter the uploaded ZIP: the operator
must separately inspect its entry names and reject secret/environment files before
upload. Never add guarded environment files to Git or the archive. Dependencies
are installed inside the image rather than uploaded from the workstation.

### Runtime configuration and persistence

In Dokploy choose the Dockerfile build at the source root and container port 80.
Provide `APP_ENV=production`, `APP_DEBUG=false`, a unique runtime `APP_KEY`, the
public `APP_URL`, and the PostgreSQL variables listed above via deployment secrets.
Do not place them in build arguments, source or transcripts. Keep `LOG_CHANNEL=stderr`,
`SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` and
`QUEUE_FAILED_DRIVER=null`. Use a new isolated internal PostgreSQL service, not an
existing application's database. No separate frontend service is required.

Persist **`/var/www/html/storage`** for file sessions, cache and future application
files. Before starting Apache, the mount must contain `app/private`, `app/public`,
`framework/cache/data`, `framework/cache/locks`, `framework/sessions`,
`framework/views` and `logs`, writable by `www-data` (Debian UID/GID 33).
The image creates these paths; an empty bind mount can hide them, so the operator
must initialize it or use a volume that copies the image's initial contents.
`/var/www/html/bootstrap/cache` is also image-initialized and writable by `www-data`;
keep it writable and deployment-local, not shared with older releases. A read-only
root filesystem needs a separate writable mount there with correct ownership.
No storage symlink is created: public user uploads are not implemented.

Startup runs **Apache only**. It never copies environment files, generates a key,
runs migrations, or caches configuration before runtime secrets exist. Composer
scripts/package discovery are deliberately not run at build time. Laravel can
create its package manifest on first runtime boot in writable `bootstrap/cache`.
Do not enable upstream migrations: they have not been reconciled with the report's
17-table schema.

The image health check requests `http://127.0.0.1/up` with bounded timeouts. This
checks Laravel boot, **not PostgreSQL readiness**. After deployment, verify `/`,
`/css/clinic.css` and `/up` over HTTP and independently verify the isolated database
connection. Local structural tests are not proof of a successful image build or
live deployment; the actual Dokploy build/runtime checks remain required.

```sh
vendor/bin/phpunit --do-not-cache-result tests/Unit/DeploymentConfigurationTest.php
vendor/bin/phpunit --do-not-cache-result
composer validate --strict --no-check-publish
```

## Database and delivery boundary

A dedicated PostgreSQL 17 service and the Laravel application have been deployed
in Dokploy. Its runtime health check extends the image check with an authenticated
PDO `SELECT 1`: the application container is healthy and database login is proven.
Both services have named persistent volumes; PostgreSQL has no published external
port. Persistence across a subsequent redeployment has not been tested.

**Public access is blocked.** The configured hostname is
`https://clinica.187.127.19.244.sslip.io`, but public ports 80/443 refuse connections
and the read-only Dokploy inventory shows no Traefik container. Do not treat the
hostname as live or restart the shared proxy without operator authorization;
Electrónica may also depend on it. Public HTTP/TLS/CSS and browser checks are pending.

No migration ran. Reconcile the upstream migration files with the report's
17-table schema before explicit authorization. Authentication and clinic business
workflows are not yet implemented. See `odd/tasks/clinic-dokploy.md` for deployment
evidence and the open blocker.
