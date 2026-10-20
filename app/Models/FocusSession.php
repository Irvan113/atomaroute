<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FocusSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_id',
        'minutes',
        'mode',
        'ambient_sound',
        'xp_earned',
        'coins_earned',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'xp_earned' => 'integer',
            'coins_earned' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
