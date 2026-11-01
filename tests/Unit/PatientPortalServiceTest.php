<?php

namespace Tests\Unit;

use App\Modules\Identity\DuplicateAccountIdentifier;
use App\Modules\Identity\IdentityAccountService;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\PatientPortalService;
use App\Modules\Identity\PatientRegistrationDisabled;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\Identity\PermissionCatalogService;
use App\Modules\Identity\RoleNotFound;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PatientPortalServiceTest extends TestCase
{
    public function test_disabled_registration_is_rejected_before_writes(): void
    {
        config(['identity.patient_portal_registration' => false]);
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('createAccount')->never();
        $repository->shouldReceive('getRoleByName')->never();
        Hash::shouldReceive('make')->never();

        try {
            $this->service($repository)->registerPatientAccount([
                'nombre_usuario' => 'paciente1',
                'password' => 'twelve-chars',
            ]);
            $this->fail('Expected PatientRegistrationDisabled');
        } catch (PatientRegistrationDisabled) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_missing_patient_role_is_rejected_after_catalog_sync(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->twice()->with(PermissionCatalog::PATIENT_ROLE)->andReturn(null);
        $repository->shouldReceive('permissionCatalogIsApplied')->once()->andReturn(true);
        $repository->shouldReceive('upsertPermissionCatalog')->never();
        $repository->shouldReceive('createAccount')->never();

        try {
            $this->service($repository)->registerPatientAccount([
                'nombre_usuario' => 'paciente1',
                'password' => 'twelve-chars',
            ]);
            $this->fail('Expected RoleNotFound');
        } catch (RoleNotFound) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_registration_assigns_the_patient_role_and_hashes_once(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->once()->with(PermissionCatalog::PATIENT_ROLE)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        $repository->shouldReceive('identifierTaken')->once()->with('paciente1', null)->andReturn(false);
        $repository->shouldReceive('getRoleById')->once()->with(4)
            ->andReturn((object) ['id_rol' => 4]);
        $repository->shouldReceive('createAccount')->once()
            ->with('paciente1', null, 'stored-hash', true, 4)->andReturn(44);
        Hash::shouldReceive('make')->once()->with('twelve-chars')->andReturn('stored-hash');

        $id = $this->service($repository)->registerPatientAccount([
            'nombre_usuario' => 'paciente1',
            'password' => 'twelve-chars',
        ]);

        $this->assertSame(44, $id);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_taken_username_is_rejected_before_hashing(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->once()->with(PermissionCatalog::PATIENT_ROLE)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        $repository->shouldReceive('identifierTaken')->once()->with('paciente1', null)->andReturn(true);
        $repository->shouldReceive('createAccount')->never();
        Hash::shouldReceive('make')->never();

        try {
            $this->service($repository)->registerPatientAccount([
                'nombre_usuario' => 'paciente1',
                'password' => 'twelve-chars',
            ]);
            $this->fail('Expected DuplicateAccountIdentifier');
        } catch (DuplicateAccountIdentifier) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function service(IdentityRepository $identities): PatientPortalService
    {
        return new PatientPortalService(
            new IdentityAccountService($identities),
            $identities,
            new PermissionCatalogService($identities),
        );
    }
}
