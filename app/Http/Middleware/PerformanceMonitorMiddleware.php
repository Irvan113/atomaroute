<?php

namespace App\Http\Middleware;

use App\Services\SystemLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitorMiddleware
{
    /**
     * Threshold in milliseconds above which a request is flagged as slow.
     */
    protected int $slowThresholdMs = 1200;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        // Filter out static asset requests
        $path = $request->path();
        if (str_starts_with($path, 'build/') || str_starts_with($path, 'vendor/') || $path === 'favicon.ico') {
            return $response;
        }

        // 1. Log Slow Request if threshold exceeded (Monitoring & Performa)
        if ($durationMs >= $this->slowThresholdMs) {
            SystemLogService::performance(
                'http.slow_request',
                $durationMs,
                "Request lambat ({$durationMs}ms) pada [{$request->method()}] /{$path}",
                [
                    'status_code' => $response->getStatusCode(),
                    'query_params' => $request->query(),
                    'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                ],
                $this->slowThresholdMs
            );
        }

        // 2. Log 5xx Server Error responses if encountered
        if ($response->getStatusCode() >= 500) {
            SystemLogService::error(
                "HTTP {$response->getStatusCode()} Server Error pada [{$request->method()}] /{$path}",
                [
                    'status_code' => $response->getStatusCode(),
                    'duration_ms' => $durationMs,
                ],
                'http.server_error'
            );
        }

        return $response;
    }
}
