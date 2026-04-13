<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',   
        apiPrefix: 'api',                    
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\JsonResponse::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\LogApiErrorResponses::class);
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $resolveUserId = function (\Illuminate\Http\Request $request): mixed {
            try {
                return $request->user()?->getAuthIdentifier();
            } catch (\Throwable) {
                return null;
            }
        };

        $jsonResponse = static function (array $payload, int $status, array $headers = []): \Illuminate\Http\JsonResponse {
            return new \Illuminate\Http\JsonResponse($payload, $status, $headers);
        };

        $logException = function (
            \Throwable $e,
            \Illuminate\Http\Request $request,
            int $status,
            array $extra = []
        ) use ($resolveUserId): void {
            if (!$request->is('api/*')) {
                return;
            }

            $requestId = $request->attributes->get('request_id');

            if (!is_string($requestId) || $requestId === '') {
                $requestId = (string) Str::uuid();
                $request->attributes->set('request_id', $requestId);
            }

            $context = array_merge([
                'request_id' => $requestId,
                'method' => $request->method(),
                'route' => $request->route()?->uri(),
                'path' => $request->path(),
                'full_url' => $request->fullUrl(),
                'status' => $status,
                'user_id' => $resolveUserId($request),
                'ip' => $request->ip(),
                'query' => $request->query(),
                'body' => $request->except([
                    '_token',
                    'password',
                    'password_confirmation',
                    'token',
                    'access_token',
                    'refresh_token',
                    'authorization',
                ]),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ], $extra);

            if (!app()->isProduction()) {
                $context['trace'] = $e->getTraceAsString();
            }

            Log::channel('api_errors')->error('API exception rendered.', $context);
        };

        $errorHeaders = function (\Illuminate\Http\Request $request): array {
            $requestId = $request->attributes->get('request_id');

            if (!is_string($requestId) || $requestId === '') {
                $requestId = (string) Str::uuid();
                $request->attributes->set('request_id', $requestId);
            }

            return [
                'X-Request-Id' => $requestId,
            ];
        };

        $exceptions->render(function (
            \Illuminate\Validation\ValidationException $e,
            \Illuminate\Http\Request $request
        ) use ($jsonResponse, $logException, $errorHeaders) {
            $logException($e, $request, 422, ['errors' => $e->errors()]);

            return $jsonResponse([
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
            ], 422, $errorHeaders($request));
        });

        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) use ($jsonResponse, $logException, $errorHeaders) {
            $logException($e, $request, 401);

            return $jsonResponse(['message' => 'Unauthenticated.'], 401, $errorHeaders($request));
        });

        $exceptions->render(function (
            \Illuminate\Auth\Access\AuthorizationException $e,
            \Illuminate\Http\Request $request
        ) use ($jsonResponse, $logException, $errorHeaders) {
            $logException($e, $request, 403);

            return $jsonResponse(['message' => 'Forbidden.'], 403, $errorHeaders($request));
        });

        $exceptions->render(function (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e,
            \Illuminate\Http\Request $request
        ) use ($jsonResponse, $logException, $errorHeaders) {
            $logException($e, $request, 404);

            return $jsonResponse(['message' => 'Resource not found.'], 404, $errorHeaders($request));
        });

        $exceptions->render(function (
            \Throwable $e,
            \Illuminate\Http\Request $request
        ) use ($jsonResponse, $logException, $errorHeaders) {
            $logException($e, $request, 500);

            $message = app()->isProduction()
                ? 'An unexpected error occurred.'
                : $e->getMessage();

            return $jsonResponse([
                'message' => $message,
                'exception' => $e::class,
            ], 500, $errorHeaders($request));
        });
    })->create();
