<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Controllers;

use App\Modules\Appointments\Http\Requests\SearchAdministrativeReportsRequest;
use App\Modules\Appointments\InvalidReportInput;
use App\Modules\Appointments\ReportCriterionInvalid;
use App\Modules\Appointments\ReportService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class ReportController
{
    public function __construct(private ReportService $reports) {}

    public function generateAdministrativeReport(SearchAdministrativeReportsRequest $request): View
    {
        $input = $request->validated();
        $generate = ($input['generar'] ?? null) === '1';
        $status = null;
        $result = [
            'type' => $input['tipo'] ?? '',
            'generated' => false,
            'rows' => null,
            'groups' => null,
            'totals' => null,
            'physicians' => collect(),
            'specialties' => collect(),
            'fecha' => $input['fecha'] ?? '',
            'fecha_inicio' => $input['fecha_inicio'] ?? '',
            'fecha_fin' => $input['fecha_fin'] ?? '',
            'id_medico' => isset($input['id_medico']) ? (int) $input['id_medico'] : null,
            'id_especialidad' => isset($input['id_especialidad']) ? (int) $input['id_especialidad'] : null,
            'estado' => $input['estado'] ?? '',
        ];
        try {
            $result = $this->reports->generateAdministrativeReport([
                'tipo' => $input['tipo'] ?? null,
                'fecha' => $input['fecha'] ?? null,
                'fecha_inicio' => $input['fecha_inicio'] ?? null,
                'fecha_fin' => $input['fecha_fin'] ?? null,
                'id_medico' => isset($input['id_medico']) ? (int) $input['id_medico'] : null,
                'id_especialidad' => isset($input['id_especialidad']) ? (int) $input['id_especialidad'] : null,
                'estado' => $input['estado'] ?? null,
            ], $generate);
        } catch (InvalidReportInput) {
            $status = 'Faltan filtros obligatorios o los valores ingresados no son válidos.';
        } catch (ReportCriterionInvalid) {
            $status = 'El médico, la especialidad o el estado seleccionado no es válido para este reporte.';
        } catch (Throwable $exception) {
            Log::error('[ReportController - generateAdministrativeReport] '.$exception::class);
            $status = 'No fue posible generar el reporte.';
        }

        return view('appointments.reports', [
            'types' => ReportService::types(),
            'type' => $result['type'],
            'generated' => $result['generated'] && $status === null,
            'rows' => $result['rows'],
            'groups' => $result['groups'],
            'totals' => $status === null ? $result['totals'] : null,
            'physicians' => $result['physicians'],
            'specialties' => $result['specialties'],
            'fecha' => $result['fecha'],
            'fecha_inicio' => $result['fecha_inicio'],
            'fecha_fin' => $result['fecha_fin'],
            'id_medico' => $result['id_medico'],
            'id_especialidad' => $result['id_especialidad'],
            'estado' => $result['estado'],
            'status' => $status,
        ]);
    }
}
