<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PomodoroPlaylist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'folders',
    ];

    protected function casts(): array
    {
        return [
            'folders' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
