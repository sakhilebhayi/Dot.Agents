<?php

use App\Http\Middleware\ConsentRequiredMiddleware;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\OrganizationContextMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\Guardian\ErrorCounter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Correlation ID on every request — must be first for log context
        $middleware->prepend(CorrelationIdMiddleware::class);

        // Security headers on every response
        $middleware->append(SecurityHeadersMiddleware::class);

        // CORS handling for API routes
        $middleware->api(prepend: [HandleCors::class]);

        // CSRF verification with Stripe webhook exemption
        $middleware->web(replace: [
            Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => VerifyCsrfToken::class,
        ]);

        // Custom aliases
        $middleware->alias([
            'org.context' => OrganizationContextMiddleware::class,
            'consent.required' => ConsentRequiredMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Feeds the guardian's hourly error counter (ErrorRateCheck). The
        // counter swallows its own failures, so this cannot break reporting.
        $exceptions->report(function (Throwable $e): void {
            app(ErrorCounter::class)->record($e);
        });
    })->create();
