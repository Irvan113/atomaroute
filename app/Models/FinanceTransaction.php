<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'category',
        'description',
        'date'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
