<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubscriptionTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'admin_id',
        'reference_no',
        'plan_type',
        'plan_name',
        'amount',
        'payment_method',
        'starts_at',
        'expires_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * User who owns this subscription.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin who granted/approved this subscription.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Generate unique reference number like ATM-SUB-202610-A1B2C3
     */
    public static function generateReferenceNo(): string
    {
        do {
            $ref = 'ATM-SUB-' . date('Ym') . '-' . strtoupper(Str::random(6));
        } while (static::where('reference_no', $ref)->exists());

        return $ref;
    }

    /**
     * Format amount in IDR Rupiah
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Check if currently active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
