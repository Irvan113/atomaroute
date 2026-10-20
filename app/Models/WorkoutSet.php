<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_exercise_id',
        'set_number',
        'weight_kg',
        'reps',
        'distance_km',
        'duration_seconds',
        'pace',
        'set_type',
        'is_completed',
        'is_pr',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'distance_km' => 'float',
        'duration_seconds' => 'integer',
        'is_completed' => 'boolean',
        'is_pr' => 'boolean',
    ];

    public function exercise()
    {
        return $this->belongsTo(WorkoutExercise::class, 'workout_exercise_id');
    }
}
