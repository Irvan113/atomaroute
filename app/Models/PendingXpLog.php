<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingXpLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'source',
        'title',
        'description',
        'reference_id',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reference_id' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
