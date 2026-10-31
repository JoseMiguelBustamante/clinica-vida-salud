<?php

namespace Tests\Unit;

use App\Modules\Patients\DuplicatePatientDocument;
use App\Modules\Patients\InvalidPatientInput;
use App\Modules\Patients\PatientRepository;
use App\Modules\Patients\PatientService;
use Mockery;
use Tests\TestCase;

class PatientServiceTest extends TestCase
{
    public function test_empty_document_is_stored_as_null_and_is_not_checked_for_duplicates(): void
    {
        $repository = Mockery::mock(PatientRepository::class);
        $repository->shouldReceive('documentTaken')->never();
        $repository->shouldReceive('createPatient')->once()->with(Mockery::on(function (array $values): bool {
            return $values['ci'] === null
                && $values['nombres'] === 'Ana'
                && $values['apellidos'] === 'Rojas'
                && $values['activo'] === true
                && isset($values['fecha_registro']);
        }))->andReturn(8);

        $id = (new PatientService($repository))->createPatient([
            'ci' => '',
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
        ]);

        $this->assertSame(8, $id);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_duplicate_document_is_rejected_before_insert(): void
    {
        $repository = Mockery::mock(PatientRepository::class);
        $repository->shouldReceive('documentTaken')->once()->with('123', null)->andReturn(true);
        $repository->shouldReceive('createPatient')->never();

        try {
            (new PatientService($repository))->createPatient([
                'ci' => '123',
                'nombres' => 'Ana',
                'apellidos' => 'Rojas',
            ]);
            $this->fail('Expected DuplicatePatientDocument');
        } catch (DuplicatePatientDocument) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_future_birth_date_is_rejected(): void
    {
        $repository = Mockery::mock(PatientRepository::class);
        $repository->shouldReceive('documentTaken')->never();
        $repository->shouldReceive('createPatient')->never();

        try {
            (new PatientService($repository))->createPatient([
                'nombres' => 'Ana',
                'apellidos' => 'Rojas',
                'fecha_nacimiento' => '2999-01-01',
            ]);
            $this->fail('Expected InvalidPatientInput');
        } catch (InvalidPatientInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }
}
