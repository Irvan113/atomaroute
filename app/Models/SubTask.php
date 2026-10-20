<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubTask extends Model
{
    protected $fillable = ['task_id', 'skill_target_id', 'title', 'is_completed', 'earned_xp', 'xp_settled_at'];
    
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'earned_xp' => 'integer',
            'xp_settled_at' => 'datetime',
        ];
    }
    
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function skillTarget()
    {
        return $this->belongsTo(SkillTarget::class, 'skill_target_id');
    }
}
