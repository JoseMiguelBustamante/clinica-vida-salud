<?php

namespace Tests\Unit;

use Illuminate\Database\Migrations\Migration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ClinicSchemaTest extends TestCase
{
    public function test_only_the_report_migration_is_available_instead_of_laravel_scaffolding(): void
    {
        $paths = glob(dirname(__DIR__, 2).'/database/migrations/*.php');

        $this->assertCount(1, $paths);
        $this->assertSame('2026_10_28_000000_create_clinic_schema.php', basename($paths[0]));
        $migration = require $paths[0];
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue($migration->withinTransaction);
    }

    public function test_isolated_runner_explains_its_scope_without_contacting_docker(): void
    {
        $process = new Process(['bash', dirname(__DIR__).'/Integration/run-postgres.sh', '--help']);
        $process->setTimeout(5);
        $process->run();

        $this->assertSame(0, $process->getExitCode());
        $this->assertSame("Usage: bash tests/Integration/run-postgres.sh\nRuns the clinic migration integration test on an owned disposable PostgreSQL 17 container.\nRequires Docker access, PHP pdo_pgsql and installed development dependencies.\nPublishes only a random loopback port; never connects to the clinic database.\n", $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    public function test_opt_in_integration_test_is_discoverable_without_connecting_to_postgresql(): void
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/vendor/bin/phpunit', '--do-not-cache-result', '--list-tests', dirname(__DIR__).'/Integration/ClinicMigrationTest.php']);
        $process->setTimeout(5);
        $process->run();

        $this->assertSame(0, $process->getExitCode());
        $this->assertSame('', $process->getErrorOutput());
        $this->assertStringContainsString('test_report_schema_is_atomic_repeatable_constrained_and_reversible_on_an_empty_fixture', $process->getOutput());
    }

    public function test_sql_matches_the_report_physical_schema_without_an_independent_transaction(): void
    {
        $path = dirname(__DIR__, 2).'/database/schema/clinic.sql';
        $this->assertFileExists($path);
        $sql = file_get_contents($path);
        $canonical = trim(preg_replace('/\s+/', ' ', preg_replace('/--[^\n]*/', '', $sql)));

        $this->assertSame('a65a5b915914de7cd4cdbe81df0ccfa3b552c2bf7a0469443e607eb21bb156d7', hash('sha256', $canonical));
        $this->assertSame(17, preg_match_all('/CREATE TABLE public\./', $sql));
        $this->assertSame(11, preg_match_all('/CREATE INDEX /', $sql));
        $this->assertDoesNotMatchRegularExpression('/\b(?:BEGIN|COMMIT|CASCADE)\b/i', $sql);
    }
}
