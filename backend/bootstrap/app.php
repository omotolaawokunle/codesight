<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Standardise all JSON error responses as { success, message, errors?, code }

        $exceptions->render(function (ValidationException $e, $request): JsonResponse|null {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
                'code'    => 422,
            ], 422);
        });

        $exceptions->render(function (AuthorizationException $e, $request): JsonResponse|null {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
                'code'    => 403,
            ], 403);
        });

        $exceptions->render(function (AuthenticationException $e, $request): JsonResponse|null {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'code'    => 401,
            ], 401);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, $request): JsonResponse|null {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The requested resource was not found.',
                'code'    => 404,
            ], 404);
        });

        $exceptions->render(function (\Throwable $e, $request): JsonResponse|null {
            if (! $request->expectsJson()) {
                return null;
            }

            $isDebug = config('app.debug', false);

            return response()->json([
                'success' => false,
                'message' => $isDebug ? $e->getMessage() : 'An unexpected error occurred. Please try again later.',
                'code'    => 500,
            ], 500);
        });
    })->create();
