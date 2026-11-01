<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivityLogRepository
{
    /**
     * @param  array{fecha_hora: string, accion: string, modulo: string, detalle: string, direccion_ip: ?string, actor_nombre: ?string, actor_rol: ?string, id_usuario: ?int, id_registro: ?int, cambios: ?string}  $values
     */
    public function createActivityLog(array $values): void
    {
        DB::table('bitacora')->insert($values);
    }

    /**
     * @return Collection<int, object>
     */
    public function getActivityLogs(
        ?string $fragment,
        ?int $userId,
        ?string $action,
        ?string $from,
        ?string $to,
        int $limit,
        int $offset,
    ): Collection {
        return $this->filteredQuery($fragment, $userId, $action, $from, $to)
            ->orderByDesc('bitacora.fecha_hora')
            ->orderByDesc('bitacora.id_bitacora')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public function countActivityLogs(
        ?string $fragment,
        ?int $userId,
        ?string $action,
        ?string $from,
        ?string $to,
    ): int {
        return $this->filteredQuery($fragment, $userId, $action, $from, $to)->count();
    }

    private function filteredQuery(
        ?string $fragment,
        ?int $userId,
        ?string $action,
        ?string $from,
        ?string $to,
    ): Builder {
        $query = DB::table('bitacora')->select(
            'bitacora.id_bitacora',
            'bitacora.fecha_hora',
            'bitacora.accion',
            'bitacora.modulo',
            'bitacora.detalle',
            'bitacora.direccion_ip',
            'bitacora.actor_nombre',
            'bitacora.actor_rol',
            'bitacora.id_usuario',
            'bitacora.id_registro',
            'bitacora.cambios',
        );

        if ($fragment !== null && $fragment !== '') {
            $like = '%'.addcslashes($fragment, '%_\\').'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('bitacora.actor_nombre', 'ilike', $like)
                    ->orWhere('bitacora.modulo', 'ilike', $like)
                    ->orWhere('bitacora.detalle', 'ilike', $like);
            });
        }

        if ($userId !== null) {
            $query->where('bitacora.id_usuario', $userId);
        }

        if ($action !== null && $action !== '') {
            $query->where('bitacora.accion', $action);
        }

        if ($from !== null && $from !== '') {
            $query->where('bitacora.fecha_hora', '>=', Carbon::parse($from, 'UTC')->startOfDay()->toIso8601String());
        }

        if ($to !== null && $to !== '') {
            $query->where('bitacora.fecha_hora', '<', Carbon::parse($to, 'UTC')->addDay()->startOfDay()->toIso8601String());
        }

        return $query;
    }
}
