<?php

namespace Tests\Unit;

use App\Modules\Appointments\AppointmentAttendanceNotAllowed;
use App\Modules\Appointments\AppointmentNotAttendable;
use App\Modules\Appointments\AppointmentNotCancellable;
use App\Modules\Appointments\AppointmentNotReschedulable;
use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Appointments\AppointmentService;
use App\Modules\Appointments\AvailabilityService;
use App\Modules\Appointments\InvalidAvailabilityInput;
use App\Modules\Appointments\PatientNotEligible;
use App\Modules\Appointments\PhysicianNotEligible;
use App\Modules\Appointments\SlotUnavailable;
use App\Modules\MedicalStaff\PhysicianRepository;
use App\Modules\MedicalStaff\SpecialtyRepository;
use App\Modules\Patients\PatientNotFound;
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

    public function test_a_cancelled_appointment_cannot_be_rescheduled(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Cancelada'));
        $appointments->shouldReceive('updateAppointmentSchedule')->never();

        try {
            $this->service($appointments, $patients)->rescheduleAppointment(21, $this->reschedulePayload(), 17);
            $this->fail('Expected AppointmentNotReschedulable');
        } catch (AppointmentNotReschedulable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_same_interval_is_rejected_before_writes(): void
    {
        $day = Carbon::now('UTC')->addDays(10)->startOfDay();
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Programada', $day));
        $appointments->shouldReceive('lockPhysicianAppointments')->never();
        $appointments->shouldReceive('updateAppointmentSchedule')->never();

        try {
            $this->service($appointments, $patients)->rescheduleAppointment(21, [
                'fecha' => $day->toDateString(),
                'hora_inicio' => '08:00',
                'hora_fin' => '08:30',
            ], 17);
            $this->fail('Expected InvalidAvailabilityInput');
        } catch (InvalidAvailabilityInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_confirmed_appointment_returns_to_programada(): void
    {
        $day = Carbon::now('UTC')->addDays(10)->startOfDay();
        $weekday = ScheduleService::WEEKDAYS[$day->isoWeekday() - 1];
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $row = $this->storedAppointment('Confirmada', $day);
        $appointments->shouldReceive('getAppointmentById')->twice()->with(21)->andReturn($row);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->once()->with(4, 3)->andReturn(true);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->once()->with(4, $weekday)
            ->andReturn(collect([
                (object) ['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita_minutos' => 30],
            ]));
        $appointments->shouldReceive('getOccupyingAppointments')->once()
            ->withArgs(fn (int $physicianId, string $start, string $end, ?int $except): bool => $physicianId === 4 && $except === 21)
            ->andReturn(collect());
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('updateAppointmentSchedule')->once()
            ->with(21, Mockery::type('string'), Mockery::type('string'), 'Programada');
        $appointments->shouldReceive('createAppointmentEvent')->once()->with(Mockery::on(function (array $values): bool {
            return $values['tipo'] === 'Reprogramacion'
                && $values['id_cita'] === 21
                && $values['id_usuario'] === 17;
        }));

        $result = $this->service($appointments, $patients, $physicians, $specialties)
            ->rescheduleAppointment(21, $this->reschedulePayload($day->toDateString()), 17);

        $this->assertSame(21, $result['appointment_id']);
        $this->assertTrue($result['required_new_confirmation']);
    }

    public function test_an_occupied_new_slot_does_not_change_the_appointment(): void
    {
        $day = Carbon::now('UTC')->addDays(10)->startOfDay();
        $weekday = ScheduleService::WEEKDAYS[$day->isoWeekday() - 1];
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $appointments->shouldReceive('getAppointmentById')->twice()->with(21)
            ->andReturn($this->storedAppointment('Confirmada', $day));
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->once()->with(4, 3)->andReturn(true);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->once()->with(4, $weekday)
            ->andReturn(collect([
                (object) ['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita_minutos' => 30],
            ]));
        $from = $day->copy()->setTime(8, 30);
        $until = $day->copy()->setTime(9, 0);
        $appointments->shouldReceive('getOccupyingAppointments')->once()->andReturn(collect([
            (object) ['id_cita' => 99, 'inicio' => $from->toIso8601String(), 'fin' => $until->toIso8601String(), 'estado' => 'Programada'],
        ]));
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('updateAppointmentSchedule')->never();

        try {
            $this->service($appointments, $patients, $physicians, $specialties)
                ->rescheduleAppointment(21, $this->reschedulePayload($day->toDateString()), 17);
            $this->fail('Expected SlotUnavailable');
        } catch (SlotUnavailable) {
        }
    }

    public function test_an_attended_appointment_cannot_be_cancelled(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Atendida'));
        $appointments->shouldReceive('cancelAppointment')->never();

        try {
            $this->service($appointments, $patients)->cancelAppointment(21, 'Cambio de planes', 17);
            $this->fail('Expected AppointmentNotCancellable');
        } catch (AppointmentNotCancellable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_cancelled_appointment_cannot_be_cancelled_again(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Cancelada'));
        $appointments->shouldReceive('cancelAppointment')->never();

        try {
            $this->service($appointments, $patients)->cancelAppointment(21, null, 17);
            $this->fail('Expected AppointmentNotCancellable');
        } catch (AppointmentNotCancellable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_programada_appointment_is_cancelled_and_frees_the_slot(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $row = $this->storedAppointment('Programada');
        $appointments->shouldReceive('getAppointmentById')->twice()->with(21)->andReturn($row);
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('cancelAppointment')->once()->with(21, 'Cambio de planes');
        $appointments->shouldReceive('discardPendingReminders')->once()->with(21);
        $appointments->shouldReceive('createAppointmentEvent')->once()->with(Mockery::on(function (array $values): bool {
            return $values['tipo'] === 'Cancelacion'
                && $values['id_cita'] === 21
                && $values['motivo'] === 'Cambio de planes'
                && $values['id_usuario'] === 17;
        }));

        $this->service($appointments, $patients)->cancelAppointment(21, 'Cambio de planes', 17);
    }

    public function test_a_programada_appointment_can_be_confirmed(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $row = $this->storedAppointment('Programada');
        $appointments->shouldReceive('getAppointmentById')->twice()->with(21)->andReturn($row);
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('confirmAppointment')->once()->with(21);
        $appointments->shouldReceive('createAppointmentEvent')->once()->with(Mockery::on(function (array $values): bool {
            return $values['tipo'] === 'Confirmacion'
                && $values['id_cita'] === 21
                && $values['id_usuario'] === 17;
        }));

        $this->service($appointments, $patients)->confirmAppointment(21, 17);
    }

    public function test_a_cancelled_appointment_cannot_be_confirmed(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Cancelada'));
        $appointments->shouldReceive('confirmAppointment')->never();

        try {
            $this->service($appointments, $patients)->confirmAppointment(21, 17);
            $this->fail('Expected AppointmentAttendanceNotAllowed');
        } catch (AppointmentAttendanceNotAllowed) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_confirmed_appointment_is_not_confirmed_again(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Confirmada'));
        $appointments->shouldReceive('confirmAppointment')->never();

        try {
            $this->service($appointments, $patients)->confirmAppointment(21, 17);
            $this->fail('Expected AppointmentAttendanceNotAllowed');
        } catch (AppointmentAttendanceNotAllowed) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_arrival_does_not_mark_the_appointment_attended(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $row = $this->storedAppointment('Confirmada');
        $appointments->shouldReceive('getAppointmentById')->twice()->with(21)->andReturn($row);
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('registerAppointmentArrival')->once()->with(21, Mockery::type('string'));
        $appointments->shouldReceive('confirmAppointment')->never();
        $appointments->shouldReceive('registerAppointmentAbsence')->never();
        $appointments->shouldReceive('createAppointmentEvent')->once()->with(Mockery::on(function (array $values): bool {
            return $values['tipo'] === 'Llegada' && $values['id_cita'] === 21;
        }));

        $this->service($appointments, $patients)->registerAppointmentArrival(21, 17);
    }

    public function test_absence_before_the_end_is_rejected(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Programada'));
        $appointments->shouldReceive('registerAppointmentAbsence')->never();

        try {
            $this->service($appointments, $patients)->registerAppointmentAbsence(21, 17);
            $this->fail('Expected AppointmentAttendanceNotAllowed');
        } catch (AppointmentAttendanceNotAllowed) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_absence_is_rejected_when_arrival_is_known(): void
    {
        $day = Carbon::now('UTC')->subDay()->startOfDay();
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $row = $this->storedAppointment('Confirmada', $day);
        $row->fecha_hora_llegada = $day->copy()->setTime(8, 5)->toIso8601String();
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($row);
        $appointments->shouldReceive('registerAppointmentAbsence')->never();

        try {
            $this->service($appointments, $patients)->registerAppointmentAbsence(21, 17);
            $this->fail('Expected AppointmentAttendanceNotAllowed');
        } catch (AppointmentAttendanceNotAllowed) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_absence_after_the_end_without_arrival_is_recorded(): void
    {
        $day = Carbon::now('UTC')->subDay()->startOfDay();
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $row = $this->storedAppointment('Programada', $day);
        $appointments->shouldReceive('getAppointmentById')->twice()->with(21)->andReturn($row);
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('registerAppointmentAbsence')->once()->with(21);
        $appointments->shouldReceive('createAppointmentEvent')->once()->with(Mockery::on(function (array $values): bool {
            return $values['tipo'] === 'Inasistencia' && $values['id_cita'] === 21;
        }));

        $this->service($appointments, $patients)->registerAppointmentAbsence(21, 17);
    }

    public function test_a_confirmed_appointment_can_be_marked_attended(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $row = $this->storedAppointment('Confirmada');
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true, 'id_usuario' => 17]);
        $appointments->shouldReceive('getAppointmentById')->twice()->with(21)->andReturn($row);
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
        $appointments->shouldReceive('lockPhysicianAppointments')->once()->with(4);
        $appointments->shouldReceive('markAppointmentAttended')->once()->with(21, Mockery::type('string'));
        $appointments->shouldReceive('createCareRecord')->once()->with(Mockery::on(function (array $values): bool {
            return $values['id_cita'] === 21
                && $values['id_medico'] === 4
                && $values['resumen_atencion'] === 'Consulta realizada.'
                && $values['diagnostico'] === null;
        }));
        $appointments->shouldReceive('discardPendingReminders')->once()->with(21);
        $appointments->shouldReceive('createAppointmentEvent')->once()->with(Mockery::on(function (array $values): bool {
            return $values['tipo'] === 'Atencion' && $values['id_cita'] === 21 && $values['id_usuario'] === 17;
        }));

        $this->service($appointments, $patients)->recordAppointmentCare(21, [], 17);
    }

    public function test_a_cancelled_appointment_cannot_be_marked_attended(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true, 'id_usuario' => 17]);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Cancelada'));
        $appointments->shouldReceive('markAppointmentAttended')->never();

        try {
            $this->service($appointments, $patients)->recordAppointmentCare(21, [], 17);
            $this->fail('Expected AppointmentNotAttendable');
        } catch (AppointmentNotAttendable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_absence_cannot_be_marked_attended(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true, 'id_usuario' => 17]);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Inasistencia'));
        $appointments->shouldReceive('markAppointmentAttended')->never();

        try {
            $this->service($appointments, $patients)->recordAppointmentCare(21, [], 17);
            $this->fail('Expected AppointmentNotAttendable');
        } catch (AppointmentNotAttendable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_already_attended_appointment_is_not_repeated(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true, 'id_usuario' => 17]);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Atendida'));
        $appointments->shouldReceive('createCareRecord')->never();

        try {
            $this->service($appointments, $patients)->recordAppointmentCare(21, [], 17);
            $this->fail('Expected AppointmentNotAttendable');
        } catch (AppointmentNotAttendable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_another_physician_cannot_mark_the_appointment_attended(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)
            ->andReturn((object) ['id_medico' => 9, 'activo' => true, 'id_usuario' => 17]);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn($this->storedAppointment('Confirmada'));
        $appointments->shouldReceive('markAppointmentAttended')->never();

        try {
            $this->service($appointments, $patients)->recordAppointmentCare(21, [], 17);
            $this->fail('Expected AppointmentNotAttendable');
        } catch (AppointmentNotAttendable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_unlinked_account_cannot_mark_attended(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)->andReturn(null);
        $appointments->shouldReceive('getAppointmentById')->never();
        $appointments->shouldReceive('markAppointmentAttended')->never();

        try {
            $this->service($appointments, $patients)->recordAppointmentCare(21, [], 17);
            $this->fail('Expected AppointmentNotAttendable');
        } catch (AppointmentNotAttendable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_physician_agenda_query_uses_the_linked_physician_and_date(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true, 'id_usuario' => 17]);
        $appointments->shouldReceive('getRegisteredPhysicians')->never();
        $appointments->shouldReceive('getAppointmentsForPhysicianOnDate')->once()
            ->withArgs(function (int $physicianId, string $start, string $end, ?string $status): bool {
                return $physicianId === 4
                    && str_starts_with($start, '2026-11-01')
                    && str_starts_with($end, '2026-11-02')
                    && $status === 'Confirmada';
            })
            ->andReturn(collect([(object) ['id_cita' => 21, 'estado' => 'Confirmada']]));

        $result = $this->service($appointments, $patients)->consultPhysicianAgenda(17, [
            'fecha' => '2026-11-01',
            'id_medico' => 9,
            'estado' => 'Confirmada',
        ], false);

        $this->assertSame(4, $result['physician_id']);
        $this->assertCount(1, $result['appointments']);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_missing_selected_physician_is_rejected_before_listing(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)->andReturn(null);
        $appointments->shouldReceive('getRegisteredPhysicians')->once()->andReturn(collect());
        $appointments->shouldReceive('getRegisteredPhysicianById')->once()->with(9)->andReturn(null);
        $appointments->shouldReceive('getAppointmentsForPhysicianOnDate')->never();

        try {
            $this->service($appointments, $patients)->consultPhysicianAgenda(17, [
                'fecha' => '2026-11-01',
                'id_medico' => 9,
            ], true);
            $this->fail('Expected PhysicianNotEligible');
        } catch (PhysicianNotEligible) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_agenda_without_a_date_does_not_list_appointments(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $appointments->shouldReceive('getPhysicianByUserId')->once()->with(17)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true, 'id_usuario' => 17]);
        $appointments->shouldReceive('getAppointmentsForPhysicianOnDate')->never();

        $result = $this->service($appointments, $patients)->consultPhysicianAgenda(17, [], false);

        $this->assertNull($result['appointments']);
        $this->assertSame(4, $result['physician_id']);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_missing_patient_is_rejected_before_listing_history(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getAllPatients')->never();
        $patients->shouldReceive('getPatientById')->once()->with(8)->andReturn(null);
        $appointments->shouldReceive('getAppointmentsForPatient')->never();

        try {
            $this->service($appointments, $patients)->consultPatientHistory([
                'id_paciente' => 8,
            ]);
            $this->fail('Expected PatientNotFound');
        } catch (PatientNotFound) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_patient_history_query_passes_the_optional_status(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getAllPatients')->never();
        $patients->shouldReceive('getPatientById')->once()->with(8)
            ->andReturn((object) ['id_paciente' => 8, 'nombres' => 'Ana', 'apellidos' => 'Rojas', 'activo' => false]);
        $appointments->shouldReceive('getAppointmentsForPatient')->once()->with(8, 'Atendida')
            ->andReturn(collect([(object) ['id_cita' => 21, 'estado' => 'Atendida']]));

        $result = $this->service($appointments, $patients)->consultPatientHistory([
            'id_paciente' => 8,
            'estado' => 'Atendida',
        ]);

        $this->assertSame(8, (int) $result['patient']->id_paciente);
        $this->assertCount(1, $result['appointments']);
        $this->assertSame('Atendida', $result['estado']);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_patient_history_without_a_patient_does_not_list_appointments(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getAllPatients')->once()->with('Rojas')
            ->andReturn(collect([(object) ['id_paciente' => 8, 'nombres' => 'Ana', 'apellidos' => 'Rojas']]));
        $patients->shouldReceive('getPatientById')->never();
        $appointments->shouldReceive('getAppointmentsForPatient')->never();

        $result = $this->service($appointments, $patients)->consultPatientHistory([
            'q' => 'Rojas',
        ]);

        $this->assertCount(1, $result['matches']);
        $this->assertNull($result['patient']);
        $this->assertNull($result['appointments']);
        $this->assertSame([], $this->app['db']->getConnections());
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

    /**
     * @return array{fecha: string, hora_inicio: string, hora_fin: string}
     */
    private function reschedulePayload(?string $date = null): array
    {
        return [
            'fecha' => $date ?? Carbon::now('UTC')->addDays(10)->toDateString(),
            'hora_inicio' => '08:30',
            'hora_fin' => '09:00',
        ];
    }

    private function storedAppointment(string $status, ?Carbon $day = null): object
    {
        $day ??= Carbon::now('UTC')->addDays(10)->startOfDay();

        return (object) [
            'id_cita' => 21,
            'codigo' => 'CIT-1',
            'inicio' => $day->copy()->setTime(8, 0)->toIso8601String(),
            'fin' => $day->copy()->setTime(8, 30)->toIso8601String(),
            'estado' => $status,
            'id_paciente' => 8,
            'id_medico' => 4,
            'id_especialidad' => 3,
            'fecha_hora_llegada' => null,
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
