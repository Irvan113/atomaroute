<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'target_value',
        'current_value',
        'unit',
        'is_public',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'integer',
            'current_value' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->target_value <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->current_value / $this->target_value) * 100));
    }
}
