<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlation ID Middleware
 *
 * Reads the incoming X-Correlation-ID request header (or generates a new UUID
 * if absent), stores it on the request, propagates it to every log entry via
 * Log::withContext(), and echoes it back in the response header.
 *
 * This enables distributed tracing: a single request ID can be followed across
 * web servers, queue workers, and log aggregation tools (e.g. CloudWatch, Loki).
 */
class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // A caller's id goes into logs and the response, so only an id that looks like one is kept.
        $supplied = $request->header('X-Correlation-ID') ?? $request->header('X-Request-ID');
        $correlationId = is_string($supplied) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $supplied) ? $supplied : (string) Str::uuid();

        // Attach to request so controllers/services can read it via request()
        $request->headers->set('X-Correlation-ID', $correlationId);

        // Propagate to all subsequent log entries for this request lifecycle
        Log::withContext([
            'correlation_id' => $correlationId,
            'url' => $request->url(),
            'method' => $request->method(),
        ]);

        /** @var Response $response */
        $response = $next($request);

        // Echo the correlation ID in the response so clients can correlate
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
