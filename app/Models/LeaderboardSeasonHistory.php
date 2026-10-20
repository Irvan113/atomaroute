<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaderboardSeasonHistory extends Model
{
    protected $fillable = [
        'user_id',
        'period_type',
        'period_key',
        'xp',
        'rank',
        'league',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
