#!/usr/bin/env bash
set -euo pipefail

if [[ "${1:-}" == '--help' ]]; then
    printf '%s\n' \
        'Usage: bash tests/Integration/run-postgres.sh' \
        'Runs the clinic migration integration test on an owned disposable PostgreSQL 17 container.' \
        'Requires Docker access, PHP pdo_pgsql and installed development dependencies.' \
        'Publishes only a random loopback port; never connects to the clinic database.' \
        'Use --identity to run backend authentication and private administrator bootstrap tests.'
    exit 0
fi

cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.."
if [[ -v APP_CONFIG_CACHE || -f bootstrap/cache/config.php ]]; then
    echo 'Refusing integration tests with cached application configuration.' >&2
    exit 1
fi
php -r 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);'
test -f vendor/bin/phpunit
docker info --format '{{.ServerVersion}}' >/dev/null

nonce=$(php -r 'echo bin2hex(random_bytes(8));')
container="clinic-migration-test-${nonce}"
export CLINIC_DISPOSABLE_PG=yes
export ISOLATED_PG_DATABASE="clinic_migration_test_${nonce}"
export ISOLATED_PG_PASSWORD
ISOLATED_PG_PASSWORD=$(php -r 'echo bin2hex(random_bytes(24));')
export POSTGRES_DB="$ISOLATED_PG_DATABASE" POSTGRES_USER="$ISOLATED_PG_DATABASE" POSTGRES_PASSWORD="$ISOLATED_PG_PASSWORD"
created=''
cleanup() {
    status=$?
    trap - EXIT
    if [[ -n "$created" ]]; then
        if ! docker stop "$created" >/dev/null; then
            echo "Could not stop owned test fixture: $container" >&2
            if [[ "$status" == 0 ]]; then status=1; fi
        fi
    fi
    exit "$status"
}
trap cleanup EXIT
created=$(docker run --detach --rm --name "$container" \
    --publish 127.0.0.1::5432 \
    --env POSTGRES_DB --env POSTGRES_USER --env POSTGRES_PASSWORD \
    --tmpfs /var/lib/postgresql/data:rw,noexec,nosuid,size=128m \
    postgres:17-bookworm)

ready=false
for ((attempt=0; attempt<60; attempt++)); do
    if docker exec "$created" pg_isready -q -h 127.0.0.1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"; then
        ready=true
        break
    fi
    sleep 1
done
if [[ "$ready" != true ]]; then
    echo 'Disposable PostgreSQL did not become ready within 60 seconds.' >&2
    exit 1
fi
address=$(docker port "$created" 5432/tcp)
[[ "$address" == 127.0.0.1:* ]]
export ISOLATED_PG_PORT="${address##*:}"
echo 'Testing migrations on disposable PostgreSQL; production is not connected.'
target='tests/Integration/ClinicMigrationTest.php'
if [[ "${1:-}" == '--identity' ]]; then
    target='tests/Integration/Identity'
fi
php vendor/bin/phpunit --do-not-cache-result "$target"
