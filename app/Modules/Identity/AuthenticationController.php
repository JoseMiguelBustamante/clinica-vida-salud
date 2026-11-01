<?php

namespace App\Modules\Identity;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationController
{
    public function __construct(
        private IdentitySessionService $sessions,
        private ActivityLogService $activity,
    ) {}

    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => $request->session()->token()]);
    }

    public function login(Request $request): JsonResponse
    {
        $input = $request->validate(['identifier' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);

        try {
            $user = $this->sessions->authenticate($input['identifier'], $input['password'], (string) $request->ip());
            $representation = $this->sessions->representation($user);
        } catch (TooManyLoginAttempts $exception) {
            return response()->json(['message' => 'Too many login attempts. Try again later.'], 429,
                ['Retry-After' => (string) $exception->retryAfter]);
        } catch (AuthenticationRejected) {
            $this->activity->recordFailedLogin($input['identifier'], (string) $request->ip());

            return response()->json(['message' => 'Invalid credentials or account unavailable.'], 401);
        }

        $this->sessions->forgetLoginAttempts($input['identifier'], (string) $request->ip());
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($user);
        $this->activity->recordLogin($representation, (string) $request->ip());

        return response()->json(['user' => $representation, 'csrf_token' => $request->session()->token()]);
    }

    public function session(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->sessions->representation($request->attributes->get('identity')),
            'csrf_token' => $request->session()->token()]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->attributes->get('identity');
        if (is_object($user)) {
            $this->activity->record(
                'Cierre de sesión',
                'Acceso',
                'Sesión cerrada',
                null,
                null,
                isset($user->id_usuario) ? (int) $user->id_usuario : null,
                isset($user->nombre_usuario) ? (string) $user->nombre_usuario : null,
                null,
                (string) $request->ip(),
            );
        }

        // No remember-me cookies are issued by this backend.
        Auth::guard('web')->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
