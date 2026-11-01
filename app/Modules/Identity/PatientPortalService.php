<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Appointments\AppointmentRepository;
use Illuminate\Support\Facades\DB;

final class PatientPortalService
{
    private const NOTIFICATION_CHANNEL = 'CorreoElectronico';

    public function __construct(
        private IdentityAccountService $accounts,
        private IdentityRepository $identities,
        private PermissionCatalogService $catalog,
        private AppointmentRepository $appointments,
    ) {}

    public function registrationIsEnabled(): bool
    {
        return (bool) config('identity.patient_portal_registration', true);
    }

    public function portalIsEnabled(): bool
    {
        return (bool) config('identity.patient_portal', true);
    }

    /**
     * @param  array{nombre_usuario: string, correo_acceso?: ?string, password: string}  $input
     */
    public function registerPatientAccount(array $input): int
    {
        if (! $this->registrationIsEnabled()) {
            throw new PatientRegistrationDisabled;
        }

        $role = $this->requirePatientRole();

        return $this->accounts->createAccount([
            'nombre_usuario' => $input['nombre_usuario'],
            'correo_acceso' => $input['correo_acceso'] ?? null,
            'password' => $input['password'],
            'id_rol' => (int) $role->id_rol,
            'activo' => true,
        ]);
    }

    /**
     * @return array{account: object, preferences: array{recordatorios_habilitados: bool, canal: string, destino: ?string, destino_verificado: bool}}
     */
    public function getPatientProfile(int $userId): array
    {
        $this->assertPortalEnabled();
        $account = $this->requirePatientAccount($userId);

        return [
            'account' => $account,
            'preferences' => $this->preferenceState($account, $this->identities->getNotificationPreferences($userId)),
        ];
    }

    /**
     * @param  array{nombre_usuario: string, correo_acceso?: ?string, password?: ?string, recordatorios_habilitados: bool}  $input
     */
    public function updatePatientProfile(int $userId, array $input): void
    {
        $this->assertPortalEnabled();
        $account = $this->requirePatientAccount($userId);
        $email = $this->normalizedEmail($input['correo_acceso'] ?? null);
        $preferences = $this->preferenceState($account, $this->identities->getNotificationPreferences($userId));
        $emailChanged = $email !== $this->normalizedEmail($account->correo_acceso ?? null);
        $verified = $preferences['destino_verificado'] && ! $emailChanged;
        $requestedEnabled = $input['recordatorios_habilitados'];
        if ($requestedEnabled && ! $verified) {
            throw new ReminderPreferenceRejected;
        }

        $enabled = $requestedEnabled && $verified;
        $wasEnabled = $preferences['recordatorios_habilitados'];

        DB::transaction(function () use ($userId, $input, $email, $enabled, $verified, $wasEnabled): void {
            $this->accounts->updateAccountCredentials($userId, [
                'nombre_usuario' => $input['nombre_usuario'],
                'correo_acceso' => $email,
                'password' => $input['password'] ?? null,
            ]);
            $this->identities->upsertNotificationPreferences(
                $userId,
                $enabled,
                self::NOTIFICATION_CHANNEL,
                $email,
                $verified,
            );
            if ($wasEnabled && ! $enabled) {
                $this->appointments->discardUndeliveredRemindersForAccount($userId);
            }
        });
    }

    public function verifyPatientEmail(int $userId): void
    {
        $this->assertPortalEnabled();
        $account = $this->requirePatientAccount($userId);
        $email = $this->normalizedEmail($account->correo_acceso ?? null);
        if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidAccountInput;
        }

        $preferences = $this->preferenceState($account, $this->identities->getNotificationPreferences($userId));
        $this->identities->upsertNotificationPreferences(
            $userId,
            $preferences['recordatorios_habilitados'],
            self::NOTIFICATION_CHANNEL,
            $email,
            true,
        );
    }

    private function requirePatientRole(): object
    {
        $role = $this->identities->getRoleByName(PermissionCatalog::PATIENT_ROLE);
        if ($role === null) {
            $this->catalog->ensureCurrent();
            $role = $this->identities->getRoleByName(PermissionCatalog::PATIENT_ROLE);
        }
        if ($role === null) {
            throw new RoleNotFound;
        }

        return $role;
    }

    private function requirePatientAccount(int $userId): object
    {
        $account = $this->identities->getAccountById($userId);
        if ($account === null) {
            throw new AccountNotFound;
        }
        if ((string) $account->rol_nombre !== PermissionCatalog::PATIENT_ROLE) {
            throw new PatientProfileNotAllowed;
        }

        return $account;
    }

    /**
     * @return array{recordatorios_habilitados: bool, canal: string, destino: ?string, destino_verificado: bool}
     */
    private function preferenceState(object $account, ?object $stored): array
    {
        if ($stored === null) {
            return [
                'recordatorios_habilitados' => false,
                'canal' => self::NOTIFICATION_CHANNEL,
                'destino' => $this->normalizedEmail($account->correo_acceso ?? null),
                'destino_verificado' => false,
            ];
        }

        $destination = $stored->destino ?? null;

        return [
            'recordatorios_habilitados' => $this->booleanFlag($stored->recordatorios_habilitados),
            'canal' => (string) $stored->canal,
            'destino' => $this->normalizedEmail(is_string($destination) ? $destination : null),
            'destino_verificado' => $this->booleanFlag($stored->destino_verificado),
        ];
    }

    private function assertPortalEnabled(): void
    {
        if (! $this->portalIsEnabled()) {
            throw new PatientPortalDisabled;
        }
    }

    private function normalizedEmail(?string $email): ?string
    {
        return $email === null || $email === '' ? null : $email;
    }

    private function booleanFlag(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
