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
}
