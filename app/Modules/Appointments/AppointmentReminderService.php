<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use Illuminate\Support\Carbon;
use Throwable;

final class AppointmentReminderService
{
    public const PROVIDER_ACCEPTED = 'AceptadoPorProveedor';

    public const PROVIDER_FAILED = 'Fallido';

    public const PROVIDER_UNKNOWN = 'ResultadoDesconocido';

    public const PLANNING_INCIDENT_TYPE = 'IncidenciaPlanificacionRecordatorio';

    public function __construct(
        private AppointmentRepository $appointments,
        private AppointmentReminderMailer $mailer,
    ) {}

    public function planForRegisteredAppointment(int $appointmentId, int $actorUserId): void
    {
        try {
            $this->planIfEligible($appointmentId);
        } catch (Throwable) {
            $this->recordPlanningIncident($appointmentId, $actorUserId);
        }
    }

    public function planAfterReschedule(int $appointmentId, int $actorUserId): void
    {
        try {
            if (! $this->appointments->appointmentWasOriginallyEligibleForReminders($appointmentId)) {
                return;
            }

            $this->planIfEligible($appointmentId);
        } catch (Throwable) {
            $this->recordPlanningIncident($appointmentId, $actorUserId);
        }
    }

    public function sendDueReminders(): int
    {
        $now = Carbon::now('UTC')->toIso8601String();
        $processed = 0;

        foreach ($this->appointments->getDuePendingReminders($now) as $reminder) {
            $reminderId = (int) $reminder->id_recordatorio;
            $attemptedAt = Carbon::now('UTC')->toIso8601String();
            if (! $this->appointments->claimReminder($reminderId, $attemptedAt)) {
                continue;
            }

            $processed++;
            $this->dispatchClaimedReminder($reminderId, $attemptedAt);
        }

        return $processed;
    }

    /**
     * @return array{
     *     reminder: ?object,
     *     preferences_ready: bool,
     *     can_send_now: bool,
     *     status_label: string,
     *     detail: string
     * }
     */
    public function overviewForAppointment(int $appointmentId): array
    {
        $appointment = $this->appointments->getAppointmentById($appointmentId);
        if ($appointment === null) {
            throw new AppointmentNotFound;
        }

        $reminder = $this->appointments->getLatestReminderForAppointment($appointmentId);
        $preferences = $this->appointments->getNotificationPreferencesForPatient((int) $appointment->id_paciente);
        $preferencesReady = $this->preferencesAllowSend($preferences);
        $canSendNow = $reminder !== null && (string) $reminder->estado === 'Pendiente' && $preferencesReady
            && $this->appointmentIsCurrent($appointment);

        return [
            'reminder' => $reminder,
            'preferences_ready' => $preferencesReady,
            'can_send_now' => $canSendNow,
            'status_label' => $this->overviewStatusLabel($reminder, $preferencesReady),
            'detail' => $this->overviewDetail($reminder, $preferencesReady, $appointment),
        ];
    }

    public function sendPendingReminderNow(int $appointmentId): string
    {
        $overview = $this->overviewForAppointment($appointmentId);
        $reminder = $overview['reminder'];
        if (! $overview['can_send_now'] || $reminder === null) {
            throw new ReminderNotSendable;
        }

        $now = Carbon::now('UTC')->toIso8601String();
        if (! $this->appointments->markReminderDueNow((int) $reminder->id_recordatorio, $now)) {
            throw new ReminderNotSendable;
        }
        if (! $this->appointments->claimReminder((int) $reminder->id_recordatorio, $now)) {
            throw new ReminderNotSendable;
        }

        $this->dispatchClaimedReminder((int) $reminder->id_recordatorio, $now);
        $updated = $this->appointments->getLatestReminderForAppointment($appointmentId);

        return $updated === null ? 'ResultadoDesconocido' : (string) $updated->estado;
    }

    private function overviewStatusLabel(?object $reminder, bool $preferencesReady): string
    {
        if ($reminder === null) {
            return $preferencesReady ? 'Sin planificar' : 'No elegible';
        }

        return match ((string) $reminder->estado) {
            'Pendiente' => 'Pendiente de envío',
            'EnProceso' => 'En proceso',
            'AceptadoPorProveedor' => 'Aceptado por el correo',
            'Fallido' => 'Fallido',
            'ResultadoDesconocido' => 'Resultado desconocido',
            'Descartado' => 'Descartado',
            default => (string) $reminder->estado,
        };
    }

    private function overviewDetail(?object $reminder, bool $preferencesReady, object $appointment): string
    {
        if (! $preferencesReady) {
            return 'El paciente necesita cuenta de portal vinculada, recordatorios habilitados y correo verificado en Perfil.';
        }
        if ($reminder === null) {
            if (! $this->appointmentIsCurrent($appointment)) {
                return 'Solo se planifican recordatorios para citas Programada o Confirmada.';
            }

            return 'No hay fila de recordatorio. Suele ocurrir si al programar aún no cumplía las preferencias.';
        }
        if ((string) $reminder->estado === 'Pendiente') {
            $when = Carbon::parse((string) $reminder->fecha_programada)->timezone('UTC')->format('Y-m-d H:i');

            return 'Programado para enviarse desde '.$when.' UTC (por defecto 24 h antes de la cita).';
        }
        if ((string) $reminder->estado === 'AceptadoPorProveedor') {
            return 'El proveedor de correo aceptó el aviso. Eso no prueba que el paciente lo haya leído.';
        }

        return 'Estado actual del aviso: '.$reminder->estado.'.';
    }

