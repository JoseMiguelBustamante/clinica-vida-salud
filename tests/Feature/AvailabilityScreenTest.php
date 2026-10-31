<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\IdentityRepository;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\MedicalStaff\SpecialtyRepository;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class AvailabilityScreenTest extends TestCase
{
    public function test_guests_are_sent_to_login_for_availability(): void
    {
        $this->get('/disponibilidad')->assertRedirect(route('login'));
        $this->get('/appointments')->assertNotFound();
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_session_without_the_permission_is_denied(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/disponibilidad')->assertForbidden()->assertSee('No tiene permiso para realizar esta operación.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_authorized_session_opens_the_lookup_without_sql_for_slots(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::CONSULT_AVAILABILITY]);
        $this->app->instance(IdentityRepository::class, $identities);

        $specialties = Mockery::mock(SpecialtyRepository::class);
        $specialties->shouldReceive('getActiveSpecialties')->once()->andReturn(collect([
            (object) ['id_especialidad' => 3, 'nombre' => 'Pediatría', 'activo' => true],
        ]));
        $this->app->instance(SpecialtyRepository::class, $specialties);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/disponibilidad')->assertOk()->assertSee('Médicos y disponibilidad')
            ->assertSee('Pediatría')->assertSee('no lo reserva', false);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_panel_shows_availability_when_permitted(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true]);
        $representation = ['id' => 17, 'username' => 'recepcion', 'email' => null,
            'role' => ['id' => 2, 'name' => 'Recepcionista'],
            'permissions' => [PermissionCatalog::CONSULT_AVAILABILITY]];
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('representation')->once()->with($user)->andReturn($representation);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->get('/panel')->assertOk()->assertSee('Citas')->assertDontSee('Horarios')
            ->assertDontSee('Pendiente — aún no está implementado.');
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_past_date_is_rejected_without_sql(): void
    {
        $user = (new User)->forceFill(['id_usuario' => 17, 'activo' => true, 'id_rol' => 2]);
        $identities = Mockery::mock(IdentityRepository::class);
        $identities->shouldReceive('active')->once()->with(17)->andReturn($user);
        $identities->shouldReceive('permissionCodes')->once()->with($user)->andReturn([PermissionCatalog::CONSULT_AVAILABILITY]);
        $this->app->instance(IdentityRepository::class, $identities);

        $this->withSession([Auth::guard('web')->getName() => 17]);
        $this->from('/disponibilidad')->get('/disponibilidad?fecha=2020-01-01')
            ->assertRedirect('/disponibilidad')->assertSessionHasErrors('fecha');
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
