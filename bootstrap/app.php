<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Remove stateful middleware for pure API usage
        // $middleware->api(prepend: [
        //     EnsureFrontendRequestsAreStateful::class,
        // ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/';
        });

        // Exclude API routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'sanctum/csrf-cookie',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'audit' => \App\Http\Middleware\AuditMiddleware::class,
            'security' => \App\Http\Middleware\SecurityMiddleware::class,
            'impersonation' => \App\Http\Middleware\ImpersonationMiddleware::class,
            'claims.role' => \App\Http\Middleware\ClaimsRoleMiddleware::class,
            'enrollee' => \App\Http\Middleware\EnsureEnrollee::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'errors' => [],
                ], 401);
            }

            throw $exception;
        });
    })->create();
