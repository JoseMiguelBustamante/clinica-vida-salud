<?php

namespace App\Modules\Identity;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IdentityRepository
{
    /** @return Collection<int, User> */
    public function matching(string $identifier): Collection
    {
        return User::query()->where('nombre_usuario', $identifier)
            ->orWhere('correo_acceso', $identifier)->limit(2)->get();
    }

    public function active(int $id): ?User
    {
        return User::query()->whereKey($id)->where('activo', true)->first();
    }

    public function representation(User $user): array
    {
        $role = DB::table('rol')->where('id_rol', $user->id_rol)->firstOrFail();
        $permissions = DB::table('permiso')->join('rol_permiso', 'permiso.id_permiso', '=', 'rol_permiso.id_permiso')
            ->where('rol_permiso.id_rol', $role->id_rol)->orderBy('permiso.codigo')->pluck('permiso.codigo')->all();

        return ['id' => $user->getKey(), 'username' => $user->nombre_usuario, 'email' => $user->correo_acceso,
            'role' => ['id' => $role->id_rol, 'name' => $role->nombre], 'permissions' => $permissions];
    }

    /** @return list<string> */
    public function permissionCodes(User $user): array
    {
        return DB::table('permiso')->join('rol_permiso', 'permiso.id_permiso', '=', 'rol_permiso.id_permiso')
            ->where('rol_permiso.id_rol', $user->id_rol)->orderBy('permiso.codigo')->pluck('permiso.codigo')->all();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public function getAllRoles(): \Illuminate\Support\Collection
    {
        return DB::table('rol')->orderBy('nombre')->get();
    }

    public function getRoleById(int $roleId): ?object
    {
        return DB::table('rol')->where('id_rol', $roleId)->first();
    }

    public function getRoleByName(string $name): ?object
    {
        return DB::table('rol')->whereRaw('nombre COLLATE "C" = ?', [$name])->first();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public function getAllAccounts(?string $fragment): \Illuminate\Support\Collection
    {
        $query = DB::table('usuario')->join('rol', 'rol.id_rol', '=', 'usuario.id_rol')
            ->select('usuario.id_usuario', 'usuario.nombre_usuario', 'usuario.correo_acceso',
                'usuario.activo', 'usuario.id_rol', 'rol.nombre as rol_nombre')
            ->orderBy('usuario.nombre_usuario');

        if ($fragment !== null && $fragment !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $fragment).'%';
            $query->where(function ($inner) use ($like): void {
                $inner->whereRaw('usuario.nombre_usuario ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('usuario.correo_acceso ILIKE ? ESCAPE \'!\'', [$like]);
            });
        }

        return $query->get();
    }

    public function getAccountById(int $id): ?object
    {
        return DB::table('usuario')->join('rol', 'rol.id_rol', '=', 'usuario.id_rol')
            ->select('usuario.id_usuario', 'usuario.nombre_usuario', 'usuario.correo_acceso',
                'usuario.activo', 'usuario.id_rol', 'rol.nombre as rol_nombre')
            ->where('usuario.id_usuario', $id)->first();
    }

    public function identifierTaken(string $identifier, ?int $exceptId = null): bool
    {
        $query = User::query()->where(function ($inner) use ($identifier): void {
            $inner->where('nombre_usuario', $identifier)->orWhere('correo_acceso', $identifier);
        });
        if ($exceptId !== null) {
            $query->where('id_usuario', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function createAccount(string $username, ?string $email, string $passwordHash, bool $active, int $roleId): int
    {
        return (int) DB::table('usuario')->insertGetId([
            'nombre_usuario' => $username,
            'correo_acceso' => $email,
            'contrasena_hash' => $passwordHash,
            'activo' => $active,
            'id_rol' => $roleId,
        ], 'id_usuario');
    }

    /**
     * @param  array{nombre_usuario: string, correo_acceso: ?string, activo: bool, id_rol: int, contrasena_hash?: string}  $values
     */
    public function updateAccount(int $id, array $values): void
    {
        DB::table('usuario')->where('id_usuario', $id)->update($values);
    }

    public function setAccountActive(int $id, bool $active): void
    {
        DB::table('usuario')->where('id_usuario', $id)->update(['activo' => $active]);
    }

    public function getNotificationPreferences(int $userId): ?object
    {
        return DB::table('preferencias_notificacion')
            ->select('id_usuario', 'recordatorios_habilitados', 'canal', 'destino', 'destino_verificado')
            ->where('id_usuario', $userId)
            ->first();
    }

    public function upsertNotificationPreferences(
        int $userId,
        bool $remindersEnabled,
        string $channel,
        ?string $destination,
        bool $destinationVerified,
    ): void {
        $values = [
            'recordatorios_habilitados' => $remindersEnabled,
            'canal' => $channel,
            'destino' => $destination,
            'destino_verificado' => $destinationVerified,
        ];
        $existing = DB::table('preferencias_notificacion')->where('id_usuario', $userId)->exists();
        if ($existing) {
            DB::table('preferencias_notificacion')->where('id_usuario', $userId)->update($values);

            return;
        }

        DB::table('preferencias_notificacion')->insert([
            'id_usuario' => $userId,
            ...$values,
        ]);
    }

    /**
     * @param  list<string>  $permissionCodes
     * @param  array<string, list<string>>  $codesByRole
     */
    public function permissionCatalogIsApplied(array $permissionCodes, array $codesByRole): bool
    {
        if ($permissionCodes === []) {
            return true;
        }

        $stored = DB::table('permiso')->whereIn('codigo', $permissionCodes)->pluck('codigo')->all();
        if (count($stored) !== count($permissionCodes)) {
            return false;
        }

        foreach ($codesByRole as $roleName => $codes) {
            if ($codes === []) {
                continue;
            }
            $linked = (int) DB::table('rol_permiso')
                ->join('rol', 'rol.id_rol', '=', 'rol_permiso.id_rol')
                ->join('permiso', 'permiso.id_permiso', '=', 'rol_permiso.id_permiso')
                ->whereRaw('rol.nombre COLLATE "C" = ?', [$roleName])
                ->whereIn('permiso.codigo', $codes)
                ->selectRaw('count(distinct permiso.codigo) as total')
                ->value('total');
            if ($linked !== count($codes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{nombre: string, descripcion: string}>  $roles
     * @param  list<array{codigo: string, descripcion: string}>  $permissions
     * @param  array<string, list<string>>  $codesByRole
     */
    public function upsertPermissionCatalog(array $roles, array $permissions, array $codesByRole): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Permission catalog requires PostgreSQL.');
        }

        $connection->transaction(function () use ($connection, $roles, $permissions, $codesByRole): void {
            $connection->select('SELECT pg_advisory_xact_lock(1129072974, 2)');
            $roleIds = [];
            foreach ($roles as $role) {
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
            foreach ($permissions as $permission) {
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

            foreach ($codesByRole as $roleName => $codes) {
                $roleId = $roleIds[$roleName];
                foreach ($codes as $code) {
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
    }
}
