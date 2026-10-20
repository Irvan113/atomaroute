<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'priority',
        'estimated_minutes',
        'mode',
        'subtasks',
        'is_public',
        'clones_count',
    ];

    protected $casts = [
        'subtasks' => 'array',
        'is_public' => 'boolean',
        'estimated_minutes' => 'integer',
        'clones_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shares()
    {
        return $this->hasMany(TemplateShare::class, 'task_template_id');
    }

    public function scopePublicCommunity($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
