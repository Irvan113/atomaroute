<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $queryCount = 0;
        $dbDuration = 0;
        try {
            \Illuminate\Support\Facades\DB::enableQueryLog();
        } catch (\Throwable $e) {}

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        try {
            $queries = \Illuminate\Support\Facades\DB::getQueryLog();
            $queryCount = count($queries);
            foreach ($queries as $q) {
                $dbDuration += ($q['time'] ?? 0);
            }
        } catch (\Throwable $e) {}

        $duration = defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 2) : 0;
        $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $response->headers->set('X-Execution-Time', $duration . 'ms');
        $response->headers->set('X-Query-Count', (string) $queryCount);
        $response->headers->set('X-Query-Time', round($dbDuration, 2) . 'ms');
        $response->headers->set('X-Memory-Peak', $memoryPeak . 'MB');
        $response->headers->set('Server-Timing', "app;desc=\"Execution\";dur={$duration}, db;desc=\"Database\";dur=" . round($dbDuration, 2));
        $response->headers->set('Access-Control-Expose-Headers', 'X-Execution-Time, X-Query-Count, X-Query-Time, X-Memory-Peak, Server-Timing');

        return $response;
    }
}
