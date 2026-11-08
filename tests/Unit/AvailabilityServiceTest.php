<?php

namespace Tests\Unit;

use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Appointments\AvailabilityService;
use App\Modules\Appointments\InvalidAvailabilityInput;
use App\Modules\Appointments\PhysicianNotEligible;
use App\Modules\Appointments\SlotUnavailable;
use App\Modules\MedicalStaff\PhysicianRepository;
use App\Modules\MedicalStaff\SpecialtyRepository;
use App\Modules\Schedules\ScheduleService;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    public function test_an_inactive_physician_cannot_be_offered(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => false]);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->never();

        try {
            (new AvailabilityService($appointments, $physicians, $specialties))
                ->getAvailableSlots(3, 4, Carbon::now('UTC')->addDay()->toDateString());
            $this->fail('Expected PhysicianNotEligible');
        } catch (PhysicianNotEligible) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_past_date_is_rejected(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->once()->with(4, 3)->andReturn(true);

        try {
            (new AvailabilityService($appointments, $physicians, $specialties))
                ->getAvailableSlots(3, 4, '2020-01-01');
            $this->fail('Expected InvalidAvailabilityInput');
        } catch (InvalidAvailabilityInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_occupied_and_cancelled_intervals_are_handled(): void
    {
        $day = Carbon::now('UTC')->addDays(10)->startOfDay();
        $weekday = ScheduleService::WEEKDAYS[$day->isoWeekday() - 1];
        $appointments = Mockery::mock(AppointmentRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->once()->with(4, 3)->andReturn(true);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->once()->with(4, $weekday)
            ->andReturn(collect([
                (object) [
                    'hora_inicio' => '08:00:00',
                    'hora_fin' => '09:00:00',
                    'duracion_cita_minutos' => 30,
                ],
            ]));
        $appointments->shouldReceive('getOccupyingAppointments')->once()->andReturn(collect([
            (object) [
                'inicio' => $day->copy()->setTime(8, 0)->toIso8601String(),
                'fin' => $day->copy()->setTime(8, 30)->toIso8601String(),
                'estado' => 'Programada',
            ],
        ]));

        $slots = (new AvailabilityService($appointments, $physicians, $specialties))
            ->getAvailableSlots(3, 4, $day->toDateString());

        $this->assertSame([['hora_inicio' => '08:30', 'hora_fin' => '09:00']], $slots);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_board_slots_keep_the_physician_and_date(): void
    {
        $day = Carbon::now('UTC')->addDays(10)->startOfDay();
        $weekday = ScheduleService::WEEKDAYS[$day->isoWeekday() - 1];
        $appointments = Mockery::mock(AppointmentRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->once()->with(4, 3)->andReturn(true);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->once()->with(4, $weekday)
            ->andReturn(collect([
                (object) [
                    'hora_inicio' => '08:00:00',
                    'hora_fin' => '09:00:00',
                    'duracion_cita_minutos' => 30,
                ],
            ]));
        $appointments->shouldReceive('getOccupyingAppointments')->once()->andReturn(collect());

        $slots = (new AvailabilityService($appointments, $physicians, $specialties))
            ->getAvailableSlotsForDates(3, [4], [$day->toDateString()]);

        $this->assertSame([
            [
                'fecha' => $day->toDateString(),
                'hora_inicio' => '08:00',
                'hora_fin' => '08:30',
                'id_medico' => 4,
            ],
            [
                'fecha' => $day->toDateString(),
                'hora_inicio' => '08:30',
                'hora_fin' => '09:00',
                'id_medico' => 4,
            ],
        ], $slots);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_cu22_rejects_an_occupied_slot(): void
    {
        $day = Carbon::now('UTC')->addDays(10)->startOfDay();
        $weekday = ScheduleService::WEEKDAYS[$day->isoWeekday() - 1];
        $appointments = Mockery::mock(AppointmentRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $specialties->shouldReceive('getSpecialtyById')->once()->with(3)
            ->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->once()->with(4, 3)->andReturn(true);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->once()->with(4, $weekday)
            ->andReturn(collect([
                (object) [
                    'hora_inicio' => '08:00:00',
                    'hora_fin' => '09:00:00',
                    'duracion_cita_minutos' => 30,
                ],
            ]));
        $from = $day->copy()->setTime(8, 0);
        $until = $day->copy()->setTime(8, 30);
        $appointments->shouldReceive('getOccupyingAppointments')->once()->andReturn(collect([
            (object) ['inicio' => $from->toIso8601String(), 'fin' => $until->toIso8601String(), 'estado' => 'Programada'],
        ]));

        try {
            (new AvailabilityService($appointments, $physicians, $specialties))
                ->validateRequestedSlot(3, 4, $from->toIso8601String(), $until->toIso8601String());
            $this->fail('Expected SlotUnavailable');
        } catch (SlotUnavailable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_board_appointments_reject_an_unknown_status(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $appointments->shouldReceive('getAppointmentsInRange')->never();

        try {
            (new AvailabilityService($appointments, $physicians, $specialties))
                ->getBoardAppointments('2026-11-02T00:00:00+00:00', '2026-11-07T00:00:00+00:00', null, null, 'Otro');
            $this->fail('Expected InvalidAvailabilityInput');
        } catch (InvalidAvailabilityInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_bookable_physicians_for_a_slot_exclude_doctors_without_that_interval(): void
    {
        $day = Carbon::now('UTC')->addDays(12)->startOfDay();
        $weekday = ScheduleService::WEEKDAYS[$day->isoWeekday() - 1];
        $date = $day->toDateString();
        $appointments = Mockery::mock(AppointmentRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $specialties = Mockery::mock(SpecialtyRepository::class);
        $specialties->shouldReceive('getSpecialtyById')->andReturn((object) ['id_especialidad' => 3, 'activo' => true]);
        $appointments->shouldReceive('getActivePhysiciansForSpecialty')->once()->with(3)->andReturn(collect([
            (object) ['id_medico' => 4, 'nombres' => 'Ana', 'apellidos' => 'Rojas', 'activo' => true],
            (object) ['id_medico' => 5, 'nombres' => 'Luis', 'apellidos' => 'Soto', 'activo' => true],
        ]));
        $physicians->shouldReceive('getPhysicianById')->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $physicians->shouldReceive('getPhysicianById')->with(5)
            ->andReturn((object) ['id_medico' => 5, 'activo' => true]);
        $appointments->shouldReceive('physicianAttendsSpecialty')->with(4, 3)->andReturn(true);
        $appointments->shouldReceive('physicianAttendsSpecialty')->with(5, 3)->andReturn(true);
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->with(4, $weekday)
            ->andReturn(collect([
                (object) ['hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00', 'duracion_cita_minutos' => 30],
            ]));
        $appointments->shouldReceive('getActiveSchedulesForPhysicianOnDay')->with(5, $weekday)
            ->andReturn(collect([
                (object) ['hora_inicio' => '14:00:00', 'hora_fin' => '18:00:00', 'duracion_cita_minutos' => 30],
            ]));
        $appointments->shouldReceive('getOccupyingAppointments')->andReturn(collect());

        $matches = (new AvailabilityService($appointments, $physicians, $specialties))
            ->getBookablePhysiciansForSlot(3, $date, '08:00', '08:30');

        $this->assertCount(1, $matches);
        $this->assertSame(4, (int) $matches->first()->id_medico);
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
