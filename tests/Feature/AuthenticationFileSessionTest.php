<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\IdentityRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationFileSessionTest extends TestCase
{
    private string $sessions;

    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessions = sys_get_temp_dir().'/clinic-expiry-'.bin2hex(random_bytes(8));
        mkdir($this->sessions, 0700);
        config(['session.driver' => 'file', 'session.files' => $this->sessions, 'session.lottery' => [0, 100]]);
        app('session')->forgetDrivers();
        $this->app->instance('env', 'file-session-verification');
    }

    protected function tearDown(): void
    {
        if (isset($this->sessions)) {
            foreach (glob($this->sessions.'/*') as $file) {
                unlink($file);
            }
            rmdir($this->sessions);
        }
        parent::tearDown();
    }

    public static function expiredRequests(): array
    {
        return [
            'protected read' => ['GET', '/auth/session', 401],
            'csrf recovery' => ['GET', '/auth/csrf', 200],
            'login mutation' => ['POST', '/auth/login', 419],
            'logout mutation' => ['POST', '/auth/logout', 419],
        ];
    }

    #[DataProvider('expiredRequests')]
    public function test_expired_file_cookies_reject_access_and_allow_reauthentication(string $method, string $path, int $status): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true,
            'contrasena_hash' => Hash::make('fixture password')]);
        $representation = ['id' => 17, 'username' => 'fixture', 'email' => null,
            'role' => ['id' => 7, 'name' => 'Fixture'], 'permissions' => []];
        $activeReads = 0;
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('matching')->with('fixture')->andReturn(new Collection([$user]));
        $repository->shouldReceive('representation')->with($user)->andReturn($representation);
        $repository->shouldReceive('active')->with(17)->andReturnUsing(function () use ($user, &$activeReads) {
            $activeReads++;

            return $user;
        });
        $this->app->instance(IdentityRepository::class, $repository);

        $initialToken = $this->browser('GET', '/auth/csrf')->assertOk()->json('csrf_token');
        $oldToken = $this->browser('POST', '/auth/login', ['identifier' => 'fixture',
            'password' => 'fixture password', '_token' => $initialToken])->assertOk()->json('csrf_token');
        $this->browser('GET', '/auth/session')->assertOk()->assertJsonPath('user', $representation);
        $this->assertSame(1, $activeReads);
        $expiredCookies = $this->cookies;
        foreach (glob($this->sessions.'/*') as $file) {
            touch($file, time() - (config('session.lifetime') + 1) * 60);
        }
        clearstatcache();

        $response = $this->browser($method, $path, ['identifier' => 'fixture',
            'password' => 'fixture password', '_token' => $oldToken]);
        $response->assertStatus($status)->assertHeader('Cache-Control', 'no-store, private');
        if ($status === 401) {
            $response->assertExactJson(['message' => 'Unauthenticated.']);
        } elseif ($status === 419) {
            $response->assertExactJson(['message' => 'CSRF token mismatch.']);
        } else {
            $response->assertExactJson(['csrf_token' => $response->json('csrf_token')]);
            $this->assertNotSame($oldToken, $response->json('csrf_token'));
        }
        $this->assertGuest();
        $this->assertSame(1, $activeReads);
        $this->assertSame([], $this->app['db']->getConnections());

        $this->cookies = $expiredCookies;
        $this->browser('GET', '/auth/session')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->browser('POST', '/auth/logout', ['_token' => $oldToken])->assertStatus(419)
            ->assertExactJson(['message' => 'CSRF token mismatch.']);
        $this->assertGuest();
        $newToken = $this->browser('GET', '/auth/csrf')->assertOk()->json('csrf_token');
        $this->assertNotSame($oldToken, $newToken);
        $newLogin = $this->browser('POST', '/auth/login', ['identifier' => 'fixture',
            'password' => 'fixture password', '_token' => $newToken])->assertOk();
        $newCookies = $this->cookies;
        $this->browser('GET', '/auth/session')->assertOk()->assertJsonPath('user', $representation);
        $this->browser('POST', '/auth/logout', ['_token' => $newLogin->json('csrf_token')])->assertNoContent()->assertContent('');
        $this->cookies = $newCookies;
        $this->browser('GET', '/auth/session')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertGuest();
        $this->assertSame(3, $activeReads);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function browser(string $method, string $path, array $input = []): TestResponse
    {
        app('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
        Auth::forgetGuards();
        $response = $this->call($method, $path, $input, $this->cookies, [], ['HTTP_ACCEPT' => 'application/json']);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $response;
    }
}
