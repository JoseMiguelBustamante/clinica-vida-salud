<?php

use App\Modules\Identity\AuthenticationController;
use App\Modules\Identity\Http\Controllers\IdentityAccountController;
use App\Modules\Identity\Http\Controllers\IdentitySessionController;
use App\Modules\Identity\Http\Controllers\PatientPortalController;
use App\Modules\Identity\PermissionCatalog;
use App\Modules\Identity\RequireActiveSession;
use App\Modules\Identity\RequirePermission;
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
    Route::get('/registro', [PatientPortalController::class, 'showPatientAccountForm'])->name('portal.register');
    Route::post('/registro', [PatientPortalController::class, 'registerPatientAccount'])->name('portal.register.store');
});

Route::middleware(['web', RequireActiveSession::class])->group(function (): void {
    Route::get('/panel', [IdentitySessionController::class, 'showIdentityPanel'])->name('panel');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::UPDATE_PROFILE,
])->group(function (): void {
    Route::get('/perfil', [PatientPortalController::class, 'showPatientProfile'])->name('portal.profile');
    Route::post('/perfil', [PatientPortalController::class, 'updatePatientProfile'])->name('portal.profile.update');
    Route::post('/perfil/verificar-correo', [PatientPortalController::class, 'verifyPatientEmail'])->name('portal.profile.verify');
});

Route::middleware([
    'web',
    RequireActiveSession::class,
    RequirePermission::class.':'.PermissionCatalog::MANAGE_ACCOUNTS,
])->group(function (): void {
    Route::get('/usuarios', [IdentityAccountController::class, 'getAllAccounts'])->name('accounts.index');
    Route::get('/usuarios/registrar', [IdentityAccountController::class, 'showCreateAccountForm'])->name('accounts.create');
    Route::post('/usuarios', [IdentityAccountController::class, 'createAccount'])->name('accounts.store');
    Route::get('/usuarios/{usuario}', [IdentityAccountController::class, 'getAccountById'])->name('accounts.show')
        ->whereNumber('usuario');
    Route::get('/usuarios/{usuario}/editar', [IdentityAccountController::class, 'showEditAccountForm'])->name('accounts.edit')
        ->whereNumber('usuario');
    Route::post('/usuarios/{usuario}', [IdentityAccountController::class, 'updateAccount'])->name('accounts.update')
        ->whereNumber('usuario');
    Route::post('/usuarios/{usuario}/desactivar', [IdentityAccountController::class, 'deactivateAccount'])->name('accounts.deactivate')
        ->whereNumber('usuario');
    Route::post('/usuarios/{usuario}/activar', [IdentityAccountController::class, 'activateAccount'])->name('accounts.activate')
        ->whereNumber('usuario');
});
