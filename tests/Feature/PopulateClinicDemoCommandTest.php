<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PopulateClinicDemoCommandTest extends TestCase
{
    public function test_help_describes_demo_effects_without_connecting(): void
    {
        $this->assertSame(0, Artisan::call('help', ['command_name' => 'clinic:populate-demo']));
        $help = Artisan::output();
        foreach (['already migrated PostgreSQL', 'Administrador', 'Existing usernames', 'Idempotent', 'db:seed', '--force', '--reset-passwords', 'DEMO-', 'patients', 'hours for every active', 'reservation for every demo patient', 'Atendida', 'demo.recepcionista', 'demo.medico', 'shown once'] as $text) {
            $this->assertStringContainsString($text, $help);
        }
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_non_interactive_runs_without_force_do_not_touch_the_database(): void
    {
        $this->assertSame(1, Artisan::call('clinic:populate-demo'));
        $this->assertStringContainsString('Confirmation is required; no changes made.', Artisan::output());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_non_pgsql_drivers_are_refused_without_writes(): void
    {
        $database = $this->app['db'];
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('sqlsrv');
        $connection->shouldReceive('transaction')->never();
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->assertSame(1, Artisan::call('clinic:populate-demo', ['--force' => true]));
        $this->assertStringContainsString('Unable to load demo data; stop and contact the operator.', Artisan::output());
        $this->assertSame([], $database->getConnections());
    }

    public function test_operational_failures_are_generic_and_never_leak(): void
    {
        $database = $this->app['db'];
        DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('dsn secret host=internal'));

        $this->assertSame(1, Artisan::call('clinic:populate-demo', ['--force' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('Unable to load demo data; stop and contact the operator.', $output);
        $this->assertStringNotContainsString('dsn secret', $output);
        $this->assertSame([], $database->getConnections());
    }
}
