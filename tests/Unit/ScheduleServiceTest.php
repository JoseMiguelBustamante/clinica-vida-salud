<?php

namespace Tests\Unit;

use App\Modules\MedicalStaff\PhysicianNotFound;
use App\Modules\MedicalStaff\PhysicianRepository;
use App\Modules\Schedules\InvalidScheduleInput;
use App\Modules\Schedules\OverlappingSchedule;
use App\Modules\Schedules\ScheduleBlocksExistingAppointments;
use App\Modules\Schedules\ScheduleRepository;
use App\Modules\Schedules\ScheduleService;
use Mockery;
use Tests\TestCase;

class ScheduleServiceTest extends TestCase
{
    public function test_a_missing_physician_is_rejected_before_writes(): void
    {
        $schedules = Mockery::mock(ScheduleRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)->andReturn(null);
        $schedules->shouldReceive('createSchedule')->never();

        try {
            (new ScheduleService($schedules, $physicians))->createSchedule([
                'id_medico' => 4,
                'dia_semana' => 'Lunes',
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'duracion_cita_minutos' => 30,
                'activo' => true,
            ]);
            $this->fail('Expected PhysicianNotFound');
        } catch (PhysicianNotFound) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_duration_must_fill_the_window_with_complete_slots(): void
    {
        $schedules = Mockery::mock(ScheduleRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $schedules->shouldReceive('hasOverlappingActiveSchedule')->never();
        $schedules->shouldReceive('createSchedule')->never();

        try {
            (new ScheduleService($schedules, $physicians))->createSchedule([
                'id_medico' => 4,
                'dia_semana' => 'Lunes',
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'duracion_cita_minutos' => 45,
                'activo' => true,
            ]);
            $this->fail('Expected InvalidScheduleInput');
        } catch (InvalidScheduleInput) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_overlapping_active_fringes_are_rejected_before_writes(): void
    {
        $schedules = Mockery::mock(ScheduleRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $physicians->shouldReceive('getPhysicianById')->once()->with(4)
            ->andReturn((object) ['id_medico' => 4, 'activo' => true]);
        $schedules->shouldReceive('hasOverlappingActiveSchedule')->once()
            ->with(4, 'Lunes', '08:00:00', '12:00:00', null)->andReturn(true);
        $schedules->shouldReceive('lockPhysicianSchedules')->never();
        $schedules->shouldReceive('createSchedule')->never();

        try {
            (new ScheduleService($schedules, $physicians))->createSchedule([
                'id_medico' => 4,
                'dia_semana' => 'Lunes',
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'duracion_cita_minutos' => 30,
                'activo' => true,
            ]);
            $this->fail('Expected OverlappingSchedule');
        } catch (OverlappingSchedule) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_shrinking_a_window_is_refused_when_appointments_would_fall_outside(): void
    {
        $schedules = Mockery::mock(ScheduleRepository::class);
        $physicians = Mockery::mock(PhysicianRepository::class);
        $schedules->shouldReceive('getScheduleById')->once()->with(9)->andReturn((object) [
            'id_horario' => 9,
            'dia_semana' => 'Lunes',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'duracion_cita_minutos' => 30,
            'activo' => true,
            'id_medico' => 4,
        ]);
        $schedules->shouldReceive('scheduledAppointmentsWouldFallOutside')->once()
            ->with(4, 'Lunes', '08:00:00', '12:00:00', 'Lunes', '08:00:00', '10:00:00')
            ->andReturn(true);
        $schedules->shouldReceive('updateSchedule')->never();

        try {
            (new ScheduleService($schedules, $physicians))->updateSchedule(9, [
                'dia_semana' => 'Lunes',
                'hora_inicio' => '08:00',
                'hora_fin' => '10:00',
                'duracion_cita_minutos' => 30,
            ]);
            $this->fail('Expected ScheduleBlocksExistingAppointments');
        } catch (ScheduleBlocksExistingAppointments) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }
}
