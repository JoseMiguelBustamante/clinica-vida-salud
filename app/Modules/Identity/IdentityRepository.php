<?php

namespace App\Modules\Identity;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
}
