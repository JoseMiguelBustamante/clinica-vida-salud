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
}
