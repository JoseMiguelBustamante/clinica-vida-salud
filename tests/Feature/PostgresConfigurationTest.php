<?php

namespace Tests\Feature;

use Tests\TestCase;

class PostgresConfigurationTest extends TestCase
{
    public function test_booted_application_only_offers_postgresql_without_opening_connections(): void
    {
        $this->assertSame([], $this->app['db']->getConnections());
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame(['pgsql'], array_keys(config('database.connections')));
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
