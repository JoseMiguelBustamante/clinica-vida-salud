<?php

namespace Tests\Unit;

use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Appointments\InvalidReportInput;
use App\Modules\Appointments\ReportCriterionInvalid;
use App\Modules\Appointments\ReportService;
use App\Modules\MedicalStaff\SpecialtyRepository;
use App\Modules\Patients\PatientRepository;
use Mockery;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    public function test_a_report_without_generate_does_not_list_rows(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $appointments->shouldReceive('getRegisteredPhysicians')->once()->andReturn(collect());
        $specialties->shouldReceive('getAllSpecialties')->once()->with(null)->andReturn(collect());
        $appointments->shouldReceive('getAppointmentsInRange')->never();

        $result = $this->service($appointments, $patients, $specialties)->generateAdministrativeReport([
            'tipo' => ReportService::TYPE_APPOINTMENTS_ON_DATE,
            'fecha' => '2026-11-01',
        ], false);

        $this->assertFalse($result['generated']);
        $this->assertNull($result['rows']);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_missing_physician_is_rejected_before_listing(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $appointments->shouldReceive('getRegisteredPhysicians')->once()->andReturn(collect());
        $specialties->shouldReceive('getAllSpecialties')->once()->with(null)->andReturn(collect());
        $appointments->shouldReceive('getRegisteredPhysicianById')->once()->with(9)->andReturn(null);
        $appointments->shouldReceive('getAppointmentsInRange')->never();

        try {
            $this->service($appointments, $patients, $specialties)->generateAdministrativeReport([
                'tipo' => ReportService::TYPE_APPOINTMENTS_BY_PHYSICIAN,
                'id_medico' => 9,
                'fecha_inicio' => '2026-11-01',
                'fecha_fin' => '2026-11-02',
            ], true);
            $this->fail('Expected ReportCriterionInvalid');
        } catch (ReportCriterionInvalid) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_reversed_period_is_rejected_before_listing(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $appointments->shouldReceive('getRegisteredPhysicians')->once()->andReturn(collect());
        $specialties->shouldReceive('getAllSpecialties')->once()->with(null)->andReturn(collect());
        $appointments->shouldReceive('getAppointmentStatusCountsInRange')->never();

        try {
            $this->service($appointments, $patients, $specialties)->generateAdministrativeReport([
                'tipo' => ReportService::TYPE_PERIOD_TOTALS,
                'fecha_inicio' => '2026-11-02',
                'fecha_fin' => '2026-11-01',
            ], true);
            $this->fail('Expected InvalidReportInput');
        } catch (InvalidReportInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_period_totals_fill_missing_states_with_zero_after_a_successful_query(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $appointments->shouldReceive('getRegisteredPhysicians')->once()->andReturn(collect());
        $specialties->shouldReceive('getAllSpecialties')->once()->with(null)->andReturn(collect());
        $appointments->shouldReceive('getAppointmentStatusCountsInRange')->once()
            ->withArgs(function (string $start, string $end): bool {
                return str_starts_with($start, '2026-11-01') && str_starts_with($end, '2026-11-03');
            })
            ->andReturn(collect([
                (object) ['estado' => 'Atendida', 'total' => 4],
            ]));

        $result = $this->service($appointments, $patients, $specialties)->generateAdministrativeReport([
            'tipo' => ReportService::TYPE_PERIOD_TOTALS,
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-02',
        ], true);

        $this->assertTrue($result['generated']);
        $this->assertSame(4, $result['totals']['Atendida']);
        $this->assertSame(0, $result['totals']['Cancelada']);
        $this->assertSame(0, $result['totals']['Inasistencia']);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_registered_patients_do_not_query_appointments(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $appointments->shouldReceive('getRegisteredPhysicians')->once()->andReturn(collect());
        $specialties->shouldReceive('getAllSpecialties')->once()->with(null)->andReturn(collect());
        $patients->shouldReceive('getAllPatients')->once()->with(null)->andReturn(collect([
            (object) ['id_paciente' => 8, 'nombres' => 'Ana', 'apellidos' => 'Rojas', 'activo' => true],
            (object) ['id_paciente' => 9, 'nombres' => 'Luis', 'apellidos' => 'Soto', 'activo' => false],
        ]));
        $appointments->shouldReceive('getAppointmentsInRange')->never();

        $result = $this->service($appointments, $patients, $specialties)->generateAdministrativeReport([
            'tipo' => ReportService::TYPE_REGISTERED_PATIENTS,
        ], true);

        $this->assertSame(2, $result['totals']['pacientes']);
        $this->assertSame(1, $result['totals']['activos']);
        $this->assertSame(1, $result['totals']['inactivos']);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function service(
        AppointmentRepository $appointments,
        PatientRepository $patients,
        SpecialtyRepository $specialties,
    ): ReportService {
        return new ReportService($appointments, $patients, $specialties);
    }
}
