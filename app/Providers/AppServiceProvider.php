<?php

namespace App\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel merges vendor connections into configuration before providers register.
        $this->app['config']->set('database.connections', Arr::only(
            $this->app['config']->get('database.connections'),
            ['pgsql'],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) $this->app['config']->get('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
