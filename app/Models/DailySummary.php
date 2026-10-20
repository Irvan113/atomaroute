<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySummary extends Model
{
    protected $fillable = [
        'user_id',
        'content',
        'summary_date',
    ];

    protected $casts = [
        'summary_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
