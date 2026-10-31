<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PhysicianService
{
    public function __construct(
        private PhysicianRepository $physicians,
        private SpecialtyRepository $specialties,
    ) {}

    public function getAllPhysicians(?string $fragment): Collection
    {
        $physicians = $this->physicians->getAllPhysicians($fragment === '' ? null : $fragment);
        $specialties = $this->physicians->getSpecialtiesForPhysicians(
            $physicians->pluck('id_medico')->map(fn ($id): int => (int) $id)->all()
        );

        return $physicians->map(function (object $physician) use ($specialties): object {
            $physician->especialidades = $specialties->get((string) $physician->id_medico, collect());

            return $physician;
        });
    }

    public function getPhysicianById(int $id): object
    {
        $physician = $this->physicians->getPhysicianById($id);
        if ($physician === null) {
            throw new PhysicianNotFound;
        }
        $physician->especialidades = $this->physicians->getSpecialtiesForPhysicians([$id])->get((string) $id, collect());
        $physician->id_especialidades = $this->physicians->getSpecialtyIdsForPhysician($id);

        return $physician;
    }

    /**
     * @param  array{registro_profesional: string, nombres: string, apellidos: string, telefono?: ?string, correo_contacto?: ?string, activo: bool, especialidades: list<int>}  $input
     */
    public function createPhysician(array $input): int
    {
        $values = $this->normalizedRecord($input, null);
        $specialtyIds = $this->validatedSpecialtyIds($input['especialidades'] ?? [], []);

        return DB::transaction(function () use ($values, $specialtyIds): int {
            $id = $this->physicians->createPhysician($values);
            $this->physicians->replaceSpecialties($id, $specialtyIds);

            return $id;
        });
    }

    /**
     * @param  array{registro_profesional: string, nombres: string, apellidos: string, telefono?: ?string, correo_contacto?: ?string, activo: bool, especialidades: list<int>}  $input
     */
    public function updatePhysician(int $id, array $input): void
    {
        $this->getPhysicianById($id);
        $values = $this->normalizedRecord($input, $id);
        $specialtyIds = $this->validatedSpecialtyIds(
            $input['especialidades'] ?? [],
            $this->physicians->getSpecialtyIdsForPhysician($id),
        );

        DB::transaction(function () use ($id, $values, $specialtyIds): void {
            $this->physicians->updatePhysician($id, $values);
            $this->physicians->replaceSpecialties($id, $specialtyIds);
        });
    }

    public function deactivatePhysician(int $id): void
    {
        $this->getPhysicianById($id);
        $this->physicians->setPhysicianActive($id, false);
    }

    public function activatePhysician(int $id): void
    {
        $this->getPhysicianById($id);
        $this->physicians->setPhysicianActive($id, true);
    }

    /**
     * @param  array{registro_profesional: string, nombres: string, apellidos: string, telefono?: ?string, correo_contacto?: ?string, activo: bool}  $input
     * @return array{registro_profesional: string, nombres: string, apellidos: string, telefono: ?string, correo_contacto: ?string, activo: bool}
     */
    private function normalizedRecord(array $input, ?int $exceptId): array
    {
        $license = $this->requiredText($input['registro_profesional'] ?? '', 50);
        if ($this->physicians->licenseTaken($license, $exceptId)) {
            throw new DuplicateProfessionalLicense;
        }
        $email = $this->optionalText($input['correo_contacto'] ?? null, 255);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPhysicianInput;
        }

        return [
            'registro_profesional' => $license,
            'nombres' => $this->requiredText($input['nombres'] ?? '', 100),
            'apellidos' => $this->requiredText($input['apellidos'] ?? '', 100),
            'telefono' => $this->optionalText($input['telefono'] ?? null, 30),
            'correo_contacto' => $email,
            'activo' => (bool) $input['activo'],
        ];
    }

    /**
     * @param  list<int|string>  $requested
     * @param  list<int>  $alreadyLinked
     * @return list<int>
     */
    private function validatedSpecialtyIds(array $requested, array $alreadyLinked): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $requested)));
        if ($ids === []) {
            throw new InvalidPhysicianInput;
        }
        foreach ($ids as $id) {
            $specialty = $this->specialties->getSpecialtyById($id);
            if ($specialty === null) {
                throw new SpecialtyNotFound;
            }
            if (! $specialty->activo && ! in_array($id, $alreadyLinked, true)) {
                throw new InactiveSpecialtyAssociation;
            }
        }

        return $ids;
    }

    private function requiredText(string $value, int $maximum): string
    {
        if (! mb_check_encoding($value, 'UTF-8') || $value === '' || mb_strlen($value) > $maximum
            || preg_match('/\p{C}/u', $value) !== 0) {
            throw new InvalidPhysicianInput;
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
            throw new InvalidPhysicianInput;
        }

        return $value;
    }
}
