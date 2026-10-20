<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = (auth()->check() ? auth()->user()->timezone : null) 
            ?? session('auto_timezone') 
            ?? config('app.timezone', 'Asia/Jakarta');

        if ($timezone) {
            date_default_timezone_set($timezone);
            \Illuminate\Support\Facades\Config::set('app.timezone', $timezone);
        }

        $locale = (auth()->check() ? auth()->user()->locale : null) 
            ?? session('locale') 
            ?? config('app.locale', 'id');

        if ($locale) {
            \Illuminate\Support\Facades\App::setLocale($locale);
        }

        if (auth()->check()) {
            \App\Services\StreakEvaluatorService::evaluate(auth()->user());
        }

        return $next($request);
    }
}
