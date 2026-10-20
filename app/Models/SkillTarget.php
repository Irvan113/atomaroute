<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillTarget extends Model
{
    protected $fillable = [
        'skill_id', 'day_number', 'title', 'description', 'is_ai_generated', 
        'status', 'evaluation_feedback', 'pending_evaluation_data', 
        'evaluation_ai_suggestion', 'reference_url', 'ai_chat_url'
    ];

    protected $casts = [
        'pending_evaluation_data' => 'array',
        'evaluation_ai_suggestion' => 'array',
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

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function task()
    {
        return $this->hasOne(Task::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function subTasks()
    {
        return $this->hasMany(SubTask::class, 'skill_target_id');
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->status === 'completed') {
            return 100;
        }

        $targetTasks = $this->tasks;
        if ($targetTasks->isNotEmpty()) {
            $done = $targetTasks->where('is_completed', true)->count();
            return (int) round(($done / $targetTasks->count()) * 100);
        }

        $subs = $this->subTasks;
        if ($subs->isEmpty()) {
            return 0;
        }

        $done = $subs->where('is_completed', true)->count();
        return (int) round(($done / $subs->count()) * 100);
    }
}
