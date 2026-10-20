<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['user_id', 'title', 'target_days', 'current_day', 'status', 'mode', 'description', 'prompt', 'training_days', 'interview_context', 'setup_status', 'reference_url', 'ai_chat_url', 'start_date'];

    protected $casts = [
        'training_days' => 'array',
        'interview_context' => 'array',
        'start_date' => 'date',
    ];

    public function getEffectiveAiChatUrlAttribute(): ?string
    {
        if (!empty($this->ai_chat_url)) {
            return $this->ai_chat_url;
        }

        if (!empty($this->reference_url) && \App\Services\AiChatReferenceService::isAiChatUrl($this->reference_url)) {
            return $this->reference_url;
        }

        return null;
    }

    public function getEffectiveYoutubeUrlAttribute(): ?string
    {
        if (empty($this->reference_url)) {
            return null;
        }

        if (\App\Services\AiChatReferenceService::isAiChatUrl($this->reference_url)) {
            return null;
        }

        return $this->reference_url;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targets()
    {
        return $this->hasMany(SkillTarget::class)->orderBy('day_number', 'asc');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function getCalculatedProgressAttribute(): int
    {
        if ($this->status === 'completed') {
            return 100;
        }

        // If milestone has custom direct tasks, calculate progress directly based on them
        $directTasks = $this->tasks()->whereNull('skill_target_id')->get();
        if ($directTasks->isNotEmpty()) {
            $totalTasks = $directTasks->count();
            $completedTasks = $directTasks->where('is_completed', true)->count();
            return (int) round(($completedTasks / $totalTasks) * 100);
        }

        $tasks = $this->tasks;
        $targets = $this->targets()->with('subTasks')->get();

        if ($tasks->isNotEmpty() && $targets->isEmpty()) {
            $totalTasks = $tasks->count();
            $completedTasks = $tasks->where('is_completed', true)->count();
            return (int) round(($completedTasks / $totalTasks) * 100);
        }

        if ($targets->isEmpty()) {
            return 0;
        }

        $totalScore = 0;
        $totalWeight = max((int) $this->target_days, $targets->count());

        foreach ($targets as $target) {
            $subTasksCount = $target->subTasks->count();
            if ($subTasksCount > 0) {
                $completedSubs = $target->subTasks->where('is_completed', true)->count();
                $totalScore += ($completedSubs / $subTasksCount);
            } else {
                $totalScore += ($target->status === 'completed' ? 1.0 : 0.0);
            }
        }

        return min(100, (int) round(($totalScore / $totalWeight) * 100));
    }

    public function getTotalSubTasksCountAttribute(): int
    {
        return $this->targets()->withCount('subTasks')->get()->sum('sub_tasks_count');
    }

    public function getCompletedSubTasksCountAttribute(): int
    {
        return SubTask::whereIn('skill_target_id', $this->targets()->pluck('id'))->where('is_completed', true)->count();
    }
}
