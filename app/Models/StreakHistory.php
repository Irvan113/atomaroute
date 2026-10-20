<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StreakHistory extends Model
{
    protected $fillable = ['user_id', 'date', 'status'];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
