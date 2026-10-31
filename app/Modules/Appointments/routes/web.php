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
    Route::get('/citas/{cita}', [AppointmentController::class, 'getAppointmentById'])->name('appointments.show')->whereNumber('cita');
});
