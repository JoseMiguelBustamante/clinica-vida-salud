<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\ActivityLogService;
use App\Modules\Identity\AuthenticationRejected;
use App\Modules\Identity\Http\Requests\CreateSessionRequest;
use App\Modules\Identity\IdentitySessionService;
use App\Modules\Identity\TooManyLoginAttempts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class IdentitySessionController
{
    public function __construct(
        private IdentitySessionService $sessions,
        private ActivityLogService $activity,
    ) {}

    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if ($request->session()->has(Auth::guard('web')->getName())) {
            return redirect()->route('panel');
        }

        return view('identity.login');
    }

    public function createIdentitySession(CreateSessionRequest $request): RedirectResponse
    {
        $input = $request->validated();

        try {
            $user = $this->sessions->authenticate($input['identifier'], $input['password'], (string) $request->ip());
            $representation = $this->sessions->representation($user);
        } catch (TooManyLoginAttempts) {
            return back()->withInput($request->except('password'))
                ->withErrors(['identifier' => 'Demasiados intentos. Intente más tarde.']);
        } catch (AuthenticationRejected) {
            $this->activity->recordFailedLogin($input['identifier'], (string) $request->ip());

            return back()->withInput($request->except('password'))
                ->withErrors(['identifier' => 'Credenciales incorrectas o cuenta no habilitada.']);
        } catch (Throwable $exception) {
            Log::error('[IdentitySessionController - createIdentitySession] '.$exception::class);

            return back()->withInput($request->except('password'))
                ->withErrors(['identifier' => 'No fue posible completar el acceso.']);
        }

        $this->sessions->forgetLoginAttempts($input['identifier'], (string) $request->ip());
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($user);
        $this->activity->recordLogin($representation, (string) $request->ip());

        return redirect()->route('panel');
    }

    public function destroyIdentitySession(Request $request): RedirectResponse
    {
        $guard = Auth::guard('web');
        $sessionId = $request->session()->get($guard->getName());
        $this->activity->record(
            'Cierre de sesión',
            'Acceso',
            'Sesión cerrada',
            null,
            null,
            is_numeric($sessionId) ? (int) $sessionId : null,
            null,
            null,
            (string) $request->ip(),
        );
        $guard->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showIdentityPanel(Request $request): View
    {
        $user = $this->sessions->representation($request->attributes->get('identity'));
        view()->share('identityPermissions', $user['permissions']);

        return view('identity.panel', ['user' => $user]);
    }
}
