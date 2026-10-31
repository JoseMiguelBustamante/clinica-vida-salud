# Privately create the first administrator

Only an authorized operator may run `clinic:bootstrap-admin`, manually, after
isolated PostgreSQL authentication proof and deployment approval. This is not a
startup hook, seed, public registration endpoint, account editor, or reset tool.
No real administrator has been created by the implementation/tests.

## Before running

- Obtain passing operator evidence from the disposable PostgreSQL runner:
  `bash tests/Integration/run-postgres.sh --identity`. Discovery and database-free
  tests are not PostgreSQL transaction or concurrency proof.
- Deploy the approved source, confirm the existing report schema is migrated,
  verify the intended application/database target privately, and complete the
  deployment/session-security checks. This command never migrates or provisions.
- Use a private trusted terminal with Docker TTY access and hidden input support.
  Disable terminal recording, shell tracing, and session sharing. Never paste
  credentials into chat, tickets, command arguments, logs, or shell history.
- Select the one verified running application container, not PostgreSQL. Assign
  its identifier to the non-secret shell variable `app`; do not infer the target
  from the first Docker result. Do not share deployment secret values.

## Manual operator command

The deployed clinic uses the exact Swarm service label below. This guarded block
selects its single running application container and stops on zero/multiple
matches; it never chooses the first unrelated Docker result. Run it manually in
the private terminal only:

```sh
(
  set -eu
  ids=$(sudo docker ps -q --filter 'label=com.docker.swarm.service.name=clinica-vida-salud-web-yn2epv')
  set -- $ids
  if [ "$#" -ne 1 ]; then
    echo 'STOP: expected exactly one running clinic application container.' >&2
    exit 1
  fi
  app="$1"
  if sudo docker exec -it --user www-data -w /var/www/html "$app" php artisan clinic:bootstrap-admin; then
    printf '\nExit code: 0\n'
  else
    result=$?
    printf '\nExit code: %s\n' "$result"
    exit "$result"
  fi
)
```

For non-mutating command help, use the same guarded block with `--help` appended
to the Artisan command.
Do not pipe input, supply a password option, use `--no-interaction`, or automate
this invocation. The command has no custom options or arguments.

| Prompt | Operator input |
| --- | --- |
| `Username` | Required, at most 100 characters. |
| `Email (optional)` | Empty for no email, otherwise valid email up to 255 characters. |
| `Password` | Hidden; at least 12 characters and at most 72 UTF-8 bytes. |
| `Confirm password` | Hidden; exactly the same password, including any spaces. |

Prompts remove only the final LF/CRLF line delimiter supplied by Enter; this is
transport framing, not part of the identifier or password. All user-supplied
spaces remain intact. Identifiers reject surrounding whitespace and control
characters; no other normalization occurs. Passwords reject control characters
and are not trimmed. The real console reader, not just mocked prompt answers,
is covered by regression tests.
Both identifiers are compared case-sensitively against existing usernames AND
emails to avoid ambiguous login identifiers. Choose identifiers privately.
If hidden entry is unavailable, the command fails instead of echoing the password.

Success exits **0** and prints only this result after the prompts:

```text
Initial administrator created. Permissions were not changed.
```

Share only that final message and the exit code, not the prompt transcript,
username, email or password.

It creates one active `usuario` using `Hash::make`, creating or reusing the exact
`Administrador` role. It does not change existing users, role descriptions,
`permiso`, or `rol_permiso`. The role label grants no permission bypass; an empty
permission matrix remains empty. This command does not implement permission
assignment or promise access to future permission-protected clinical operations.

## Stop on every refusal or error

All failures exit **1**. Do not blindly retry, reset, delete accounts, or rerun
migrations. Contact the responsible operator to inspect the target and state
privately; do not copy credentials or raw database errors into chat.

| Result | Meaning |
| --- | --- |
| `An interactive terminal is required; no changes made.` | No prompts or database access occurred. |
| `Invalid administrator input; no changes made.` | Input validation failed before database access. |
| `An identifier is already in use; no changes made.` | Username/email collides with an existing identifier. |
| `An administrator already exists; no changes made.` | An account already uses `Administrador`, including an inactive account; no replacement/reset is supported. |
| `Unable to bootstrap administrator; stop and contact the operator.` | Hidden-input, database, hashing, lock, or operational failure; no success is claimed. |

Complete validation and collision checks precede writes. A PostgreSQL
transaction-scoped advisory lock serializes cooperating bootstrap invocations,
including when the role already exists; each invocation checks again while
holding the lock. The transaction rolls back owned row changes on operational
failure. PostgreSQL sequences are inherently nontransactional: database failures
may consume IDs even when rows roll back. Validation/refusal paths consume no IDs.
The lock is not a general account-management synchronization protocol.

After a success, do not run this command again. Continue with the approved
backend authentication checks without placing the password in a shell command.
There is no public registration or recovery in this private-bootstrap procedure.

## Permission catalog (after the first administrator)

`clinic:bootstrap-admin` still grants no permissions. After this source is
deployed, the first signed-in request applies missing `rol`, `permiso` and
`rol_permiso` rows from `PermissionCatalog` (including later codes such as
`pacientes.gestionar`, `especialidades.gestionar`, `medicos.gestionar`,
`horarios.gestionar`, `citas.consultar` and `citas.programar`).
No docker exec is required on each catalog change. Sign out and back in, or
reload `/panel`, so the session reads the new links.

`clinic:sync-permissions` remains a non-interactive manual repair: no password
prompt, no `-it`, and it does not change accounts. Use it only if a signed-in
request could not apply the catalog. Do not reuse a bare `$app` unless you just
assigned it in this shell.

```sh
(
  set -eu
  ids=$(sudo docker ps -q --filter 'label=com.docker.swarm.service.name=clinica-vida-salud-web-yn2epv')
  set -- $ids
  if [ "$#" -ne 1 ]; then
    echo 'STOP: expected exactly one running clinic application container.' >&2
    exit 1
  fi
  app="$1"
  sudo docker exec --user www-data -w /var/www/html "$app" php artisan clinic:sync-permissions
)
```

Success prints `Roles and permission codes are defined. Existing accounts were not changed.`
If the block prints STOP, list clinic app containers with
`sudo docker ps --filter name=clinica --format 'table {{.ID}}\t{{.Names}}\t{{.Label "com.docker.swarm.service.name"}}'`
and stop; do not pick the first unrelated result. If Artisan says the command
is missing, this source is not deployed yet.
