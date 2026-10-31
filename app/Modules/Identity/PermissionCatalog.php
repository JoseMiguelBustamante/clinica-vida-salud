<?php

declare(strict_types=1);

namespace App\Modules\Identity;

final class PermissionCatalog
{
    public const MANAGE_ACCOUNTS = 'usuarios.administrar';

    public const MANAGE_PATIENTS = 'pacientes.gestionar';

    public const MANAGE_SPECIALTIES = 'especialidades.gestionar';

    public const MANAGE_PHYSICIANS = 'medicos.gestionar';

    public const MANAGE_SCHEDULES = 'horarios.gestionar';

    public const CONSULT_AVAILABILITY = 'citas.consultar';

    public const BOOK_APPOINTMENTS = 'citas.programar';

    public const RESCHEDULE_APPOINTMENTS = 'citas.reprogramar';

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
            ['codigo' => self::MANAGE_PATIENTS, 'descripcion' => 'Registrar, consultar, modificar y desactivar pacientes'],
            ['codigo' => self::MANAGE_SPECIALTIES, 'descripcion' => 'Administrar el catálogo de especialidades'],
            ['codigo' => self::MANAGE_PHYSICIANS, 'descripcion' => 'Administrar médicos y sus especialidades'],
            ['codigo' => self::MANAGE_SCHEDULES, 'descripcion' => 'Configurar horarios de atención de los médicos'],
            ['codigo' => self::CONSULT_AVAILABILITY, 'descripcion' => 'Consultar médicos habilitados e intervalos disponibles'],
            ['codigo' => self::BOOK_APPOINTMENTS, 'descripcion' => 'Programar citas médicas en intervalos disponibles'],
            ['codigo' => self::RESCHEDULE_APPOINTMENTS, 'descripcion' => 'Reprogramar citas médicas existentes'],
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
            self::ADMINISTRATOR_ROLE => [
                self::MANAGE_ACCOUNTS,
                self::MANAGE_PATIENTS,
                self::MANAGE_SPECIALTIES,
                self::MANAGE_PHYSICIANS,
                self::MANAGE_SCHEDULES,
                self::CONSULT_AVAILABILITY,
                self::BOOK_APPOINTMENTS,
                self::RESCHEDULE_APPOINTMENTS,
            ],
            self::RECEPTIONIST_ROLE => [
                self::MANAGE_PATIENTS,
                self::CONSULT_AVAILABILITY,
                self::BOOK_APPOINTMENTS,
                self::RESCHEDULE_APPOINTMENTS,
            ],
            default => [],
        };
    }

    public static function fingerprint(): string
    {
        $assignments = [];
        foreach (self::roles() as $role) {
            $assignments[$role['nombre']] = self::codesForRole($role['nombre']);
        }

        return hash('sha256', json_encode([
            self::roles(),
            self::permissions(),
            $assignments,
        ], JSON_THROW_ON_ERROR));
    }
}
