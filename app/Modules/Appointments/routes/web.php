<?php

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
