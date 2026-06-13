<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiErrorResponses
{
    private const MAX_LOGGED_RESPONSE_CHARS = 2000;

    private const REDACTED_FIELDS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        $status = $response->getStatusCode();

        if ($status < 400) {
            return $response;
        }

        $logLevel = $status >= 500 ? 'error' : 'warning';
        $logger = Log::channel('api_errors');

        $logger->$logLevel('API endpoint returned an error response.', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'route' => $request->route()?->uri(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'status' => $status,
            'user_id' => $this->resolveUserId($request),
            'ip' => $request->ip(),
            'query' => $request->query(),
            'body' => $this->sanitize($request->except(['_token'])),
            'response' => $this->truncateResponse($response->getContent()),
        ]);

        return $response;
    }

    private function sanitize(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_FIELDS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function resolveUserId(Request $request): mixed
    {
        try {
            return $request->user()?->getAuthIdentifier();
        } catch (\Throwable) {
            return null;
        }
    }

    private function truncateResponse(mixed $content): ?string
    {
        if (!is_string($content) || $content === '') {
            return null;
        }

        if (strlen($content) <= self::MAX_LOGGED_RESPONSE_CHARS) {
            return $content;
        }

        return substr($content, 0, self::MAX_LOGGED_RESPONSE_CHARS).'... [truncated]';
    }
}
