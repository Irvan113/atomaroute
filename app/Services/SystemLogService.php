<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemLogService
{
    /**
     * Standard Log Levels.
     */
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARN = 'WARN';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Categories according to McCall & user requirements.
     */
    public const CAT_TROUBLESHOOT = 'troubleshooting';
    public const CAT_PERFORMANCE = 'performance';
    public const CAT_SECURITY = 'security';
    public const CAT_AUDIT = 'audit';
    public const CAT_SYSTEM = 'system';

    /**
     * Keys containing sensitive information that must be masked before storage.
     */
    protected static array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        'authorization',
        'credit_card',
        'pin',
    ];

    /**
     * Core logging dispatcher (Dual-write: Database + Laravel Monolog).
     */
    public static function log(
        string $level,
        string $category,
        string $action,
        string $message,
        array $context = [],
        ?int $executionTimeMs = null,
        ?int $userId = null
    ): ?SystemLog {
        try {
            $level = strtoupper($level);
            $sanitizedContext = self::maskSensitiveData($context);

            $req = request();
            $ipAddress = $req ? $req->ip() : null;
            $userAgent = $req ? mb_substr((string) $req->userAgent(), 0, 500) : null;
            $url = $req ? mb_substr((string) $req->fullUrl(), 0, 500) : null;
            $method = $req ? $req->method() : null;
            $effectiveUserId = $userId ?: (auth()->check() ? auth()->id() : null);

            // 1. Dual-write to standard Laravel file logger (Monolog)
            try {
                $monologLevel = match ($level) {
                    self::LEVEL_DEBUG => 'debug',
                    self::LEVEL_INFO => 'info',
                    self::LEVEL_WARN => 'warning',
                    self::LEVEL_ERROR => 'error',
                    self::LEVEL_CRITICAL => 'critical',
                    default => 'info',
                };
                Log::$monologLevel("[{$category}.{$action}] {$message}", array_merge($sanitizedContext, [
                    'ip' => $ipAddress,
                    'user_id' => $effectiveUserId,
                    'duration_ms' => $executionTimeMs,
                ]));
            } catch (Throwable) {
                // Ignore file logging failure
            }

            // 2. Write to MySQL/SQLite Database for Dashboard & Search
            return SystemLog::create([
                'level' => $level,
                'category' => $category,
                'action' => $action,
                'message' => $message,
                'context' => !empty($sanitizedContext) ? $sanitizedContext : null,
                'user_id' => $effectiveUserId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'url' => $url,
                'method' => $method,
                'execution_time_ms' => $executionTimeMs,
            ]);
        } catch (Throwable $e) {
            // Failsafe: Never let logging error crash the main user request
            try {
                Log::error('SystemLogService failed to write log: ' . $e->getMessage());
            } catch (Throwable) {}
            return null;
        }
    }

    /**
     * 1. DEBUG: Informasi sangat detail untuk proses development / bug fixing.
     */
    public static function debug(string $message, array $context = [], string $action = 'debug'): ?SystemLog
    {
        return self::log(self::LEVEL_DEBUG, self::CAT_TROUBLESHOOT, $action, $message, $context);
    }

    /**
     * 2. INFO: Informasi normal mengenai operasional sistem.
     */
    public static function info(string $message, array $context = [], string $action = 'info'): ?SystemLog
    {
        return self::log(self::LEVEL_INFO, self::CAT_SYSTEM, $action, $message, $context);
    }

    /**
     * 3. WARN: Peringatan akan potensi masalah.
     */
    public static function warn(string $message, array $context = [], string $action = 'warning'): ?SystemLog
    {
        return self::log(self::LEVEL_WARN, self::CAT_SYSTEM, $action, $message, $context);
    }

    /**
     * 4. ERROR: Terjadi kegagalan proses namun sistem tetap berjalan.
     */
    public static function error(string $message, array $context = [], string $action = 'error', ?Throwable $e = null): ?SystemLog
    {
        if ($e) {
            $context['exception'] = get_class($e);
            $context['exception_message'] = $e->getMessage();
            $context['file'] = $e->getFile() . ':' . $e->getLine();
            $context['trace'] = self::formatStackTrace($e);
        }
        return self::log(self::LEVEL_ERROR, self::CAT_TROUBLESHOOT, $action, $message, $context);
    }

    /**
     * 5. FATAL / CRITICAL: Kesalahan fatal yang berisiko menyebabkan crash total.
     */
    public static function critical(string $message, array $context = [], string $action = 'critical', ?Throwable $e = null): ?SystemLog
    {
        if ($e) {
            $context['exception'] = get_class($e);
            $context['exception_message'] = $e->getMessage();
            $context['file'] = $e->getFile() . ':' . $e->getLine();
            $context['trace'] = self::formatStackTrace($e);
        }
        return self::log(self::LEVEL_CRITICAL, self::CAT_TROUBLESHOOT, $action, $message, $context);
    }

    /**
     * FUNGSI SPESIFIK 1: Troubleshooting & Exception Capture
     */
    public static function troubleshoot(Throwable $e, string $action = 'app.exception', array $extraContext = [], string $level = self::LEVEL_ERROR): ?SystemLog
    {
        $context = array_merge($extraContext, [
            'exception' => get_class($e),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'code' => $e->getCode(),
            'trace' => self::formatStackTrace($e),
        ]);

        return self::log(
            $level,
            self::CAT_TROUBLESHOOT,
            $action,
            $e->getMessage() ?: 'Uncaught Exception: ' . get_class($e),
            $context
        );
    }

    /**
     * FUNGSI SPESIFIK 2: Monitoring & Performa (Response time & Slow Query)
     */
    public static function performance(
        string $action,
        int $executionTimeMs,
        string $message,
        array $context = [],
        int $warningThresholdMs = 1000
    ): ?SystemLog {
        $level = $executionTimeMs >= $warningThresholdMs ? self::LEVEL_WARN : self::LEVEL_INFO;
        $context['execution_time_ms'] = $executionTimeMs;
        $context['threshold_ms'] = $warningThresholdMs;
        if (!isset($context['memory_peak'])) {
            $context['memory_peak'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
        }

        return self::log($level, self::CAT_PERFORMANCE, $action, $message, $context, $executionTimeMs);
    }

    /**
     * FUNGSI SPESIFIK 3: Keamanan & Audit (Brute force, unauthorized, login)
     */
    public static function security(
        string $action,
        string $message,
        array $context = [],
        string $level = self::LEVEL_WARN
    ): ?SystemLog {
        return self::log($level, self::CAT_SECURITY, $action, $message, $context);
    }

    /**
     * FUNGSI SPESIFIK 4: Compliance & Jejak Audit Pengguna
     */
    public static function audit(
        string $action,
        string $message,
        array $context = [],
        ?int $userId = null
    ): ?SystemLog {
        return self::log(self::LEVEL_INFO, self::CAT_AUDIT, $action, $message, $context, null, $userId);
    }

    /**
     * Masking data sensitif agar tidak bocor di log (GDPR/Compliance).
     */
    public static function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::maskSensitiveData($value);
            } elseif (is_string($key)) {
                foreach (self::$sensitiveKeys as $sensitive) {
                    if (stripos($key, $sensitive) !== false) {
                        $data[$key] = '******** (masked)';
                        break;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Format clean stack trace without bloating database.
     */
    protected static function formatStackTrace(Throwable $e, int $limit = 8): array
    {
        $trace = [];
        $rawTrace = $e->getTrace();
        $count = min(count($rawTrace), $limit);

        for ($i = 0; $i < $count; $i++) {
            $item = $rawTrace[$i];
            $file = isset($item['file']) ? str_replace(base_path() . '/', '', $item['file']) : '[internal]';
            $line = $item['line'] ?? 0;
            $func = ($item['class'] ?? '') . ($item['type'] ?? '') . ($item['function'] ?? '');
            $trace[] = "#{$i} {$file}:{$line} -> {$func}()";
        }

        return $trace;
    }

    /**
     * Clean up logs older than given days.
     */
    public static function prune(int $days = 30): int
    {
        $cutoff = now()->subDays($days);
        return SystemLog::where('created_at', '<', $cutoff)->delete();
    }
}
