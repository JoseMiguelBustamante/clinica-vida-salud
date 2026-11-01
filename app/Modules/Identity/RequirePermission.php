<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function __construct(private IdentityRepository $identities) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->attributes->get('identity');
        $codes = $this->identities->permissionCodes($user);
        $request->attributes->set('identity_permissions', $codes);
        view()->share('identityPermissions', $codes);

        foreach ($permissions as $permission) {
            if (in_array($permission, $codes, true)) {
                return $next($request);
            }
        }

        return response()->view('identity.denied', [
            'message' => 'No tiene permiso para realizar esta operación.',
        ], 403);
    }
}
