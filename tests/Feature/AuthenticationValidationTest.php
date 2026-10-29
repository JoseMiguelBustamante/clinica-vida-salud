<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\IdentityRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationValidationTest extends TestCase
{
    public static function malformedCredentials(): array
    {
        return [[[]], [['identifier' => [], 'password' => []]], [['identifier' => 'a', 'password' => '']],
            [['identifier' => str_repeat('a', 256), 'password' => 'valid password']],
            [['identifier' => 123, 'password' => true]]];
    }

    #[DataProvider('malformedCredentials')]
    public function test_malformed_input_is_json_validation_without_queries_or_throttle(array $input): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')->never();
        $this->postJson('/auth/login', $input)->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        $this->assertGuest();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_csrf_and_anonymous_contracts_do_not_query_sql(): void
    {
        $this->get('/auth/csrf')->assertOk()->assertJsonStructure(['csrf_token'])->assertHeader('Cache-Control', 'no-store, private');
        foreach (['/auth/session', '/auth/logout'] as $path) {
            $response = $path === '/auth/logout' ? $this->post($path) : $this->get($path);
            $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        }
        $this->get('/login')->assertNotFound();
        $this->assertSame(['web'], $this->app['router']->getRoutes()->getByName('home')->middleware());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_real_csrf_rejects_mutations_before_validation_and_queries(): void
    {
        // Change only the in-memory request environment; never load environment files.
        $this->app->instance('env', 'csrf-verification');
        foreach (['/auth/login', '/auth/logout'] as $path) {
            $this->postJson($path, [])->assertStatus(419)->assertExactJson(['message' => 'CSRF token mismatch.']);
        }
        $this->assertSame([], $this->app['db']->getConnections());
        $this->assertGuest();
    }

    public function test_identifier_and_password_are_not_trimmed_or_case_folded(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'contrasena_hash' => 'stored hash']);
        $repository->shouldReceive('matching')->once()->with(' Mixed@Example.test ')->andReturn(new Collection([$user]));
        Hash::shouldReceive('check')->once()->with(' password ', 'stored hash')->andReturn(false);
        $this->app->instance(IdentityRepository::class, $repository);
        $this->postJson('/auth/login', ['identifier' => ' Mixed@Example.test ', 'password' => ' password '])
            ->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials or account unavailable.']);
        $this->assertGuest();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_over_72_bytes_is_generic_and_never_queries_or_increments_throttle(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')->never();
        foreach ([str_repeat('x', 73), str_repeat('é', 37)] as $password) {
            $this->postJson('/auth/login', ['identifier' => 'someone', 'password' => $password])
                ->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials or account unavailable.']);
        }
        $this->assertGuest();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_throttle_is_five_attempts_per_hashed_identifier_and_ip(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('matching')->times(5)->with('unknown')->andReturn(new Collection);
        $this->app->instance(IdentityRepository::class, $repository);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/login', ['identifier' => 'unknown', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/auth/login', ['identifier' => 'unknown', 'password' => 'wrong'])->assertStatus(429)
            ->assertExactJson(['message' => 'Too many login attempts. Try again later.'])->assertHeader('Retry-After');
        $this->assertSame(5, RateLimiter::attempts('identity:'.hash('sha256', 'unknown127.0.0.1')));
        $this->assertGuest();
    }

    public function test_operational_failures_are_generic_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('matching')->once()->andThrow(new \RuntimeException('SQL secret hash'));
        $this->app->instance(IdentityRepository::class, $repository);
        $this->postJson('/auth/login', ['identifier' => 'someone', 'password' => 'password'])
            ->assertStatus(503)->assertExactJson(['message' => 'Unable to complete authentication. Please try again.']);
        $this->assertGuest();
    }

    public function test_session_write_failure_never_returns_a_success_or_cookie(): void
    {
        $handler = Mockery::mock(\SessionHandlerInterface::class);
        $handler->shouldReceive('read')->andReturn('');
        $handler->shouldReceive('write')->once()->andReturn(false);
        config(['session.lottery' => [0, 100]]);
        app('session')->driver()->setHandler($handler);
        $response = $this->get('/auth/csrf')->assertStatus(503)
            ->assertExactJson(['message' => 'Unable to complete authentication. Please try again.']);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertGuest();
    }

    public function test_login_storage_failure_never_confirms_authentication(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true,
            'contrasena_hash' => Hash::make(' exact password ')]);
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('matching')->once()->with('exact')->andReturn(new Collection([$user]));
        $repository->shouldReceive('representation')->once()->with($user)->andReturn(['id' => 17]);
        $this->app->instance(IdentityRepository::class, $repository);
        $handler = Mockery::mock(\SessionHandlerInterface::class);
        $handler->shouldReceive('read')->andReturn('');
        $handler->shouldReceive('destroy')->andReturn(true);
        $handler->shouldReceive('write')->once()->andReturn(false);
        config(['session.lottery' => [0, 100]]);
        app('session')->driver()->setHandler($handler);
        $response = $this->postJson('/auth/login', ['identifier' => 'exact', 'password' => ' exact password '])
            ->assertStatus(503)->assertExactJson(['message' => 'Unable to complete authentication. Please try again.']);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertGuest();
    }

    public function test_logout_invalidation_failure_never_confirms_success(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true]);
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('active')->once()->with(17)->andReturn($user);
        $this->app->instance(IdentityRepository::class, $repository);
        $handler = Mockery::mock(\SessionHandlerInterface::class);
        $handler->shouldReceive('read')->andReturn('');
        $handler->shouldReceive('destroy')->once()->andReturn(false);
        $handler->shouldReceive('write')->andReturn(true);
        config(['session.lottery' => [0, 100]]);
        $this->withSession([Auth::guard('web')->getName() => 17]);
        app('session')->driver()->setHandler($handler);
        $this->post('/auth/logout')->assertStatus(503)
            ->assertExactJson(['message' => 'Unable to complete sign out. Please try again.']);
    }

    public function test_cache_failure_is_generic_without_resolving_sql(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andThrow(new \RuntimeException('cache secret'));
        $this->postJson('/auth/login', ['identifier' => 'someone', 'password' => 'password'])
            ->assertStatus(503)->assertExactJson(['message' => 'Unable to complete authentication. Please try again.']);
        $this->assertGuest();
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
