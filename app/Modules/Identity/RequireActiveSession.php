<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequireActiveSession
{
    public function __construct(
        private IdentityRepository $identities,
        private PermissionCatalogService $catalog,
        private Application $app,
    ) {}

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
        $this->ensurePermissionCatalog();

        return $next($request);
    }

    private function ensurePermissionCatalog(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        try {
            $this->catalog->ensureCurrent();
        } catch (Throwable $exception) {
            Log::error('[RequireActiveSession - ensurePermissionCatalog] '.$exception::class);
        }
    }
}
