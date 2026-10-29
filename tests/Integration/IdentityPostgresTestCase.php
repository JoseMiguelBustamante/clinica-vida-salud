<?php

namespace Tests\Integration;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

abstract class IdentityPostgresTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $database = getenv('ISOLATED_PG_DATABASE') ?: '';
        $password = getenv('ISOLATED_PG_PASSWORD') ?: '';
        $port = getenv('ISOLATED_PG_PORT') ?: '';

        if (getenv('CLINIC_DISPOSABLE_PG') !== 'yes'
            || ! preg_match('/^clinic_migration_test_[a-f0-9]{16}$/', $database)
            || $password === '' || ! ctype_digit($port) || (int) $port < 1024
            || getenv('APP_CONFIG_CACHE') !== false
            || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Run tests/Integration/run-postgres.sh --identity on its owned disposable PostgreSQL container.');
        }

        $app = parent::createApplication();
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql', 'url' => null, 'host' => '127.0.0.1', 'port' => $port,
            'database' => $database, 'username' => $database, 'password' => $password,
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'disable',
        ]);
        $app['config']->set('database.default', 'pgsql');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $identity = DB::selectOne('SELECT current_database() AS db, current_user AS usr');
        $this->assertSame(getenv('ISOLATED_PG_DATABASE'), $identity->db);
        $this->assertSame($identity->db, $identity->usr);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]));
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->app && $this->app->bound('db')) {
            foreach ($this->app['db']->getConnections() as $connection) {
                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
            }
        }

        parent::tearDown();
    }
}
