<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class McpToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relationship to the user who owns this token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to generate a unique random MCP token with atoma_mcp_ prefix.
     */
    public static function createForUser(User $user, string $name = 'Default MCP Client', array $abilities = ['*']): self
    {
        $rawToken = 'atoma_mcp_' . Str::random(40);

        return self::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => $rawToken,
            'abilities' => $abilities,
        ]);
    }

    /**
     * Find token record by raw token string.
     */
    public static function findByToken(?string $rawToken): ?self
    {
        if (empty($rawToken)) {
            return null;
        }

        return self::with('user')->where('token', trim($rawToken))->first();
    }
}
