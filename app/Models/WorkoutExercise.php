<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_session_id',
        'exercise_name',
        'muscle_group',
        'weight_unit',
        'cardio_mode',
        'order',
    ];

    public function session()
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function sets()
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('set_number');
    }
}
