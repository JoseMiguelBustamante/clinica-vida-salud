<?php

namespace App\Modules\Identity;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationController
{
    public function __construct(private IdentityRepository $identities) {}

    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => $request->session()->token()]);
    }

    public function login(Request $request): JsonResponse
    {
        $input = $request->validate(['identifier' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);
        $failure = ['message' => 'Invalid credentials or account unavailable.'];
        if (strlen($input['password']) > 72) {
            return response()->json($failure, 401);
        }

        $key = 'identity:'.hash('sha256', $input['identifier'].$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many login attempts. Try again later.'], 429,
                ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }

        $matches = $this->identities->matching($input['identifier']);
        $user = $matches->count() === 1 ? $matches->first() : null;
        if (! $user || ! $user->activo || ! Hash::check($input['password'], $user->getAuthPassword())) {
            RateLimiter::hit($key, 60);

            return response()->json($failure, 401);
        }

        // Resolve the existing grants before establishing authentication.
        $representation = $this->identities->representation($user);
        RateLimiter::clear($key);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($user);

        return response()->json(['user' => $representation, 'csrf_token' => $request->session()->token()]);
    }

    public function session(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->identities->representation($request->attributes->get('identity')),
            'csrf_token' => $request->session()->token()]);
    }

    public function logout(Request $request): Response
    {
        // No remember-me cookies are issued by this backend.
        Auth::guard('web')->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
