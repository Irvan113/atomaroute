<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'email_verified_at',
        'password',
        'current_streak',
        'longest_streak',
        'last_task_completed_date',
        'last_secret_protection_at',
        'xp',
        'level',
        'streak_coupons',
        'daily_xp_earned',
        'last_xp_date',
        'pending_xp',
        'last_settlement_date',
        'avatar_path',
        'bio',
        'last_pomodoro_xp_at',
        'monthly_xp',
        'monthly_xp_reset_at',
        'weekly_xp',
        'weekly_xp_reset_at',
        'coins',
        'xp_multiplier',
        'xp_multiplier_expires_at',
        'daily_clear_streak',
        'daily_clear_achieved_at',
        'active_theme',
        'unlocked_themes',
        'active_sound_pack',
        'unlocked_sound_packs',
        'is_admin',
        'league',
        'prev_league',
        'prev_week_xp',
        'prev_month_xp',
        'prev_year_xp',
        'league_promoted_at',
        'league_season_claimed',
        'equipped_badge',
        'equipped_badges',
        'timezone',
        'locale',
        'share_profile_with_friends',
        'share_daily_tasks',
        'share_achievements',
        'allow_friend_nudges',
        'prestige_tier',
        'prestige_level',
        'prestige_xp',
        'lifetime_xp',
        'stat_focus',
        'stat_consistency',
        'stat_execution',
        'stat_organization',
        'seasonal_xp',
        'seasonal_level',
        'current_season',
        'member_since',
        'total_tasks_completed',
        'monthly_budget',
        'subscription_tier',
        'subscription_expires_at',
        'subscription_status',
    ];

    public function getTodayTasksSummaryAttribute(): array
    {
        $tz = $this->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $today = now($tz)->toDateString();
        $tasks = $this->tasks()->whereDate('date', $today)->get();
        $total = $tasks->count();
        $completed = $tasks->where('is_completed', true)->count();
        $pending = $tasks->where('is_completed', false)->values();

        return [
            'total' => $total,
            'completed' => $completed,
            'remaining' => max(0, $total - $completed),
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'pending_tasks' => $pending->take(5),
        ];
    }

    public function getLeagueDetailsAttribute()
    {
        return \App\Services\LeagueService::getLeagueDetails($this->league ?? 'bronze_3');
    }

    public function getEquippedBadgesDetailsAttribute(): array
    {
        $badges = $this->equipped_badges;
        if (!is_array($badges) || empty($badges)) {
            if (!empty($this->equipped_badge)) {
                $badges = [$this->equipped_badge];
            } else {
                return [];
            }
        }

        $details = [];
        foreach ($badges as $code) {
            $d = \App\Services\AchievementService::getBadgeDetails($code);
            if ($d) {
                $details[] = $d;
            }
        }
        return $details;
    }

    public function getEquippedBadgeDetailsAttribute(): ?array
    {
        $list = $this->equipped_badges_details;
        return !empty($list) ? $list[0] : null;
    }

    public function getPrestigeTitleAttribute(): string
    {
        return \App\Services\PrestigeService::getPrestigeTitle($this);
    }

    public function getPrestigeTierDetailsAttribute(): array
    {
        return \App\Services\PrestigeService::getTierDetails($this->prestige_tier ?? 1);
    }

    public function getLevelProgressAttribute(): array
    {
        return \App\Services\PrestigeService::getLevelProgress($this);
    }

    public function getRpgStatsAttribute(): array
    {
        return [
            'focus' => (int) ($this->stat_focus ?? 0),
            'consistency' => (int) ($this->stat_consistency ?? 0),
            'execution' => (int) ($this->stat_execution ?? 0),
            'organization' => (int) ($this->stat_organization ?? 0),
        ];
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar_path) {
            if (str_starts_with($this->avatar_path, 'http')) {
                return $this->avatar_path;
            }
            return Storage::url($this->avatar_path);
        }
        
        return 'https://api.dicebear.com/9.x/notionists/svg?seed=' . urlencode($this->name);
    }

    /**
     * Add XP and calculate earned Coins using 10:1 ratio rounded UP (ceil).
     * 10 XP = 1 Coin. Example: 25 XP => ceil(25/10) = 3 Coins.
     */
    public function addXp(int $amount)
    {
        $this->xp += $amount;
        
        // 10 XP = 1 Coin (rounded up)
        $earnedCoins = (int) ceil($amount / 10.0);
        $this->coins += $earnedCoins;
        
        // Monthly XP
        $startOfMonth = now()->startOfMonth();
        if (!$this->monthly_xp_reset_at || $this->monthly_xp_reset_at < $startOfMonth) {
            $this->monthly_xp = $amount;
            $this->monthly_xp_reset_at = now();
        } else {
            $this->monthly_xp += $amount;
        }

        // Weekly XP
        $startOfWeek = now()->startOfWeek();
        if (!$this->weekly_xp_reset_at || $this->weekly_xp_reset_at < $startOfWeek) {
            $this->weekly_xp = $amount;
            $this->weekly_xp_reset_at = now();
        } else {
            $this->weekly_xp += $amount;
        }
        
        $newLevel = (int) floor($this->xp / 100) + 1;
        if ($newLevel > $this->level) {
            $this->level = $newLevel;
            return true; // leveled up
        }
        
        return false;
    }

    /**
     * Subtract XP when a task is unchecked.
     * Note: Deducts from pending_xp so main XP and Level NEVER decrease!
     */
    public function subtractXp(int $amount)
    {
        return $this->subtractPendingXp($amount);
    }

    /**
     * Relationship to Pending XP Logs.
     */
    public function pendingXpLogs()
    {
        return $this->hasMany(\App\Models\PendingXpLog::class);
    }

    /**
     * Add XP to Pending XP pool for H+1 Settlement and record ledger entry.
     */
    public function addPendingXp(int $amount, string $source = 'other', ?string $title = null, ?string $description = null, ?int $referenceId = null)
    {
        if ($amount <= 0) {
            return;
        }

        $this->pending_xp += $amount;
        $this->save();

        \App\Models\PendingXpLog::create([
            'user_id' => $this->id,
            'amount' => $amount,
            'source' => $source,
            'title' => $title ?? match($source) {
                'task' => 'Tugas Selesai',
                'subtask' => 'Subtugas Selesai',
                'achievement' => 'Pencapaian Terbuka',
                'pomodoro' => 'Sesi Fokus Pomodoro',
                'workout' => 'Latihan Fisik Selesai',
                'flashcard' => 'Review Kartu SRS',
                'journal' => 'Refleksi Jurnal',
                'duel' => 'Duel Senggol Bacok',
                'habit' => 'Kebiasaan Selesai',
                'skill' => 'Milestone Skill',
                'hidden_mission' => 'Misi Rahasia Harian',
                default => 'Aktivitas Harian',
            },
            'description' => $description,
            'reference_id' => $referenceId,
            'settled_at' => null,
        ]);
    }

    /**
     * Subtract XP from Pending XP pool when a task is unchecked.
     * Main XP and Level are NEVER decreased!
     */
    public function subtractPendingXp(int $amount, string $source = 'task', ?int $referenceId = null)
    {
        $this->pending_xp = max(0, $this->pending_xp - $amount);
        $this->save();

        if ($referenceId) {
            $log = \App\Models\PendingXpLog::where('user_id', $this->id)
                ->whereNull('settled_at')
                ->where('source', $source)
                ->where('reference_id', $referenceId)
                ->latest()
                ->first();
            if ($log) {
                $log->delete();
                return;
            }
        }

        $latest = \App\Models\PendingXpLog::where('user_id', $this->id)
            ->whereNull('settled_at')
            ->where('source', $source)
            ->latest()
            ->first();
        if ($latest) {
            if ($latest->amount <= $amount) {
                $latest->delete();
            } else {
                $latest->decrement('amount', $amount);
            }
        }
    }

    /**
     * Process H+1 Midnight Settlement: Converts pending XP to main XP & Coins.
     */
    public function processDailySettlement(): array
    {
        $userTz = $this->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $today = \Carbon\Carbon::today($userTz)->toDateString();

        $lastSettlement = $this->last_settlement_date 
            ? ($this->last_settlement_date instanceof \Carbon\Carbon 
                ? $this->last_settlement_date->toDateString() 
                : \Carbon\Carbon::parse($this->last_settlement_date)->toDateString())
            : null;

        // Strictly run ONCE per day!
        if ($lastSettlement === $today) {
            return ['settled' => false];
        }

        $pending = (int) $this->pending_xp;
        if ($pending <= 0) {
            $this->last_settlement_date = $today;
            $this->save();
            return ['settled' => false];
        }

        $earnedCoins = (int) ceil($pending / 10.0);
        $leveledUp = $this->addXp($pending);
        
        // Settle Prestige XP & Tiers on H+1
        $prestigeResult = \App\Services\PrestigeService::addXp($this, $pending);

        // Synchronize legacy level with unified Prestige global level
        $this->level = \App\Services\PrestigeService::getGlobalLevel($this);

        // Mark completed tasks and subtasks as settled so they cannot be exploited by unchecking/rechecking
        $this->tasks()->where('is_completed', true)
            ->whereNull('xp_settled_at')
            ->update(['xp_settled_at' => now()]);

        \App\Models\SubTask::whereHas('task', function($q) {
            $q->where('user_id', $this->id);
        })->where(function($q) {
            $q->where('is_completed', true)->orWhere('earned_xp', '>', 0);
        })->whereNull('xp_settled_at')
          ->update(['xp_settled_at' => now()]);

        // Mark all pending XP logs as settled
        \App\Models\PendingXpLog::where('user_id', $this->id)
            ->whereNull('settled_at')
            ->update(['settled_at' => now()]);

        $this->lifetime_xp = max($this->lifetime_xp ?? 0, $this->xp);
        $this->pending_xp = 0;
        $this->last_settlement_date = $today;
        $this->save();

        return [
            'settled' => true,
            'xp' => $pending,
            'coins' => $earnedCoins,
            'leveled_up' => $leveledUp || !empty($prestigeResult['leveled_up']),
            'ascended' => !empty($prestigeResult['ascended']),
            'new_level' => $this->prestige_level ?? $this->level,
            'prestige_tier' => $this->prestige_tier ?? 1,
            'prestige_level' => $this->prestige_level ?? 1,
            'prestige_title' => $this->prestige_title,
            'prestige_result' => $prestigeResult,
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_pomodoro_xp_at' => 'date',
            'last_settlement_date' => 'date',
            'monthly_xp_reset_at' => 'datetime',
            'weekly_xp_reset_at' => 'datetime',
            'xp_multiplier_expires_at' => 'datetime',
            'daily_clear_achieved_at' => 'date',
            'unlocked_frames' => 'array',
            'unlocked_themes' => 'array',
            'unlocked_sound_packs' => 'array',
            'badges' => 'array',
            'equipped_badges' => 'array',
            'league_season_claimed' => 'boolean',
            'league_promoted_at' => 'datetime',
            'share_profile_with_friends' => 'boolean',
            'share_daily_tasks' => 'boolean',
            'share_achievements' => 'boolean',
            'allow_friend_nudges' => 'boolean',
            'member_since' => 'date',
            'monthly_budget' => 'decimal:2',
            'is_admin' => 'boolean',
            'last_secret_protection_at' => 'date',
            'subscription_expires_at' => 'datetime',
        ];
    }

    /**
     * Determine if the user has an active Pro subscription.
     */
    public function isPro(): bool
    {
        if ($this->is_admin) {
            return true;
        }

        if (($this->subscription_tier ?? 'free') !== 'pro') {
            return false;
        }

        if ($this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
            return false;
        }

        return ($this->subscription_status ?? 'active') === 'active';
    }

    /**
     * Determine if the user is on the free tier.
     */
    public function isFree(): bool
    {
        return !$this->isPro();
    }

    /**
     * Grant or extend Pro subscription for user.
     */
    public function grantPro(string $planType = 'monthly', ?\Carbon\Carbon $expiresAt = null, ?string $notes = null, ?User $admin = null, float $amount = 0, string $paymentMethod = 'manual_transfer'): SubscriptionTransaction
    {
        $now = now();
        // If current subscription is still active in the future, extend from that date
        $baseDate = ($this->subscription_expires_at && $this->subscription_expires_at->isFuture()) 
            ? $this->subscription_expires_at 
            : $now;

        if (!$expiresAt && $planType !== 'lifetime') {
            $expiresAt = match($planType) {
                'monthly' => (clone $baseDate)->addDays(30),
                'quarterly' => (clone $baseDate)->addDays(90),
                'yearly' => (clone $baseDate)->addDays(365),
                default => (clone $baseDate)->addDays(30),
            };
        }

        $planName = match($planType) {
            'monthly' => 'ATOMA PRO Bulanan (30 Hari)',
            'quarterly' => 'ATOMA PRO 3 Bulan (90 Hari)',
            'yearly' => 'ATOMA PRO Tahunan (365 Hari)',
            'lifetime' => 'ATOMA PRO Akses Seumur Hidup (Lifetime)',
            default => 'ATOMA PRO Kustom',
        };

        if ($amount <= 0 && $paymentMethod !== 'promo') {
            $amount = match($planType) {
                'monthly' => 29000,
                'quarterly' => 79000,
                'yearly' => 199000,
                'lifetime' => 499000,
                default => 0,
            };
        }

        $this->subscription_tier = 'pro';
        $this->subscription_expires_at = $expiresAt;
        $this->subscription_status = 'active';
        $this->save();

        return SubscriptionTransaction::create([
            'user_id' => $this->id,
            'admin_id' => $admin?->id,
            'reference_no' => SubscriptionTransaction::generateReferenceNo(),
            'plan_type' => $planType,
            'plan_name' => $planName,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'starts_at' => $now,
            'expires_at' => $expiresAt,
            'status' => 'active',
            'notes' => $notes,
        ]);
    }

    /**
     * Revoke Pro subscription back to Free tier.
     */
    public function revokePro(?string $reason = null, ?User $admin = null): void
    {
        $this->subscription_tier = 'free';
        $this->subscription_status = 'cancelled';
        $this->subscription_expires_at = now();
        $this->save();

        $this->subscriptionTransactions()->where('status', 'active')->update(['status' => 'revoked']);
    }

    /**
     * Check if user can create a new milestone.
     * Free: max 1 active milestone.
     * Pro: unlimited active milestones.
     */
    public function canCreateMilestone(): bool
    {
        if ($this->isPro()) {
            return true;
        }

        return $this->skills()->where('status', '!=', 'completed')->count() < 1;
    }

    /**
     * Maximum allowed Pomodoro playlist folders.
     * Free: 2 folders.
     * Pro: unlimited (999).
     */
    public function maxPomodoroFolders(): int
    {
        return $this->isPro() ? 999 : 2;
    }

    /**
     * Check if user has access to 90 Days Challenge.
     * Pro feature.
     */
    public function canAccess90DayChallenge(): bool
    {
        return $this->isPro();
    }

    public function goals()
    {
        return $this->hasMany(Goal::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the tasks for the user.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function focusSessions()
    {
        return $this->hasMany(FocusSession::class);
    }

    public function pomodoroPlaylist()
    {
        return $this->hasOne(PomodoroPlaylist::class);
    }

    public function sentFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'sender_id');
    }

    public function receivedFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'receiver_id');
    }

    public function journals()
    {
        return $this->hasMany(Journal::class)->orderBy('created_at', 'desc');
    }

    public function skills()
    {
        return $this->hasMany(Skill::class)->orderBy('created_at', 'desc');
    }

    public function financeTransactions()
    {
        return $this->hasMany(FinanceTransaction::class)->orderBy('date', 'desc');
    }

    public function flashcards()
    {
        return $this->hasMany(Flashcard::class)->orderBy('created_at', 'desc');
    }
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    public function likes()
    {
        return $this->hasMany(Like::class);
    }
    
    public function friendships()
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }
    
    public function friends()
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id');
    }

    public function receivedSharedTaskInvitations()
    {
        return $this->hasMany(SharedTaskInvitation::class, 'invitee_id')->where('status', 'pending');
    }

    public function taskTemplates()
    {
        return $this->hasMany(TaskTemplate::class);
    }

    public function receivedTemplateShares()
    {
        return $this->hasMany(TemplateShare::class, 'receiver_id');
    }

    public function feedbacks()
    {
        return $this->hasMany(UserFeedback::class)->orderBy('created_at', 'desc');
    }

    public function workoutSessions()
    {
        return $this->hasMany(WorkoutSession::class)->orderBy('created_at', 'desc');
    }

    public function mcpTokens()
    {
        return $this->hasMany(McpToken::class)->orderBy('created_at', 'desc');
    }

    public function subscriptionTransactions()
    {
        return $this->hasMany(SubscriptionTransaction::class)->orderBy('created_at', 'desc');
    }
}
