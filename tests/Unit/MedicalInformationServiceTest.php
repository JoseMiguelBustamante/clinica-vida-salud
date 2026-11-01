<?php

namespace Tests\Unit;

use App\Modules\Patients\DeclaredInformationUnavailable;
use App\Modules\Patients\InvalidPatientInput;
use App\Modules\Patients\MedicalInformationService;
use App\Modules\Patients\PatientLinkRequired;
use App\Modules\Patients\PatientRepository;
use Mockery;
use Tests\TestCase;

class MedicalInformationServiceTest extends TestCase
{
    public function test_a_missing_patient_link_is_rejected_before_writes(): void
    {
        $repository = Mockery::mock(PatientRepository::class);
        $repository->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn(null);
        $repository->shouldReceive('getMedicalInformationByPatientId')->never();
        $repository->shouldReceive('upsertMedicalInformation')->never();

        try {
            (new MedicalInformationService($repository))->getDeclaredMedicalInformation(17);
            $this->fail('Expected PatientLinkRequired');
        } catch (PatientLinkRequired) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_invalid_blood_group_is_rejected_before_writes(): void
    {
        $repository = Mockery::mock(PatientRepository::class);
        $repository->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn($this->linkedPatient());
        $repository->shouldReceive('upsertMedicalInformation')->never();

        try {
            (new MedicalInformationService($repository))->updateDeclaredMedicalInformation(17, [
                'grupo_sanguineo' => 'XX',
            ]);
            $this->fail('Expected InvalidPatientInput');
        } catch (InvalidPatientInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_first_declaration_is_upserted_without_professional_fields(): void
    {
        $repository = Mockery::mock(PatientRepository::class);
        $repository->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn($this->linkedPatient());
        $repository->shouldReceive('upsertMedicalInformation')->once()
            ->with(8, Mockery::on(function (array $values): bool {
                return $values['grupo_sanguineo'] === 'O+'
                    && $values['detalle_alergias'] === 'Polen'
                    && $values['situacion_alergias'] === 'Conocidas'
                    && isset($values['fecha_actualizacion'])
                    && ! array_key_exists('diagnostico', $values)
                    && ! array_key_exists('resumen_atencion', $values);
            }));

        (new MedicalInformationService($repository))->updateDeclaredMedicalInformation(17, [
            'grupo_sanguineo' => 'O+',
            'situacion_alergias' => 'Conocidas',
            'detalle_alergias' => 'Polen',
            'diagnostico' => 'No debe guardarse',
        ]);

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_disabled_portal_rejects_declared_information(): void
    {
        config(['identity.patient_portal' => false]);
        $repository = Mockery::mock(PatientRepository::class);
        $repository->shouldReceive('getPatientByAccount')->never();
        $repository->shouldReceive('upsertMedicalInformation')->never();

        try {
            (new MedicalInformationService($repository))->updateDeclaredMedicalInformation(17, [
                'grupo_sanguineo' => 'O+',
            ]);
            $this->fail('Expected DeclaredInformationUnavailable');
        } catch (DeclaredInformationUnavailable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function linkedPatient(): object
    {
        return (object) [
            'id_paciente' => 8,
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'id_usuario' => 17,
            'activo' => true,
        ];
    }
}
