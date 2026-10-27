<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_existing_health_endpoint_remains_available_without_a_database(): void
    {
        $this->get('/up')->assertOk();

        $this->assertSame([], $this->app['db']->getConnections());
    }
}
