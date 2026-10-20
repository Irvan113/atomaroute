<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsight extends Model
{
    protected $fillable = [
        'user_id',
        'archetype',
        'observasi',
        'tantangan',
        'afirmasi',
        'period_start',
        'period_end',
        'generated_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
