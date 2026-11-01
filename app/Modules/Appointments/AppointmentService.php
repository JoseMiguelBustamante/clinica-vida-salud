<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use App\Modules\Patients\PatientRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AppointmentService
{
    public function __construct(
        private AppointmentRepository $appointments,
        private AvailabilityService $availability,
        private PatientRepository $patients,
    ) {}

    public function getBookablePatients(?string $fragment): Collection
    {
        return $this->patients->getActivePatients($fragment === '' ? null : $fragment);
    }

    public function getAllAppointments(?string $fragment): Collection
    {
        return $this->appointments->getAllAppointments($fragment === '' ? null : $fragment);
    }

    public function getAppointmentById(int $id): object
    {
        $appointment = $this->appointments->getAppointmentById($id);
        if ($appointment === null) {
            throw new AppointmentNotFound;
        }

        return $appointment;
    }

    /**
     * @param  array{id_paciente: int, id_especialidad: int, id_medico: int, fecha: string, hora_inicio: string, hora_fin: string, modalidad: string, motivo_consulta?: ?string}  $input
     */
    public function createAppointment(array $input, int $actorUserId): int
    {
        $patientId = (int) $input['id_paciente'];
        $specialtyId = (int) $input['id_especialidad'];
        $physicianId = (int) $input['id_medico'];
        $this->requireActivePatient($patientId);
        $interval = $this->normalizedInterval((string) $input['fecha'], (string) $input['hora_inicio'], (string) $input['hora_fin']);
        $modality = $this->normalizedModality((string) $input['modalidad']);
        $reason = $this->optionalReason($input['motivo_consulta'] ?? null);

        return DB::transaction(function () use ($patientId, $specialtyId, $physicianId, $interval, $modality, $reason, $actorUserId): int {
            $this->appointments->lockPhysicianAppointments($physicianId);
            $this->requireActivePatient($patientId);
            $this->availability->validateRequestedSlot($specialtyId, $physicianId, $interval['inicio'], $interval['fin']);
            $createdAt = Carbon::now('UTC')->toIso8601String();
            $appointmentId = $this->appointments->createAppointment([
                'codigo' => $this->nextAppointmentCode(),
                'inicio' => $interval['inicio'],
                'fin' => $interval['fin'],
                'motivo_consulta' => $reason,
                'modalidad' => $modality,
                'estado' => 'Programada',
                'fecha_creacion' => $createdAt,
                'id_paciente' => $patientId,
                'id_medico' => $physicianId,
                'id_especialidad' => $specialtyId,
            ]);
            $this->appointments->createAppointmentEvent([
                'tipo' => 'Programacion',
                'fecha_hora' => $createdAt,
                'motivo' => $reason,
                'id_cita' => $appointmentId,
                'id_usuario' => $actorUserId,
            ]);

            return $appointmentId;
        });
    }

    /**
     * @param  array{fecha: string, hora_inicio: string, hora_fin: string}  $input
     * @return array{appointment_id: int, required_new_confirmation: bool}
     */
    public function rescheduleAppointment(int $id, array $input, int $actorUserId): array
    {
        $current = $this->getAppointmentById($id);
        $this->assertReschedulable($current);
        $interval = $this->normalizedInterval((string) $input['fecha'], (string) $input['hora_inicio'], (string) $input['hora_fin']);
        $this->assertDifferentInterval($current, $interval);

        return DB::transaction(function () use ($id, $current, $interval, $actorUserId): array {
            $this->appointments->lockPhysicianAppointments((int) $current->id_medico);
            $locked = $this->getAppointmentById($id);
            $this->assertReschedulable($locked);
            $this->availability->validateRequestedSlot(
                (int) $locked->id_especialidad,
                (int) $locked->id_medico,
                $interval['inicio'],
                $interval['fin'],
                $id,
            );
            $wasConfirmed = $locked->estado === 'Confirmada';
            $this->appointments->updateAppointmentSchedule($id, $interval['inicio'], $interval['fin'], 'Programada');
            $this->appointments->createAppointmentEvent([
                'tipo' => 'Reprogramacion',
                'fecha_hora' => Carbon::now('UTC')->toIso8601String(),
                'motivo' => null,
                'id_cita' => $id,
                'id_usuario' => $actorUserId,
            ]);

            return [
                'appointment_id' => $id,
                'required_new_confirmation' => $wasConfirmed,
            ];
        });
    }

    public function cancelAppointment(int $id, ?string $reason, int $actorUserId): void
    {
        $current = $this->getAppointmentById($id);
        $this->assertCancellable($current);
        $note = $this->optionalReason($reason);

        DB::transaction(function () use ($id, $current, $note, $actorUserId): void {
            $this->appointments->lockPhysicianAppointments((int) $current->id_medico);
            $verified = $this->getAppointmentById($id);
            $this->assertCancellable($verified);
            $this->appointments->cancelAppointment($id, $note);
            $this->appointments->discardPendingReminders($id);
            $this->appointments->createAppointmentEvent([
                'tipo' => 'Cancelacion',
                'fecha_hora' => Carbon::now('UTC')->toIso8601String(),
                'motivo' => $note,
                'id_cita' => $id,
                'id_usuario' => $actorUserId,
            ]);
        });
    }

    public function confirmAppointment(int $id, int $actorUserId): void
    {
        $current = $this->getAppointmentById($id);
        $this->assertConfirmable($current);

        DB::transaction(function () use ($id, $current, $actorUserId): void {
            $this->appointments->lockPhysicianAppointments((int) $current->id_medico);
            $verified = $this->getAppointmentById($id);
            $this->assertConfirmable($verified);
            $this->appointments->confirmAppointment($id);
            $this->appointments->createAppointmentEvent([
                'tipo' => 'Confirmacion',
                'fecha_hora' => Carbon::now('UTC')->toIso8601String(),
                'motivo' => null,
                'id_cita' => $id,
                'id_usuario' => $actorUserId,
            ]);
        });
    }

    public function registerAppointmentArrival(int $id, int $actorUserId): void
    {
        $current = $this->getAppointmentById($id);
        $this->assertArrivalAllowed($current);

        DB::transaction(function () use ($id, $current, $actorUserId): void {
            $this->appointments->lockPhysicianAppointments((int) $current->id_medico);
            $verified = $this->getAppointmentById($id);
            $this->assertArrivalAllowed($verified);
            $arrivedAt = Carbon::now('UTC')->toIso8601String();
            $this->appointments->registerAppointmentArrival($id, $arrivedAt);
            $this->appointments->createAppointmentEvent([
                'tipo' => 'Llegada',
                'fecha_hora' => $arrivedAt,
                'motivo' => null,
                'id_cita' => $id,
                'id_usuario' => $actorUserId,
            ]);
        });
    }

    public function registerAppointmentAbsence(int $id, int $actorUserId): void
    {
        $current = $this->getAppointmentById($id);
        $this->assertAbsenceAllowed($current);

        DB::transaction(function () use ($id, $current, $actorUserId): void {
            $this->appointments->lockPhysicianAppointments((int) $current->id_medico);
            $verified = $this->getAppointmentById($id);
            $this->assertAbsenceAllowed($verified);
            $this->appointments->registerAppointmentAbsence($id);
            $this->appointments->createAppointmentEvent([
                'tipo' => 'Inasistencia',
                'fecha_hora' => Carbon::now('UTC')->toIso8601String(),
                'motivo' => null,
                'id_cita' => $id,
                'id_usuario' => $actorUserId,
            ]);
        });
    }

    private function assertConfirmable(object $appointment): void
    {
        if ((string) $appointment->estado !== 'Programada') {
            throw new AppointmentAttendanceNotAllowed;
        }
    }

    private function assertArrivalAllowed(object $appointment): void
    {
        if (! in_array((string) $appointment->estado, ['Programada', 'Confirmada'], true)) {
            throw new AppointmentAttendanceNotAllowed;
        }
        if ($this->hasRecordedArrival($appointment)) {
            throw new AppointmentAttendanceNotAllowed;
        }
    }

    private function assertAbsenceAllowed(object $appointment): void
    {
        if (! in_array((string) $appointment->estado, ['Programada', 'Confirmada'], true)) {
            throw new AppointmentAttendanceNotAllowed;
        }
        if ($this->hasRecordedArrival($appointment)) {
            throw new AppointmentAttendanceNotAllowed;
        }
        $end = Carbon::parse((string) $appointment->fin, 'UTC');
        if (! Carbon::now('UTC')->gt($end)) {
            throw new AppointmentAttendanceNotAllowed;
        }
    }

    private function hasRecordedArrival(object $appointment): bool
    {
        $arrival = $appointment->fecha_hora_llegada ?? null;

        return $arrival !== null && $arrival !== '';
    }

    private function assertCancellable(object $appointment): void
    {
        if (! in_array((string) $appointment->estado, ['Programada', 'Confirmada'], true)) {
            throw new AppointmentNotCancellable;
        }
    }

    private function assertReschedulable(object $appointment): void
    {
        if (! in_array((string) $appointment->estado, ['Programada', 'Confirmada'], true)) {
            throw new AppointmentNotReschedulable;
        }
    }

    /**
     * @param  array{inicio: string, fin: string}  $interval
     */
    private function assertDifferentInterval(object $appointment, array $interval): void
    {
        $currentStart = Carbon::parse((string) $appointment->inicio, 'UTC');
        $currentEnd = Carbon::parse((string) $appointment->fin, 'UTC');
        $requestedStart = Carbon::parse($interval['inicio'], 'UTC');
        $requestedEnd = Carbon::parse($interval['fin'], 'UTC');
        if ($currentStart->eq($requestedStart) && $currentEnd->eq($requestedEnd)) {
            throw new InvalidAvailabilityInput;
        }
    }

    private function requireActivePatient(int $patientId): void
    {
        $patient = $this->patients->getPatientById($patientId);
        if ($patient === null || ! $patient->activo) {
            throw new PatientNotEligible;
        }
    }

    /**
     * @return array{inicio: string, fin: string}
     */
    private function normalizedInterval(string $date, string $startClock, string $endClock): array
    {
        $from = $this->clockOnDate($date, $startClock);
        $until = $this->clockOnDate($date, $endClock);
        if ($until->lte($from) || $from->lt(Carbon::now('UTC'))) {
            throw new InvalidAvailabilityInput;
        }

        return [
            'inicio' => $from->toIso8601String(),
            'fin' => $until->toIso8601String(),
        ];
    }

    private function clockOnDate(string $date, string $time): Carbon
    {
        $clock = strlen($time) >= 8 ? substr($time, 0, 8) : $time.':00';
        try {
            $parsed = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$clock, 'UTC');
        } catch (\Throwable) {
            throw new InvalidAvailabilityInput;
        }
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidAvailabilityInput;
        }

        return $parsed;
    }

    private function normalizedModality(string $modality): string
    {
        if (! in_array($modality, ['Presencial', 'Virtual'], true)) {
            throw new InvalidAvailabilityInput;
        }

        return $modality;
    }

    private function optionalReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }
        $trimmed = trim($reason);
        if ($trimmed === '') {
            return null;
        }
        if (strlen($trimmed) > 2000) {
            throw new InvalidAvailabilityInput;
        }

        return $trimmed;
    }

    private function nextAppointmentCode(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = 'CIT-'.Carbon::now('UTC')->format('YmdHis').bin2hex(random_bytes(2));
            if (! $this->appointments->appointmentCodeTaken($code)) {
                return $code;
            }
        }

        throw new InvalidAvailabilityInput;
    }
}
