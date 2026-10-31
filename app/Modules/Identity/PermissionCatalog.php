<?php

declare(strict_types=1);

namespace App\Modules\Identity;

final class PermissionCatalog
{
    public const MANAGE_ACCOUNTS = 'usuarios.administrar';

    public const ADMINISTRATOR_ROLE = 'Administrador';

    public const RECEPTIONIST_ROLE = 'Recepcionista';

    public const PHYSICIAN_ROLE = 'Médico';

    /**
     * @return list<array{codigo: string, descripcion: string}>
     */
    public static function permissions(): array
    {
        return [
            ['codigo' => self::MANAGE_ACCOUNTS, 'descripcion' => 'Administrar cuentas de acceso y roles'],
        ];
    }

    /**
     * @return list<array{nombre: string, descripcion: string}>
     */
    public static function roles(): array
    {
        return [
            ['nombre' => self::ADMINISTRATOR_ROLE, 'descripcion' => 'Administra cuentas, catálogos y reportes autorizados'],
            ['nombre' => self::RECEPTIONIST_ROLE, 'descripcion' => 'Gestiona pacientes y citas según permisos'],
            ['nombre' => self::PHYSICIAN_ROLE, 'descripcion' => 'Consulta su agenda y registra la atención'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function codesForRole(string $roleName): array
    {
        return match ($roleName) {
            self::ADMINISTRATOR_ROLE => [self::MANAGE_ACCOUNTS],
            default => [],
        };
    }
}
