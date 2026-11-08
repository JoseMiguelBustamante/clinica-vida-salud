<?php

declare(strict_types=1);

namespace App\Modules\Patients;

use App\Modules\Identity\PermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PatientRepository
{
    /** @return Collection<int, object> */
    public function getAllPatients(?string $fragment): Collection
    {
        $query = DB::table('paciente')
            ->select('id_paciente', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo',
                'telefono', 'correo_contacto', 'direccion', 'activo', 'fecha_registro', 'id_usuario')
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

    /** @return Collection<int, object> */
    public function getActivePatients(?string $fragment): Collection
    {
        $query = DB::table('paciente')
            ->select('id_paciente', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo',
                'telefono', 'correo_contacto', 'direccion', 'activo', 'fecha_registro', 'id_usuario')
            ->where('activo', true)
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
                'telefono', 'correo_contacto', 'direccion', 'activo', 'fecha_registro', 'id_usuario')
            ->where('id_paciente', $id)->first();
    }

    public function getPatientByAccount(int $userId): ?object
    {
        return DB::table('paciente')
            ->select('id_paciente', 'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo',
                'telefono', 'correo_contacto', 'direccion', 'activo', 'fecha_registro', 'id_usuario')
            ->where('id_usuario', $userId)
            ->first();
    }

    public function countActivePatients(): int
    {
        return (int) DB::table('paciente')->where('activo', true)->count();
    }

    public function getMedicalInformationByPatientId(int $patientId): ?object
    {
        return DB::table('informacion_medica')
            ->select(
                'id_paciente',
                'grupo_sanguineo',
                'situacion_alergias',
                'detalle_alergias',
                'situacion_enfermedades',
                'detalle_enfermedades',
                'situacion_medicamentos',
                'detalle_medicamentos',
                'antecedentes',
                'fecha_actualizacion',
            )
            ->where('id_paciente', $patientId)
            ->first();
    }

    /**
     * @param  array{grupo_sanguineo: ?string, situacion_alergias: ?string, detalle_alergias: ?string, situacion_enfermedades: ?string, detalle_enfermedades: ?string, situacion_medicamentos: ?string, detalle_medicamentos: ?string, antecedentes: ?string, fecha_actualizacion: string}  $values
     */
    public function upsertMedicalInformation(int $patientId, array $values): void
    {
        $existing = DB::table('informacion_medica')->where('id_paciente', $patientId)->exists();
        if ($existing) {
            DB::table('informacion_medica')->where('id_paciente', $patientId)->update($values);

            return;
        }

        DB::table('informacion_medica')->insert([
            'id_paciente' => $patientId,
            ...$values,
        ]);
    }

    public function userAccountTaken(int $userId, ?int $exceptId = null): bool
    {
        $query = DB::table('paciente')->where('id_usuario', $userId);
        if ($exceptId !== null) {
            $query->where('id_paciente', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function patientAccountIsLinkable(int $userId, ?int $exceptPatientId): bool
    {
        $account = DB::table('usuario')
            ->join('rol', 'rol.id_rol', '=', 'usuario.id_rol')
            ->select('usuario.id_usuario', 'usuario.activo', 'rol.nombre as rol_nombre')
            ->where('usuario.id_usuario', $userId)
            ->first();
        if ($account === null || ! $account->activo || (string) $account->rol_nombre !== PermissionCatalog::PATIENT_ROLE) {
            return false;
        }

        return ! $this->userAccountTaken($userId, $exceptPatientId);
    }

    /** @return Collection<int, object> */
    public function getLinkablePatientAccounts(?int $currentPatientId): Collection
    {
        $query = DB::table('usuario')
            ->join('rol', 'rol.id_rol', '=', 'usuario.id_rol')
            ->leftJoin('paciente', 'paciente.id_usuario', '=', 'usuario.id_usuario')
            ->select(
                'usuario.id_usuario',
                'usuario.nombre_usuario',
                'usuario.correo_acceso',
                'rol.nombre as rol_nombre',
            )
            ->where('usuario.activo', true)
            ->where('rol.nombre', PermissionCatalog::PATIENT_ROLE)
            ->where(function ($inner) use ($currentPatientId): void {
                $inner->whereNull('paciente.id_paciente');
                if ($currentPatientId !== null) {
                    $inner->orWhere('paciente.id_paciente', $currentPatientId);
                }
            })
            ->orderBy('usuario.nombre_usuario');

        return $query->get();
    }

    public function getLinkablePatientAccountById(int $userId, ?int $currentPatientId): ?object
    {
        $query = DB::table('usuario')
            ->join('rol', 'rol.id_rol', '=', 'usuario.id_rol')
            ->leftJoin('paciente', 'paciente.id_usuario', '=', 'usuario.id_usuario')
            ->select(
                'usuario.id_usuario',
                'usuario.nombre_usuario',
                'usuario.correo_acceso',
                'rol.nombre as rol_nombre',
            )
            ->where('usuario.id_usuario', $userId)
            ->where('usuario.activo', true)
            ->where('rol.nombre', PermissionCatalog::PATIENT_ROLE)
            ->where(function ($inner) use ($currentPatientId): void {
                $inner->whereNull('paciente.id_paciente');
                if ($currentPatientId !== null) {
                    $inner->orWhere('paciente.id_paciente', $currentPatientId);
                }
            });

        return $query->first();
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
     * @param  array{ci: ?string, nombres: string, apellidos: string, fecha_nacimiento: ?string, sexo: ?string, telefono: ?string, correo_contacto: ?string, direccion: ?string, activo: bool, fecha_registro: string, id_usuario: ?int}  $values
     */
    public function createPatient(array $values): int
    {
        return (int) DB::table('paciente')->insertGetId($values, 'id_paciente');
    }

    /**
     * @param  array{ci: ?string, nombres: string, apellidos: string, fecha_nacimiento: ?string, sexo: ?string, telefono: ?string, correo_contacto: ?string, direccion: ?string, id_usuario: ?int}  $values
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
