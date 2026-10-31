<?php

use App\Modules\Identity\AuthenticationController;
use App\Modules\Identity\Http\Controllers\IdentitySessionController;
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

Route::middleware('web')->group(function (): void {
    Route::get('/login', [IdentitySessionController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [IdentitySessionController::class, 'createIdentitySession'])->name('login.attempt');
    Route::post('/logout', [IdentitySessionController::class, 'destroyIdentitySession'])->name('logout');
});

Route::middleware(['web', RequireActiveSession::class])->group(function (): void {
    Route::get('/panel', [IdentitySessionController::class, 'showIdentityPanel'])->name('panel');
});
