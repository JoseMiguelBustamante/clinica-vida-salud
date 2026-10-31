<?php

use App\Modules\Identity\PermissionCatalog;
use App\Modules\Identity\RequireActiveSession;
use App\Modules\Identity\RequirePermission;
use App\Modules\Patients\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::MANAGE_PATIENTS,
])->group(function (): void {
    Route::get('/pacientes', [PatientController::class, 'getAllPatients'])->name('patients.index');
    Route::get('/pacientes/registrar', [PatientController::class, 'showCreatePatientForm'])->name('patients.create');
    Route::post('/pacientes', [PatientController::class, 'createPatient'])->name('patients.store');
    Route::get('/pacientes/{paciente}', [PatientController::class, 'getPatientById'])->name('patients.show')
        ->whereNumber('paciente');
    Route::get('/pacientes/{paciente}/editar', [PatientController::class, 'showEditPatientForm'])->name('patients.edit')
        ->whereNumber('paciente');
    Route::post('/pacientes/{paciente}', [PatientController::class, 'updatePatient'])->name('patients.update')
        ->whereNumber('paciente');
    Route::post('/pacientes/{paciente}/desactivar', [PatientController::class, 'deactivatePatient'])->name('patients.deactivate')
        ->whereNumber('paciente');
    Route::post('/pacientes/{paciente}/activar', [PatientController::class, 'activatePatient'])->name('patients.activate')
        ->whereNumber('paciente');
});
