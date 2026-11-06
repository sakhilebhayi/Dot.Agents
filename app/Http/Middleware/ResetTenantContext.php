<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A request starts with no business in context. OrganizationContextMiddleware sets one for a signed-in person. */
class ResetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $before = TenantContext::snapshot();
        $lenient = TenantContext::lenient(false);
        TenantContext::set(null);

        try {
            return $next($request);
        } finally {
            TenantContext::restore($before);
            TenantContext::lenient($lenient);
        }
    }
}
