<?php

namespace Tests\Unit;

use App\Modules\Appointments\AppointmentReminderMailer;
use App\Modules\Appointments\AppointmentReminderService;
use App\Modules\Appointments\AppointmentRepository;
use App\Modules\Appointments\ReminderNotSendable;
use Illuminate\Support\Carbon;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AppointmentReminderServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_eligible_future_appointment_is_planned_twenty_four_hours_ahead(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('hasUndeliveredReminder')->once()->with(21)->andReturn(false);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)
            ->andReturn($this->enabledPreferences());
        $appointments->shouldReceive('createReminder')->once()
            ->with('2026-10-31T08:00:00+00:00', 21)->andReturn(9);
        $mailer->shouldReceive('requestSend')->never();

        $this->service($appointments, $mailer)->planForRegisteredAppointment(21, 17);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_short_notice_appointment_is_planned_as_soon_as_possible(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $row = $this->appointment();
        $row->inicio = '2026-10-31T14:00:00+00:00';
        $appointments->shouldReceive('hasUndeliveredReminder')->once()->with(21)->andReturn(false);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($row);
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)
            ->andReturn($this->enabledPreferences());
        $appointments->shouldReceive('createReminder')->once()
            ->with('2026-10-31T12:00:00+00:00', 21)->andReturn(9);

        $this->service($appointments, $mailer)->planForRegisteredAppointment(21, 17);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_an_appointment_that_already_started_is_not_planned(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01T08:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('hasUndeliveredReminder')->once()->with(21)->andReturn(false);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->never();
        $appointments->shouldReceive('createReminder')->never();

        $this->service($appointments, $mailer)->planForRegisteredAppointment(21, 17);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_disabled_or_unverified_preferences_do_not_plan_a_reminder(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('hasUndeliveredReminder')->once()->with(21)->andReturn(false);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)->andReturn((object) [
            'recordatorios_habilitados' => false,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => true,
        ]);
        $appointments->shouldReceive('createReminder')->never();

        $this->service($appointments, $mailer)->planForRegisteredAppointment(21, 17);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_planning_failure_is_recorded_and_does_not_throw(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('hasUndeliveredReminder')->once()->with(21)->andReturn(false);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)
            ->andReturn($this->enabledPreferences());
        $appointments->shouldReceive('createReminder')->once()->andThrow(new RuntimeException('dsn secret host=internal'));
        $appointments->shouldReceive('createAppointmentEvent')->once()->with(Mockery::on(function (array $values): bool {
            return $values['tipo'] === AppointmentReminderService::PLANNING_INCIDENT_TYPE
                && $values['id_cita'] === 21
                && $values['id_usuario'] === 17
                && $values['motivo'] === 'No pudo planificarse el recordatorio de la cita.';
        }));

        $this->service($appointments, $mailer)->planForRegisteredAppointment(21, 17);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_reschedule_does_not_plan_when_the_original_booking_was_not_eligible(): void
    {
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('appointmentWasOriginallyEligibleForReminders')->once()->with(21)->andReturn(false);
        $appointments->shouldReceive('createReminder')->never();

        $this->service($appointments, $mailer)->planAfterReschedule(21, 17);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_reschedule_can_plan_a_new_notice_after_an_accepted_previous_one(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('appointmentWasOriginallyEligibleForReminders')->once()->with(21)->andReturn(true);
        $appointments->shouldReceive('hasUndeliveredReminder')->once()->with(21)->andReturn(false);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)
            ->andReturn($this->enabledPreferences());
        $appointments->shouldReceive('createReminder')->once()
            ->with('2026-10-31T08:00:00+00:00', 21)->andReturn(10);

        $this->service($appointments, $mailer)->planAfterReschedule(21, 17);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_provider_acceptance_is_recorded_without_changing_the_appointment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getDuePendingReminders')->once()->andReturn(collect([
            (object) ['id_recordatorio' => 9, 'id_cita' => 21, 'fecha_programada' => '2026-10-31T08:00:00+00:00'],
        ]));
        $appointments->shouldReceive('claimReminder')->once()->with(9, '2026-10-31T12:00:00+00:00')->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(9)->andReturn($this->dispatchContext());
        $mailer->shouldReceive('requestSend')->once()
            ->with(
                'ana@example.test',
                Mockery::on(fn (string $subject): bool => str_contains($subject, 'CIT-1') && str_contains($subject, 'Recordatorio de cita')),
                Mockery::on(function (string $body): bool {
                    return str_contains($body, 'CIT-1')
                        && str_contains($body, 'no registra llegada')
                        && str_contains($body, 'Ana Rojas')
                        && ! str_contains($body, 'diagnostico');
                }),
                Mockery::on(function (string $html): bool {
                    return str_contains($html, 'CIT-1')
                        && str_contains($html, 'Recordatorio de cita')
                        && str_contains($html, 'Ana Rojas')
                        && ! str_contains($html, 'diagnostico');
                }),
            )
            ->andReturn(AppointmentReminderService::PROVIDER_ACCEPTED);
        $appointments->shouldReceive('recordReminderResult')->once()->with(
            9,
            AppointmentReminderService::PROVIDER_ACCEPTED,
            'aviso-9-20261031120000',
            '2026-10-31T12:00:00+00:00',
        );
        $appointments->shouldReceive('confirmAppointment')->never();
        $appointments->shouldReceive('cancelAppointment')->never();
        $appointments->shouldReceive('markAppointmentAttended')->never();

        $this->assertSame(1, $this->service($appointments, $mailer)->sendDueReminders());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_a_provider_failure_is_recorded_once_without_retrying(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getDuePendingReminders')->once()->andReturn(collect([
            (object) ['id_recordatorio' => 9, 'id_cita' => 21],
        ]));
        $appointments->shouldReceive('claimReminder')->once()->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(9)->andReturn($this->dispatchContext());
        $mailer->shouldReceive('requestSend')->once()->andReturn(AppointmentReminderService::PROVIDER_FAILED);
        $appointments->shouldReceive('recordReminderResult')->once()->with(
            9,
            AppointmentReminderService::PROVIDER_FAILED,
            Mockery::type('string'),
            Mockery::type('string'),
        );
        $appointments->shouldReceive('getDuePendingReminders')->never();

        $this->assertSame(1, $this->service($appointments, $mailer)->sendDueReminders());
    }

    public function test_an_unknown_provider_result_is_not_retried(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getDuePendingReminders')->once()->andReturn(collect([
            (object) ['id_recordatorio' => 9, 'id_cita' => 21],
        ]));
        $appointments->shouldReceive('claimReminder')->once()->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(9)->andReturn($this->dispatchContext());
        $mailer->shouldReceive('requestSend')->once()->andReturn(AppointmentReminderService::PROVIDER_UNKNOWN);
        $appointments->shouldReceive('recordReminderResult')->once()->with(
            9,
            AppointmentReminderService::PROVIDER_UNKNOWN,
            Mockery::type('string'),
            Mockery::type('string'),
        );

        $this->assertSame(1, $this->service($appointments, $mailer)->sendDueReminders());
    }

    public function test_a_cancelled_appointment_is_not_sent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $context = $this->dispatchContext();
        $context->estado = 'Cancelada';
        $appointments->shouldReceive('getDuePendingReminders')->once()->andReturn(collect([
            (object) ['id_recordatorio' => 9, 'id_cita' => 21],
        ]));
        $appointments->shouldReceive('claimReminder')->once()->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(9)->andReturn($context);
        $mailer->shouldReceive('requestSend')->never();
        $appointments->shouldReceive('discardReminder')->once()->with(9);
        $appointments->shouldReceive('recordReminderResult')->never();

        $this->assertSame(1, $this->service($appointments, $mailer)->sendDueReminders());
    }

    public function test_disabled_preferences_stop_the_send(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $context = $this->dispatchContext();
        $context->recordatorios_habilitados = false;
        $appointments->shouldReceive('getDuePendingReminders')->once()->andReturn(collect([
            (object) ['id_recordatorio' => 9, 'id_cita' => 21],
        ]));
        $appointments->shouldReceive('claimReminder')->once()->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(9)->andReturn($context);
        $mailer->shouldReceive('requestSend')->never();
        $appointments->shouldReceive('discardReminder')->once()->with(9);

        $this->assertSame(1, $this->service($appointments, $mailer)->sendDueReminders());
    }

    public function test_an_appointment_that_already_started_is_not_sent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01T08:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getDuePendingReminders')->once()->andReturn(collect([
            (object) ['id_recordatorio' => 9, 'id_cita' => 21],
        ]));
        $appointments->shouldReceive('claimReminder')->once()->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(9)->andReturn($this->dispatchContext());
        $mailer->shouldReceive('requestSend')->never();
        $appointments->shouldReceive('discardReminder')->once()->with(9);

        $this->assertSame(1, $this->service($appointments, $mailer)->sendDueReminders());
    }

    public function test_a_result_recording_failure_does_not_resend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getDuePendingReminders')->once()->andReturn(collect([
            (object) ['id_recordatorio' => 9, 'id_cita' => 21],
        ]));
        $appointments->shouldReceive('claimReminder')->once()->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(9)->andReturn($this->dispatchContext());
        $mailer->shouldReceive('requestSend')->once()->andReturn(AppointmentReminderService::PROVIDER_ACCEPTED);
        $appointments->shouldReceive('recordReminderResult')->once()->andThrow(new RuntimeException('dsn secret host=internal'));

        $this->assertSame(1, $this->service($appointments, $mailer)->sendDueReminders());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_send_pending_reminder_now_claims_and_dispatches(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getLatestReminderForAppointment')->twice()->with(21)->andReturn(
            (object) [
                'id_recordatorio' => 9,
                'fecha_programada' => '2026-10-31T08:00:00+00:00',
                'fecha_intento' => null,
                'estado' => 'Pendiente',
                'referencia_envio' => null,
                'id_cita' => 21,
            ],
            (object) [
                'id_recordatorio' => 9,
                'fecha_programada' => '2026-10-30T12:00:00+00:00',
                'fecha_intento' => '2026-10-30T12:00:00+00:00',
                'estado' => 'AceptadoPorProveedor',
                'referencia_envio' => 'aviso-9-20261030120000',
                'id_cita' => 21,
            ],
        );
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)
            ->andReturn($this->enabledPreferences());
        $appointments->shouldReceive('getPatientContactEmail')->once()->with(8)->andReturn(null);
        $appointments->shouldReceive('markReminderDueNow')->once()
            ->with(9, '2026-10-30T12:00:00+00:00')->andReturn(true);
        $appointments->shouldReceive('claimReminder')->once()
            ->with(9, '2026-10-30T12:00:00+00:00')->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(9)
            ->andReturn($this->dispatchContext());
        $mailer->shouldReceive('requestSend')->once()
            ->with('ana@example.test', Mockery::type('string'), Mockery::type('string'), Mockery::type('string'))
            ->andReturn(AppointmentReminderService::PROVIDER_ACCEPTED);
        $appointments->shouldReceive('recordReminderResult')->once()
            ->with(9, AppointmentReminderService::PROVIDER_ACCEPTED, Mockery::type('string'), '2026-10-30T12:00:00+00:00');

        $status = $this->service($appointments, $mailer)->sendPendingReminderNow(21);
        $this->assertSame('AceptadoPorProveedor', $status);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_send_now_creates_a_reminder_when_none_is_pending(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getLatestReminderForAppointment')->twice()->with(21)->andReturn(
            null,
            (object) [
                'id_recordatorio' => 11,
                'fecha_programada' => '2026-10-30T12:00:00+00:00',
                'fecha_intento' => '2026-10-30T12:00:00+00:00',
                'estado' => 'AceptadoPorProveedor',
                'referencia_envio' => 'aviso-11-20261030120000',
                'id_cita' => 21,
            ],
        );
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)
            ->andReturn($this->enabledPreferences());
        $appointments->shouldReceive('getPatientContactEmail')->once()->with(8)->andReturn(null);
        $appointments->shouldReceive('createReminder')->once()
            ->with('2026-10-30T12:00:00+00:00', 21)->andReturn(11);
        $appointments->shouldReceive('claimReminder')->once()
            ->with(11, '2026-10-30T12:00:00+00:00')->andReturn(true);
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(11)
            ->andReturn($this->dispatchContext(11));
        $mailer->shouldReceive('requestSend')->once()
            ->with('ana@example.test', Mockery::type('string'), Mockery::type('string'), Mockery::type('string'))
            ->andReturn(AppointmentReminderService::PROVIDER_ACCEPTED);
        $appointments->shouldReceive('recordReminderResult')->once()
            ->with(11, AppointmentReminderService::PROVIDER_ACCEPTED, Mockery::type('string'), '2026-10-30T12:00:00+00:00');

        $status = $this->service($appointments, $mailer)->sendPendingReminderNow(21);
        $this->assertSame('AceptadoPorProveedor', $status);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_send_now_uses_ficha_email_when_portal_preferences_are_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getLatestReminderForAppointment')->twice()->with(21)->andReturn(
            null,
            (object) [
                'id_recordatorio' => 12,
                'estado' => 'AceptadoPorProveedor',
                'id_cita' => 21,
            ],
        );
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)->andReturn(null);
        $appointments->shouldReceive('getPatientContactEmail')->once()->with(8)->andReturn('ficha@example.test');
        $appointments->shouldReceive('createReminder')->once()
            ->with('2026-10-30T12:00:00+00:00', 21)->andReturn(12);
        $appointments->shouldReceive('claimReminder')->once()
            ->with(12, '2026-10-30T12:00:00+00:00')->andReturn(true);
        $context = $this->dispatchContext(12);
        $context->recordatorios_habilitados = null;
        $context->destino = null;
        $context->destino_verificado = false;
        $context->correo_contacto = 'ficha@example.test';
        $appointments->shouldReceive('getReminderDispatchContext')->once()->with(12)->andReturn($context);
        $mailer->shouldReceive('requestSend')->once()
            ->with('ficha@example.test', Mockery::type('string'), Mockery::type('string'), Mockery::type('string'))
            ->andReturn(AppointmentReminderService::PROVIDER_ACCEPTED);
        $appointments->shouldReceive('recordReminderResult')->once()
            ->with(12, AppointmentReminderService::PROVIDER_ACCEPTED, Mockery::type('string'), '2026-10-30T12:00:00+00:00');

        $status = $this->service($appointments, $mailer)->sendPendingReminderNow(21);
        $this->assertSame('AceptadoPorProveedor', $status);
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_send_pending_reminder_now_rejects_when_no_destination_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30T12:00:00Z'));
        $appointments = Mockery::mock(AppointmentRepository::class);
        $mailer = Mockery::mock(AppointmentReminderMailer::class);
        $appointments->shouldReceive('getAppointmentById')->once()->with(21)->andReturn($this->appointment());
        $appointments->shouldReceive('getLatestReminderForAppointment')->once()->with(21)->andReturn(null);
        $appointments->shouldReceive('getNotificationPreferencesForPatient')->once()->with(8)->andReturn(null);
        $appointments->shouldReceive('getPatientContactEmail')->once()->with(8)->andReturn(null);
        $mailer->shouldReceive('requestSend')->never();

        try {
            $this->service($appointments, $mailer)->sendPendingReminderNow(21);
            $this->fail('Expected ReminderNotSendable');
        } catch (ReminderNotSendable) {
        }

        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function service(
        AppointmentRepository $appointments,
        AppointmentReminderMailer $mailer,
    ): AppointmentReminderService {
        return new AppointmentReminderService($appointments, $mailer);
    }

    private function appointment(): object
    {
        return (object) [
            'id_cita' => 21,
            'codigo' => 'CIT-1',
            'inicio' => '2026-11-01T08:00:00+00:00',
            'estado' => 'Programada',
            'id_paciente' => 8,
        ];
    }

    private function enabledPreferences(): object
    {
        return (object) [
            'recordatorios_habilitados' => true,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => true,
        ];
    }

    private function dispatchContext(int $reminderId = 9): object
    {
        return (object) [
            'id_recordatorio' => $reminderId,
            'id_cita' => 21,
            'fecha_programada' => '2026-10-30T12:00:00+00:00',
            'codigo' => 'CIT-1',
            'inicio' => '2026-11-01T08:00:00+00:00',
            'fin' => '2026-11-01T08:30:00+00:00',
            'estado' => 'Programada',
            'modalidad' => 'Presencial',
            'id_paciente' => 8,
            'paciente_nombres' => 'Ana',
            'paciente_apellidos' => 'Rojas',
            'correo_contacto' => null,
            'medico_nombres' => 'Luis',
            'medico_apellidos' => 'Soto',
            'especialidad_nombre' => 'Pediatría',
            'recordatorios_habilitados' => true,
            'canal' => 'CorreoElectronico',
            'destino' => 'ana@example.test',
            'destino_verificado' => true,
        ];
    }
}
