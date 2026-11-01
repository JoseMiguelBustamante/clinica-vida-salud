<?php

namespace App\Providers;

use App\Modules\Appointments\AppointmentReminderMailer;
use App\Modules\Appointments\LaravelAppointmentReminderMailer;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppointmentReminderMailer::class, LaravelAppointmentReminderMailer::class);
    }

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
