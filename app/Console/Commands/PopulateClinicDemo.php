<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Appointments\AppointmentService;
use App\Modules\Appointments\AvailabilityService;
use App\Modules\Identity\DuplicateAccountIdentifier;
use App\Modules\Identity\IdentityAccountService;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\InvalidAccountInput;
use App\Modules\Identity\PatientPortalService;
use App\Modules\Identity\PatientRegistrationDisabled;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\Identity\PermissionCatalogService;
use App\Modules\MedicalStaff\DuplicateProfessionalLicense;
use App\Modules\MedicalStaff\DuplicateSpecialtyName;
use App\Modules\MedicalStaff\InvalidPhysicianInput;
use App\Modules\MedicalStaff\PhysicianService;
use App\Modules\MedicalStaff\SpecialtyService;
use App\Modules\Patients\DuplicatePatientDocument;
use App\Modules\Patients\MedicalInformationService;
use App\Modules\Patients\PatientService;
use App\Modules\Schedules\ScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PopulateClinicDemo extends Command
{
    protected $signature = 'clinic:populate-demo {--force : Write demo rows without confirmation} {--reset-passwords : Replace passwords of demo.* accounts and print a new one}';

    protected $description = 'Load demo users, patients, schedules and appointments';

    protected $help = <<<'HELP'
        Requires an already migrated PostgreSQL database and an existing active Administrador.
        Creates missing demo specialties with descriptions, weekday hours for every active
        bookable physician (Saturday morning for the first), patients identified by DEMO-
        CI values, and a reservation for every demo patient assigned to a physician
        (Programada, Confirmada, Cancelada, reprogrammed, arrived). Atendida with a care
        record is created only when a physician already has an active linked account.
        If no physician can take appointments, one unlinked DEMO-MP-01 physician is created.
        Also creates demo.recepcionista, demo.medico and demo.1001–demo.1008 (Paciente),
        links them, and prints one random password shown once for accounts created in
        this run. Does not create Administrador. Existing usernames, emails and password
        hashes are not changed unless --reset-passwords is passed.
        --reset-passwords replaces passwords of demo.recepcionista, demo.medico and
        demo.1001–demo.1008 and prints a new password shown once. It does not change admin.
        Idempotent: safe to rerun; rows that already exist are skipped. This is not db:seed
        and does not run on deploy. Confirmation is required unless --force is passed.
        --no-interaction without --force refuses before database access. On any error, stop
        and contact the operator.
        HELP;

    public function __construct(
        private IdentityRepository $identities,
        private IdentityAccountService $accounts,
        private PermissionCatalogService $catalog,
        private PatientPortalService $portal,
        private MedicalInformationService $medicalInformation,
        private SpecialtyService $specialties,
        private PhysicianService $physicians,
        private PatientService $patients,
        private ScheduleService $schedules,
        private AppointmentService $appointments,
        private AvailabilityService $availability,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->input->isInteractive()) {
            $this->error('Confirmation is required; no changes made. Re-run with --force after reviewing.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Create demo users, patients, schedules and appointments? Existing passwords are not changed.')) {
            $this->error('Confirmation is required; no changes made.');

            return self::FAILURE;
        }

        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                $this->error('Unable to load demo data; stop and contact the operator.');

                return self::FAILURE;
            }

            $actorId = $this->activeAdministratorId();
            if ($actorId === null) {
                $this->error('An active administrator is required; no changes made.');

                return self::FAILURE;
            }

            $this->catalog->synchronize();
            $this->ensureSpecialties();
            $physicians = $this->bookablePhysicians();
            foreach ($physicians as $index => $physician) {
                try {
                    $this->ensureWeekdayHours((int) $physician->id_medico, $index);
                } catch (Throwable $exception) {
                    Log::error('[PopulateClinicDemo - hours] '.$exception::class);
                }
            }
            $patients = $this->ensurePatients();
            $password = $this->ensureDemoUsers($physicians, $patients);
            if ($this->option('reset-passwords')) {
                $password = $this->resetDemoPasswords() ?? $password;
            }
            $this->info('Demo catalog is available. Existing passwords were not changed unless --reset-passwords was used.');
            $this->printDemoPassword(is_string($password) ? $password : null);
            try {
                $this->ensureAppointments($physicians, $patients, $actorId);
            } catch (Throwable $exception) {
                Log::error('[PopulateClinicDemo - appointments] '.$exception::class);
                $this->warn('Demo users were saved; appointments could not be completed.');
                $this->warn($exception::class);
            }
        } catch (Throwable $exception) {
            Log::error('[PopulateClinicDemo] '.$exception::class);
            $this->error('Unable to load demo data; stop and contact the operator.');
            $this->warn($exception::class);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function activeAdministratorId(): ?int
    {
        $admin = $this->identities->getAllAccounts(null)->first(
            fn (object $account): bool => $account->rol_nombre === 'Administrador' && (bool) $account->activo,
        );

        return $admin === null ? null : (int) $admin->id_usuario;
    }

    private function printDemoPassword(?string $password): void
    {
        if ($password === null || $password === '') {
            return;
        }
        $this->newLine();
        $this->warn('Password for accounts created in this run (shown once):');
        $this->line($password);
        $this->line('demo.recepcionista  Recepcionista');
        $this->line('demo.medico         Médico');
        $this->line('demo.1001 … demo.1008  Paciente');
    }

    /**
     * @param  list<object>  $physicians
     * @param  list<object>  $patients
     */
    private function ensureDemoUsers(array $physicians, array $patients): ?string
    {
        $password = $this->missingDemoUsernames($patients) === [] ? null : $this->generatedDemoPassword();
        $this->ensureStaffUser(PermissionCatalog::RECEPTIONIST_ROLE, 'demo.recepcionista', 'demo.recepcionista@example.com', $password);
        $medicoId = $this->ensureStaffUser(PermissionCatalog::PHYSICIAN_ROLE, 'demo.medico', 'demo.medico@example.com', $password);
        if ($medicoId !== null) {
            try {
                $this->linkFirstUnlinkedPhysician($physicians, $medicoId);
            } catch (Throwable $exception) {
                Log::error('[PopulateClinicDemo - physician-link] '.$exception::class);
            }
        }
        foreach ($patients as $patient) {
            $suffix = str_replace('DEMO-', '', (string) $patient->ci);
            $username = 'demo.'.$suffix;
            $userId = $this->ensurePatientUser($username, $username.'@example.com', $password);
            if ($userId !== null) {
                try {
                    $this->linkPatientAndPortalData($patient, $userId);
                } catch (Throwable $exception) {
                    Log::error('[PopulateClinicDemo - patient-link] '.$exception::class);
                }
            }
        }

        return $password;
    }

    /**
     * @param  list<object>  $patients
     * @return list<string>
     */
    private function missingDemoUsernames(array $patients): array
    {
        $names = ['demo.recepcionista', 'demo.medico'];
        foreach ($patients as $patient) {
            $names[] = 'demo.'.str_replace('DEMO-', '', (string) $patient->ci);
        }

        return array_values(array_filter(
            $names,
            fn (string $username): bool => $this->accountByUsername($username) === null,
        ));
    }

    private function generatedDemoPassword(): string
    {
        return Str::password(16, true, true, false, false);
    }

    private function resetDemoPasswords(): ?string
    {
        $names = ['demo.recepcionista', 'demo.medico'];
        for ($number = 1001; $number <= 1008; $number++) {
            $names[] = 'demo.'.$number;
        }
        $password = $this->generatedDemoPassword();
        $updated = 0;
        foreach ($names as $username) {
            $account = $this->accountByUsername($username);
            if ($account === null) {
                continue;
            }
            try {
                $this->accounts->updateAccountCredentials((int) $account->id_usuario, [
                    'nombre_usuario' => (string) $account->nombre_usuario,
                    'correo_acceso' => $this->usableAccountEmail($username, $account->correo_acceso ?? null),
                    'password' => $password,
                ]);
                $updated++;
            } catch (Throwable $exception) {
                Log::error('[PopulateClinicDemo - reset] '.$exception::class);
            }
        }

        return $updated === 0 ? null : $password;
    }

    private function accountByUsername(string $username): ?object
    {
        return $this->identities->getAllAccounts($username)->first(
            fn (object $account): bool => (string) $account->nombre_usuario === $username,
        );
    }

    private function ensureStaffUser(string $roleName, string $username, string $email, ?string $password): ?int
    {
        $existing = $this->accountByUsername($username);
        if ($existing !== null) {
            return (int) $existing->id_usuario;
        }
        if ($password === null) {
            return null;
        }
        $role = $this->identities->getRoleByName($roleName);
        if ($role === null) {
            return null;
        }
        try {
            return $this->accounts->createAccount([
                'nombre_usuario' => $username,
                'correo_acceso' => $email,
                'password' => $password,
                'id_rol' => (int) $role->id_rol,
                'activo' => true,
            ]);
        } catch (DuplicateAccountIdentifier) {
            $again = $this->accountByUsername($username);

            return $again === null ? null : (int) $again->id_usuario;
        } catch (InvalidAccountInput $exception) {
            Log::error('[PopulateClinicDemo - staff] '.$exception::class);

            return null;
        }
    }

    private function ensurePatientUser(string $username, string $email, ?string $password): ?int
    {
        $existing = $this->accountByUsername($username);
        if ($existing !== null) {
            return (int) $existing->id_usuario;
        }
        if ($password === null) {
            return null;
        }
        try {
            return $this->portal->registerPatientAccount([
                'nombre_usuario' => $username,
                'correo_acceso' => $email,
                'password' => $password,
            ]);
        } catch (DuplicateAccountIdentifier) {
            $again = $this->accountByUsername($username);

            return $again === null ? null : (int) $again->id_usuario;
        } catch (PatientRegistrationDisabled) {
            return $this->ensureStaffUser(PermissionCatalog::PATIENT_ROLE, $username, $email, $password);
        } catch (InvalidAccountInput $exception) {
            Log::error('[PopulateClinicDemo - patient-user] '.$exception::class);

            return null;
        }
    }

    private function usableAccountEmail(string $username, mixed $current): string
    {
        if (is_string($current) && $current !== '' && filter_var($current, FILTER_VALIDATE_EMAIL) !== false) {
            return $current;
        }

        return $username.'@example.com';
    }

    /**
     * @param  list<object>  $physicians
     */
    private function linkFirstUnlinkedPhysician(array $physicians, int $userId): void
    {
        foreach ($physicians as $physician) {
            if ($this->tryLinkPhysician((int) $physician->id_medico, $userId)) {
                return;
            }
        }

        $demo = $this->createDemoPhysician();
        if (! $this->tryLinkPhysician((int) $demo->id_medico, $userId)) {
            throw new InvalidPhysicianInput;
        }
    }

    private function tryLinkPhysician(int $physicianId, int $userId): bool
    {
        $current = $this->physicians->getPhysicianById($physicianId);
        if ($current->id_usuario !== null && (int) $current->id_usuario > 0) {
            return (int) $current->id_usuario === $userId;
        }

        try {
            $this->physicians->linkPhysicianAccount($physicianId, $userId);
        } catch (InvalidPhysicianInput) {
            return false;
        }

        return true;
    }

    private function linkPatientAndPortalData(object $patient, int $userId): void
    {
        if ($patient->id_usuario === null || (int) $patient->id_usuario < 1) {
            $birth = $patient->fecha_nacimiento ?? null;
            $this->patients->updatePatient((int) $patient->id_paciente, [
                'ci' => $patient->ci,
                'nombres' => (string) $patient->nombres,
                'apellidos' => (string) $patient->apellidos,
                'fecha_nacimiento' => $birth === null || $birth === '' ? null : substr((string) $birth, 0, 10),
                'sexo' => $patient->sexo,
                'telefono' => $patient->telefono,
                'correo_contacto' => $patient->correo_contacto,
                'direccion' => $patient->direccion,
                'id_usuario' => $userId,
            ]);
        }
        try {
            $this->portal->verifyPatientEmail($userId);
            $account = $this->identities->getAccountById($userId);
            if ($account !== null) {
                $this->portal->updatePatientProfile($userId, [
                    'nombre_usuario' => (string) $account->nombre_usuario,
                    'correo_acceso' => $account->correo_acceso,
                    'recordatorios_habilitados' => true,
                ]);
            }
            $declared = $this->medicalInformation->getDeclaredMedicalInformation($userId);
            if ($declared['information'] === null) {
                $this->medicalInformation->updateDeclaredMedicalInformation($userId, [
                    'grupo_sanguineo' => 'O+',
                    'situacion_alergias' => 'Ninguna',
                    'situacion_enfermedades' => 'Ninguna',
                    'situacion_medicamentos' => 'Ninguna',
                    'antecedentes' => 'Declaración de demostración.',
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('[PopulateClinicDemo - portal] '.$exception::class);
        }
    }

    private function ensureSpecialties(): void
    {
        $catalog = [
            'Cardiología' => 'Atención del corazón y vasos sanguíneos.',
            'Dermatología' => 'Atención de piel, cabello y uñas.',
            'Geriatría' => 'Atención de personas adultas mayores.',
            'Neumología' => 'Atención del aparato respiratorio.',
            'Pediatría' => 'Atención de niñas, niños y adolescentes.',
        ];
        foreach ($catalog as $name => $description) {
            try {
                $this->specialties->createSpecialty($name, $description, true);
            } catch (DuplicateSpecialtyName) {
                continue;
            }
        }
    }

    /**
     * @return list<object>
     */
    private function bookablePhysicians(): array
    {
        $bookable = [];
        foreach ($this->physicians->getAllPhysicians(null) as $physician) {
            if (! $physician->activo) {
                continue;
            }
            if ($this->firstSpecialtyId($physician) !== null) {
                $bookable[] = $physician;
            }
        }

        if ($bookable !== []) {
            return $bookable;
        }

        return [$this->createDemoPhysician()];
    }

    private function createDemoPhysician(): object
    {
        $specialtyId = $this->firstActiveSpecialtyId();
        try {
            $id = $this->physicians->createPhysician([
                'registro_profesional' => 'DEMO-MP-01',
                'nombres' => 'Ana',
                'apellidos' => 'Rojas',
                'telefono' => '70000000',
                'correo_contacto' => 'ana.rojas@example.com',
                'activo' => true,
                'id_usuario' => null,
                'especialidades' => [$specialtyId],
            ]);
        } catch (DuplicateProfessionalLicense) {
            $existing = $this->physicians->getAllPhysicians('DEMO-MP-01')->first();
            if ($existing === null) {
                throw new DuplicateProfessionalLicense;
            }

            return $this->physicians->getPhysicianById((int) $existing->id_medico);
        }

        return $this->physicians->getPhysicianById($id);
    }

    private function firstSpecialtyId(object $physician): ?int
    {
        $specialties = $physician->especialidades ?? collect();
        $match = collect($specialties)->first(function (object $specialty): bool {
            return ! isset($specialty->activo) || (bool) $specialty->activo;
        });

        return $match === null ? null : (int) $match->id_especialidad;
    }

    private function firstActiveSpecialtyId(): int
    {
        $specialty = $this->specialties->getActiveSpecialties()->first();
        if ($specialty === null) {
            throw new \RuntimeException('No active specialty is available for demo data.');
        }

        return (int) $specialty->id_especialidad;
    }

    private function ensureWeekdayHours(int $physicianId, int $index): void
    {
        $existingDays = $this->schedules->getAllSchedules(null)
            ->where('id_medico', $physicianId)
            ->where('activo', true)
            ->pluck('dia_semana');
        $morning = $index % 2 === 0;
        $start = $morning ? '08:00' : '14:00';
        $end = $morning ? '12:00' : '18:00';
        $days = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];
        if ($morning) {
            $days[] = 'Sabado';
        }

        foreach ($days as $day) {
            if ($existingDays->contains($day)) {
                continue;
            }

            try {
                $this->schedules->createSchedule([
                    'id_medico' => $physicianId,
                    'dia_semana' => $day,
                    'hora_inicio' => $start,
                    'hora_fin' => $end,
                    'duracion_cita_minutos' => 30,
                    'activo' => true,
                ]);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * @return list<object>
     */
    private function ensurePatients(): array
    {
        $rows = [
            ['ci' => 'DEMO-1001', 'nombres' => 'Lucía', 'apellidos' => 'Vargas', 'fecha_nacimiento' => '1988-03-12', 'sexo' => 'Femenino', 'telefono' => '70000001', 'correo_contacto' => 'lucia.vargas@example.com', 'direccion' => 'Av. Arce 1201, La Paz'],
            ['ci' => 'DEMO-1002', 'nombres' => 'Marco', 'apellidos' => 'Quispe', 'fecha_nacimiento' => '1975-11-04', 'sexo' => 'Masculino', 'telefono' => '70000002', 'correo_contacto' => 'marco.quispe@example.com', 'direccion' => 'Calle Sagarnaga 45, La Paz'],
            ['ci' => 'DEMO-1003', 'nombres' => 'Elena', 'apellidos' => 'Soto', 'fecha_nacimiento' => '1994-07-21', 'sexo' => 'Femenino', 'telefono' => '70000003', 'correo_contacto' => 'elena.soto@example.com', 'direccion' => 'Av. Busch 890, La Paz'],
            ['ci' => 'DEMO-1004', 'nombres' => 'Diego', 'apellidos' => 'Mamani', 'fecha_nacimiento' => '1969-01-30', 'sexo' => 'Masculino', 'telefono' => '70000004', 'correo_contacto' => 'diego.mamani@example.com', 'direccion' => 'Zona Sur, Calle 21, La Paz'],
            ['ci' => 'DEMO-1005', 'nombres' => 'Rosa', 'apellidos' => 'Flores', 'fecha_nacimiento' => '2001-09-08', 'sexo' => 'Femenino', 'telefono' => '70000005', 'correo_contacto' => 'rosa.flores@example.com', 'direccion' => 'Villa Fatima, Av. Las Americas 310, La Paz'],
            ['ci' => 'DEMO-1006', 'nombres' => 'Pablo', 'apellidos' => 'Choque', 'fecha_nacimiento' => '1982-05-16', 'sexo' => 'Masculino', 'telefono' => '70000006', 'correo_contacto' => 'pablo.choque@example.com', 'direccion' => 'El Alto, Av. Juan Pablo II 1500'],
            ['ci' => 'DEMO-1007', 'nombres' => 'Nadia', 'apellidos' => 'Rojas', 'fecha_nacimiento' => '1990-12-02', 'sexo' => 'Femenino', 'telefono' => '70000007', 'correo_contacto' => 'nadia.rojas@example.com', 'direccion' => 'Sopocachi, Av. 6 de Agosto 2200, La Paz'],
            ['ci' => 'DEMO-1008', 'nombres' => 'Luis', 'apellidos' => 'Condori', 'fecha_nacimiento' => '1964-08-19', 'sexo' => 'Masculino', 'telefono' => '70000008', 'correo_contacto' => 'luis.condori@example.com', 'direccion' => 'Miraflores, Plaza Villarroel 12, La Paz'],
        ];

        $patients = [];
        foreach ($rows as $row) {
            try {
                $id = $this->patients->createPatient($row);
            } catch (DuplicatePatientDocument) {
                $existing = $this->patients->getAllPatients($row['ci'])->first(
                    fn (object $patient): bool => (string) $patient->ci === $row['ci'],
                );
                if ($existing === null) {
                    throw new DuplicatePatientDocument;
                }
                $patients[] = $existing;

                continue;
            }

            $patients[] = $this->patients->getPatientById($id);
        }

        return $patients;
    }

    /**
     * @param  list<object>  $physicians
     * @param  list<object>  $patients
     */
    private function ensureAppointments(array $physicians, array $patients, int $actorUserId): void
    {
        if ($physicians === [] || $patients === []) {
            return;
        }

        $used = [];
        $actions = ['attend', 'confirm_arrive', 'programada', 'cancel', 'reschedule', 'confirm', 'programada', 'confirm'];
        foreach ($patients as $index => $patient) {
            if ($this->patientHasAppointments((int) $patient->id_paciente)) {
                continue;
            }
            try {
                $this->bookPatientReservation(
                    $physicians[$index % count($physicians)],
                    $patient,
                    $actions[$index % count($actions)],
                    $actorUserId,
                    $used,
                );
            } catch (Throwable $exception) {
                Log::error('[PopulateClinicDemo - appointment] '.$exception::class);
            }
        }
    }

    /**
     * @param  array<int, array<string, true>>  $used
     */
    private function bookPatientReservation(object $physician, object $patient, string $action, int $actorUserId, array &$used): void
    {
        $specialtyId = $this->firstSpecialtyId($physician);
        $slot = $this->takeSlot($physician, $used);
        if ($specialtyId === null || $slot === null) {
            return;
        }

        $appointmentId = $this->appointments->createAppointment([
            'id_paciente' => (int) $patient->id_paciente,
            'id_especialidad' => $specialtyId,
            'id_medico' => (int) $physician->id_medico,
            'fecha' => $slot['fecha'],
            'hora_inicio' => $slot['hora_inicio'],
            'hora_fin' => $slot['hora_fin'],
            'modalidad' => 'Presencial',
            'motivo_consulta' => 'Control de demostración',
        ], $actorUserId);

        if ($action === 'confirm_arrive') {
            $this->appointments->confirmAppointment($appointmentId, $actorUserId);
            $this->appointments->registerAppointmentArrival($appointmentId, $actorUserId);

            return;
        }
        if ($action === 'cancel') {
            $this->appointments->cancelAppointment($appointmentId, 'El paciente no podrá asistir.', $actorUserId);

            return;
        }
        if ($action === 'reschedule') {
            $next = $this->takeSlot($physician, $used);
            if ($next !== null) {
                $this->appointments->rescheduleAppointment($appointmentId, [
                    'fecha' => $next['fecha'],
                    'hora_inicio' => $next['hora_inicio'],
                    'hora_fin' => $next['hora_fin'],
                ], $actorUserId);
            }

            return;
        }
        if ($action === 'confirm') {
            $this->appointments->confirmAppointment($appointmentId, $actorUserId);

            return;
        }
        if ($action === 'attend') {
            $linked = $this->linkedActivePhysicianUserId($physician);
            if ($linked !== null) {
                try {
                    $this->appointments->recordAppointmentCare($appointmentId, [
                        'resumen_atencion' => 'Control de demostración realizado.',
                        'diagnostico' => 'Sin hallazgos de urgencia.',
                        'indicaciones' => 'Control en 30 días.',
                    ], $linked);

                    return;
                } catch (Throwable $exception) {
                    Log::error('[PopulateClinicDemo - attend] '.$exception::class);
                }
            }
            $this->appointments->confirmAppointment($appointmentId, $actorUserId);
        }
    }

    private function linkedActivePhysicianUserId(object $physician): ?int
    {
        $row = $this->physicians->getPhysicianById((int) $physician->id_medico);
        $userId = $row->id_usuario ?? null;
        if ($userId === null || (int) $userId < 1) {
            return null;
        }
        $account = $this->identities->getAccountById((int) $userId);
        if ($account === null || ! $account->activo) {
            return null;
        }

        return (int) $userId;
    }

    /**
     * @param  array<int, array<string, true>>  $used
     * @return array{fecha: string, hora_inicio: string, hora_fin: string}|null
     */
    private function takeSlot(object $physician, array &$used): ?array
    {
        $physicianId = (int) $physician->id_medico;
        $specialtyId = $this->firstSpecialtyId($physician);
        if ($specialtyId === null) {
            return null;
        }
        foreach ($this->upcomingSlots($physicianId, $specialtyId) as $slot) {
            $key = $slot['fecha'].'|'.$slot['hora_inicio'];
            if (isset($used[$physicianId][$key])) {
                continue;
            }
            $used[$physicianId][$key] = true;

            return $slot;
        }

        return null;
    }

    private function patientHasAppointments(int $patientId): bool
    {
        $history = $this->appointments->consultPatientHistory(['id_paciente' => $patientId]);

        return ($history['appointments'] ?? collect())->isNotEmpty();
    }

    /**
     * @return list<array{fecha: string, hora_inicio: string, hora_fin: string}>
     */
    private function upcomingSlots(int $physicianId, int $specialtyId): array
    {
        $slots = [];
        $day = Carbon::now('UTC')->startOfDay()->addDay();
        for ($offset = 0; $offset < 21 && count($slots) < 16; $offset++) {
            $candidate = $day->copy()->addDays($offset);
            if ($candidate->isoWeekday() === 7) {
                continue;
            }
            $date = $candidate->format('Y-m-d');
            foreach ($this->availability->getAvailableSlots($specialtyId, $physicianId, $date) as $slot) {
                $slots[] = [
                    'fecha' => $date,
                    'hora_inicio' => $slot['hora_inicio'],
                    'hora_fin' => $slot['hora_fin'],
                ];
                if (count($slots) >= 16) {
                    break;
                }
            }
        }

        return $slots;
    }
}
