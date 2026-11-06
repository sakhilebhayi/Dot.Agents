<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** With InfoDot as the only front door, a password or reset post here must not become a second one. */
class RefuseLocalSignIn
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(config('ecosystem.sso_only') && ! $request->isMethodSafe() && $request->routeIs('login.store', 'password.email', 'password.update', 'two-factor.login.store'), 404);

        return $next($request);
    }
}
