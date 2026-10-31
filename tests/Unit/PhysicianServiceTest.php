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
}
