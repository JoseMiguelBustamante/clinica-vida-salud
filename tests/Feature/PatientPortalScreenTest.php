<?php

namespace Tests\Feature;

use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PatientPortalScreenTest extends TestCase
{
    public function test_the_registration_form_is_public_and_does_not_query_sql(): void
    {
        $this->get('/registro')->assertOk()->assertSee('Registrar cuenta')
            ->assertSee('name="nombre_usuario"', false)->assertSee('Crear cuenta')
            ->assertSee('no queda vinculada')
            ->assertDontSee('id_rol', false);
        $this->get('/register')->assertNotFound();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_signed_in_session_is_sent_to_the_panel(): void
    {
        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/registro')->assertRedirect(route('panel'));
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_registration_validation_does_not_query_sql(): void
    {
        $this->from('/registro')->post('/registro', [])->assertRedirect('/registro')
            ->assertSessionHasErrors('nombre_usuario');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_disabled_registration_does_not_create_an_account(): void
    {
        config(['identity.patient_portal_registration' => false]);
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('createAccount')->never();
        $this->app->instance(IdentityRepository::class, $repository);

        $this->get('/registro')->assertOk()->assertSee('El registro de cuentas no está habilitado.');
        $this->from('/registro')->post('/registro', [
            'nombre_usuario' => 'paciente1',
            'password' => 'twelve-chars',
            'password_confirmation' => 'twelve-chars',
        ])->assertRedirect('/registro')->assertSessionHasErrors('nombre_usuario');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_duplicate_username_is_rejected_without_inserting(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->once()->with(PermissionCatalog::PATIENT_ROLE)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        $repository->shouldReceive('identifierTaken')->once()->with('paciente1', null)->andReturn(true);
        $repository->shouldReceive('createAccount')->never();
        Hash::shouldReceive('make')->never();
        $this->app->instance(IdentityRepository::class, $repository);

        $this->from('/registro')->post('/registro', [
            'nombre_usuario' => 'paciente1',
            'password' => 'twelve-chars',
            'password_confirmation' => 'twelve-chars',
        ])->assertRedirect('/registro')
            ->assertSessionHasErrors(['nombre_usuario' => 'El nombre de usuario solicitado ya está utilizado.']);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_valid_registration_creates_an_active_patient_role_account(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->once()->with(PermissionCatalog::PATIENT_ROLE)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        $repository->shouldReceive('identifierTaken')->once()->with('paciente1', null)->andReturn(false);
        $repository->shouldReceive('getRoleById')->once()->with(4)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        $repository->shouldReceive('createAccount')->once()
            ->with('paciente1', null, 'stored-hash', true, 4)->andReturn(44);
        Hash::shouldReceive('make')->once()->with('twelve-chars')->andReturn('stored-hash');
        $this->app->instance(IdentityRepository::class, $repository);

        $this->post('/registro', [
            'nombre_usuario' => 'paciente1',
            'password' => 'twelve-chars',
            'password_confirmation' => 'twelve-chars',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'La cuenta fue registrada. El acceso a las citas requiere un vínculo aprobado con el registro de paciente.');
        $this->assertGuest();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_storage_failure_is_not_shown_as_success(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->once()->with(PermissionCatalog::PATIENT_ROLE)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        $repository->shouldReceive('identifierTaken')->once()->with('paciente1', null)->andReturn(false);
        $repository->shouldReceive('getRoleById')->once()->with(4)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        Hash::shouldReceive('make')->once()->with('twelve-chars')->andReturn('stored-hash');
        $repository->shouldReceive('createAccount')->once()->andThrow(new RuntimeException('constraint'));
        $this->app->instance(IdentityRepository::class, $repository);

        $this->from('/registro')->post('/registro', [
            'nombre_usuario' => 'paciente1',
            'password' => 'twelve-chars',
            'password_confirmation' => 'twelve-chars',
        ])->assertRedirect('/registro')
            ->assertSessionHasErrors(['nombre_usuario' => 'No fue posible completar el registro.'])
            ->assertSessionMissing('status');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_oversized_password_is_rejected_without_sql(): void
    {
        $this->from('/registro')->post('/registro', [
            'nombre_usuario' => 'paciente1',
            'password' => str_repeat('x', 73),
            'password_confirmation' => str_repeat('x', 73),
        ])->assertRedirect('/registro')->assertSessionHasErrors('password');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_login_form_links_to_patient_registration(): void
    {
        $this->get('/login')->assertOk()->assertSee('Crear cuenta de paciente');
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
