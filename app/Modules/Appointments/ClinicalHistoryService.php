<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use App\Modules\Patients\PatientLinkRequired;
use App\Modules\Patients\PatientRepository;
use Illuminate\Support\Collection;

final class ClinicalHistoryService
{
    public function __construct(
        private AppointmentRepository $appointments,
        private PatientRepository $patients,
    ) {}

    public function portalIsEnabled(): bool
    {
        return (bool) config('identity.patient_portal', true);
    }

    /**
     * @return array{patient: object, records: Collection<int, object>}
     */
    public function getOwnClinicalHistory(int $userId): array
    {
        $this->assertPortalEnabled();
        $patient = $this->requireLinkedPatient($userId);

        return [
            'patient' => $patient,
            'records' => $this->appointments->getAppointmentsForPatient((int) $patient->id_paciente, null)
                ->map(fn (object $appointment): object => $this->authorizedListRecord($appointment)),
        ];
    }

    /**
     * @return array{patient: object, record: object}
     */
    public function getOwnClinicalHistoryRecord(int $userId, int $appointmentId): array
    {
        $this->assertPortalEnabled();
        $patient = $this->requireLinkedPatient($userId);
        $appointment = $this->appointments->getAppointmentById($appointmentId);
        if ($appointment === null || (int) $appointment->id_paciente !== (int) $patient->id_paciente) {
            throw new ClinicalHistoryRecordUnavailable;
        }

        return [
            'patient' => $patient,
            'record' => $this->authorizedDetailRecord($appointment),
        ];
    }

    private function requireLinkedPatient(int $userId): object
    {
        $patient = $this->patients->getPatientByAccount($userId);
        if ($patient === null) {
            throw new PatientLinkRequired;
        }

        return $patient;
    }

    private function authorizedListRecord(object $appointment): object
    {
        return (object) [
            'id_cita' => $appointment->id_cita,
            'codigo' => $appointment->codigo,
            'inicio' => $appointment->inicio,
            'fin' => $appointment->fin,
            'estado' => $appointment->estado,
            'modalidad' => $appointment->modalidad,
            'medico_nombres' => $appointment->medico_nombres,
            'medico_apellidos' => $appointment->medico_apellidos,
            'especialidad_nombre' => $appointment->especialidad_nombre,
        ];
    }

    private function authorizedDetailRecord(object $appointment): object
    {
        return (object) [
            'id_cita' => $appointment->id_cita,
            'codigo' => $appointment->codigo,
            'inicio' => $appointment->inicio,
            'fin' => $appointment->fin,
            'estado' => $appointment->estado,
            'modalidad' => $appointment->modalidad,
            'motivo_consulta' => $appointment->motivo_consulta ?? null,
            'motivo_cancelacion' => $appointment->motivo_cancelacion ?? null,
            'medico_nombres' => $appointment->medico_nombres,
            'medico_apellidos' => $appointment->medico_apellidos,
            'registro_profesional' => $appointment->registro_profesional ?? null,
            'especialidad_nombre' => $appointment->especialidad_nombre,
        ];
    }

    private function assertPortalEnabled(): void
    {
        if (! $this->portalIsEnabled()) {
            throw new ClinicalHistoryUnavailable;
        }
    }
}
