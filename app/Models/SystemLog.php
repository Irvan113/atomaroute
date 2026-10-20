<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    protected $fillable = [
        'level',
        'category',
        'action',
        'message',
        'context',
        'user_id',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'execution_time_ms',
    ];

    protected $casts = [
        'context' => 'array',
        'execution_time_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Associated user if logged in during the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Color classes for badge display in UI.
     */
    public function getLevelBadgeAttribute(): array
    {
        return match (strtoupper($this->level)) {
            'DEBUG' => [
                'bg' => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700',
                'dot' => 'bg-gray-400',
            ],
            'INFO' => [
                'bg' => 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                'dot' => 'bg-blue-500',
            ],
            'WARN', 'WARNING' => [
                'bg' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                'dot' => 'bg-amber-500',
            ],
            'ERROR' => [
                'bg' => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                'dot' => 'bg-rose-500',
            ],
            'FATAL', 'CRITICAL' => [
                'bg' => 'bg-purple-50 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-800 animate-pulse',
                'dot' => 'bg-purple-600',
            ],
            default => [
                'bg' => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700',
                'dot' => 'bg-gray-400',
            ],
        };
    }

    /**
     * Category metadata badge.
     */
    public function getCategoryMetaAttribute(): array
    {
        return match (strtolower($this->category)) {
            'troubleshooting' => ['label' => 'Troubleshoot', 'icon' => '🩺', 'color' => 'text-rose-600 dark:text-rose-400'],
            'performance' => ['label' => 'Performa', 'icon' => '⚡', 'color' => 'text-amber-600 dark:text-amber-400'],
            'security' => ['label' => 'Keamanan', 'icon' => '🛡️', 'color' => 'text-indigo-600 dark:text-indigo-400'],
            'audit' => ['label' => 'Kepatuhan', 'icon' => '📜', 'color' => 'text-emerald-600 dark:text-emerald-400'],
            default => ['label' => 'Sistem', 'icon' => '⚙️', 'color' => 'text-gray-600 dark:text-gray-400'],
        };
    }
}
