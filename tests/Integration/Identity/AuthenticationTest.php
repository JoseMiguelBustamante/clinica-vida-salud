<?php

namespace Tests\Integration\Identity;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Integration\IdentityPostgresTestCase;

class AuthenticationTest extends IdentityPostgresTestCase
{
    private string $sessions;

    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessions = sys_get_temp_dir().'/clinic-auth-'.bin2hex(random_bytes(8));
        mkdir($this->sessions, 0700);
        config(['session.driver' => 'file', 'session.files' => $this->sessions, 'session.lottery' => [0, 100]]);
        app('session')->forgetDrivers();
        // Exercise CSRF instead of Laravel's testing bypass, without loading any environment file.
        $this->app->instance('env', 'identity-http-verification');
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

    /** Requests carry actual encrypted response cookies through a fresh session/guard each time. */
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

    private function account(string $username = 'ExactUser', ?string $email = 'Exact@Example.test', string $password = ' exact password ', bool $active = true): array
    {
        $role = DB::table('rol')->insertGetId(['nombre' => 'Administrador'], 'id_rol');
        $id = DB::table('usuario')->insertGetId(['nombre_usuario' => $username, 'correo_acceso' => $email,
            'contrasena_hash' => Hash::make($password), 'activo' => $active, 'id_rol' => $role], 'id_usuario');

        return ['id' => $id, 'username' => $username, 'email' => $email, 'role' => ['id' => $role, 'name' => 'Administrador'], 'permissions' => []];
    }

    private function login(string $identifier, string $password = ' exact password '): TestResponse
    {
        $token = $this->browser('GET', '/auth/csrf')->assertOk()->json('csrf_token');

        return $this->browser('POST', '/auth/login', ['identifier' => $identifier, 'password' => $password, '_token' => $token]);
    }

    public function test_username_and_email_login_rotate_cookie_and_token_and_load_only_existing_grants(): void
    {
        $expected = $this->account();
        $permission = DB::table('permiso')->insertGetId(['codigo' => 'fixture.read'], 'id_permiso');
        DB::table('rol_permiso')->insert(['id_rol' => $expected['role']['id'], 'id_permiso' => $permission]);
        $expected['permissions'] = ['fixture.read'];
        foreach (['ExactUser', 'Exact@Example.test'] as $identifier) {
            $this->cookies = [];
            $token = $this->browser('GET', '/auth/csrf')->json('csrf_token');
            $anonymousCookies = $this->cookies;
            $response = $this->browser('POST', '/auth/login', ['identifier' => $identifier, 'password' => ' exact password ', '_token' => $token]);
            $response->assertOk()->assertExactJson(['user' => $expected, 'csrf_token' => $response->json('csrf_token')]);
            $this->assertNotSame($token, $response->json('csrf_token'));
            $this->assertNotSame($anonymousCookies[config('session.cookie')], $this->cookies[config('session.cookie')]);
            $authenticatedCookies = $this->cookies;
            $this->browser('GET', '/auth/session')->assertOk()->assertJsonPath('user', $expected);
            $this->cookies = $anonymousCookies;
            $this->browser('GET', '/auth/session')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
            $this->cookies = $authenticatedCookies;
        }
    }

    public function test_rejected_credentials_do_not_authenticate_or_modify_identity_rows(): void
    {
        $expected = $this->account();
        $before = DB::table('usuario')->get()->toJson();
        foreach ([['unknown', ' exact password '], ['exactuser', ' exact password '], [' ExactUser ', ' exact password '],
            ['ExactUser', 'exact password'], ['ExactUser', str_repeat('x', 73)], ['ExactUser', str_repeat('é', 37)]] as [$identifier, $password]) {
            $this->cookies = [];
            $this->login($identifier, $password)->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials or account unavailable.']);
            $this->browser('GET', '/auth/session')->assertUnauthorized();
            $this->assertSame($before, DB::table('usuario')->get()->toJson());
        }
        DB::table('usuario')->where('id_usuario', $expected['id'])->update(['activo' => false]);
        $this->login('ExactUser')->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials or account unavailable.']);
        $this->browser('GET', '/auth/session')->assertUnauthorized();
    }

