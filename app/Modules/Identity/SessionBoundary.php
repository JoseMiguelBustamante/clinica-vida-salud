<?php

namespace App\Modules\Identity;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SessionBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = app('session')->driver();
        $handler = $session->getHandler();
        $session->setHandler(new CheckedSessionHandler($handler));
        try {
            $response = $next($request);
        } catch (Throwable) {
            $response = response()->json(['message' => $request->is('auth/logout')
                ? 'Unable to complete sign out. Please try again.'
                : 'Unable to complete authentication. Please try again.'], 503);
        } finally {
            $session->setHandler($handler);
        }
        // Laravel's routing pipeline may render a storage exception before it reaches this boundary.
        if ($response->getStatusCode() >= 500) {
            Auth::guard('web')->forgetUser();
            $session->flush();
            $response->headers->remove('Set-Cookie');
        }
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
