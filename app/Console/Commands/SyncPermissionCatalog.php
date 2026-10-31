<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncPermissionCatalog extends Command
{
    protected $signature = 'clinic:sync-permissions';

    protected $description = 'Define clinic roles and permission codes without changing passwords or accounts';

    protected $help = <<<'HELP'
        Requires an already migrated PostgreSQL database. Creates missing rol, permiso and
        rol_permiso rows from the clinic catalog. Existing usernames, emails and password hashes
        are not changed.         Administrador receives usuarios.administrar, pacientes.gestionar,
        especialidades.gestionar and medicos.gestionar; Recepcionista receives
        pacientes.gestionar. Idempotent: safe to rerun. No password prompts, arguments, defaults
        or environment writes. On any error, stop and contact the operator.
        HELP;

    public function handle(): int
    {
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'pgsql') {
                $this->error('Unable to synchronize permissions; stop and contact the operator.');

                return self::FAILURE;
            }

            $connection->transaction(function () use ($connection): void {
                $roleIds = [];
                foreach (PermissionCatalog::roles() as $role) {
                    $existing = $connection->table('rol')->whereRaw('nombre COLLATE "C" = ?', [$role['nombre']])->first();
                    if ($existing) {
                        $roleIds[$role['nombre']] = $existing->id_rol;
                        $connection->table('rol')->where('id_rol', $existing->id_rol)
                            ->update(['descripcion' => $role['descripcion']]);

                        continue;
                    }

                    $roleIds[$role['nombre']] = $connection->table('rol')->insertGetId([
                        'nombre' => $role['nombre'],
                        'descripcion' => $role['descripcion'],
                    ], 'id_rol');
                }

                $permissionIds = [];
                foreach (PermissionCatalog::permissions() as $permission) {
                    $existing = $connection->table('permiso')->whereRaw('codigo COLLATE "C" = ?', [$permission['codigo']])->first();
                    if ($existing) {
                        $permissionIds[$permission['codigo']] = $existing->id_permiso;
                        $connection->table('permiso')->where('id_permiso', $existing->id_permiso)
                            ->update(['descripcion' => $permission['descripcion']]);

                        continue;
                    }

                    $permissionIds[$permission['codigo']] = $connection->table('permiso')->insertGetId([
                        'codigo' => $permission['codigo'],
                        'descripcion' => $permission['descripcion'],
                    ], 'id_permiso');
                }

                foreach ($roleIds as $roleName => $roleId) {
                    foreach (PermissionCatalog::codesForRole($roleName) as $code) {
                        $permissionId = $permissionIds[$code];
                        $linked = $connection->table('rol_permiso')
                            ->where('id_rol', $roleId)->where('id_permiso', $permissionId)->exists();
                        if (! $linked) {
                            $connection->table('rol_permiso')->insert([
                                'id_rol' => $roleId,
                                'id_permiso' => $permissionId,
                            ]);
                        }
                    }
                }
            });
        } catch (Throwable) {
            $this->error('Unable to synchronize permissions; stop and contact the operator.');

            return self::FAILURE;
        }

        $this->info('Roles and permission codes are defined. Existing accounts were not changed.');

        return self::SUCCESS;
    }
}
