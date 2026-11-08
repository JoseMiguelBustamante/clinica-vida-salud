<?php

declare(strict_types=1);

namespace App\Modules\Patients;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PatientService
{
    public function __construct(private PatientRepository $patients) {}

    public function getAllPatients(?string $fragment): Collection
    {
        return $this->patients->getAllPatients($fragment === '' ? null : $fragment);
    }

    public function getPatientById(int $id): object
    {
        $patient = $this->patients->getPatientById($id);
        if ($patient === null) {
            throw new PatientNotFound;
        }

        return $patient;
    }

    /** @return Collection<int, object> */
    public function getLinkablePatientAccounts(?int $currentPatientId): Collection
    {
        return $this->patients->getLinkablePatientAccounts($currentPatientId);
    }

    public function getLinkablePatientAccountById(int $userId, ?int $currentPatientId = null): ?object
    {
        return $this->patients->getLinkablePatientAccountById($userId, $currentPatientId);
    }

    /**
     * @param  array{ci?: ?string, nombres: string, apellidos: string, fecha_nacimiento?: ?string, sexo?: ?string, telefono?: ?string, correo_contacto?: ?string, direccion?: ?string, id_usuario?: ?int}  $input
     */
    public function createPatient(array $input): int
    {
        $values = $this->normalizedRecord($input, null);
        $values['activo'] = true;
        $values['fecha_registro'] = Carbon::now()->toIso8601String();

        return $this->patients->createPatient($values);
    }

    /**
     * @param  array{ci?: ?string, nombres: string, apellidos: string, fecha_nacimiento?: ?string, sexo?: ?string, telefono?: ?string, correo_contacto?: ?string, direccion?: ?string, id_usuario?: ?int}  $input
     */
    public function updatePatient(int $id, array $input): void
    {
        $this->getPatientById($id);
        $this->patients->updatePatient($id, $this->normalizedRecord($input, $id));
    }

    public function deactivatePatient(int $id): void
    {
        $this->getPatientById($id);
        $this->patients->setPatientActive($id, false);
    }

    public function activatePatient(int $id): void
    {
        $this->getPatientById($id);
        $this->patients->setPatientActive($id, true);
    }

    /**
     * @param  array{ci?: ?string, nombres: string, apellidos: string, fecha_nacimiento?: ?string, sexo?: ?string, telefono?: ?string, correo_contacto?: ?string, direccion?: ?string, id_usuario?: ?int}  $input
     * @return array{ci: ?string, nombres: string, apellidos: string, fecha_nacimiento: ?string, sexo: ?string, telefono: ?string, correo_contacto: ?string, direccion: ?string, id_usuario: ?int}
     */
    private function normalizedRecord(array $input, ?int $exceptId): array
    {
        $firstName = $this->requiredText($input['nombres'] ?? '', 100);
        $lastName = $this->requiredText($input['apellidos'] ?? '', 100);
        $ci = $this->optionalText($input['ci'] ?? null, 30);
        $this->assertUniqueDocument($ci, $exceptId);
        $birthDate = $this->normalizedBirthDate($input['fecha_nacimiento'] ?? null);
        $email = $this->optionalText($input['correo_contacto'] ?? null, 255);
        $accountId = $this->optionalAccountId($input['id_usuario'] ?? null, $exceptId);
        if ($email === null && $accountId !== null) {
            $account = $this->patients->getLinkablePatientAccountById($accountId, $exceptId);
            if ($account !== null) {
                $email = $this->optionalText(
                    isset($account->correo_acceso) ? (string) $account->correo_acceso : null,
                    255,
                );
            }
        }
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPatientInput;
        }

        return [
            'ci' => $ci,
            'nombres' => $firstName,
            'apellidos' => $lastName,
            'fecha_nacimiento' => $birthDate,
            'sexo' => $this->optionalText($input['sexo'] ?? null, 30),
            'telefono' => $this->optionalText($input['telefono'] ?? null, 30),
            'correo_contacto' => $email,
            'direccion' => $this->optionalText($input['direccion'] ?? null, 2000),
            'id_usuario' => $accountId,
        ];
    }

    private function optionalAccountId(mixed $value, ?int $exceptPatientId): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $accountId = (int) $value;
        if ($accountId < 1 || ! $this->patients->patientAccountIsLinkable($accountId, $exceptPatientId)) {
            throw new InvalidPatientInput;
        }

        return $accountId;
    }

    private function requiredText(string $value, int $maximum): string
    {
        if (! mb_check_encoding($value, 'UTF-8') || $value === '' || mb_strlen($value) > $maximum
            || preg_match('/\p{C}/u', $value) !== 0) {
            throw new InvalidPatientInput;
        }

        return $value;
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

    private function normalizedBirthDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            throw new InvalidPatientInput;
        }

        if ($date === false || $date->format('Y-m-d') !== $value || $date->isFuture()) {
            throw new InvalidPatientInput;
        }

        return $date->toDateString();
    }

    private function assertUniqueDocument(?string $ci, ?int $exceptId): void
    {
        if ($ci !== null && $this->patients->documentTaken($ci, $exceptId)) {
            throw new DuplicatePatientDocument;
        }
    }
}
