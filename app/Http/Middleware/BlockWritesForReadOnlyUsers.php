<?php

namespace App\Http\Middleware;

use App\Support\ReadOnlyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `writable`. A read-only person (see ReadOnlyAccess) gets 403 on every
 * request that is not a safe method. `writable:any` also refuses GET/HEAD, for
 * the few GET routes that start something (the social OAuth connect flow).
 */
class BlockWritesForReadOnlyUsers
{
    public function handle(Request $request, Closure $next, string $scope = 'writes'): Response
    {
        $mutates = $scope === 'any' || ! $request->isMethodSafe();

        abort_if($mutates && ReadOnlyAccess::applies($request->user()), 403, 'Your role is read-only.');

        return $next($request);
    }
}
