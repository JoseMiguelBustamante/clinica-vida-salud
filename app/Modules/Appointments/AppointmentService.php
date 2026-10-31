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
