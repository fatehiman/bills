<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\ApiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/mainadmin');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->alias([
            'admin' => AdminOnly::class,
            'api.token' => ApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One error format for the whole API: {"ok": false, "error": {"code", "message", "details"}}
        $apiError = fn (string $code, string $message, int $status, ?array $details = null) => response()->json(
            ['ok' => false, 'error' => array_filter(['code' => $code, 'message' => $message, 'details' => $details])],
            $status
        );
        $exceptions->render(function (ValidationException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('validation_failed', 'The given data is not valid.', 422, $e->errors());
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('not_found', 'Not found.', 404);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('rate_limited', 'Too many requests. Slow down.', 429);
            }
        });
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('method_not_allowed', 'This method is not allowed here.', 405);
            }
        });
    })->create();
