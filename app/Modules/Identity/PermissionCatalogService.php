<?php

declare(strict_types=1);

namespace App\Modules\Identity;

final class PermissionCatalogService
{
    public function __construct(private IdentityRepository $identities) {}

    public function ensureCurrent(): void
    {
        $assignments = $this->roleAssignments();
        $codes = array_column(PermissionCatalog::permissions(), 'codigo');
        if ($this->identities->permissionCatalogIsApplied($codes, $assignments)) {
            return;
        }

        $this->synchronize();
    }

    public function synchronize(): void
    {
        $this->identities->upsertPermissionCatalog(
            PermissionCatalog::roles(),
            PermissionCatalog::permissions(),
            $this->roleAssignments(),
        );
    }

    /** @return array<string, list<string>> */
    private function roleAssignments(): array
    {
        $assignments = [];
        foreach (PermissionCatalog::roles() as $role) {
            $assignments[$role['nombre']] = PermissionCatalog::codesForRole($role['nombre']);
        }

        return $assignments;
    }
}
