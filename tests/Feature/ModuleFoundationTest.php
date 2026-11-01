<?php

namespace Tests\Feature;

use App\Providers\ModuleServiceProvider;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class ModuleFoundationTest extends TestCase
{
    public function test_the_registered_module_serves_the_public_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertViewIs('home');
        $this->assertTrue($this->app->providerIsLoaded(ModuleServiceProvider::class));
        $this->assertSame(url('/'), route('home'));
        $this->assertSame(['web'], $this->app['router']->getRoutes()->getByName('home')->middleware());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_layout_is_semantic_and_honest_about_implementation_status(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Clínica Vida Salud')
            ->assertSee('Sistema web')
            ->assertSee('Gestión de citas médicas')
            ->assertSee('Los casos de uso CU01 a CU22 están disponibles en esta aplicación.')
            ->assertSee('Inicio de sesión del personal, cuentas, pacientes, médicos, especialidades, horarios, consulta de disponibilidad, programación, reprogramación, cancelación, confirmación, asistencia, atención, agenda del médico, historial de citas, reportes administrativos, registro del portal, perfil, información médica declarada, historial clínico autorizado y recordatorios de cita están disponibles.')
            ->assertDontSee('Clinical workflows are still pending implementation.')
            ->assertSee('href="'.url('/login').'"', false);

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        foreach (['/html[@lang="es"]', '//title', '//meta[@name="viewport"]', '//header', '//main[@id="main-content"]', '//h1', '//footer', '//a[@href="#main-content"]'] as $selector) {
            $this->assertSame(1, $xpath->query($selector)->length, $selector);
        }

        $this->assertSame(0, $xpath->query('//form | //script')->length);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_the_layout_references_a_real_local_stylesheet_without_a_build(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertSee('/css/clinic.css', false);
        $this->assertFileExists(public_path('css/clinic.css'));
        $stylesheet = file_get_contents(public_path('css/clinic.css'));
        $this->assertStringContainsString(':focus-visible', $stylesheet);
        $this->assertStringContainsString('@media', $stylesheet);
        $response->assertDontSee('/build/', false)->assertDontSee('vite', false);
    }

    public function test_business_endpoints_are_not_exposed_by_the_foundation(): void
    {
        foreach (['/patients', '/medical-staff', '/schedules', '/appointments'] as $path) {
            $this->get($path)->assertNotFound();
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }
}
