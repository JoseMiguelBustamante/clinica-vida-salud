<?php

namespace Tests\Unit;

use App\Modules\MedicalStaff\DuplicateProfessionalLicense;
use App\Modules\MedicalStaff\InactiveSpecialtyAssociation;
use App\Modules\MedicalStaff\InvalidPhysicianInput;
use App\Modules\MedicalStaff\PhysicianRepository;
use App\Modules\MedicalStaff\PhysicianService;
use App\Modules\MedicalStaff\SpecialtyRepository;
use Mockery;
use Tests\TestCase;

class PhysicianServiceTest extends TestCase
{
    public function test_a_duplicate_license_is_rejected_before_writes(): void
    {
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $physicians->shouldReceive('licenseTaken')->once()->with('MP-1', null)->andReturn(true);
        $physicians->shouldReceive('createPhysician')->never();
        $specialties->shouldReceive('getSpecialtyById')->never();

        try {
            (new PhysicianService($physicians, $specialties))->createPhysician([
                'registro_profesional' => 'MP-1',
                'nombres' => 'Luis',
                'apellidos' => 'Soto',
                'activo' => true,
                'especialidades' => [3],
            ]);
            $this->fail('Expected DuplicateProfessionalLicense');
        } catch (DuplicateProfessionalLicense) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_physician_requires_at_least_one_specialty(): void
    {
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $physicians->shouldReceive('licenseTaken')->once()->with('MP-1', null)->andReturn(false);
        $physicians->shouldReceive('createPhysician')->never();

        try {
            (new PhysicianService($physicians, $specialties))->createPhysician([
                'registro_profesional' => 'MP-1',
                'nombres' => 'Luis',
                'apellidos' => 'Soto',
                'activo' => true,
                'especialidades' => [],
            ]);
            $this->fail('Expected InvalidPhysicianInput');
        } catch (InvalidPhysicianInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_inactive_specialty_cannot_be_newly_associated(): void
    {
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $physicians->shouldReceive('licenseTaken')->once()->with('MP-1', null)->andReturn(false);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)->andReturn(
            (object) ['id_especialidad' => 3, 'nombre' => 'Pediatría', 'activo' => false]
        );
        $physicians->shouldReceive('createPhysician')->never();

        try {
            (new PhysicianService($physicians, $specialties))->createPhysician([
                'registro_profesional' => 'MP-1',
                'nombres' => 'Luis',
                'apellidos' => 'Soto',
                'activo' => true,
                'especialidades' => [3],
            ]);
            $this->fail('Expected InactiveSpecialtyAssociation');
        } catch (InactiveSpecialtyAssociation) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_linking_an_account_does_not_rewrite_contact_fields(): void
    {
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)->andReturn(
            (object) ['id_medico' => 4, 'id_usuario' => null, 'activo' => true]
        );
        $physicians->shouldReceive('getSpecialtiesForPhysicians')->once()->andReturn(collect());
        $physicians->shouldReceive('getSpecialtyIdsForPhysician')->once()->with(4)->andReturn([3]);
        $physicians->shouldReceive('userAccountTaken')->once()->with(22, 4)->andReturn(false);
        $physicians->shouldReceive('getLinkableStaffAccounts')->once()->with(4)->andReturn(collect([
            (object) ['id_usuario' => 22, 'nombre_usuario' => 'demo.medico', 'rol_nombre' => 'Médico'],
        ]));
        $physicians->shouldReceive('setPhysicianUserAccount')->once()->with(4, 22);
        $physicians->shouldReceive('updatePhysician')->never();

        (new PhysicianService($physicians, $specialties))->linkPhysicianAccount(4, 22);

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_physician_already_linked_to_another_account_is_rejected(): void
    {
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)->andReturn(
            (object) ['id_medico' => 4, 'id_usuario' => 9, 'activo' => true]
        );
        $physicians->shouldReceive('getSpecialtiesForPhysicians')->once()->andReturn(collect());
        $physicians->shouldReceive('getSpecialtyIdsForPhysician')->once()->with(4)->andReturn([3]);
        $physicians->shouldReceive('setPhysicianUserAccount')->never();

        try {
            (new PhysicianService($physicians, $specialties))->linkPhysicianAccount(4, 22);
            $this->fail('Expected InvalidPhysicianInput');
        } catch (InvalidPhysicianInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }
}
