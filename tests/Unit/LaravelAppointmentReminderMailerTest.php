<?php

namespace Tests\Unit;

use App\Modules\Appointments\AppointmentReminderService;
use App\Modules\Appointments\LaravelAppointmentReminderMailer;
use Illuminate\Contracts\Mail\Mailer;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LaravelAppointmentReminderMailerTest extends TestCase
{
    public function test_a_successful_mail_request_is_accepted_by_the_provider(): void
    {
        $inner = Mockery::mock(Mailer::class);
        $inner->shouldReceive('raw')->once()->with('Cuerpo', Mockery::type('callable'))->andReturnNull();
        $mailer = new LaravelAppointmentReminderMailer($inner);

        $this->assertSame(
            AppointmentReminderService::PROVIDER_ACCEPTED,
            $mailer->requestSend('ana@example.test', 'Recordatorio de cita CIT-1', 'Cuerpo'),
        );
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_mail_exception_is_recorded_as_a_known_failure(): void
    {
        $inner = Mockery::mock(Mailer::class);
        $inner->shouldReceive('raw')->once()->andThrow(new RuntimeException('dsn secret host=internal'));
        $mailer = new LaravelAppointmentReminderMailer($inner);

        $this->assertSame(
            AppointmentReminderService::PROVIDER_FAILED,
            $mailer->requestSend('ana@example.test', 'Recordatorio', 'Cuerpo'),
        );
        $this->assertSame([], $this->app['db']->getConnections());
    }
}
