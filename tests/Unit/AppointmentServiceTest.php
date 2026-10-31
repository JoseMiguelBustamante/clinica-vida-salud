<?php

namespace Tests\Unit;

use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Appointments\AppointmentService;
use App\Modules\Appointments\AvailabilityService;
use App\Modules\Appointments\PatientNotEligible;
use App\Modules\Appointments\SlotUnavailable;
use App\Modules\MedicalStaff\PhysicianRepository;
use App\Modules\MedicalStaff\SpecialtyRepository;
use App\Modules\Patients\PatientRepository;
use App\Modules\Schedules\ScheduleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    public function test_an_inactive_patient_is_rejected_before_writes(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientById')->once()->with(8)
            ->andReturn((object) ['id_paciente' => 8, 'activo' => false]);
        $appointments->shouldReceive('createAppointment')->never();

        try {
            $this->service($appointments, $patients)->createAppointment($this->payload(), 17);
            $this->fail('Expected PatientNotEligible');
        } catch (PatientNotEligible) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_missing_patient_is_rejected_before_writes(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientById')->once()->with(8)->andReturn(null);
        $appointments->shouldReceive('createAppointment')->never();

        try {
            $this->service($appointments, $patients)->createAppointment($this->payload(), 17);
            $this->fail('Expected PatientNotEligible');
        } catch (PatientNotEligible) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_cu22_rejection_does_not_insert_an_appointment(): void
    {
        $day = Carbon::now('UTC')->addDays(10)->startOfDay();
        $weekday = ScheduleService::WEEKDAYS[$day->isoWeekday() - 1];
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $patients->shouldReceive('getPatientById')->twice()->with(8)
            ->andReturn((object) ['id_paciente' => 8, 'activo' => true]);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->once()->with(4, 3)->andReturn(true);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->once()->with(4, $weekday)
            ->andReturn(collect([
                (object) ['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita_minutos' => 30],
            ]));
        $from = $day->copy()->setTime(8, 0);
        $until = $day->copy()->setTime(8, 30);
        $appointments->shouldReceive('getOccupyingAppointments')->once()->andReturn(collect([
            (object) ['inicio' => $from->toIso8601String(), 'fin' => $until->toIso8601String(), 'estado' => 'Programada'],
        ]));
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('createAppointment')->never();

        try {
            $this->service($appointments, $patients, $physicians, $specialties)
                ->createAppointment($this->payload($day->toDateString()), 17);
            $this->fail('Expected SlotUnavailable');
        } catch (SlotUnavailable) {
        }
    }

    public function test_a_valid_slot_is_stored_as_programada(): void
    {
        $day = Carbon::now('UTC')->addDays(10)->startOfDay();
        $weekday = ScheduleService::WEEKDAYS[$day->isoWeekday() - 1];
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $patients->shouldReceive('getPatientById')->twice()->with(8)
            ->andReturn((object) ['id_paciente' => 8, 'activo' => true]);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->once()->with(4, 3)->andReturn(true);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->once()->with(4, $weekday)
            ->andReturn(collect([
                (object) ['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita_minutos' => 30],
            ]));
        $appointments->shouldReceive('getOccupyingAppointments')->once()->andReturn(collect());
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('appointmentCodeTaken')->once()->andReturn(false);
        $appointments->shouldReceive('createAppointment')->once()->with(Mockery::on(function (array $values) use ($day): bool {
            return $values['estado'] === 'Programada'
                && $values['modalidad'] === 'Presencial'
                && $values['id_paciente'] === 8
                && $values['id_medico'] === 4
                && $values['id_especialidad'] === 3
                && str_starts_with($values['codigo'], 'CIT-')
                && str_starts_with($values['inicio'], $day->toDateString());
        }))->andReturn(21);
        $appointments->shouldReceive('createAppointmentEvent')->once()->with(Mockery::on(function (array $values): bool {
            return $values['tipo'] === 'Programacion'
                && $values['id_cita'] === 21
                && $values['id_usuario'] === 17;
        }));

        $id = $this->service($appointments, $patients, $physicians, $specialties)
            ->createAppointment($this->payload($day->toDateString()), 17);

        $this->assertSame(21, $id);
    }

    /**
     * @return array{id_paciente: int, id_especialidad: int, id_medico: int, fecha: string, hora_inicio: string, hora_fin: string, modalidad: string, motivo_consulta: ?string}
     */
    private function payload(?string $date = null): array
    {
        return [
            'id_paciente' => 8,
            'id_especialidad' => 3,
            'id_medico' => 4,
            'fecha' => $date ?? Carbon::now('UTC')->addDays(10)->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin' => '08:30',
            'modalidad' => 'Presencial',
            'motivo_consulta' => 'Control',
        ];
    }

    private function service(
        AppointmentRepository $appointments,
        PatientRepository $patients,
        ?PhysicianRepository $physicians = null,
        ?SpecialtyRepository $specialties = null,
    ): AppointmentService {
        return new AppointmentService(
            $appointments,
            new AvailabilityService(
                $appointments,
                $physicians ?? Mockery::mock(PhysicianRepository::class),
                $specialties ?? Mockery::mock(SpecialtyRepository::class),
            ),
            $patients,
        );
    }
}
