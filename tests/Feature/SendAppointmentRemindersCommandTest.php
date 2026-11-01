<?php

namespace Tests\Feature;

use App\Modules\Appointments\AppointmentReminderMailer;
use App\Modules\Appointments\AppointmentRepository;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SendAppointmentRemindersCommandTest extends TestCase
{
    public function test_help_describes_scheduler_effects_without_connecting(): void
    {
        $this->assertSame(0, Artisan::call('help', ['command_name' => 'clinic:send-reminders']));
        $help = Artisan::output();
        foreach ([
            'already migrated PostgreSQL',
            'task scheduler',
            'recordatorio_cita',
            'Provider acceptance is not proof of delivery',
            'appointment status is not changed',
            'environment writes',
        ] as $text) {
            $this->assertStringContainsString($text, $help);
        }
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_no_due_reminders_are_reported_without_sending(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getDuePendingReminders')->once()->andReturn(collect());
        $mailer->shouldReceive('requestSend')->never();
        $this->app->instance(AppointmentRepository::class, $appointments);
        $this->app->instance(AppointmentReminderMailer::class, $mailer);

        $this->assertSame(0, Artisan::call('clinic:send-reminders'));
        $this->assertStringContainsString('No reminders were due.', Artisan::output());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_operational_failures_are_generic_and_never_leak(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getDuePendingReminders')->once()
            ->andThrow(new RuntimeException('dsn secret host=internal'));
        $this->app->instance(AppointmentRepository::class, $appointments);
        $this->app->instance(AppointmentReminderMailer::class, $mailer);

        $this->assertSame(1, Artisan::call('clinic:send-reminders'));
        $output = Artisan::output();
        $this->assertStringContainsString('Unable to send appointment reminders; stop and contact the operator.', $output);
        $this->assertStringNotContainsString('dsn secret', $output);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_english_reminder_routes_stay_not_found(): void
    {
        $this->get('/reminders')->assertNotFound();
        $this->get('/appointment-reminders')->assertNotFound();
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
