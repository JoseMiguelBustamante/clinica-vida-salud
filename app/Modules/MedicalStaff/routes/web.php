<?php

use App\Modules\Identity\PermissionCatalog;
use App\Modules\Identity\RequireActiveSession;
use App\Modules\Identity\RequirePermission;
use App\Modules\MedicalStaff\Http\Controllers\PhysicianController;
use App\Modules\MedicalStaff\Http\Controllers\SpecialtyController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::MANAGE_SPECIALTIES,
])->group(function (): void {
    Route::get('/especialidades', [SpecialtyController::class, 'getAllSpecialties'])->name('specialties.index');
    Route::get('/especialidades/registrar', [SpecialtyController::class, 'showCreateSpecialtyForm'])->name('specialties.create');
    Route::post('/especialidades', [SpecialtyController::class, 'createSpecialty'])->name('specialties.store');
    Route::get('/especialidades/{especialidad}', [SpecialtyController::class, 'getSpecialtyById'])->name('specialties.show')
        ->whereNumber('especialidad');
    Route::get('/especialidades/{especialidad}/editar', [SpecialtyController::class, 'showEditSpecialtyForm'])->name('specialties.edit')
        ->whereNumber('especialidad');
    Route::post('/especialidades/{especialidad}', [SpecialtyController::class, 'updateSpecialty'])->name('specialties.update')
        ->whereNumber('especialidad');
    Route::post('/especialidades/{especialidad}/desactivar', [SpecialtyController::class, 'deactivateSpecialty'])->name('specialties.deactivate')
        ->whereNumber('especialidad');
    Route::post('/especialidades/{especialidad}/activar', [SpecialtyController::class, 'activateSpecialty'])->name('specialties.activate')
        ->whereNumber('especialidad');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::MANAGE_PHYSICIANS,
])->group(function (): void {
    Route::get('/medicos', [PhysicianController::class, 'getAllPhysicians'])->name('physicians.index');
    Route::get('/medicos/registrar', [PhysicianController::class, 'showCreatePhysicianForm'])->name('physicians.create');
    Route::post('/medicos', [PhysicianController::class, 'createPhysician'])->name('physicians.store');
    Route::get('/medicos/{medico}', [PhysicianController::class, 'getPhysicianById'])->name('physicians.show')
        ->whereNumber('medico');
    Route::get('/medicos/{medico}/editar', [PhysicianController::class, 'showEditPhysicianForm'])->name('physicians.edit')
        ->whereNumber('medico');
    Route::post('/medicos/{medico}', [PhysicianController::class, 'updatePhysician'])->name('physicians.update')
        ->whereNumber('medico');
    Route::post('/medicos/{medico}/desactivar', [PhysicianController::class, 'deactivatePhysician'])->name('physicians.deactivate')
        ->whereNumber('medico');
    Route::post('/medicos/{medico}/activar', [PhysicianController::class, 'activatePhysician'])->name('physicians.activate')
        ->whereNumber('medico');
});
