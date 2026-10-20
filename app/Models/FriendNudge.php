<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FriendNudge extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'nudge_type',
        'message',
        'status',
        'target_task_count',
        'sender_task_count',
        'receiver_task_count',
        'winner_id',
        'xp_reward',
        'expires_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
