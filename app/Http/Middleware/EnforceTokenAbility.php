<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** An API key may only do what it was given. Reads, changes, runs and approvals are separate abilities. */
class EnforceTokenAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            abort_unless($token->can($this->needed($request)), 403, 'This API key does not allow that.');
        }

        return $next($request);
    }

    private function needed(Request $request): string
    {
        return match (true) {
            $request->isMethodSafe() => 'read',
            $request->routeIs('api.v1.skill-approvals.*') => 'approve',
            $request->routeIs('api.v1.deployments.skills.execute') => 'run',
            default => 'write',
        };
    }
}
