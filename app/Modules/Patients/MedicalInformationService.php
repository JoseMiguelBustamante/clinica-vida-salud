<?php

declare(strict_types=1);

namespace App\Modules\Patients;

use Illuminate\Support\Carbon;

final class MedicalInformationService
{
    /** @var list<string> */
    public const BLOOD_GROUPS = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

    public function __construct(private PatientRepository $patients) {}

    public function portalIsEnabled(): bool
    {
        return (bool) config('identity.patient_portal', true);
    }

    /**
     * @return array{patient: object, information: ?object}
     */
    public function getDeclaredMedicalInformation(int $userId): array
    {
        $this->assertPortalEnabled();
        $patient = $this->requireLinkedPatient($userId);

        return [
            'patient' => $patient,
            'information' => $this->patients->getMedicalInformationByPatientId((int) $patient->id_paciente),
        ];
    }

    /**
     * @param  array{grupo_sanguineo?: ?string, situacion_alergias?: ?string, detalle_alergias?: ?string, situacion_enfermedades?: ?string, detalle_enfermedades?: ?string, situacion_medicamentos?: ?string, detalle_medicamentos?: ?string, antecedentes?: ?string}  $input
     */
    public function updateDeclaredMedicalInformation(int $userId, array $input): void
    {
        $this->assertPortalEnabled();
        $patient = $this->requireLinkedPatient($userId);
        $this->patients->upsertMedicalInformation((int) $patient->id_paciente, $this->normalizedDeclaration($input));
    }

    private function requireLinkedPatient(int $userId): object
    {
        $patient = $this->patients->getPatientByAccount($userId);
        if ($patient === null) {
            throw new PatientLinkRequired;
        }

        return $patient;
    }

    /**
     * @param  array{grupo_sanguineo?: ?string, situacion_alergias?: ?string, detalle_alergias?: ?string, situacion_enfermedades?: ?string, detalle_enfermedades?: ?string, situacion_medicamentos?: ?string, detalle_medicamentos?: ?string, antecedentes?: ?string}  $input
     * @return array{grupo_sanguineo: ?string, situacion_alergias: ?string, detalle_alergias: ?string, situacion_enfermedades: ?string, detalle_enfermedades: ?string, situacion_medicamentos: ?string, detalle_medicamentos: ?string, antecedentes: ?string, fecha_actualizacion: string}
     */
    private function normalizedDeclaration(array $input): array
    {
        return [
            'grupo_sanguineo' => $this->normalizedBloodGroup($input['grupo_sanguineo'] ?? null),
            'situacion_alergias' => $this->optionalText($input['situacion_alergias'] ?? null, 50),
            'detalle_alergias' => $this->optionalText($input['detalle_alergias'] ?? null, 2000),
            'situacion_enfermedades' => $this->optionalText($input['situacion_enfermedades'] ?? null, 50),
            'detalle_enfermedades' => $this->optionalText($input['detalle_enfermedades'] ?? null, 2000),
            'situacion_medicamentos' => $this->optionalText($input['situacion_medicamentos'] ?? null, 50),
            'detalle_medicamentos' => $this->optionalText($input['detalle_medicamentos'] ?? null, 2000),
            'antecedentes' => $this->optionalText($input['antecedentes'] ?? null, 4000),
            'fecha_actualizacion' => Carbon::now()->toIso8601String(),
        ];
    }

    private function normalizedBloodGroup(?string $value): ?string
    {
        $group = $this->optionalText($value, 5);
        if ($group === null) {
            return null;
        }
        if (! in_array($group, self::BLOOD_GROUPS, true)) {
            throw new InvalidPatientInput;
        }

        return $group;
    }

    private function optionalText(?string $value, int $maximum): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $maximum
            || preg_match('/\p{C}/u', $value) !== 0) {
            throw new InvalidPatientInput;
        }

        return $value;
    }

    private function assertPortalEnabled(): void
    {
        if (! $this->portalIsEnabled()) {
            throw new DeclaredInformationUnavailable;
        }
    }
}
