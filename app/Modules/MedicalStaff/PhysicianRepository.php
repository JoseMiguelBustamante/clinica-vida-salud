<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PhysicianRepository
{
    /** @return Collection<int, object> */
    public function getAllPhysicians(?string $fragment): Collection
    {
        $query = DB::table('medico')
            ->select('medico.id_medico', 'medico.registro_profesional', 'medico.nombres', 'medico.apellidos',
                'medico.telefono', 'medico.correo_contacto', 'medico.activo')
            ->orderBy('medico.apellidos')->orderBy('medico.nombres');

        if ($fragment !== null && $fragment !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $fragment).'%';
            $query->leftJoin('medico_especialidad', 'medico_especialidad.id_medico', '=', 'medico.id_medico')
                ->leftJoin('especialidad', 'especialidad.id_especialidad', '=', 'medico_especialidad.id_especialidad')
                ->where(function ($inner) use ($like): void {
                    $inner->whereRaw('medico.nombres ILIKE ? ESCAPE \'!\'', [$like])
                        ->orWhereRaw('medico.apellidos ILIKE ? ESCAPE \'!\'', [$like])
                        ->orWhereRaw('medico.registro_profesional ILIKE ? ESCAPE \'!\'', [$like])
                        ->orWhereRaw('especialidad.nombre ILIKE ? ESCAPE \'!\'', [$like]);
                })
                ->distinct();
        }

        return $query->get();
    }

    public function getPhysicianById(int $id): ?object
    {
        return DB::table('medico')
            ->select('id_medico', 'registro_profesional', 'nombres', 'apellidos',
                'telefono', 'correo_contacto', 'activo')
            ->where('id_medico', $id)->first();
    }

    public function licenseTaken(string $license, ?int $exceptId = null): bool
    {
        $query = DB::table('medico')->where('registro_profesional', $license);
        if ($exceptId !== null) {
            $query->where('id_medico', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * @param  array{registro_profesional: string, nombres: string, apellidos: string, telefono: ?string, correo_contacto: ?string, activo: bool}  $values
     */
    public function createPhysician(array $values): int
    {
        return (int) DB::table('medico')->insertGetId($values, 'id_medico');
    }

    /**
     * @param  array{registro_profesional: string, nombres: string, apellidos: string, telefono: ?string, correo_contacto: ?string, activo: bool}  $values
     */
    public function updatePhysician(int $id, array $values): void
    {
        DB::table('medico')->where('id_medico', $id)->update($values);
    }

    public function setPhysicianActive(int $id, bool $active): void
    {
        DB::table('medico')->where('id_medico', $id)->update(['activo' => $active]);
    }

    /** @return list<int> */
    public function getSpecialtyIdsForPhysician(int $physicianId): array
    {
        return DB::table('medico_especialidad')->where('id_medico', $physicianId)
            ->orderBy('id_especialidad')->pluck('id_especialidad')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  list<int>  $physicianIds
     * @return Collection<int, Collection<int, object>>
     */
    public function getSpecialtiesForPhysicians(array $physicianIds): Collection
    {
        if ($physicianIds === []) {
            return collect();
        }

        return DB::table('medico_especialidad')
            ->join('especialidad', 'especialidad.id_especialidad', '=', 'medico_especialidad.id_especialidad')
            ->whereIn('medico_especialidad.id_medico', $physicianIds)
            ->orderBy('especialidad.nombre')
            ->get(['medico_especialidad.id_medico', 'especialidad.id_especialidad', 'especialidad.nombre', 'especialidad.activo'])
            ->groupBy('id_medico');
    }

    /** @param  list<int>  $specialtyIds */
    public function replaceSpecialties(int $physicianId, array $specialtyIds): void
    {
        DB::table('medico_especialidad')->where('id_medico', $physicianId)->delete();
        foreach ($specialtyIds as $specialtyId) {
            DB::table('medico_especialidad')->insert([
                'id_medico' => $physicianId,
                'id_especialidad' => $specialtyId,
            ]);
        }
    }
}
