<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (['Identity', 'Patients', 'MedicalStaff', 'Schedules', 'Appointments'] as $module) {
            $routes = app_path('Modules/'.$module.'/routes/web.php');

            if (is_file($routes)) {
                $this->loadRoutesFrom($routes);
            }
        }
    }
}
