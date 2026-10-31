<?php

namespace Tests\Unit;

use App\Modules\Identity\DuplicateAccountIdentifier;
use App\Modules\Identity\IdentityAccountService;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\ProtectedAccountChange;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class IdentityAccountServiceTest extends TestCase
{
    public function test_self_deactivation_is_rejected_without_writes(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getAccountById')->once()->with(17)
            ->andReturn((object) ['id_usuario' => 17, 'id_rol' => 1, 'activo' => true]);
        $repository->shouldReceive('setAccountActive')->never();

        try {
            (new IdentityAccountService($repository))->deactivateAccount(17, 17);
            $this->fail('Expected ProtectedAccountChange');
        } catch (ProtectedAccountChange) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_duplicate_identifier_is_rejected_before_hashing(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('identifierTaken')->once()->with('admin', null)->andReturn(true);
        $repository->shouldReceive('createAccount')->never();
        Hash::shouldReceive('make')->never();

        try {
            (new IdentityAccountService($repository))->createAccount([
                'nombre_usuario' => 'admin',
                'correo_acceso' => null,
                'password' => 'twelve-chars',
                'id_rol' => 1,
                'activo' => true,
            ]);
            $this->fail('Expected DuplicateAccountIdentifier');
        } catch (DuplicateAccountIdentifier) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_create_account_hashes_the_password_once(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('identifierTaken')->once()->with('admin', null)->andReturn(false);
        $repository->shouldReceive('getRoleById')->once()->with(2)->andReturn((object) ['id_rol' => 2]);
        $repository->shouldReceive('createAccount')->once()
            ->with('admin', null, 'stored-hash', true, 2)->andReturn(44);
        Hash::shouldReceive('make')->once()->with('twelve-chars')->andReturn('stored-hash');

        $id = (new IdentityAccountService($repository))->createAccount([
            'nombre_usuario' => 'admin',
            'correo_acceso' => null,
            'password' => 'twelve-chars',
            'id_rol' => 2,
            'activo' => true,
        ]);

        $this->assertSame(44, $id);
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
