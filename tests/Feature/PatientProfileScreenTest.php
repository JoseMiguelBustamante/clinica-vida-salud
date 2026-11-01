<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PatientProfileScreenTest extends TestCase
{
    public function test_guests_are_sent_to_login_for_the_profile(): void
    {
        $this->get('/perfil')->assertRedirect(route('login'));
        $this->get('/profile')->assertNotFound();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_session_without_profile_permission_is_denied(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 1]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([
            PermissionCatalog::GENERATE_REPORTS,
        ]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/perfil')->assertForbidden()->assertSee('No tiene permiso para realizar esta operación.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_profile_form_opens_for_the_signed_in_patient(): void
    {
        $this->authorizeProfile();
        $identities = $this->app->make(IdentityRepository::class);
        $identities->shouldReceive('getAccountById')->once()->with(17)->andReturn($this->patientAccount());
        $identities->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn(null);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/perfil')->assertOk()->assertSee('Perfil y preferencias')
            ->assertSee('name="nombre_usuario"', false)
            ->assertSee('name="recordatorios_habilitados"', false)
            ->assertSee('Marcar correo como verificado')
            ->assertDontSee('name="id_rol"', false)
            ->assertDontSee('name="activo"', false);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_profile_validation_does_not_query_sql(): void
    {
        $this->authorizeProfile();

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->from('/perfil')->post('/perfil', [])->assertRedirect('/perfil')
            ->assertSessionHasErrors('nombre_usuario');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_enabling_reminders_without_verification_is_rejected(): void
    {
        $this->authorizeProfile();
        $identities = $this->app->make(IdentityRepository::class);
        $identities->shouldReceive('getAccountById')->once()->with(17)->andReturn($this->patientAccount());
        $identities->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => false,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => false,
        ]);
        $identities->shouldReceive('updateAccount')->never();
        $identities->shouldReceive('upsertNotificationPreferences')->never();
        Hash::shouldReceive('make')->never();

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->from('/perfil')->post('/perfil', [
            'nombre_usuario' => 'paciente1',
            'correo_acceso' => 'ana@example.test',
            'recordatorios_habilitados' => '1',
        ])->assertRedirect('/perfil')->assertSessionHasErrors('recordatorios_habilitados');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_valid_profile_update_keeps_role_and_ignores_posted_staff_fields(): void
    {
        $this->authorizeProfile();
        $identities = $this->app->make(IdentityRepository::class);
        $identities->shouldReceive('getAccountById')->twice()->with(17)->andReturn($this->patientAccount());
        $identities->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => false,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => true,
        ]);
        $identities->shouldReceive('identifierTaken')->once()->with('paciente1', 17)->andReturn(false);
        $identities->shouldReceive('identifierTaken')->once()->with('ana@example.test', 17)->andReturn(false);
        $identities->shouldReceive('updateAccount')->once()->with(17, Mockery::on(function (array $values): bool {
            return $values['nombre_usuario'] === 'paciente1'
                && $values['correo_acceso'] === 'ana@example.test'
                && $values['activo'] === true
                && $values['id_rol'] === 4;
        }));
        $identities->shouldReceive('upsertNotificationPreferences')->once()
            ->with(17, false, 'CorreoElectronico', 'ana@example.test', true);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('discardUndeliveredRemindersForAccount')->never();
        $this->app->instance(AppointmentRepository::class, $appointments);
        Hash::shouldReceive('make')->never();
        $database = $this->app['db'];
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->post('/perfil', [
            'nombre_usuario' => 'paciente1',
            'correo_acceso' => 'ana@example.test',
            'recordatorios_habilitados' => '0',
            'id_rol' => '1',
            'activo' => '0',
        ])->assertRedirect(route('portal.profile'))
            ->assertSessionHas('status', 'El perfil quedó actualizado.');
        $this->assertSame([], $database->getConnections());
    }

    public function test_verifying_email_does_not_enable_reminders(): void
    {
        $this->authorizeProfile();
        $identities = $this->app->make(IdentityRepository::class);
        $identities->shouldReceive('getAccountById')->once()->with(17)->andReturn($this->patientAccount());
        $identities->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => false,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => false,
        ]);
        $identities->shouldReceive('upsertNotificationPreferences')->once()
            ->with(17, false, 'CorreoElectronico', 'ana@example.test', true);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->post('/perfil/verificar-correo')->assertRedirect(route('portal.profile'))
            ->assertSessionHas('status', 'El correo quedó marcado como verificado. Eso no habilita los recordatorios.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_disabling_reminders_discards_undelivered_rows(): void
    {
        $this->authorizeProfile();
        $identities = $this->app->make(IdentityRepository::class);
        $identities->shouldReceive('getAccountById')->twice()->with(17)->andReturn($this->patientAccount());
        $identities->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => true,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => true,
        ]);
        $identities->shouldReceive('identifierTaken')->once()->with('paciente1', 17)->andReturn(false);
        $identities->shouldReceive('identifierTaken')->once()->with('ana@example.test', 17)->andReturn(false);
        $identities->shouldReceive('updateAccount')->once();
        $identities->shouldReceive('upsertNotificationPreferences')->once()
            ->with(17, false, 'CorreoElectronico', 'ana@example.test', true);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('discardUndeliveredRemindersForAccount')->once()->with(17);
        $this->app->instance(AppointmentRepository::class, $appointments);
        $database = $this->app['db'];
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->post('/perfil', [
            'nombre_usuario' => 'paciente1',
            'correo_acceso' => 'ana@example.test',
            'recordatorios_habilitados' => '0',
        ])->assertRedirect(route('portal.profile'));
        $this->assertSame([], $database->getConnections());
    }

    public function test_a_storage_failure_is_not_shown_as_success(): void
    {
        $this->authorizeProfile();
        $identities = $this->app->make(IdentityRepository::class);
        $identities->shouldReceive('getAccountById')->twice()->with(17)->andReturn($this->patientAccount());
        $identities->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => false,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => false,
        ]);
        $identities->shouldReceive('identifierTaken')->once()->with('paciente1', 17)->andReturn(false);
        $identities->shouldReceive('identifierTaken')->once()->with('ana@example.test', 17)->andReturn(false);
        $identities->shouldReceive('updateAccount')->once()->andThrow(new RuntimeException('constraint'));
        $identities->shouldReceive('upsertNotificationPreferences')->never();
        $database = $this->app['db'];
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->from('/perfil')->post('/perfil', [
            'nombre_usuario' => 'paciente1',
            'correo_acceso' => 'ana@example.test',
            'recordatorios_habilitados' => '0',
        ])->assertRedirect('/perfil')
            ->assertSessionHasErrors(['nombre_usuario' => 'No fue posible guardar los cambios.'])
            ->assertSessionMissing('status');
        $this->assertSame([], $database->getConnections());
    }

    public function test_the_panel_shows_the_profile_tile_for_a_patient(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 4]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('representation')->once()->with($user)->andReturn([
            'id' => 17,
            'username' => 'paciente1',
            'email' => 'ana@example.test',
            'role' => ['id' => 4, 'name' => PermissionCatalog::PATIENT_ROLE],
            'permissions' => [PermissionCatalog::UPDATE_PROFILE],
        ]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/panel')->assertOk()->assertSee('Perfil y preferencias')
            ->assertDontSee('perfil.actualizar')
            ->assertSee('vínculo aprobado');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_disabled_portal_does_not_write_the_profile(): void
    {
        config(['identity.patient_portal' => false]);
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 4]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->twice()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->twice()->with($user)->andReturn([
            PermissionCatalog::UPDATE_PROFILE,
        ]);
        $identities->shouldReceive('getAccountById')->never();
        $identities->shouldReceive('upsertNotificationPreferences')->never();
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/perfil')->assertOk()->assertSee('El portal no está habilitado.');
        $this->from('/perfil')->post('/perfil', [
            'nombre_usuario' => 'paciente1',
            'correo_acceso' => 'ana@example.test',
            'recordatorios_habilitados' => '0',
        ])->assertRedirect('/perfil')->assertSessionHasErrors('nombre_usuario');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function authorizeProfile(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 4]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([
            PermissionCatalog::UPDATE_PROFILE,
        ]);
        $this->app->instance(IdentityRepository::class, $identities);
    }

    private function patientAccount(): object
    {
        return (object) [
            'id_usuario' => 17,
            'nombre_usuario' => 'paciente1',
            'correo_acceso' => 'ana@example.test',
            'activo' => true,
            'id_rol' => 4,
            'rol_nombre' => PermissionCatalog::PATIENT_ROLE,
        ];
    }
}
