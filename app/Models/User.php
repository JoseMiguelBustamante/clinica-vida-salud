<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['nombre_usuario', 'correo_acceso', 'contrasena_hash', 'activo', 'id_rol'])]
#[Hidden(['contrasena_hash'])]
class User extends Authenticatable
{
    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $authPasswordName = 'contrasena_hash';

    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