    public function test_cross_column_ambiguity_rejects_both_valid_passwords(): void
    {
        $first = $this->account('collision', 'first@example.test');
        DB::table('usuario')->insert(['nombre_usuario' => 'second', 'correo_acceso' => 'collision',
            'contrasena_hash' => Hash::make('other password'), 'activo' => true, 'id_rol' => $first['role']['id']]);
        foreach ([' exact password ', 'other password'] as $password) {
            $this->login('collision', $password)->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials or account unavailable.']);
            $this->browser('GET', '/auth/session')->assertUnauthorized();
        }
    }

    public function test_72_byte_password_is_accepted_but_longer_bcrypt_prefix_is_not(): void
    {
        $this->account(password: str_repeat('é', 36));
        $this->login('ExactUser', str_repeat('é', 36))->assertOk();
        $this->cookies = [];
        $key = 'identity:'.hash('sha256', 'ExactUser127.0.0.1');
        $this->login('ExactUser', str_repeat('é', 36).'x')->assertUnauthorized();
        $this->assertSame(0, RateLimiter::attempts($key));
        $this->browser('GET', '/auth/session')->assertUnauthorized();
    }

    public function test_logout_invalidates_the_original_cookie_and_rotates_csrf(): void
    {
        $this->account();
        $token = $this->login('ExactUser')->assertOk()->json('csrf_token');
        $authenticated = $this->cookies;
        $this->browser('POST', '/auth/logout', ['_token' => $token])->assertNoContent();
        $this->assertNotSame($authenticated[config('session.cookie')], $this->cookies[config('session.cookie')]);
        $this->browser('GET', '/auth/session')->assertUnauthorized();
        $this->assertNotSame($token, $this->browser('GET', '/auth/csrf')->json('csrf_token'));
        $this->cookies = $authenticated;
        $this->browser('GET', '/auth/session')->assertUnauthorized();
        $this->browser('POST', '/auth/logout', ['_token' => $token])->assertStatus(419);
    }

    public function test_deactivation_is_rechecked_after_an_authenticated_request(): void
    {
        $user = $this->account();
        $this->login('ExactUser')->assertOk();
        $this->browser('GET', '/auth/session')->assertOk();
        $old = $this->cookies;
        DB::table('usuario')->where('id_usuario', $user['id'])->update(['activo' => false]);
        $this->browser('GET', '/auth/session')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        DB::table('usuario')->where('id_usuario', $user['id'])->update(['activo' => true]);
        $this->cookies = $old;
        $this->browser('GET', '/auth/session')->assertUnauthorized();
    }

    public function test_expired_session_cannot_authorize_reads_or_mutations(): void
    {
        $this->account();
        $token = $this->login('ExactUser')->assertOk()->json('csrf_token');
        foreach (glob($this->sessions.'/*') as $file) {
            touch($file, time() - (config('session.lifetime') + 1) * 60);
        }
        $this->browser('GET', '/auth/session')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->browser('POST', '/auth/logout', ['_token' => $token])->assertStatus(419);
    }

    public function test_csrf_is_enforced_on_login_and_logout_with_real_cookies(): void
    {
        $this->account();
        $this->browser('GET', '/auth/csrf')->assertOk();
        $before = DB::table('usuario')->get()->toJson();
        $this->browser('POST', '/auth/login', ['identifier' => 'ExactUser', 'password' => ' exact password '])
            ->assertStatus(419)->assertExactJson(['message' => 'CSRF token mismatch.']);
        $this->browser('GET', '/auth/session')->assertUnauthorized();
        $token = $this->login('ExactUser')->assertOk()->json('csrf_token');
        $this->browser('POST', '/auth/logout', ['_token' => 'incorrect'])->assertStatus(419);
        $this->browser('GET', '/auth/session')->assertOk();
        $this->browser('POST', '/auth/logout', ['_token' => $token])->assertNoContent();
        $this->assertSame($before, DB::table('usuario')->get()->toJson());
    }
}
