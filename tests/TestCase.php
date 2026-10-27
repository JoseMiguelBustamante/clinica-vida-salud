<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private ?string $compiledViews = null;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';

        $this->traitsUsedByTest = class_uses_recursive(static::class);

        // Tests use process configuration only; guarded environment files are never loaded.
        $app->instance(LoadEnvironmentVariables::class, new class
        {
            public function bootstrap(Application $app): void {}
        });

        $app->make(Kernel::class)->bootstrap();

        $this->compiledViews = sys_get_temp_dir().'/clinic-views-'.bin2hex(random_bytes(8));
        mkdir($this->compiledViews, 0700);
        $app['config']->set('view.compiled', $this->compiledViews);

        return $app;
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->compiledViews !== null) {
                foreach (glob($this->compiledViews.'/*') as $file) {
                    unlink($file);
                }

                rmdir($this->compiledViews);
                $this->compiledViews = null;
            }
        }
    }
}
