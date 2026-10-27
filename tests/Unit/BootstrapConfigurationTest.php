<?php

namespace Tests\Unit;

use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

class BootstrapConfigurationTest extends TestCase
{
    private function configuration(string $name): array
    {
        new Application(dirname(__DIR__, 2));

        return require dirname(__DIR__, 2).'/config/'.$name.'.php';
    }

    public function test_database_defaults_to_postgresql_and_offers_no_other_sql_drivers(): void
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()->addAdapter(PutenvAdapter::class)->make();
        $previous = $repository->get('DB_CONNECTION');
        $repository->clear('DB_CONNECTION');

        try {
            $config = $this->configuration('database');
            $this->assertSame('pgsql', $config['default']);
            $this->assertSame(['pgsql'], array_keys($config['connections']));
            $this->assertSame('pgsql', $config['connections']['pgsql']['driver']);
            $this->assertSame('public', $config['connections']['pgsql']['search_path']);
        } finally {
            $previous === null ? $repository->clear('DB_CONNECTION') : $repository->set('DB_CONNECTION', $previous);
        }
    }

    public function test_local_infrastructure_defaults_do_not_need_extra_database_tables(): void
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()->addAdapter(PutenvAdapter::class)->make();
        $keys = ['SESSION_DRIVER', 'CACHE_STORE', 'QUEUE_CONNECTION', 'QUEUE_FAILED_DRIVER'];
        $previous = [];

        foreach ($keys as $key) {
            $previous[$key] = $repository->get($key);
            $repository->clear($key);
        }

        try {
            $this->assertSame('file', $this->configuration('session')['driver']);
            $this->assertSame('file', $this->configuration('cache')['default']);
            $queue = $this->configuration('queue');
            $this->assertSame('sync', $queue['default']);
            $this->assertSame('null', $queue['failed']['driver']);
            $this->assertSame('pgsql', $queue['batching']['database']);
            $this->assertSame('pgsql', $queue['failed']['database']);
        } finally {
            foreach ($previous as $key => $value) {
                $value === null ? $repository->clear($key) : $repository->set($key, $value);
            }
        }
    }

    public function test_phpunit_selects_postgresql_without_an_in_memory_database(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 2).'/phpunit.xml');
        $variables = [];

        foreach ($xml->php->env as $variable) {
            $variables[(string) $variable['name']] = (string) $variable['value'];
        }

        $this->assertSame('pgsql', $variables['DB_CONNECTION']);
        $this->assertArrayNotHasKey('DB_DATABASE', $variables);
        $this->assertNotEmpty($variables['APP_KEY']);
    }

    public function test_composer_bootstrap_does_not_provision_environment_or_database(): void
    {
        $scripts = $this->scripts();
        $this->assertSame(['composer install', 'npm install --ignore-scripts', 'npm run build'], $scripts['setup']);
        $this->assertArrayNotHasKey('post-root-package-install', $scripts);
        $this->assertArrayNotHasKey('post-create-project-cmd', $scripts);
        $this->assertDoesNotMatchRegularExpression('/migrate|sqlite|key:generate|\.env/', json_encode($scripts));
    }

    public function test_existing_composer_development_test_and_package_hooks_are_preserved(): void
    {
        $scripts = $this->scripts();
        $this->assertSame(['Composer\\Config::disableProcessTimeout', '@php artisan dev'], $scripts['dev']);
        $this->assertSame(['@php artisan config:clear --ansi @no_additional_args', '@php artisan test'], $scripts['test']);
        $this->assertSame(['Illuminate\\Foundation\\ComposerScripts::postAutoloadDump', '@php artisan package:discover --ansi'], $scripts['post-autoload-dump']);
        $this->assertSame(['@php artisan vendor:publish --tag=laravel-assets --ansi --force'], $scripts['post-update-cmd']);
        $this->assertSame(['Illuminate\\Foundation\\ComposerScripts::prePackageUninstall'], $scripts['pre-package-uninstall']);
    }

    public function test_postgresql_environment_options_remain_configurable(): void
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()->addAdapter(PutenvAdapter::class)->make();
        $previous = $repository->get('DB_SSLMODE');
        $repository->set('DB_SSLMODE', 'verify-full');

        try {
            $this->assertSame('verify-full', $this->configuration('database')['connections']['pgsql']['sslmode']);
        } finally {
            $previous === null ? $repository->clear('DB_SSLMODE') : $repository->set('DB_SSLMODE', $previous);
        }
    }

    public function test_existing_infrastructure_environment_overrides_are_preserved(): void
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()->addAdapter(PutenvAdapter::class)->make();
        $overrides = ['SESSION_DRIVER' => 'cookie', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'deferred'];
        $previous = [];

        foreach ($overrides as $key => $value) {
            $previous[$key] = $repository->get($key);
            $repository->set($key, $value);
        }

        try {
            $this->assertSame('cookie', $this->configuration('session')['driver']);
            $this->assertSame('array', $this->configuration('cache')['default']);
            $this->assertSame('deferred', $this->configuration('queue')['default']);
        } finally {
            foreach ($previous as $key => $value) {
                $value === null ? $repository->clear($key) : $repository->set($key, $value);
            }
        }
    }

    private function scripts(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR)['scripts'];
    }
}
