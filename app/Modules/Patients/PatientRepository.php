<?php

declare(strict_types=1);

namespace App\Modules\Patients;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PatientRepository
{
    /** @return Collection<int, object> */
    public function getAllPatients(?string $fragment): Collection
    {
        $query = DB::table('paciente')
            ->select('id_paciente', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo',
                'telefono', 'correo_contacto', 'direccion', 'activo', 'fecha_registro')
            ->orderBy('apellidos')->orderBy('nombres');

        if ($fragment !== null && $fragment !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $fragment).'%';
            $query->where(function ($inner) use ($like): void {
                $inner->whereRaw('paciente.ci ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('paciente.nombres ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('paciente.apellidos ILIKE ? ESCAPE \'!\'', [$like]);
            });
        }

        return $query->get();
    }

    public function getPatientById(int $id): ?object
    {
        return DB::table('paciente')
            ->select('id_paciente', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo',
                'telefono', 'correo_contacto', 'direccion', 'activo', 'fecha_registro')
            ->where('id_paciente', $id)->first();
    }

    public function documentTaken(string $ci, ?int $exceptId = null): bool
    {
        $query = DB::table('paciente')->where('ci', $ci);
        if ($exceptId !== null) {
            $query->where('id_paciente', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * @param  array{ci: ?string, nombres: string, apellidos: string, fecha_nacimiento: ?string, sexo: ?string, telefono: ?string, correo_contacto: ?string, direccion: ?string, activo: bool, fecha_registro: string}  $values
     */
    public function createPatient(array $values): int
    {
        return (int) DB::table('paciente')->insertGetId($values, 'id_paciente');
    }

    /**
     * @param  array{ci: ?string, nombres: string, apellidos: string, fecha_nacimiento: ?string, sexo: ?string, telefono: ?string, correo_contacto: ?string, direccion: ?string}  $values
     */
    public function updatePatient(int $id, array $values): void
    {
        DB::table('paciente')->where('id_paciente', $id)->update($values);
    }

    public function setPatientActive(int $id, bool $active): void
    {
        DB::table('paciente')->where('id_paciente', $id)->update(['activo' => $active]);
    }
}
