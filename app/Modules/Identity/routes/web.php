<?php

use App\Modules\Identity\AuthenticationController;
use App\Modules\Identity\RequireActiveSession;
use App\Modules\Identity\SessionBoundary;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware([SessionBoundary::class, 'web'])->group(function (): void {
    Route::get('csrf', [AuthenticationController::class, 'csrf']);
    Route::post('login', [AuthenticationController::class, 'login']);
    Route::middleware(RequireActiveSession::class)->group(function (): void {
        Route::get('session', [AuthenticationController::class, 'session']);
        Route::post('logout', [AuthenticationController::class, 'logout']);
    });
});
