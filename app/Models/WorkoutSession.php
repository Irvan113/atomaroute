<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_id',
        'title',
        'notes',
        'duration_seconds',
        'total_volume_kg',
        'total_distance_km',
        'xp_earned',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'total_volume_kg' => 'float',
        'total_distance_km' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function exercises()
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('order');
    }

    public function calculateTotalVolume(): float
    {
        $volume = 0;
        foreach ($this->exercises as $exercise) {
            foreach ($exercise->sets as $set) {
                if ($set->is_completed) {
                    $volume += ($set->weight_kg * $set->reps);
                }
            }
        }
        return (float) $volume;
    }
}
