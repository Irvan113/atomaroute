<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'user_id',
        'skill_id',
        'skill_target_id',
        'goal_id',
        'title',
        'description',
        'date',
        'start_time',
        'deadline',
        'priority',
        'category',
        'is_completed',
        'is_completed_overdue',
        'is_deleted_overdue',
        'is_overdue_dismissed',
        'skip_reason',
        'skip_reason_note',
        'earned_xp',
        'xp_settled_at',
        'estimated_minutes',
        'actual_minutes',
        'pomodoro_sessions',
        'shared_with_user_id',
        'mode',
        'is_recurring',
        'recurrence_pattern',
        'recurrence_days',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'is_completed_overdue' => 'boolean',
            'is_deleted_overdue' => 'boolean',
            'is_overdue_dismissed' => 'boolean',
            'is_recurring' => 'boolean',
            'date' => 'date',
            'recurrence_days' => 'array',
            'xp_settled_at' => 'datetime',
        ];
    }

    /**
     * Determine if this task is overdue (either scheduled before today or today's deadline has passed).
     */
    public function isOverdue(?\Carbon\Carbon $now = null): bool
    {
        if ($this->is_completed) {
            return (bool) $this->is_completed_overdue;
        }

        $now = $now ?? now();
        $today = \Carbon\Carbon::today();

        if ($this->date) {
            $taskDate = \Carbon\Carbon::parse($this->date)->startOfDay();
            if ($taskDate->isBefore($today)) {
                return true;
            }
            if ($taskDate->isSameDay($today) && $this->deadline) {
                $taskDateStr = $this->date instanceof \Carbon\Carbon ? $this->date->format('Y-m-d') : \Carbon\Carbon::parse($this->date)->format('Y-m-d');
                $deadlineTime = \Carbon\Carbon::parse($taskDateStr . ' ' . $this->deadline);
                if ($now->isAfter($deadlineTime)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sharedWithUser()
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function invitation()
    {
        return $this->hasOne(SharedTaskInvitation::class);
    }

    public function subTasks()
    {
        return $this->hasMany(SubTask::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function skillTarget()
    {
        return $this->belongsTo(SkillTarget::class);
    }

    public function workoutSessions()
    {
        return $this->hasMany(WorkoutSession::class)->orderBy('created_at', 'desc');
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }
}
