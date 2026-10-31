<?php

use App\Modules\Identity\PermissionCatalog;
use App\Modules\Identity\RequireActiveSession;
use App\Modules\Identity\RequirePermission;
use App\Modules\Schedules\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::MANAGE_SCHEDULES,
])->group(function (): void {
    Route::get('/horarios', [ScheduleController::class, 'getAllSchedules'])->name('schedules.index');
    Route::get('/horarios/registrar', [ScheduleController::class, 'showCreateScheduleForm'])->name('schedules.create');
    Route::post('/horarios', [ScheduleController::class, 'createSchedule'])->name('schedules.store');
    Route::get('/horarios/{horario}', [ScheduleController::class, 'getScheduleById'])->name('schedules.show')
        ->whereNumber('horario');
    Route::get('/horarios/{horario}/editar', [ScheduleController::class, 'showEditScheduleForm'])->name('schedules.edit')
        ->whereNumber('horario');
    Route::post('/horarios/{horario}', [ScheduleController::class, 'updateSchedule'])->name('schedules.update')
        ->whereNumber('horario');
    Route::post('/horarios/{horario}/desactivar', [ScheduleController::class, 'deactivateSchedule'])->name('schedules.deactivate')
        ->whereNumber('horario');
    Route::post('/horarios/{horario}/activar', [ScheduleController::class, 'activateSchedule'])->name('schedules.activate')
        ->whereNumber('horario');
});
