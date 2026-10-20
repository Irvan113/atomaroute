<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Security & Audit Event Listeners for Compliance and Security Logging
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $userId = $event->user->id ?? null;
            if (! $userId) {
                return;
            }

            // Prevent duplicate login events triggered simultaneously within 10 seconds (e.g. Remember Me race condition across parallel requests)
            $cacheKey = "auth_login_logged_{$userId}";
            if (! \Illuminate\Support\Facades\Cache::add($cacheKey, true, 10)) {
                return;
            }

            $isRemember = property_exists($event, 'remember') && $event->remember;
            $message = $isRemember
                ? 'Pengguna terotentikasi via Remember Me'
                : 'Pengguna berhasil login ke sistem';

            \App\Services\SystemLogService::security('auth.login_success', $message, [
                'email' => $event->user->email ?? null,
                'name' => $event->user->name ?? null,
                'via_remember' => $isRemember,
            ], 'INFO');
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
            \App\Services\SystemLogService::security('auth.login_failed', 'Percobaan login gagal (Kredensial tidak valid)', [
                'email' => $event->credentials['email'] ?? 'unknown',
            ], 'WARN');
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Lockout::class, function ($event) {
            \App\Services\SystemLogService::security('auth.lockout', 'Akun / IP terkunci sementara akibat deteksi brute-force login', [
                'email' => $event->request->input('email') ?? 'unknown',
            ], 'CRITICAL');
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            if ($event->user) {
                \App\Services\SystemLogService::audit('auth.logout', 'Pengguna keluar (logout) dari sistem', [
                    'email' => $event->user->email ?? null,
                ], $event->user->id);
            }
        });
    }
}