    private function planIfEligible(int $appointmentId): void
    {
        if ($this->appointments->hasUndeliveredReminder($appointmentId)) {
            return;
        }

        $appointment = $this->appointments->getAppointmentById($appointmentId);
        if ($appointment === null || ! $this->appointmentIsCurrent($appointment)) {
            return;
        }

        $start = Carbon::parse((string) $appointment->inicio)->timezone('UTC');
        $scheduledAt = $this->scheduledInstant($start);
        if ($scheduledAt === null) {
            return;
        }

        if (! $this->preferencesAllowSend($this->appointments->getNotificationPreferencesForPatient((int) $appointment->id_paciente))) {
            return;
        }

        $this->appointments->createReminder($scheduledAt->toIso8601String(), $appointmentId);
    }

    private function scheduledInstant(Carbon $start): ?Carbon
    {
        $now = Carbon::now('UTC');
        if ($start->lessThanOrEqualTo($now)) {
            return null;
        }

        $leadHours = $this->leadHours();
        $scheduledAt = $start->copy()->subHours($leadHours);
        if ($scheduledAt->greaterThanOrEqualTo($start)) {
            return null;
        }

        if ($scheduledAt->lessThanOrEqualTo($now)) {
            return $now;
        }

        return $scheduledAt;
    }

    private function dispatchClaimedReminder(int $reminderId, string $attemptedAt): void
    {
        $context = $this->appointments->getReminderDispatchContext($reminderId);
        if ($context === null || ! $this->reminderMatchesCurrentSchedule($context) || ! $this->appointmentIsCurrent($context)) {
            $this->appointments->discardReminder($reminderId);

            return;
        }

        $start = Carbon::parse((string) $context->inicio)->timezone('UTC');
        if ($start->lessThanOrEqualTo(Carbon::now('UTC'))) {
            $this->appointments->discardReminder($reminderId);

            return;
        }

        if (! $this->preferencesAllowSend($context)) {
            $this->appointments->discardReminder($reminderId);

            return;
        }

        $destination = $this->usableDestination($context);
        if ($destination === null) {
            $this->appointments->discardReminder($reminderId);

            return;
        }

        $outcome = $this->mailer->requestSend(
            $destination,
            'Recordatorio de cita '.$context->codigo,
            $this->messageBody($context),
        );
        if (! in_array($outcome, [self::PROVIDER_ACCEPTED, self::PROVIDER_FAILED, self::PROVIDER_UNKNOWN], true)) {
            $outcome = self::PROVIDER_UNKNOWN;
        }

        $reference = 'aviso-'.$reminderId.'-'.Carbon::parse($attemptedAt)->timezone('UTC')->format('YmdHis');
        $this->recordKnownResult($reminderId, $outcome, $reference, $attemptedAt);
    }

    private function recordKnownResult(int $reminderId, string $outcome, string $reference, string $attemptedAt): void
    {
        try {
            $this->appointments->recordReminderResult($reminderId, $outcome, $reference, $attemptedAt);
        } catch (Throwable) {
            // The known result could not be stored; the same aviso is not resent.
        }
    }

    private function recordPlanningIncident(int $appointmentId, int $actorUserId): void
    {
        try {
            $this->appointments->createAppointmentEvent([
                'tipo' => self::PLANNING_INCIDENT_TYPE,
                'fecha_hora' => Carbon::now('UTC')->toIso8601String(),
                'motivo' => 'No pudo planificarse el recordatorio de la cita.',
                'id_cita' => $appointmentId,
                'id_usuario' => $actorUserId,
            ]);
        } catch (Throwable) {
            // The appointment already succeeded; do not undo it.
        }
    }

    private function reminderMatchesCurrentSchedule(object $context): bool
    {
        $scheduledAt = Carbon::parse((string) $context->fecha_programada)->timezone('UTC');
        $start = Carbon::parse((string) $context->inicio)->timezone('UTC');

        return $scheduledAt->lessThan($start);
    }

    private function appointmentIsCurrent(object $appointment): bool
    {
        return in_array((string) $appointment->estado, ['Programada', 'Confirmada'], true);
    }

    private function preferencesAllowSend(?object $preferences): bool
    {
        if ($preferences === null) {
            return false;
        }

        $destination = $this->usableDestination($preferences);

        return $this->booleanFlag($preferences->recordatorios_habilitados ?? false)
            && $this->booleanFlag($preferences->destino_verificado ?? false)
            && (string) ($preferences->canal ?? '') === 'CorreoElectronico'
            && $destination !== null;
    }

    private function usableDestination(object $preferences): ?string
    {
        $destination = isset($preferences->destino) && is_string($preferences->destino)
            ? trim($preferences->destino)
            : '';
        if ($destination === '' || filter_var($destination, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $destination;
    }

    private function messageBody(object $context): string
    {
        $start = Carbon::parse((string) $context->inicio)->timezone('UTC')->format('Y-m-d H:i');

        return implode("\n", [
            'Recordatorio de cita médica',
            '',
            'Código: '.$context->codigo,
            'Fecha y hora: '.$start.' UTC',
            'Médico: '.$context->medico_apellidos.', '.$context->medico_nombres,
            'Especialidad: '.$context->especialidad_nombre,
            'Modalidad: '.$context->modalidad,
            '',
            'Este mensaje no confirma la cita, no registra llegada ni acredita atención médica.',
        ]);
    }

    private function leadHours(): int
    {
        $hours = (int) config('appointments.reminder_lead_hours', 24);

        return $hours > 0 ? $hours : 24;
    }

    private function booleanFlag(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
