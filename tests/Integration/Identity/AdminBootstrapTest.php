<?php

namespace Tests\Integration\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\AdminBootstrapCommandTest;
use Tests\Integration\IdentityPostgresTestCase;

class AdminBootstrapTest extends IdentityPostgresTestCase
{
    #[DataProvider('validInputs')]
    public function test_creates_active_hashed_account_without_granting_permissions(string $username, ?string $email, string $password): void
    {
        $this->runCommand([$username, $email, $password, $password], 'Initial administrator created. Permissions were not changed.', 0);

        $users = DB::table('usuario')->get();
        $this->assertCount(1, $users);
        $user = $users->first();
        $this->assertSame($username, $user->nombre_usuario);
        $this->assertSame($email === '' ? null : $email, $user->correo_acceso);
        $this->assertTrue((bool) $user->activo);
        $this->assertNotSame($password, $user->contrasena_hash);
        $this->assertTrue(Hash::check($password, $user->contrasena_hash));
        $this->assertSame('Administrador', DB::table('rol')->where('id_rol', $user->id_rol)->value('nombre'));
        $this->assertSame(0, DB::table('permiso')->count());
        $this->assertSame(0, DB::table('rol_permiso')->count());
    }

    public static function validInputs(): array
    {
        return [
            ['operator', 'operator@example.test', 'twelve-characters'],
            ['operator', '', '123456789012'],
            [str_repeat('é', 100), null, str_repeat('é', 36)],
            ['operator', str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61), str_repeat('p', 72)],
            ['operator', null, '  untrimmed password  '],
        ];
    }

    public function test_reuses_role_preserves_regular_users_and_existing_permission_matrix(): void
    {
        $roleId = DB::table('rol')->insertGetId(['nombre' => 'Administrador', 'descripcion' => 'Keep description'], 'id_rol');
        $permissionId = DB::table('permiso')->insertGetId(['codigo' => 'fixture.existing'], 'id_permiso');
        DB::table('rol_permiso')->insert(['id_rol' => $roleId, 'id_permiso' => $permissionId]);
        $regularId = $this->regularUser('regular', 'regular@example.test');
        $regularBefore = (array) DB::table('usuario')->where('id_usuario', $regularId)->first();
        $rolesBefore = DB::table('rol')->orderBy('id_rol')->get()->toJson();
        $matrixBefore = DB::table('rol_permiso')->get()->toJson();
        $permissionsBefore = DB::table('permiso')->get()->toJson();

        $this->runCommand(['operator', null, 'twelve-characters', 'twelve-characters'], 'Initial administrator created. Permissions were not changed.', 0);

        $this->assertSame($roleId, DB::table('usuario')->where('nombre_usuario', 'operator')->value('id_rol'));
        $this->assertSame($regularBefore, (array) DB::table('usuario')->where('id_usuario', $regularId)->first());
        $this->assertSame($rolesBefore, DB::table('rol')->orderBy('id_rol')->get()->toJson());
        $this->assertSame($matrixBefore, DB::table('rol_permiso')->get()->toJson());
        $this->assertSame($permissionsBefore, DB::table('permiso')->get()->toJson());
        $before = $this->snapshot();
        $this->runCommand(['second', null, 'twelve-characters', 'twelve-characters'], 'An administrator already exists; no changes made.', 1);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_even_an_inactive_administrator_cannot_be_reset_or_replaced(): void
    {
        $roleId = DB::table('rol')->insertGetId(['nombre' => 'Administrador'], 'id_rol');
        DB::table('usuario')->insertGetId(['nombre_usuario' => 'existing', 'contrasena_hash' => 'keep', 'activo' => false, 'id_rol' => $roleId], 'id_usuario');
        $before = $this->snapshot();
        $this->runCommand(['operator', '', 'twelve-characters', 'twelve-characters'], 'An administrator already exists; no changes made.', 1);
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('invalidInputs')]
    public function test_validation_rejection_preserves_rows_and_sequence_counters(array $answers): void
    {
        $before = $this->snapshot();
        $this->runCommand($answers, 'Invalid administrator input; no changes made.', 1);
        $this->assertSame($before, $this->snapshot());
    }

    public static function invalidInputs(): array
    {
        return AdminBootstrapCommandTest::invalidInputs();
    }

    #[DataProvider('collidingIdentifiers')]
    public function test_same_and_cross_column_collisions_do_not_consume_ids(string $username, ?string $email): void
    {
        $this->regularUser('existing@example.test', 'mail@example.test');
        $before = $this->snapshot();
        $this->runCommand([$username, $email, 'twelve-characters', 'twelve-characters'], 'An identifier is already in use; no changes made.', 1);
        $this->assertSame($before, $this->snapshot());
    }

    public static function collidingIdentifiers(): array
    {
        return [
            ['existing@example.test', null],
            ['mail@example.test', null],
            ['operator', 'existing@example.test'],
            ['operator', 'mail@example.test'],
        ];
    }

    public function test_identifier_comparisons_are_case_sensitive(): void
    {
        $this->regularUser('OPERATOR', 'MAIL@example.test');
        $this->runCommand(['operator', 'mail@example.test', 'twelve-characters', 'twelve-characters'], 'Initial administrator created. Permissions were not changed.', 0);
        $this->assertSame(2, DB::table('usuario')->count());
    }

    public function test_operational_insert_failure_is_safe_and_rolls_back_new_role(): void
    {
        DB::statement("ALTER TABLE usuario ADD CONSTRAINT fixture_bootstrap_failure CHECK (nombre_usuario <> 'fixture-fail')");
        $rowsBefore = $this->snapshot()['rows'];
        $this->runCommand(['fixture-fail', null, 'twelve-characters', 'twelve-characters'], 'Unable to bootstrap administrator; stop and contact the operator.', 1);
        $this->assertSame($rowsBefore, $this->snapshot()['rows']);
        // PostgreSQL sequence increments are deliberately not asserted to roll back on SQL failures.
    }

    public function test_existing_role_path_holds_the_same_transaction_advisory_lock(): void
    {
        DB::table('rol')->insertGetId(['nombre' => 'Administrador'], 'id_rol');
        $this->runCommand(['operator', null, 'twelve-characters', 'twelve-characters'], 'Initial administrator created. Permissions were not changed.', 0);
        $lock = DB::selectOne("SELECT granted FROM pg_locks WHERE pid = pg_backend_pid() AND locktype = 'advisory' AND classid = 1129072974 AND objid = 1 AND objsubid = 2");
        $this->assertNotNull($lock);
        $this->assertTrue((bool) $lock->granted);
    }

    public function test_lock_contention_fails_safely_before_any_writes(): void
    {
        config(['database.connections.bootstrap_contender' => config('database.connections.pgsql')]);
        $contender = DB::connection('bootstrap_contender');
        $before = $this->snapshot();
        try {
            $contender->beginTransaction();
            $contender->select('SELECT pg_advisory_xact_lock(1129072974, 1)');
            DB::statement("SET LOCAL lock_timeout = '100ms'");
            $this->runCommand(['operator', null, 'twelve-characters', 'twelve-characters'], 'Unable to bootstrap administrator; stop and contact the operator.', 1);
            $this->assertSame($before, $this->snapshot());
        } finally {
            $contender->rollBack();
            DB::disconnect('bootstrap_contender');
        }
    }

    private function regularUser(string $username, string $email): int
    {
        $roleId = DB::table('rol')->insertGetId(['nombre' => 'Fixture regular'], 'id_rol');

        return DB::table('usuario')->insertGetId([
            'nombre_usuario' => $username, 'correo_acceso' => $email,
            'contrasena_hash' => 'fixture-only', 'activo' => true, 'id_rol' => $roleId,
        ], 'id_usuario');
    }

    private function runCommand(array $answers, string $message, int $status): void
    {
        $this->artisan('clinic:bootstrap-admin')
            ->expectsQuestion('Username', $answers[0])
            ->expectsQuestion('Email (optional)', $answers[1])
            ->expectsQuestion('Password', $answers[2])
            ->expectsQuestion('Confirm password', $answers[3])
            ->expectsOutput($message)
            ->assertExitCode($status);
    }

    private function snapshot(): array
    {
        $snapshot = ['rows' => [], 'sequences' => []];
        foreach (['rol', 'usuario', 'permiso', 'rol_permiso'] as $table) {
            $snapshot['rows'][$table] = DB::table($table)->orderByRaw('1, 2')->get()->toJson();
        }
        foreach (['rol_id_rol_seq', 'usuario_id_usuario_seq', 'permiso_id_permiso_seq'] as $sequence) {
            $snapshot['sequences'][$sequence] = (array) DB::selectOne('SELECT last_value, is_called FROM public.'.$sequence);
        }

        return $snapshot;
    }
}
