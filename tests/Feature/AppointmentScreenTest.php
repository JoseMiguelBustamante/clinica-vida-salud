<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\MedicalStaff\SpecialtyRepository;
use App\Modules\Patients\PatientRepository;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class AppointmentScreenTest extends TestCase
{
    public function test_guests_are_sent_to_login_for_booking(): void
    {
        $this->get('/citas/programar')->assertRedirect(route('login'));
        $this->get('/appointments')->assertNotFound();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_session_without_the_permission_is_denied(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::CONSULT_AVAILABILITY]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/citas/programar')->assertForbidden()->assertSee('No tiene permiso para realizar esta operación.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_authorized_session_opens_the_booking_form_without_sql(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::BOOK_APPOINTMENTS]);
        $this->app->instance(IdentityRepository::class, $identities);

        $patients = Mockery::mock(PatientRepository::class);
        $patients->shouldReceive('getActivePatients')->once()->with(null)->andReturn(collect([
            (object) ['id_paciente' => 8, 'nombres' => 'Ana', 'apellidos' => 'Rojas', 'ci' => '123'],
        ]));
        $this->app->instance(PatientRepository::class, $patients);

        $specialties = Mockery::mock(SpecialtyRepository::class);
        $specialties->shouldReceive('getActiveSpecialties')->once()->andReturn(collect([
            (object) ['id_especialidad' => 3, 'nombre' => 'Pediatría', 'activo' => true],
        ]));
        $this->app->instance(SpecialtyRepository::class, $specialties);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/citas/programar')->assertOk()->assertSee('Programar cita médica')
            ->assertSee('Ana')->assertSee('Pediatría')->assertSee('Confirmar programación');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_booking_validation_does_not_query_sql(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::BOOK_APPOINTMENTS]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->from('/citas/programar')->post('/citas', [])->assertRedirect('/citas/programar')
            ->assertSessionHasErrors('id_paciente');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_registered_appointment_is_shown_without_sql(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::BOOK_APPOINTMENTS]);
        $this->app->instance(IdentityRepository::class, $identities);

        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn((object) [
            'id_cita' => 21,
            'codigo' => 'CIT-20261101080000ab',
            'inicio' => '2026-11-01T08:00:00+00:00',
            'fin' => '2026-11-01T08:30:00+00:00',
            'motivo_consulta' => null,
            'modalidad' => 'Presencial',
            'estado' => 'Programada',
            'paciente_nombres' => 'Ana',
            'paciente_apellidos' => 'Rojas',
            'paciente_ci' => '123',
            'medico_nombres' => 'Luis',
            'medico_apellidos' => 'Soto',
            'registro_profesional' => 'MP-1',
            'especialidad_nombre' => 'Pediatría',
        ]);
        $this->app->instance(AppointmentRepository::class, $appointments);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/citas/21')->assertOk()->assertSee('CIT-20261101080000ab')
            ->assertSee('Programada')->assertSee('Ana')->assertSee('no confirma', false);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_guests_are_sent_to_login_for_the_appointment_list(): void
    {
        $this->get('/citas')->assertRedirect(route('login'));
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_session_without_reschedule_permission_is_denied_the_list(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::CONSULT_AVAILABILITY]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/citas')->assertForbidden()->assertSee('No tiene permiso para realizar esta operación.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_authorized_session_lists_appointments_without_sql(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::RESCHEDULE_APPOINTMENTS]);
        $this->app->instance(IdentityRepository::class, $identities);

        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('getAllAppointments')->once()->with(null)->andReturn(collect([
            (object) [
                'id_cita' => 21,
                'codigo' => 'CIT-20261101080000ab',
                'inicio' => '2026-11-01T08:00:00+00:00',
                'estado' => 'Programada',
                'paciente_nombres' => 'Ana',
                'paciente_apellidos' => 'Rojas',
                'medico_nombres' => 'Luis',
                'medico_apellidos' => 'Soto',
                'especialidad_nombre' => 'Pediatría',
            ],
        ]));
        $this->app->instance(AppointmentRepository::class, $appointments);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/citas')->assertOk()->assertSee('Citas registradas')
            ->assertSee('CIT-20261101080000ab')->assertSee('Reprogramar');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_reschedule_form_opens_without_sql(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::RESCHEDULE_APPOINTMENTS]);
        $this->app->instance(IdentityRepository::class, $identities);

        $appointments = Mockery::mock(AppointmentRepository::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn((object) [
            'id_cita' => 21,
            'codigo' => 'CIT-20261101080000ab',
            'inicio' => '2026-11-01T08:00:00+00:00',
            'fin' => '2026-11-01T08:30:00+00:00',
            'motivo_consulta' => null,
            'modalidad' => 'Presencial',
            'estado' => 'Confirmada',
            'id_paciente' => 8,
            'id_medico' => 4,
            'id_especialidad' => 3,
            'paciente_nombres' => 'Ana',
            'paciente_apellidos' => 'Rojas',
            'paciente_ci' => '123',
            'medico_nombres' => 'Luis',
            'medico_apellidos' => 'Soto',
            'registro_profesional' => 'MP-1',
            'especialidad_nombre' => 'Pediatría',
        ]);
        $this->app->instance(AppointmentRepository::class, $appointments);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/citas/21/reprogramar')->assertOk()->assertSee('Reprogramar CIT-20261101080000ab')
            ->assertSee('Confirmada')->assertSee('Confirmar reprogramación');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_reschedule_validation_does_not_query_sql(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::RESCHEDULE_APPOINTMENTS]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->from('/citas/21/reprogramar')->post('/citas/21/reprogramar', [])
            ->assertRedirect('/citas/21/reprogramar')->assertSessionHasErrors('fecha');
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
