<?php

namespace Tests\Feature;

use App\Modules\Schedules\ScheduleService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EnsurePhysicianSchedulesCommandTest extends TestCase
{
    public function test_help_describes_schedule_effects_without_connecting(): void
    {
        $this->assertSame(0, Artisan::call('help', ['command_name' => 'clinic:ensure-schedules']));
        $help = Artisan::output();
        foreach ([
            'already migrated PostgreSQL',
            '08:00–18:00',
            'Mon–Sat',
            'Existing day rows are not changed',
            'environment changes',
        ] as $text) {
            $this->assertStringContainsString($text, $help);
        }
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_no_missing_schedules_are_reported_without_writes(): void
    {
        $schedules = Mockery::mock(ScheduleService::class);
        $schedules->shouldReceive('ensureMissingWeekdaySchedulesForActivePhysicians')->once()->andReturn(0);
        $this->app->instance(ScheduleService::class, $schedules);

        $this->assertSame(0, Artisan::call('clinic:ensure-schedules'));
        $this->assertStringContainsString('No missing weekday schedules were created.', Artisan::output());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_created_schedule_count_is_reported(): void
    {
        $schedules = Mockery::mock(ScheduleService::class);
        $schedules->shouldReceive('ensureMissingWeekdaySchedulesForActivePhysicians')->once()->andReturn(12);
        $this->app->instance(ScheduleService::class, $schedules);

        $this->assertSame(0, Artisan::call('clinic:ensure-schedules'));
        $this->assertStringContainsString('Created 12 missing weekday schedule(s).', Artisan::output());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_operational_failures_are_generic_and_never_leak(): void
    {
        $schedules = Mockery::mock(ScheduleService::class);
        $schedules->shouldReceive('ensureMissingWeekdaySchedulesForActivePhysicians')->once()
            ->andThrow(new RuntimeException('dsn secret host=internal'));
        $this->app->instance(ScheduleService::class, $schedules);

        $this->assertSame(1, Artisan::call('clinic:ensure-schedules'));
        $output = Artisan::output();
        $this->assertStringContainsString('Unable to ensure physician schedules; stop and contact the operator.', $output);
        $this->assertStringNotContainsString('dsn secret', $output);
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
