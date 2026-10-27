# Agent instructions

Laravel Boost is installed. Read the complete generated `CLAUDE.md` guidelines
before application changes, and `.ai/rules/index.md` plus matching rules if present.
Do not rerun dependency installation or Boost installation automatically.

## Bootstrap safety

- PHP 8.3+, Laravel 13; PostgreSQL is the only supported SQL database, tests included.
- Never read or write `.env` or `.env.example` while the environment guard remains
  active. Do not copy templates or invoke environment/key-generation commands.
- For an explicitly approved dependency bootstrap, use Composer `--no-scripts`.
  Never install dependencies or run network/build work without authorization.
- No automatic database creation or migration. Existing scaffold migrations are
  not approved against the report's 17-table schema and must remain unexecuted.
- No database-dependent tests without an authorized, isolated PostgreSQL service.
  Never substitute SQLite. Current PHPUnit tests are DB-independent and skip
  environment-file loading; their fixed public APP_KEY is test-only.
- Local file sessions/cache and sync queues avoid adding framework tables.
- Keep secrets out of source control and command output. Dokploy/deployment is
  later work, not bootstrap.

## Scope and verification

The accepted architecture is a modular Laravel monolith with HTML/CSS/JavaScript
and Blade. Do not create empty modules or implement later tasks opportunistically.
See `README.md` for safe provisioning requirements and current limitations.

Run focused tests first with `vendor/bin/phpunit --do-not-cache-result <path>`;
then the available DB-independent suite with `vendor/bin/phpunit --do-not-cache-result`.
Validate Composer using `composer validate --strict --no-check-publish`.
