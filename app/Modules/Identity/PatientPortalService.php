<?php

declare(strict_types=1);

namespace App\Modules\Identity;

final class PatientPortalService
{
    public function __construct(
        private IdentityAccountService $accounts,
        private IdentityRepository $identities,
        private PermissionCatalogService $catalog,
    ) {}

    public function registrationIsEnabled(): bool
    {
        return (bool) config('identity.patient_portal_registration', true);
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
}
