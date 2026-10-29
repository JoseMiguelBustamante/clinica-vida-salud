<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class IdentityModelTest extends TestCase
{
    public function test_user_maps_only_the_report_identity_fields_without_sql(): void
    {
        $user = new User;
        $user->forceFill(['id_usuario' => 17, 'nombre_usuario' => 'Case Sensitive', 'correo_acceso' => null,
            'contrasena_hash' => 'never disclose', 'activo' => 1, 'id_rol' => 9]);

        $this->assertSame('usuario', $user->getTable());
        $this->assertSame('id_usuario', $user->getKeyName());
        $this->assertSame(17, $user->getAuthIdentifier());
        $this->assertSame('contrasena_hash', $user->getAuthPasswordName());
        $this->assertSame('never disclose', $user->getAuthPassword());
        $this->assertTrue($user->activo);
        $this->assertFalse($user->timestamps);
        $this->assertSame('', $user->getRememberTokenName());
        $this->assertArrayNotHasKey('contrasena_hash', $user->toArray());
        $this->assertSame(['nombre_usuario', 'correo_acceso', 'contrasena_hash', 'activo', 'id_rol'], $user->getFillable());
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
