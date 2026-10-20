<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedback extends Model
{
    use HasFactory;

    protected $table = 'user_feedbacks';

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'message',
        'mood',
        'device_info',
        'status',
        'dev_reply',
        'dev_replied_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'dev_replied_at' => 'datetime',
    ];

    public const TYPES = [
        'feature' => [
            'label' => 'Ide Fitur Baru',
            'icon' => '💡',
            'badge_class' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/80 dark:text-amber-200 border border-amber-300 dark:border-amber-700/70 font-bold',
        ],
        'bug' => [
            'label' => 'Lapor Bug / Error',
            'icon' => '🐛',
            'badge_class' => 'bg-red-100 text-red-900 dark:bg-red-950/80 dark:text-red-200 border border-red-300 dark:border-red-700/70 font-bold',
        ],
        'critique' => [
            'label' => 'Kritik & Masukan',
            'icon' => '💬',
            'badge_class' => 'bg-blue-100 text-blue-900 dark:bg-blue-950/80 dark:text-blue-200 border border-blue-300 dark:border-blue-700/70 font-bold',
        ],
        'appreciation' => [
            'label' => 'Apresiasi / Semangat',
            'icon' => '❤️',
            'badge_class' => 'bg-rose-100 text-rose-900 dark:bg-rose-950/80 dark:text-rose-200 border border-rose-300 dark:border-rose-700/70 font-bold',
        ],
        'donation' => [
            'label' => 'Konfirmasi Donasi ☕',
            'icon' => '☕',
            'badge_class' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/70 font-bold',
        ],
    ];

    public const STATUSES = [
        'pending' => [
            'label' => 'Menunggu Ditinjau',
            'icon' => '⏳',
            'class' => 'bg-yellow-100 text-yellow-900 dark:bg-yellow-950/80 dark:text-yellow-200 border border-yellow-300 dark:border-yellow-700/70',
        ],
        'reviewed' => [
            'label' => 'Sudah Dibaca Dev',
            'icon' => '👀',
            'class' => 'bg-blue-100 text-blue-900 dark:bg-blue-950/80 dark:text-blue-200 border border-blue-300 dark:border-blue-700/70',
        ],
        'in_progress' => [
            'label' => 'Sedang Dikerjakan',
            'icon' => '🛠️',
            'class' => 'bg-purple-100 text-purple-900 dark:bg-purple-950/80 dark:text-purple-200 border border-purple-300 dark:border-purple-700/70',
        ],
        'resolved' => [
            'label' => 'Selesai / Diterapkan',
            'icon' => '✅',
            'class' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/70',
        ],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeMetaAttribute(): array
    {
        return self::TYPES[$this->type] ?? [
            'label' => ucfirst($this->type),
            'icon' => '📝',
            'badge_class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        ];
    }

    public function getStatusMetaAttribute(): array
    {
        return self::STATUSES[$this->status] ?? [
            'label' => ucfirst($this->status),
            'icon' => '📌',
            'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        ];
    }
}
