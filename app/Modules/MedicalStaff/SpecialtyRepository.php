<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpecialtyRepository
{
    /** @return Collection<int, object> */
    public function getAllSpecialties(?string $fragment): Collection
    {
        $query = DB::table('especialidad')
            ->select('id_especialidad', 'nombre', 'descripcion', 'activo')
            ->orderBy('nombre');

        if ($fragment !== null && $fragment !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $fragment).'%';
            $query->whereRaw('especialidad.nombre ILIKE ? ESCAPE \'!\'', [$like]);
        }

        return $query->get();
    }

    /** @return Collection<int, object> */
    public function getActiveSpecialties(): Collection
    {
        return DB::table('especialidad')->select('id_especialidad', 'nombre', 'descripcion', 'activo')
            ->where('activo', true)->orderBy('nombre')->get();
    }

    /** @return Collection<int, object> */
    public function getActiveSpecialtiesWithPhysicians(): Collection
    {
        return DB::table('especialidad')
            ->join('medico_especialidad', 'medico_especialidad.id_especialidad', '=', 'especialidad.id_especialidad')
            ->join('medico', 'medico.id_medico', '=', 'medico_especialidad.id_medico')
            ->select('especialidad.id_especialidad', 'especialidad.nombre', 'especialidad.descripcion', 'especialidad.activo')
            ->where('especialidad.activo', true)
            ->where('medico.activo', true)
            ->distinct()
            ->orderBy('especialidad.nombre')
            ->get();
    }

    public function getSpecialtyById(int $id): ?object
    {
        return DB::table('especialidad')->select('id_especialidad', 'nombre', 'descripcion', 'activo')
            ->where('id_especialidad', $id)->first();
    }

    public function countActiveSpecialties(): int
    {
        return (int) DB::table('especialidad')->where('activo', true)->count();
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        $query = DB::table('especialidad')->whereRaw('lower(nombre) = lower(?)', [$name]);
        if ($exceptId !== null) {
            $query->where('id_especialidad', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function createSpecialty(string $name, ?string $description, bool $active): int
    {
        return (int) DB::table('especialidad')->insertGetId([
            'nombre' => $name,
            'descripcion' => $description,
            'activo' => $active,
        ], 'id_especialidad');
    }

    public function updateSpecialty(int $id, string $name, ?string $description): void
    {
        DB::table('especialidad')->where('id_especialidad', $id)->update([
            'nombre' => $name,
            'descripcion' => $description,
        ]);
    }

    public function setSpecialtyActive(int $id, bool $active): void
    {
        DB::table('especialidad')->where('id_especialidad', $id)->update(['activo' => $active]);
    }
}
