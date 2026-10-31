<?php

namespace App\Modules\Identity;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSession
{
    public function __construct(private IdentityRepository $identities) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        // Read the session identifier, never a guard user cached by an earlier request.
        $guard->forgetUser();
        $id = $request->session()->get($guard->getName());
        $user = is_numeric($id) ? $this->identities->active((int) $id) : null;
        if (! $user) {
            if ($id !== null) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->is('auth/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        $guard->setUser($user);
        $request->attributes->set('identity', $user);

        return $next($request);
    }
}
