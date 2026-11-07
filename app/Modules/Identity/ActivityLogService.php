<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ActivityLogService
{
    public const ACTIONS = [
        'Inicio de sesión',
        'Cierre de sesión',
        'Intento fallido',
        'Creación',
        'Actualización',
        'Reprogramación',
        'Cancelación',
        'Desactivación',
        'Activación',
        'Asistencia',
        'Atención',
    ];

    public const PER_PAGE = 8;

    public const DEFAULT_RANGE_DAYS = 7;

    public function __construct(private ActivityLogRepository $logs) {}

    /**
     * @param  list<array{campo: string, valor_anterior: ?string, valor_nuevo: ?string}>|null  $changes
     */
    public function record(
        string $action,
        string $module,
        string $detail,
        ?int $recordId = null,
        ?array $changes = null,
        ?int $actorId = null,
        ?string $actorName = null,
        ?string $actorRole = null,
        ?string $ip = null,
    ): void {
        $actor = $this->actorSnapshot($actorId, $actorName, $actorRole);
        $encodedChanges = null;
        if ($changes !== null && $changes !== []) {
            $encodedChanges = json_encode($changes, JSON_UNESCAPED_UNICODE);
            if ($encodedChanges === false) {
                $encodedChanges = null;
            }
        }

        try {
            $this->logs->createActivityLog([
                'fecha_hora' => Carbon::now('UTC')->toIso8601String(),
                'accion' => $action,
                'modulo' => $module,
                'detalle' => $detail,
                'direccion_ip' => $ip ?? (request()->ip() ?: null),
                'actor_nombre' => $actor['name'],
                'actor_rol' => $actor['role'],
                'id_usuario' => $actor['id'],
                'id_registro' => $recordId,
                'cambios' => $encodedChanges,
            ]);
        } catch (Throwable $exception) {
            Log::error('[ActivityLogService - record] '.$exception::class);
        }
    }

    public function recordLogin(array $representation, ?string $ip = null): void
    {
        $this->record(
            'Inicio de sesión',
            'Acceso',
            'Acceso correcto',
            null,
            null,
            isset($representation['id']) ? (int) $representation['id'] : null,
            isset($representation['username']) ? (string) $representation['username'] : null,
            isset($representation['role']['name']) ? (string) $representation['role']['name'] : null,
            $ip,
        );
    }

    public function recordLogout(array $representation, ?string $ip = null): void
    {
        $this->record(
            'Cierre de sesión',
            'Acceso',
            'Sesión cerrada',
            null,
            null,
            isset($representation['id']) ? (int) $representation['id'] : null,
            isset($representation['username']) ? (string) $representation['username'] : null,
            isset($representation['role']['name']) ? (string) $representation['role']['name'] : null,
            $ip,
        );
    }

    public function recordFailedLogin(string $identifier, ?string $ip = null): void
    {
        $this->record(
            'Intento fallido',
            'Acceso',
            'Credenciales incorrectas',
            null,
            null,
            null,
            $identifier,
            null,
            $ip,
        );
    }

    /**
     * @return array{entries: Collection<int, object>, total: int, page: int, pages: int, perPage: int, from: ?string, to: ?string}
     */
    public function getActivityLogs(
        ?string $fragment,
        ?int $userId,
        ?string $action,
        ?string $from,
        ?string $to,
        int $page,
    ): array {
        $perPage = self::PER_PAGE;
        $page = max(1, $page);
        $fragment = $fragment === '' ? null : $fragment;
        [$from, $to] = $this->period($from, $to);
        $total = $this->logs->countActivityLogs($fragment, $userId, $action, $from, $to);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $entries = $this->logs->getActivityLogs(
            $fragment,
            $userId,
            $action,
            $from,
            $to,
            $perPage,
            ($page - 1) * $perPage,
        )->map(fn (object $row): object => $this->present($row));

        return [
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'pages' => $total === 0 ? 1 : $pages,
            'perPage' => $perPage,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function period(?string $from, ?string $to): array
    {
        $from = $from === '' ? null : $from;
        $to = $to === '' ? null : $to;
        if ($from !== null || $to !== null) {
            return [$from, $to];
        }

        $today = Carbon::now('UTC')->startOfDay();

        return [
            $today->copy()->subDays(self::DEFAULT_RANGE_DAYS - 1)->toDateString(),
            $today->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $labels
     * @return list<array{campo: string, valor_anterior: ?string, valor_nuevo: ?string}>
     */
    public function fieldChanges(array $before, array $after, array $labels): array
    {
        $changes = [];
        foreach ($labels as $key => $label) {
            $previous = $this->printable($before[$key] ?? null);
            $next = $this->printable($after[$key] ?? null);
            if ($previous === $next) {
                continue;
            }

            $changes[] = [
                'campo' => $label,
                'valor_anterior' => $previous,
                'valor_nuevo' => $next,
            ];
        }

        return $changes;
    }

    /**
     * @return array{id: ?int, name: ?string, role: ?string}
     */
    private function actorSnapshot(?int $actorId, ?string $actorName, ?string $actorRole): array
    {
        if ($actorId === null) {
            $user = Auth::guard('web')->user();
            if ($user instanceof User) {
                $actorId = (int) $user->getAuthIdentifier();
                $actorName ??= is_string($user->nombre_usuario ?? null) ? $user->nombre_usuario : null;
            }
        }

        return [
            'id' => $actorId,
            'name' => $actorName,
            'role' => $actorRole,
        ];
    }

    private function present(object $row): object
    {
        $changes = [];
        if (is_string($row->cambios) && $row->cambios !== '') {
            $decoded = json_decode($row->cambios, true);
            if (is_array($decoded)) {
                foreach ($decoded as $change) {
                    if (! is_array($change) || ! isset($change['campo'])) {
                        continue;
                    }
                    $changes[] = [
                        'campo' => (string) $change['campo'],
                        'valor_anterior' => isset($change['valor_anterior']) ? (string) $change['valor_anterior'] : null,
                        'valor_nuevo' => isset($change['valor_nuevo']) ? (string) $change['valor_nuevo'] : null,
                    ];
                }
            }
        }

        $action = (string) $row->accion;

        return (object) [
            'id_bitacora' => $row->id_bitacora,
            'fecha_hora' => $row->fecha_hora,
            'accion' => $action,
            'modulo' => $row->modulo,
            'detalle' => $row->detalle,
            'direccion_ip' => $row->direccion_ip,
            'actor_nombre' => $row->actor_nombre,
            'actor_rol' => $row->actor_rol,
            'id_usuario' => $row->id_usuario,
            'id_registro' => $row->id_registro,
            'changes' => $changes,
            'statusClass' => match ($action) {
                'Inicio de sesión', 'Reprogramación', 'Asistencia' => 'is-programada',
                'Creación', 'Actualización', 'Activación', 'Atención' => 'is-confirmada',
                'Intento fallido', 'Cancelación', 'Desactivación' => 'is-failed',
                default => 'is-inactive',
            },
        ];
    }

    private function printable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
