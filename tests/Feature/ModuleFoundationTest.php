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
            ->assertSee('Foundation in progress')
            ->assertSee('Staff sign-in, accounts, patients, physicians, specialties, schedules, availability lookup, booking, rescheduling, cancellation, confirmation, attendance and marking visits as attended are available.')
            ->assertSee('href="'.url('/login').'"', false);

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        foreach (['/html[@lang="en"]', '//title', '//meta[@name="viewport"]', '//header', '//main[@id="main-content"]', '//h1', '//footer', '//a[@href="#main-content"]'] as $selector) {
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
