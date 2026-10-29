<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\IdentityRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationRejectionWorkTest extends TestCase
{
    public static function rejectedAccounts(): array
    {
        return [['unknown'], ['inactive'], ['ambiguous'], ['active wrong']];
    }

    private function accountMatches(string $state): Collection
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => $state !== 'inactive',
            'contrasena_hash' => 'account hash']);

        return new Collection(match ($state) {
            'unknown' => [],
            'ambiguous' => [$user, clone $user],
            default => [$user],
        });
    }

    #[DataProvider('rejectedAccounts')]
    public function test_every_rejected_account_state_performs_one_password_check(string $state): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('matching')->once()->with('candidate')->andReturn($this->accountMatches($state));
        $this->app->instance(IdentityRepository::class, $repository);
        Hash::shouldReceive('make')->once()->with(Mockery::type('string'))->andReturn('dummy hash');
        Hash::shouldReceive('check')->once()->with('wrong password', $state === 'active wrong' ? 'account hash' : 'dummy hash')->andReturn(false);

        $this->postJson('/auth/login', ['identifier' => 'candidate', 'password' => 'wrong password'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials or account unavailable.'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGuest();
        $this->assertSame([], $this->app['db']->getConnections());
        $this->assertSame(1, RateLimiter::attempts('identity:'.hash('sha256', 'candidate127.0.0.1')));
    }

    public static function ineligibleAccounts(): array
    {
        return [['unknown'], ['inactive'], ['ambiguous']];
    }

    #[DataProvider('ineligibleAccounts')]
    public function test_a_matching_dummy_hash_never_authenticates_an_ineligible_account(string $state): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('matching')->once()->with('candidate')->andReturn($this->accountMatches($state));
        $repository->shouldReceive('representation')->never();
        $this->app->instance(IdentityRepository::class, $repository);
        Hash::shouldReceive('make')->once()->with(Mockery::type('string'))->andReturn('dummy hash');
        Hash::shouldReceive('check')->once()->with('wrong password', 'dummy hash')->andReturn(true);

        $this->postJson('/auth/login', ['identifier' => 'candidate', 'password' => 'wrong password'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials or account unavailable.'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGuest();
        $this->assertSame([], $this->app['db']->getConnections());
        $this->assertSame(1, RateLimiter::attempts('identity:'.hash('sha256', 'candidate127.0.0.1')));
    }

    public static function hashProfiles(): array
    {
        return [['bcrypt', ['rounds' => 5]], ['argon2id', ['memory' => 1024, 'time' => 2, 'threads' => 1]]];
    }

    #[DataProvider('hashProfiles')]
    public function test_dummy_verification_uses_the_configured_algorithm_and_cost(string $algorithm, array $options): void
    {
        config(['hashing.driver' => $algorithm, 'hashing.bcrypt' => $options, 'hashing.argon' => $options]);
        app('hash')->forgetDrivers();
        $hasher = app('hash')->driver();
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('matching')->once()->with('candidate')->andReturn(new Collection);
        $this->app->instance(IdentityRepository::class, $repository);
        Hash::shouldReceive('make')->once()->with(Mockery::type('string'))->andReturnUsing(fn (string $value) => $hasher->make($value));
        Hash::shouldReceive('check')->once()->with('wrong password', Mockery::type('string'))
            ->andReturnUsing(function (string $password, string $hash) use ($hasher, $algorithm): bool {
                $this->assertSame($algorithm, $hasher->info($hash)['algoName']);
                $this->assertFalse($hasher->needsRehash($hash));

                return $hasher->check($password, $hash);
            });

        $this->postJson('/auth/login', ['identifier' => 'candidate', 'password' => 'wrong password'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials or account unavailable.'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGuest();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_valid_credentials_still_establish_a_rotated_session(): void
    {
        $password = ' exact password ';
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'contrasena_hash' => Hash::make($password)]);
        $representation = ['id' => 17, 'username' => 'candidate', 'email' => null,
            'role' => ['id' => 9, 'name' => 'Fixture role'], 'permissions' => []];
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('matching')->once()->with('candidate')->andReturn(new Collection([$user]));
        $repository->shouldReceive('representation')->once()->with($user)->andReturn($representation);
        $this->app->instance(IdentityRepository::class, $repository);
        $csrf = $this->getJson('/auth/csrf')->assertOk()->json('csrf_token');
        $oldId = app('session')->driver()->getId();

        $response = $this->postJson('/auth/login', ['identifier' => 'candidate', 'password' => $password]);
        $response->assertOk()->assertExactJson(['user' => $representation, 'csrf_token' => $response->json('csrf_token')])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNotSame($csrf, $response->json('csrf_token'));
        $this->assertNotSame($oldId, app('session')->driver()->getId());
        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
