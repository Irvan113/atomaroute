<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Flashcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'journal_id',
        'front',
        'back',
        'deck',
        'repetitions',
        'interval_days',
        'ease_factor',
        'last_reviewed_at',
        'next_review_at',
    ];

    protected $casts = [
        'last_reviewed_at' => 'datetime',
        'next_review_at' => 'datetime',
        'ease_factor' => 'float',
        'repetitions' => 'integer',
        'interval_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * Scope for cards due for review today or overdue.
     */
    public function scopeDueForReview($query)
    {
        return $query->where('next_review_at', '<=', Carbon::now());
    }

    /**
     * Scope for new cards that haven't been reviewed yet.
     */
    public function scopeNewCards($query)
    {
        return $query->where('repetitions', 0);
    }

    /**
     * Scope for struggle/difficult cards (low ease factor or repeated short intervals).
     */
    public function scopeStruggleCards($query)
    {
        return $query->where(function ($q) {
            $q->where('ease_factor', '<', 2.30)
              ->orWhere(function ($sub) {
                  $sub->where('repetitions', '>', 0)
                      ->where('interval_days', '<=', 2);
              });
        });
    }

    /**
     * Scope for learning cards in progress.
     */
    public function scopeLearningCards($query)
    {
        return $query->where('repetitions', '>', 0)->where('repetitions', '<', 4);
    }

    /**
     * Scope for mastered cards.
     */
    public function scopeMasteredCards($query)
    {
        return $query->where('repetitions', '>=', 4);
    }

    /**
     * Parse multi-line bulk import text into an array of ['front' => ..., 'back' => ...].
     * Supports separators: '=', ':', '-', or Tab (\t).
     */
    public static function parseBulkLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $cards = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $separator = null;
            if (str_contains($line, "\t")) {
                $separator = "\t";
            } elseif (str_contains($line, ' = ')) {
                $separator = ' = ';
            } elseif (str_contains($line, '=')) {
                $separator = '=';
            } elseif (str_contains($line, ' : ')) {
                $separator = ' : ';
            } elseif (str_contains($line, ':')) {
                $separator = ':';
            } elseif (str_contains($line, ' - ')) {
                $separator = ' - ';
            }

            if ($separator !== null) {
                $parts = explode($separator, $line, 2);
                $front = trim($parts[0]);
                $back = isset($parts[1]) ? trim($parts[1]) : '';
            } else {
                // If no clear separator, treat line as front, leave back empty or duplicate
                $front = $line;
                $back = '';
            }

            if (!empty($front) && !empty($back)) {
                $cards[] = [
                    'front' => $front,
                    'back' => $back,
                ];
            }
        }

        return $cards;
    }

    /**
     * Review the flashcard using modified SuperMemo SM-2 Spaced Repetition Algorithm.
     * 
     * Rating:
     * - 'again' (1): Reset repetitions, interval = 1 day, decrease ease factor.
     * - 'hard'  (2): Small interval bump (interval * 1.2), decrease ease factor.
     * - 'good'  (3): Standard interval bump (interval * ease_factor), maintain ease factor.
     * - 'easy'  (4): High interval bump (interval * ease_factor * 1.3), increase ease factor.
     */
    public function recordReview(string $rating): void
    {
        $userTz = $this->user?->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($userTz);
        $repetitions = $this->repetitions;
        $interval = $this->interval_days ?: 1;
        $ease = (float) ($this->ease_factor ?: 2.5);

        switch ($rating) {
            case 'again':
                $repetitions = 0;
                $interval = 1;
                $ease = max(1.3, $ease - 0.20);
                break;

            case 'hard':
                $repetitions++;
                $interval = max(1, (int) round($interval * 1.2));
                $ease = max(1.3, $ease - 0.15);
                break;

            case 'good':
                if ($repetitions === 0) {
                    $interval = 1;
                } elseif ($repetitions === 1) {
                    $interval = 3;
                } else {
                    $interval = max(2, (int) round($interval * $ease));
                }
                $repetitions++;
                break;

            case 'easy':
                if ($repetitions === 0) {
                    $interval = 3;
                } elseif ($repetitions === 1) {
                    $interval = 6;
                } else {
                    $interval = max(4, (int) round($interval * $ease * 1.3));
                }
                $repetitions++;
                $ease = min(3.0, $ease + 0.15);
                break;
        }

        $this->update([
            'repetitions' => $repetitions,
            'interval_days' => $interval,
            'ease_factor' => round($ease, 2),
            'last_reviewed_at' => $now,
            'next_review_at' => $now->copy()->addDays($interval)->startOfDay(), // Tersedia tepat jam 00:00 (tengah malam user)
        ]);
    }
}
