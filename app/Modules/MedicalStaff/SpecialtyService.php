<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff;

use Illuminate\Support\Collection;

final class SpecialtyService
{
    public function __construct(private SpecialtyRepository $specialties) {}

    public function getAllSpecialties(?string $fragment): Collection
    {
        return $this->specialties->getAllSpecialties($fragment === '' ? null : $fragment);
    }

    public function getActiveSpecialties(): Collection
    {
        return $this->specialties->getActiveSpecialties();
    }

    public function getSpecialtyById(int $id): object
    {
        $specialty = $this->specialties->getSpecialtyById($id);
        if ($specialty === null) {
            throw new SpecialtyNotFound;
        }

        return $specialty;
    }

    public function createSpecialty(string $name, ?string $description, bool $active): int
    {
        $normalized = $this->normalizedName($name);
        $this->assertUniqueName($normalized, null);

        return $this->specialties->createSpecialty($normalized, $this->optionalText($description), $active);
    }

    public function updateSpecialty(int $id, string $name, ?string $description): void
    {
        $this->getSpecialtyById($id);
        $normalized = $this->normalizedName($name);
        $this->assertUniqueName($normalized, $id);
        $this->specialties->updateSpecialty($id, $normalized, $this->optionalText($description));
    }

    public function deactivateSpecialty(int $id): void
    {
        $this->getSpecialtyById($id);
        $this->specialties->setSpecialtyActive($id, false);
    }

    public function activateSpecialty(int $id): void
    {
        $this->getSpecialtyById($id);
        $this->specialties->setSpecialtyActive($id, true);
    }

    private function normalizedName(string $name): string
    {
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ($collapsed === '' || ! mb_check_encoding($collapsed, 'UTF-8') || mb_strlen($collapsed) > 100
            || preg_match('/\p{C}/u', $collapsed) !== 0) {
            throw new InvalidSpecialtyInput;
        }

        return $collapsed;
    }

    private function optionalText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! mb_check_encoding($value, 'UTF-8') || preg_match('/\p{C}/u', $value) !== 0) {
            throw new InvalidSpecialtyInput;
        }

        return $value;
    }

    private function assertUniqueName(string $name, ?int $exceptId): void
    {
        if ($this->specialties->nameTaken($name, $exceptId)) {
            throw new DuplicateSpecialtyName;
        }
    }
}
