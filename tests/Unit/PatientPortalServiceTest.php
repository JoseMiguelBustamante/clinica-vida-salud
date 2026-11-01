<?php

namespace Tests\Unit;

use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Identity\DuplicateAccountIdentifier;
use App\Modules\Identity\IdentityAccountService;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\PatientPortalDisabled;
use App\Modules\Identity\PatientPortalService;
use App\Modules\Identity\PatientProfileNotAllowed;
use App\Modules\Identity\PatientRegistrationDisabled;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\Identity\PermissionCatalogService;
use App\Modules\Identity\ReminderPreferenceRejected;
use App\Modules\Identity\RoleNotFound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PatientPortalServiceTest extends TestCase
{
    public function test_disabled_registration_is_rejected_before_writes(): void
    {
        config(['identity.patient_portal_registration' => false]);
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('createAccount')->never();
        $repository->shouldReceive('getRoleByName')->never();
        Hash::shouldReceive('make')->never();

        try {
            $this->service($repository)->registerPatientAccount([
                'nombre_usuario' => 'paciente1',
                'password' => 'twelve-chars',
            ]);
            $this->fail('Expected PatientRegistrationDisabled');
        } catch (PatientRegistrationDisabled) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_missing_patient_role_is_rejected_after_catalog_sync(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->twice()->with(PermissionCatalog::PATIENT_ROLE)->andReturn(null);
        $repository->shouldReceive('permissionCatalogIsApplied')->once()->andReturn(true);
        $repository->shouldReceive('upsertPermissionCatalog')->never();
        $repository->shouldReceive('createAccount')->never();

        try {
            $this->service($repository)->registerPatientAccount([
                'nombre_usuario' => 'paciente1',
                'password' => 'twelve-chars',
            ]);
            $this->fail('Expected RoleNotFound');
        } catch (RoleNotFound) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_registration_assigns_the_patient_role_and_hashes_once(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->once()->with(PermissionCatalog::PATIENT_ROLE)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        $repository->shouldReceive('identifierTaken')->once()->with('paciente1', null)->andReturn(false);
        $repository->shouldReceive('getRoleById')->once()->with(4)
            ->andReturn((object) ['id_rol' => 4]);
        $repository->shouldReceive('createAccount')->once()
            ->with('paciente1', null, 'stored-hash', true, 4)->andReturn(44);
        Hash::shouldReceive('make')->once()->with('twelve-chars')->andReturn('stored-hash');

        $id = $this->service($repository)->registerPatientAccount([
            'nombre_usuario' => 'paciente1',
            'password' => 'twelve-chars',
        ]);

        $this->assertSame(44, $id);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_taken_username_is_rejected_before_hashing(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $repository->shouldReceive('getRoleByName')->once()->with(PermissionCatalog::PATIENT_ROLE)
            ->andReturn((object) ['id_rol' => 4, 'nombre' => PermissionCatalog::PATIENT_ROLE]);
        $repository->shouldReceive('identifierTaken')->once()->with('paciente1', null)->andReturn(true);
        $repository->shouldReceive('createAccount')->never();
        Hash::shouldReceive('make')->never();

        try {
            $this->service($repository)->registerPatientAccount([
                'nombre_usuario' => 'paciente1',
                'password' => 'twelve-chars',
            ]);
            $this->fail('Expected DuplicateAccountIdentifier');
        } catch (DuplicateAccountIdentifier) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_staff_account_cannot_use_the_patient_profile(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $repository->shouldReceive('getAccountById')->once()->with(17)
            ->andReturn($this->patientAccount(rol: PermissionCatalog::ADMINISTRATOR_ROLE));
        $repository->shouldReceive('getNotificationPreferences')->never();
        $appointments->shouldReceive('discardUndeliveredRemindersForAccount')->never();

        try {
            $this->service($repository, $appointments)->getPatientProfile(17);
            $this->fail('Expected PatientProfileNotAllowed');
        } catch (PatientProfileNotAllowed) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_enabling_reminders_without_a_verified_email_is_rejected_before_writes(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $repository->shouldReceive('getAccountById')->once()->with(17)->andReturn($this->patientAccount());
        $repository->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => false,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => false,
        ]);
        $repository->shouldReceive('updateAccount')->never();
        $repository->shouldReceive('upsertNotificationPreferences')->never();
        $appointments->shouldReceive('discardUndeliveredRemindersForAccount')->never();
        Hash::shouldReceive('make')->never();

        try {
            $this->service($repository, $appointments)->updatePatientProfile(17, [
                'nombre_usuario' => 'paciente1',
                'correo_acceso' => 'ana@example.test',
                'recordatorios_habilitados' => true,
            ]);
            $this->fail('Expected ReminderPreferenceRejected');
        } catch (ReminderPreferenceRejected) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_changing_email_cannot_keep_reminders_enabled(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $repository->shouldReceive('getAccountById')->once()->with(17)->andReturn($this->patientAccount());
        $repository->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => true,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => true,
        ]);
        $repository->shouldReceive('updateAccount')->never();
        $repository->shouldReceive('upsertNotificationPreferences')->never();
        $appointments->shouldReceive('discardUndeliveredRemindersForAccount')->never();

        try {
            $this->service($repository, $appointments)->updatePatientProfile(17, [
                'nombre_usuario' => 'paciente1',
                'correo_acceso' => 'nueva@example.test',
                'recordatorios_habilitados' => true,
            ]);
            $this->fail('Expected ReminderPreferenceRejected');
        } catch (ReminderPreferenceRejected) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_verifying_email_does_not_enable_reminders(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $repository->shouldReceive('getAccountById')->once()->with(17)->andReturn($this->patientAccount());
        $repository->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => false,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => false,
        ]);
        $repository->shouldReceive('upsertNotificationPreferences')->once()
            ->with(17, false, 'CorreoElectronico', 'ana@example.test', true);
        $appointments->shouldReceive('discardUndeliveredRemindersForAccount')->never();

        $this->service($repository, $appointments)->verifyPatientEmail(17);

        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_disabling_reminders_discards_undelivered_rows_and_keeps_role(): void
    {
        $repository = Mockery::mock(IdentityRepository::class);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $repository->shouldReceive('getAccountById')->twice()->with(17)->andReturn($this->patientAccount());
        $repository->shouldReceive('getNotificationPreferences')->once()->with(17)->andReturn((object) [
            'recordatorios_habilitados' => true,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => true,
        ]);
        $repository->shouldReceive('identifierTaken')->once()->with('paciente1', 17)->andReturn(false);
        $repository->shouldReceive('identifierTaken')->once()->with('ana@example.test', 17)->andReturn(false);
        $repository->shouldReceive('updateAccount')->once()->with(17, Mockery::on(function (array $values): bool {
            return $values['nombre_usuario'] === 'paciente1'
                && $values['correo_acceso'] === 'ana@example.test'
                && $values['activo'] === true
                && $values['id_rol'] === 4
                && ! array_key_exists('contrasena_hash', $values);
        }));
        $repository->shouldReceive('upsertNotificationPreferences')->once()
            ->with(17, false, 'CorreoElectronico', 'ana@example.test', true);
        $appointments->shouldReceive('discardUndeliveredRemindersForAccount')->once()->with(17);
        Hash::shouldReceive('make')->never();
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());

        $this->service($repository, $appointments)->updatePatientProfile(17, [
            'nombre_usuario' => 'paciente1',
            'correo_acceso' => 'ana@example.test',
            'recordatorios_habilitados' => false,
        ]);
    }

    public function test_a_disabled_portal_rejects_profile_writes(): void
    {
        config(['identity.patient_portal' => false]);
        $repository = Mockery::mock(IdentityRepository::class);
        $appointments = Mockery::mock(AppointmentRepository::class);
        $repository->shouldReceive('getAccountById')->never();
        $repository->shouldReceive('upsertNotificationPreferences')->never();
        $appointments->shouldReceive('discardUndeliveredRemindersForAccount')->never();

        try {
            $this->service($repository, $appointments)->updatePatientProfile(17, [
                'nombre_usuario' => 'paciente1',
                'recordatorios_habilitados' => false,
            ]);
            $this->fail('Expected PatientPortalDisabled');
        } catch (PatientPortalDisabled) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function patientAccount(string $rol = PermissionCatalog::PATIENT_ROLE): object
    {
        return (object) [
            'id_usuario' => 17,
            'nombre_usuario' => 'paciente1',
            'correo_acceso' => 'ana@example.test',
            'activo' => true,
            'id_rol' => 4,
            'rol_nombre' => $rol,
        ];
    }

    private function service(IdentityRepository $identities, ?AppointmentRepository $appointments = null): PatientPortalService
    {
        return new PatientPortalService(
            new IdentityAccountService($identities),
            $identities,
            new PermissionCatalogService($identities),
            $appointments ?? Mockery::mock(AppointmentRepository::class),
        );
    }
}
