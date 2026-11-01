<?php

use App\Modules\Appointments\Http\Controllers\AppointmentController;
use App\Modules\Appointments\Http\Controllers\AvailabilityController;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\Identity\RequireActiveSession;
use App\Modules\Identity\RequirePermission;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::view('/', 'home')->name('home');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::CONSULT_AVAILABILITY,
])->group(function (): void {
    Route::get('/disponibilidad', [AvailabilityController::class, 'consultAvailability'])->name('availability.consult');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::BOOK_APPOINTMENTS,
])->group(function (): void {
    Route::get('/citas/programar', [AppointmentController::class, 'showCreateAppointmentForm'])->name('appointments.create');
    Route::post('/citas', [AppointmentController::class, 'createAppointment'])->name('appointments.store');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::BOOK_APPOINTMENTS.','.PermissionCatalog::MARK_ATTENDED,
])->group(function (): void {
    Route::get('/citas/{cita}', [AppointmentController::class, 'getAppointmentById'])->name('appointments.show')->whereNumber('cita');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::RESCHEDULE_APPOINTMENTS,
])->group(function (): void {
    Route::get('/citas', [AppointmentController::class, 'getAllAppointments'])->name('appointments.index');
    Route::get('/citas/{cita}/reprogramar', [AppointmentController::class, 'showRescheduleAppointmentForm'])
        ->name('appointments.reschedule')->whereNumber('cita');
    Route::post('/citas/{cita}/reprogramar', [AppointmentController::class, 'rescheduleAppointment'])
        ->name('appointments.reschedule.store')->whereNumber('cita');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::CANCEL_APPOINTMENTS,
])->group(function (): void {
    Route::get('/citas/{cita}/cancelar', [AppointmentController::class, 'showCancelAppointmentForm'])
        ->name('appointments.cancel')->whereNumber('cita');
    Route::post('/citas/{cita}/cancelar', [AppointmentController::class, 'cancelAppointment'])
        ->name('appointments.cancel.store')->whereNumber('cita');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::RECORD_ATTENDANCE,
])->group(function (): void {
    Route::get('/citas/{cita}/asistencia', [AppointmentController::class, 'showAttendanceForm'])
        ->name('appointments.attendance')->whereNumber('cita');
    Route::post('/citas/{cita}/confirmar', [AppointmentController::class, 'confirmAppointment'])
        ->name('appointments.confirm')->whereNumber('cita');
    Route::post('/citas/{cita}/llegada', [AppointmentController::class, 'registerAppointmentArrival'])
        ->name('appointments.arrival')->whereNumber('cita');
    Route::post('/citas/{cita}/inasistencia', [AppointmentController::class, 'registerAppointmentAbsence'])
        ->name('appointments.absence')->whereNumber('cita');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::CONSULT_AGENDA.','.PermissionCatalog::MARK_ATTENDED,
])->group(function (): void {
    Route::get('/agenda', [AppointmentController::class, 'getPhysicianAgenda'])->name('appointments.agenda');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::MARK_ATTENDED,
])->group(function (): void {
    Route::get('/citas/{cita}/atender', [AppointmentController::class, 'showAttendAppointmentForm'])
        ->name('appointments.attend')->whereNumber('cita');
    Route::post('/citas/{cita}/atender', [AppointmentController::class, 'recordAppointmentCare'])
        ->name('appointments.attend.store')->whereNumber('cita');
});
