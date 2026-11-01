<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\Patients\PatientRepository;
use Illuminate\Support\Facades\Auth;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DeclaredMedicalInformationScreenTest extends TestCase
{
    public function test_guests_are_sent_to_login_for_declared_information(): void
    {
        $this->get('/informacion-medica')->assertRedirect(route('login'));
        $this->get('/medical-information')->assertNotFound();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_session_without_the_permission_is_denied(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 1]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([
            PermissionCatalog::UPDATE_PROFILE,
        ]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/informacion-medica')->assertForbidden()
            ->assertSee('No tiene permiso para realizar esta operación.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_missing_link_is_not_shown_as_an_empty_declaration(): void
    {
        $this->authorizeDeclaredInformation();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn(null);
        $patients->shouldReceive('getMedicalInformationByPatientId')->never();
        $patients->shouldReceive('upsertMedicalInformation')->never();
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/informacion-medica')->assertOk()
            ->assertSee('vínculo aprobado')
            ->assertDontSee('No hay información declarada')
            ->assertDontSee('name="grupo_sanguineo"', false);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_linked_patient_can_open_the_empty_declaration_form(): void
    {
        $this->authorizeDeclaredInformation();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn($this->linkedPatient());
        $patients->shouldReceive('getMedicalInformationByPatientId')->once()->with(8)->andReturn(null);
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/informacion-medica')->assertOk()
            ->assertSee('Información médica declarada')
            ->assertSee('No hay información declarada todavía')
            ->assertSee('name="grupo_sanguineo"', false)
            ->assertDontSee('name="diagnostico"', false)
            ->assertDontSee('name="resumen_atencion"', false);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_retrieval_failure_is_not_shown_as_missing_data(): void
    {
        $this->authorizeDeclaredInformation();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)
            ->andThrow(new RuntimeException('constraint'));
        $patients->shouldReceive('getMedicalInformationByPatientId')->never();
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/informacion-medica')->assertOk()
            ->assertSee('No fue posible completar la consulta.')
            ->assertDontSee('No hay información declarada todavía');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_invalid_blood_group_is_rejected(): void
    {
        $this->authorizeDeclaredInformation();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn($this->linkedPatient());
        $patients->shouldReceive('upsertMedicalInformation')->never();
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->from('/informacion-medica')->post('/informacion-medica', [
            'grupo_sanguineo' => 'XX',
        ])->assertRedirect('/informacion-medica')->assertSessionHasErrors('grupo_sanguineo');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_valid_declaration_is_saved_and_ignores_professional_fields(): void
    {
        $this->authorizeDeclaredInformation();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn($this->linkedPatient());
        $patients->shouldReceive('upsertMedicalInformation')->once()
            ->with(8, Mockery::on(function (array $values): bool {
                return $values['grupo_sanguineo'] === 'A+'
                    && $values['detalle_alergias'] === 'Polen'
                    && ! array_key_exists('diagnostico', $values);
            }));
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->post('/informacion-medica', [
            'grupo_sanguineo' => 'A+',
            'detalle_alergias' => 'Polen',
            'diagnostico' => 'No debe guardarse',
            'resumen_atencion' => 'Tampoco',
        ])->assertRedirect(route('patients.declared'))
            ->assertSessionHas('status', 'La información declarada quedó actualizada.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_storage_failure_is_not_shown_as_success(): void
    {
        $this->authorizeDeclaredInformation();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn($this->linkedPatient());
        $patients->shouldReceive('upsertMedicalInformation')->once()
            ->andThrow(new RuntimeException('constraint'));
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->from('/informacion-medica')->post('/informacion-medica', [
            'grupo_sanguineo' => 'A+',
        ])->assertRedirect('/informacion-medica')
            ->assertSessionHasErrors(['grupo_sanguineo' => 'No fue posible guardar los cambios.'])
            ->assertSessionMissing('status');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_panel_shows_the_declared_information_tile(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 4]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('representation')->once()->with($user)->andReturn([
            'id' => 17,
            'username' => 'paciente1',
            'email' => 'ana@example.test',
            'role' => ['id' => 4, 'name' => PermissionCatalog::PATIENT_ROLE],
            'permissions' => [PermissionCatalog::UPDATE_PROFILE, PermissionCatalog::DECLARE_HEALTH],
        ]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/panel')->assertOk()->assertSee('Información médica')
            ->assertDontSee('informacion.declarar');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_disabled_portal_does_not_write_declared_information(): void
    {
        config(['identity.patient_portal' => false]);
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 4]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->twice()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->twice()->with($user)->andReturn([
            PermissionCatalog::DECLARE_HEALTH,
        ]);
        $this->app->instance(IdentityRepository::class, $identities);
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->never();
        $patients->shouldReceive('upsertMedicalInformation')->never();
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/informacion-medica')->assertOk()->assertSee('El portal no está habilitado.');
        $this->from('/informacion-medica')->post('/informacion-medica', [
            'grupo_sanguineo' => 'A+',
        ])->assertRedirect('/informacion-medica')->assertSessionHasErrors('grupo_sanguineo');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function authorizeDeclaredInformation(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 4]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([
            PermissionCatalog::DECLARE_HEALTH,
        ]);
        $this->app->instance(IdentityRepository::class, $identities);
    }

    private function linkedPatient(): object
    {
        return (object) [
            'id_paciente' => 8,
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'id_usuario' => 17,
            'activo' => true,
        ];
    }
}
