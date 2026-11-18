<?php

namespace App\Http\Middleware;

use App\Support\CentralSignIn;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With InfoDot as the only front door, a password, reset or passkey sign-in here must not become a second one.
 *
 * "Only front door" means either the older ecosystem lock (ECOSYSTEM_SSO_ONLY) or the R-54 central sign-in
 * (DOTSSO_ENABLED, unless the break-glass DOTSSO_ALLOW_LOCAL_LOGIN is set). R-54 closes the passkey gap: a passkey
 * sign-in (`passkey.login`, and the `passkey.login-options` request that starts it) authenticates a session just as a
 * password does, and used to be missing from this list. Enrolling or removing a passkey while signed in is not a
 * sign-in and is left alone.
 */
class RefuseLocalSignIn
{
    /** Every route that can start or complete a local sign-in. */
    public const SIGN_IN_ROUTES = [
        'login.store',
        'password.email',
        'password.update',
        'two-factor.login.store',
        'passkey.login',
        'passkey.login-options',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $locked = config('ecosystem.sso_only') || CentralSignIn::active();

        // Passkey options are a GET (a "safe" method) that still hands out a sign-in challenge, so it is refused too.
        abort_if($locked && $request->routeIs(...self::SIGN_IN_ROUTES) && (! $request->isMethodSafe() || $request->routeIs('passkey.login-options')), 404);

        return $next($request);
    }
}
