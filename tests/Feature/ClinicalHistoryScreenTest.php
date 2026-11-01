<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\Patients\PatientRepository;
use Illuminate\Support\Facades\Auth;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ClinicalHistoryScreenTest extends TestCase
{
    public function test_guests_are_sent_to_login_for_clinical_history(): void
    {
        $this->get('/historial-medico')->assertRedirect(route('login'));
        $this->get('/medical-history')->assertNotFound();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_session_without_the_permission_is_denied(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([
            PermissionCatalog::CONSULT_HISTORY,
        ]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/historial-medico')->assertForbidden()
            ->assertSee('No tiene permiso para realizar esta operación.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_missing_link_is_not_shown_as_empty_history(): void
    {
        $this->authorizeClinicalHistory();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn(null);
        $this->app->instance(PatientRepository::class, $patients);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('getAppointmentsForPatient')->never();
        $this->app->instance(AppointmentRepository::class, $appointments);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/historial-medico')->assertOk()
            ->assertSee('vínculo aprobado')
            ->assertDontSee('No hay información disponible en el portal');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_empty_authorized_list_is_not_an_error(): void
    {
        $this->authorizeClinicalHistory();
        $this->bindLinkedPatient();
        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('getAppointmentsForPatient')->once()->with(8, null)->andReturn(collect());
        $this->app->instance(AppointmentRepository::class, $appointments);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/historial-medico')->assertOk()
            ->assertSee('No hay información disponible en el portal')
            ->assertSee('no significa que carezca de antecedentes');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_authorized_history_lists_without_professional_notes(): void
    {
        $this->authorizeClinicalHistory();
        $this->bindLinkedPatient();
        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('getAppointmentsForPatient')->once()->with(8, null)
            ->andReturn(collect([
                (object) [
                    'id_cita' => 21,
                    'codigo' => 'CIT-1',
                    'inicio' => '2026-11-01T08:00:00+00:00',
                    'fin' => '2026-11-01T08:30:00+00:00',
                    'estado' => 'Atendida',
                    'modalidad' => 'Presencial',
                    'motivo_consulta' => 'Nota no listada',
                    'diagnostico' => 'Asma',
                    'medico_nombres' => 'Luis',
                    'medico_apellidos' => 'Soto',
                    'especialidad_nombre' => 'Pediatría',
                ],
            ]));
        $this->app->instance(AppointmentRepository::class, $appointments);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/historial-medico')->assertOk()->assertSee('CIT-1')
            ->assertSee('Pediatría')->assertSee('Ver detalle')
            ->assertDontSee('Asma')->assertDontSee('Nota no listada');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_authorized_detail_omits_diagnoses(): void
    {
        $this->authorizeClinicalHistory();
        $this->bindLinkedPatient();
        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn((object) [
                'id_cita' => 21,
                'codigo' => 'CIT-1',
                'inicio' => '2026-11-01T08:00:00+00:00',
                'fin' => '2026-11-01T08:30:00+00:00',
                'estado' => 'Atendida',
                'modalidad' => 'Presencial',
                'motivo_consulta' => 'Dolor de cabeza',
                'motivo_cancelacion' => null,
                'id_paciente' => 8,
                'diagnostico' => 'Migraña',
                'resumen_atencion' => 'Nota profesional',
                'medico_nombres' => 'Luis',
                'medico_apellidos' => 'Soto',
                'registro_profesional' => 'MP-1',
                'especialidad_nombre' => 'Pediatría',
            ]);
        $this->app->instance(AppointmentRepository::class, $appointments);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/historial-medico/21')->assertOk()->assertSee('CIT-1')
            ->assertSee('Dolor de cabeza')->assertSee('Motivo declarado')
            ->assertDontSee('Migraña')->assertDontSee('Nota profesional');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_another_persons_record_is_unavailable(): void
    {
        $this->authorizeClinicalHistory();
        $this->bindLinkedPatient();
        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)
            ->andReturn((object) [
                'id_cita' => 21,
                'id_paciente' => 99,
                'codigo' => 'CIT-X',
                'diagnostico' => 'Secreto',
            ]);
        $this->app->instance(AppointmentRepository::class, $appointments);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/historial-medico/21')->assertOk()
            ->assertSee('El registro no está disponible para su consulta.')
            ->assertDontSee('CIT-X')->assertDontSee('Secreto');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_retrieval_failure_is_not_shown_as_empty_history(): void
    {
        $this->authorizeClinicalHistory();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)
            ->andThrow(new RuntimeException('constraint'));
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/historial-medico')->assertOk()
            ->assertSee('No fue posible completar la consulta.')
            ->assertDontSee('No hay información disponible en el portal');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_panel_shows_the_clinical_history_tile(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 4]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('representation')->once()->with($user)->andReturn([
            'id' => 17,
            'username' => 'paciente1',
            'email' => 'ana@example.test',
            'role' => ['id' => 4, 'name' => PermissionCatalog::PATIENT_ROLE],
            'permissions' => [PermissionCatalog::CONSULT_CLINICAL_HISTORY],
        ]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/panel')->assertOk()->assertSee('Historial médico')
            ->assertDontSee('historial.consultar');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_disabled_portal_does_not_query_clinical_history(): void
    {
        config(['identity.patient_portal' => false]);
        $this->authorizeClinicalHistory();
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->never();
        $this->app->instance(PatientRepository::class, $patients);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/historial-medico')->assertOk()->assertSee('El portal no está habilitado.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function authorizeClinicalHistory(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 4]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([
            PermissionCatalog::CONSULT_CLINICAL_HISTORY,
        ]);
        $this->app->instance(IdentityRepository::class, $identities);
    }

    private function bindLinkedPatient(): void
    {
        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getPatientByAccount')->once()->with(17)->andReturn((object) [
            'id_paciente' => 8,
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'id_usuario' => 17,
            'activo' => true,
        ]);
        $this->app->instance(PatientRepository::class, $patients);
    }
}
