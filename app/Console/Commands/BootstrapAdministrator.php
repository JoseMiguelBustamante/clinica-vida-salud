<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Question\Question;
use Throwable;

class BootstrapAdministrator extends Command
{
    protected $signature = 'clinic:bootstrap-admin';

    protected $description = 'Privately create the initial active administrator (manual interactive use only)';

    protected $help = <<<'HELP'
        Requires an interactive terminal with hidden input and an already migrated PostgreSQL database.
        Prompts for username (required, at most 100 characters), optional email (at most 255 characters),
        password (at least 12 characters, at most 72 bytes), and hidden confirmation. Input is not trimmed.
        Identifiers reject surrounding whitespace and control characters; passwords reject control characters.
        No password arguments, options, defaults, environment writes, migrations, or automatic invocation.
        Creates/reuses Administrador and creates one active usuario with a password hash in a transaction.
        Existing administrators cause refusal; no reset/overwrite and no permissions are granted or changed.
        --no-interaction refuses before prompts or database access. Never supply credentials in shell history.
        On any error, stop and contact the operator; do not retry or reset accounts blindly.
        HELP;

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('An interactive terminal is required; no changes made.');

            return self::FAILURE;
        }

        try {
            $username = $this->promptUntrimmed('Username');
            $email = $this->promptUntrimmed('Email (optional)');
            $password = $this->secret('Password', false);
            $confirmation = $this->secret('Confirm password', false);

            if (! $this->validIdentifier($username, 100, false)
                || ! $this->validIdentifier($email, 255, true)
                || ($email !== null && $email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL))
                || ! is_string($password) || ! mb_check_encoding($password, 'UTF-8')
                || mb_strlen($password) < 12 || strlen($password) > 72
                || preg_match('/\p{C}/u', $password) !== 0 || $password !== $confirmation) {
                $this->error('Invalid administrator input; no changes made.');

                return self::FAILURE;
            }

            $connection = DB::connection();
            if ($connection->getDriverName() !== 'pgsql') {
                $this->error('Unable to bootstrap administrator; stop and contact the operator.');

                return self::FAILURE;
            }

            $result = $connection->transaction(function () use ($connection, $username, $email, $password): int {
                // All invocations take the same transaction-scoped lock, including when the role exists.
                $connection->select('SELECT pg_advisory_xact_lock(1129072974, 1)');
                $role = $connection->table('rol')->whereRaw('nombre COLLATE "C" = ?', ['Administrador'])->first();
                if ($role && $connection->table('usuario')->where('id_rol', $role->id_rol)->exists()) {
                    $this->error('An administrator already exists; no changes made.');

                    return self::FAILURE;
                }

                foreach (array_filter([$username, $email], fn ($value) => $value !== null && $value !== '') as $identifier) {
                    if ($this->identifierExists($connection, $identifier)) {
                        $this->error('An identifier is already in use; no changes made.');

                        return self::FAILURE;
                    }
                }

                $hash = Hash::make($password);
                $roleId = $role?->id_rol ?? $connection->table('rol')->insertGetId(['nombre' => 'Administrador'], 'id_rol');
                $connection->table('usuario')->insertGetId([
                    'nombre_usuario' => $username,
                    'correo_acceso' => $email === '' ? null : $email,
                    'contrasena_hash' => $hash,
                    'activo' => true,
                    'id_rol' => $roleId,
                ], 'id_usuario');

                return self::SUCCESS;
            });

            if ($result === self::SUCCESS) {
                $this->info('Initial administrator created. Permissions were not changed.');
            }

            return $result;
        } catch (Throwable) {
            $this->error('Unable to bootstrap administrator; stop and contact the operator.');

            return self::FAILURE;
        }
    }

    /** Preserve password bytes rather than the framework's default trimming. */
    public function secret($question, $fallback = true): mixed
    {
        $prompt = (new Question($question))->setTrimmable(false)->setHidden(true)->setHiddenFallback($fallback);

        return $this->output->askQuestion($prompt);
    }

    private function promptUntrimmed(string $label): mixed
    {
        return $this->output->askQuestion((new Question($label))->setTrimmable(false));
    }

    private function validIdentifier(mixed $value, int $maximum, bool $optional): bool
    {
        if ($value === null || $value === '') {
            return $optional;
        }

        return is_string($value) && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value) <= $maximum
            && preg_match('/\p{C}|\A\s|\s\z/u', $value) === 0;
    }

    private function identifierExists(Connection $connection, string $identifier): bool
    {
        return $connection->table('usuario')->whereRaw(
            '(nombre_usuario COLLATE "C" = ? OR correo_acceso COLLATE "C" = ?)',
            [$identifier, $identifier],
        )->exists();
    }
}
