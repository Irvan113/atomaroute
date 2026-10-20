<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'feature',
        'prompt_text',
        'response_text',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'execution_time_ms',
        'status',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
