<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\User;
use App\Models\Friendship;
use App\Models\Post;
use App\Models\Like;
use App\Models\Message;
use App\Models\Comment;
use App\Models\FriendNudge;
use App\Services\AchievementService;
use App\Events\NudgeSent;
use App\Notifications\AppNotification;
use Carbon\Carbon;

new class extends Component {
    public $search = '';
    public $postTitle = '';
    public $postContent = '';
    public $postCategory = 'General';
    public $bookmarkedPostIds = [];
    public $activeChatFriendId = null;
    public $currentView = 'hub';
    public $chatMessageContent = '';
    public $activeTopicId = null;
    public $threadCommentContent = '';
    public $showCreateTopicModal = false;
    public $selectedTag = 'All';
    public $sortView = 'newest';
    public $timeRange = 'monthly';
    public $selectedPeriodKey = '2026-08';
    public $leaderboardScope = 'global';
    public $friendSubTab = 'friends';
    public $selectedFriendId = null;
    public $showProfileModal = false;
    public $showPrivacyModal = false;
    public $privacyShareProfile = true;
    public $privacyShareTasks = true;
    public $privacyShareAchievements = true;
    public $privacyAllowNudges = true;

    // Senggol Bacok System Properties
    public $showSenggolModal = false;
    public $targetFriendId = null;
    public $selectedNudgeType = 'bacok'; // 'bacok', 'task', 'fire', 'roast', 'shield'
    public $customRoastMessage = '';
    public $selectedRoastTemplate = 'Kopi udah dingin, tugas belum disentuh!';
    public $showIncomingNudgeModal = false;
    public $activeIncomingNudgeId = null;
    public array $roastTemplates = [
        'Kopi udah dingin, tugas belum disentuh!',
        'Bangun woy! Impian lu nggak bakal tercapai kalau rebahan terus!',
        'Katanya mau jadi orang sukses, kok baru jam segini udah rebahan?',
        'Notifikasi game dibuka terus, checklist tugas kapan dicentang?',
        'Streak-mu yang berharga mau hangus tuh kalau nggak gerak sekarang!',
        'Ingat cicilan, ingat masa depan, yuk buka checklist tugasmu!',
    ];

    #[On('open-create-feed-modal')]
    public function openCreateFeedModal()
    {
        $this->showCreateTopicModal = true;
    }
    
    public function mount()
    {
        $this->selectedPeriodKey = $this->getDefaultPeriodKey($this->timeRange);
        $user = auth()->user();
        if ($user) {
            $this->privacyShareProfile = (bool) ($user->share_profile_with_friends ?? true);
            $this->privacyShareTasks = (bool) ($user->share_daily_tasks ?? true);
            $this->privacyShareAchievements = (bool) ($user->share_achievements ?? true);
            $this->privacyAllowNudges = (bool) ($user->allow_friend_nudges ?? true);

            // Check for unacknowledged incoming nudges in the last 24h
            $incoming = FriendNudge::where('receiver_id', $user->id)
                ->whereNull('acknowledged_at')
                ->where('created_at', '>=', now()->subHours(24))
                ->latest()
                ->first();

            if ($incoming) {
                $this->activeIncomingNudgeId = $incoming->id;
                $this->showIncomingNudgeModal = true;
            }
        }
    }

    public function setTimeRange($range)
    {
        if (in_array($range, ['weekly', 'monthly', 'yearly', 'all_time'])) {
            $this->timeRange = $range;
            $this->selectedPeriodKey = $this->getDefaultPeriodKey($range);
        }
    }

    public function getDefaultPeriodKey($range)
    {
        $now = now('Asia/Jakarta');
        return match($range) {
            'weekly' => $now->format('Y-\WW'),
            'monthly' => $now->format('Y-m'),
            'yearly' => $now->format('Y'),
            'all_time' => 'all_time',
            default => $now->format('Y-m')
        };
    }

    public function setLeaderboardScope($scope)
    {
        if (in_array($scope, ['global', 'friends'])) {
            $this->leaderboardScope = $scope;
        }
    }

    public function challengeFriend($friendId)
    {
        $friend = User::find($friendId);
        if (!$friend) return;

        $this->sendNudge($friendId);
        $this->dispatch('toast', message: "⚔️ Tantangan produktivitas dikirim ke {$friend->name}!", type: 'success');
    }
    
    public function setView($view)
    {
        if (in_array($view, ['hub', 'community'])) {
            $this->currentView = $view;
        }
    }

    public function updateSort($sort)
    {
        $this->sortView = $sort;
    }
    
    public function updateTag($tag)
    {
        $this->selectedTag = $tag;
    }

    public function toggleBookmark($postId)
    {
        if (in_array($postId, $this->bookmarkedPostIds)) {
            $this->bookmarkedPostIds = array_values(array_diff($this->bookmarkedPostIds, [$postId]));
            $this->dispatch('toast', message: 'Topik dihapus dari bookmark', type: 'info');
        } else {
            $this->bookmarkedPostIds[] = $postId;
            $this->dispatch('toast', message: 'Topik disimpan ke bookmark! 🔖', type: 'success');
        }
    }

    public function togglePin($postId)
    {
        $post = Post::findOrFail($postId);
        if ($post->user_id === auth()->id() || (auth()->user()->email ?? '') === 'admin@atoma.app') {
            $post->update(['is_pinned' => !$post->is_pinned]);
            $this->dispatch('toast', message: $post->is_pinned ? 'Topik disematkan di atas (Pinned) 📌' : 'Sematkan topik dilepas', type: 'success');
        }
    }

    public function deletePost($postId)
    {
        $post = Post::findOrFail($postId);
        if ($post->user_id === auth()->id()) {
            $post->comments()->delete();
            $post->likes()->delete();
            $post->delete();
            if ($this->activeTopicId === $postId) {
                $this->activeTopicId = null;
            }
            $this->dispatch('toast', message: 'Postingan berhasil dihapus!', type: 'success');
        }
    }
    
    public function sendRequest($friendId)
    {
        $userId = auth()->id();
        if (!$userId || $userId == $friendId) {
            return;
        }

        $existing = Friendship::where(function($q) use ($userId, $friendId) {
            $q->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function($q) use ($userId, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $userId);
        })->first();

        if ($existing) {
            if ($existing->status === 'accepted') {
                $this->dispatch('toast', message: __('Kalian sudah berteman!'), type: 'info');
                return;
            }
            if ($existing->user_id == $userId) {
                $this->dispatch('toast', message: __('Permintaan pertemanan sudah dikirim sebelumnya.'), type: 'info');
                return;
            }
            // If the other user already sent a request, auto-accept it!
            $existing->update(['status' => 'accepted']);
            $this->dispatch('toast', message: __('Permintaan pertemanan diterima! Sekarang kalian berteman.'), type: 'success');
            return;
        }

        Friendship::create([
            'user_id' => $userId,
            'friend_id' => $friendId,
            'status' => 'pending'
        ]);

        $targetUser = User::find($friendId);
        if ($targetUser) {
            $targetUser->notify(new AppNotification([
                'icon' => '👥',
                'message' => __(':name mengirimkan permintaan pertemanan kepadamu!', ['name' => auth()->user()->name]),
            ]));
        }

        $this->dispatch('toast', message: __('Permintaan pertemanan berhasil dikirim!'), type: 'success');
    }

    public function addFriend($friendId)
    {
        $this->sendRequest($friendId);
    }

    public function acceptRequest($requestId)
    {
        $userId = auth()->id();
        $friendship = Friendship::where('id', $requestId)->where('friend_id', $userId)->first()
            ?? Friendship::where('user_id', $requestId)->where('friend_id', $userId)->first();

        if ($friendship) {
            $friendship->update(['status' => 'accepted']);

            $requester = $friendship->user;
            if ($requester) {
                $requester->notify(new AppNotification([
                    'icon' => '🤝',
                    'message' => __(':name menerima permintaan pertemananmu!', ['name' => auth()->user()->name]),
                ]));
            }

            $this->dispatch('toast', message: __('Permintaan pertemanan berhasil diterima!'), type: 'success');
        }
    }

    public function acceptFriend($friendId)
    {
        $this->acceptRequest($friendId);
    }

    public function rejectRequest($requestId)
    {
        $userId = auth()->id();
        $friendship = Friendship::where('id', $requestId)->where('friend_id', $userId)->first()
            ?? Friendship::where('user_id', $requestId)->where('friend_id', $userId)->first();

        if ($friendship) {
            $friendship->delete();
            $this->dispatch('toast', message: __('Permintaan pertemanan ditolak.'), type: 'nudge');
        }
    }

    public function declineFriend($friendId)
    {
        $this->rejectRequest($friendId);
    }

    public function cancelRequest($requestId)
    {
        $userId = auth()->id();
        $friendship = Friendship::where('id', $requestId)->where('user_id', $userId)->first()
            ?? Friendship::where('friend_id', $requestId)->where('user_id', $userId)->first();

        if ($friendship) {
            $friendship->delete();
            $this->dispatch('toast', message: __('Permintaan pertemanan dibatalkan.'), type: 'nudge');
        }
    }

    public function removeFriend($friendId)
    {
        $userId = auth()->id();
        Friendship::where(function($q) use ($userId, $friendId) {
            $q->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function($q) use ($userId, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $userId);
        })->delete();

        $this->dispatch('toast', message: __('Teman berhasil dihapus dari daftar.'), type: 'nudge');
    }
    
    public function setFriendSubTab($subTab)
    {
        if (in_array($subTab, ['friends', 'requests', 'sent', 'find'])) {
            $this->friendSubTab = $subTab;
        }
    }

    public function openFriendProfile($friendId)
    {
        $friend = User::with(['skills', 'tasks'])->find($friendId);
        if ($friend) {
            $this->selectedFriendId = $friendId;
            $this->showProfileModal = true;
        }
    }

    public function closeFriendProfile()
    {
        $this->showProfileModal = false;
        $this->selectedFriendId = null;
    }

    public function openPrivacyModal()
    {
        $user = auth()->user();
        $this->privacyShareProfile = (bool) ($user->share_profile_with_friends ?? true);
        $this->privacyShareTasks = (bool) ($user->share_daily_tasks ?? true);
        $this->privacyShareAchievements = (bool) ($user->share_achievements ?? true);
        $this->privacyAllowNudges = (bool) ($user->allow_friend_nudges ?? true);
        $this->showPrivacyModal = true;
    }

    public function closePrivacyModal()
    {
        $this->showPrivacyModal = false;
    }

    public function savePrivacySettings()
    {
        $user = auth()->user();
        $user->update([
            'share_profile_with_friends' => $this->privacyShareProfile,
            'share_daily_tasks' => $this->privacyShareTasks,
            'share_achievements' => $this->privacyShareAchievements,
            'allow_friend_nudges' => $this->privacyAllowNudges,
        ]);
        $this->showPrivacyModal = false;
        $this->dispatch('toast', message: '✅ Pengaturan persetujuan & privasi berhasil disimpan!', type: 'success');
    }

    public function sendTaskReminder($friendId)
    {
        $friend = User::find($friendId);
        if (!$friend) return;

        if (!$friend->allow_friend_nudges) {
            $this->dispatch('toast', message: "{$friend->name} menonaktifkan pengingat.", type: 'warning');
            return;
        }

        $user = auth()->user();
        Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
            'content' => "⏰ Hai {$friend->name}! Yuk selesaikan target tugasmu hari ini. Semangat produktif! 🔥",
        ]);

        $friend->notify(new \App\Notifications\AppNotification([
            'icon' => '⏰',
            'message' => '<strong>' . $user->name . '</strong> mengingatkanmu untuk menyelesaikan target tugas harian!'
        ]));

        $this->dispatch('toast', message: "⏰ Pengingat tugas berhasil dikirim ke {$friend->name}!", type: 'success');
    }

    public function openSenggolModal($friendId)
    {
        $this->targetFriendId = $friendId;
        $activeDuel = $this->activeDuels->get($friendId);
        $this->selectedNudgeType = ($activeDuel && $activeDuel->status === 'accepted') ? 'task' : 'bacok';
        $this->customRoastMessage = '';
        $this->selectedRoastTemplate = $this->roastTemplates[0] ?? '';
        $this->showSenggolModal = true;
    }

    public function notifyActiveDuel($friendId)
    {
        $friend = User::find($friendId);
        $name = $friend ? $friend->name : 'teman';
        $duel = $this->activeDuels->get($friendId);
        if ($duel) {
            $remMin = max(0, now()->diffInMinutes($duel->expires_at, false));
            $hours = intdiv($remMin, 60);
            $this->dispatch('toast', message: "⚔️ Kamu sedang dalam duel aktif dengan {$name}! Sisa waktu {$hours} jam lagi.", type: 'info');
        }
    }

    public function closeSenggolModal()
    {
        $this->showSenggolModal = false;
        $this->targetFriendId = null;
    }

    public function setNudgeType($type)
    {
        if ($type === 'bacok' && $this->targetFriendId) {
            $duel = $this->activeDuels->get($this->targetFriendId);
            if ($duel && $duel->status === 'accepted') {
                $this->dispatch('toast', message: '⚔️ Kamu sedang dalam duel aktif dengan teman ini!', type: 'warning');
                return;
            }
        }
        $this->selectedNudgeType = $type;
    }

    public function selectRoastTemplate($template)
    {
        $this->selectedRoastTemplate = $template;
        $this->customRoastMessage = $template;
    }

    public function getCooldownRemainingSeconds($friendId, $type = null): int
    {
        $query = FriendNudge::where('sender_id', auth()->id())
            ->where('receiver_id', $friendId)
            ->where('created_at', '>=', now()->subHours(2));

        if ($type) {
            $query->where('nudge_type', $type);
        }

        $last = $query->latest()->first();
        if (!$last) return 0;

        $elapsed = now()->diffInSeconds($last->created_at);
        return max(0, 7200 - $elapsed);
    }

    public function executeSenggol($friendId = null, $type = null, $message = null)
    {
        $friendId = $friendId ?: $this->targetFriendId;
        $type = $type ?: $this->selectedNudgeType;
        $friend = User::find($friendId);
        if (!$friend) return;

        if ($type === 'bacok' && $this->activeDuels->has($friendId)) {
            $existing = $this->activeDuels->get($friendId);
            if ($existing && $existing->status === 'accepted') {
                $this->dispatch('toast', message: "⚔️ Kamu sedang dalam duel aktif dengan {$friend->name}!", type: 'warning');
                return;
            }
        }

        if (!$friend->allow_friend_nudges) {
            $this->dispatch('toast', message: "{$friend->name} menonaktifkan colekan & senggolan.", type: 'warning');
            return;
        }

        $cooldown = $this->getCooldownRemainingSeconds($friendId, $type);
        if ($cooldown > 0) {
            $mins = (int) ceil($cooldown / 60);
            $this->dispatch('toast', message: "⏳ Baru saja menyenggol {$friend->name}! Tunggu {$mins} menit lagi.", type: 'warning');
            return;
        }

        $user = auth()->user();
        $finalMsg = $message ?: match($type) {
            'bacok' => "⚔️ {$user->name} menantangmu duel senggol bacok 24 jam! Selesaikan 3 tugas untuk menang!",
            'fire' => "🔥 {$user->name} membakar semangatmu! Pertahankan streak produktivitasmu!",
            'task' => "⏰ {$user->name} menyenggolmu: Jangan lupa bereskan target tugasmu hari ini!",
            'shield' => "🛡️ {$user->name} mengirimkan booster proteksi streak untukmu!",
            'roast' => $this->customRoastMessage ?: ($this->selectedRoastTemplate ?: "Kopi udah dingin, tugas belum disentuh!"),
            default => "👋 {$user->name} mencolekmu!"
        };

        $nudge = FriendNudge::create([
            'sender_id' => $user->id,
            'receiver_id' => $friend->id,
            'nudge_type' => $type,
            'message' => $finalMsg,
            'status' => 'pending',
            'target_task_count' => 3,
            'xp_reward' => 50,
            'expires_at' => now()->addHours(24),
        ]);

        broadcast(new NudgeSent($user, $friend->id, $type, $finalMsg, $nudge->id))->toOthers();

        $icon = match($type) {
            'bacok' => '⚔️',
            'fire' => '🔥',
            'task' => '⏰',
            'shield' => '🛡️',
            'roast' => '🥊',
            default => '👈'
        };

        $friend->notify(new \App\Notifications\AppNotification([
            'icon' => $icon,
            'message' => '<strong>' . $user->name . '</strong>: ' . $finalMsg
        ]));

        $sound = match($type) {
            'bacok' => 'slash',
            'roast' => 'bonk',
            'shield' => 'shield',
            default => 'success'
        };
        $this->dispatch('play-sound', preset: $sound);

        $ach = new AchievementService();
        $unlockedAchievements = $ach->checkAndUnlock($user);
        foreach ($unlockedAchievements as $unlockedBadge) {
            $this->dispatch('achievement-unlocked', badge: $unlockedBadge);
        }

        $toastMsg = match($type) {
            'bacok' => "⚔️ Tantangan Senggol Bacok 24 jam berhasil dikirim ke {$friend->name}!",
            'fire' => "🔥 Api semangat berhasil dikirim ke {$friend->name}!",
            'task' => "⏰ Alarm tugas berhasil dikirim ke {$friend->name}!",
            'roast' => "🥊 Tamparan realita terkirim ke {$friend->name}!",
            'shield' => "🛡️ Booster proteksi streak terkirim ke {$friend->name}!",
            default => "👈 Senggolan berhasil dikirim ke {$friend->name}!"
        };

        $this->dispatch('toast', message: $toastMsg, type: 'success');
        $this->closeSenggolModal();
    }

    public function sendNudge($friendId, $type = 'cheer')
    {
        $mappedType = match($type) {
            'challenge' => 'bacok',
            'fire' => 'fire',
            'task' => 'task',
            'shield' => 'shield',
            'roast' => 'roast',
            default => 'task'
        };
        $this->executeSenggol($friendId, $mappedType);
    }

    public function respondToDuel($nudgeId, $action = 'accept')
    {
        $nudge = FriendNudge::where('id', $nudgeId)->where('receiver_id', auth()->id())->first();
        if (!$nudge) return;

        if ($action === 'accept') {
            $nudge->update([
                'status' => 'accepted',
                'acknowledged_at' => now(),
            ]);
            $this->dispatch('toast', message: "⚔️ Duel Senggol Bacok diterima! Selesaikan {$nudge->target_task_count} tugas hari ini!", type: 'success');
            $this->dispatch('play-sound', preset: 'slash');
        } else {
            $nudge->update([
                'status' => 'declined',
                'acknowledged_at' => now(),
            ]);
            $this->dispatch('toast', message: "Duel dilewati.", type: 'info');
        }

        $this->showIncomingNudgeModal = false;
        $this->activeIncomingNudgeId = null;
    }

    public function acknowledgeIncomingNudge($nudgeId, $senggolBalik = false)
    {
        $nudge = FriendNudge::where('id', $nudgeId)->where('receiver_id', auth()->id())->first();
        $senderId = $nudge ? $nudge->sender_id : null;
        if ($nudge) {
            $nudge->update(['acknowledged_at' => now()]);
        }

        $this->showIncomingNudgeModal = false;
        $this->activeIncomingNudgeId = null;

        if ($senggolBalik && $senderId) {
            $this->openSenggolModal($senderId);
        }
    }

    public function getHeadToHeadScore($friendId): array
    {
        $myId = auth()->id();
        $myWins = FriendNudge::where('winner_id', $myId)
            ->where(function($q) use ($myId, $friendId) {
                $q->where(function($sq) use ($myId, $friendId) {
                    $sq->where('sender_id', $myId)->where('receiver_id', $friendId);
                })->orWhere(function($sq) use ($myId, $friendId) {
                    $sq->where('sender_id', $friendId)->where('receiver_id', $myId);
                });
            })->count();

        $friendWins = FriendNudge::where('winner_id', $friendId)
            ->where(function($q) use ($myId, $friendId) {
                $q->where(function($sq) use ($myId, $friendId) {
                    $sq->where('sender_id', $myId)->where('receiver_id', $friendId);
                })->orWhere(function($sq) use ($myId, $friendId) {
                    $sq->where('sender_id', $friendId)->where('receiver_id', $myId);
                });
            })->count();

        $totalExchanges = FriendNudge::where(function($q) use ($myId, $friendId) {
            $q->where(function($sq) use ($myId, $friendId) {
                $sq->where('sender_id', $myId)->where('receiver_id', $friendId);
            })->orWhere(function($sq) use ($myId, $friendId) {
                $sq->where('sender_id', $friendId)->where('receiver_id', $myId);
            });
        })->count();

        return [
            'my_wins' => $myWins,
            'friend_wins' => $friendWins,
            'total_exchanges' => $totalExchanges,
        ];
    }

    #[Computed]
    public function targetFriendUser()
    {
        if (!$this->targetFriendId) return null;
        return User::find($this->targetFriendId);
    }

    #[Computed]
    public function activeIncomingNudge()
    {
        if (!$this->activeIncomingNudgeId) return null;
        return FriendNudge::with('sender')->find($this->activeIncomingNudgeId);
    }

    #[Computed]
    public function selectedFriendUser()
    {
        if (!$this->selectedFriendId) return null;
        return User::with(['skills', 'tasks'])->find($this->selectedFriendId);
    }

    #[Computed]
    public function selectedFriend()
    {
        return $this->selectedFriendUser;
    }
    
    public function createPost()
    {
        $this->validate([
            'postTitle' => 'required|max:255',
            'postContent' => 'required|max:2000'
        ]);
        
        $finalContent = $this->postContent;
        if (!str_contains($finalContent, '#' . $this->postCategory) && $this->postCategory !== 'General') {
            $finalContent .= "\n\n#" . $this->postCategory;
        }
        
        Post::create([
            'user_id' => auth()->id(),
            'title' => $this->postTitle,
            'content' => $finalContent,
        ]);
        
        $this->postTitle = '';
        $this->postContent = '';
        $this->postCategory = 'General';
        $this->showCreateTopicModal = false;
        $this->dispatch('toast', message: 'Topik berhasil diterbitkan! 🚀', type: 'success');
    }
    
    public function openTopic($topicId)
    {
        $this->activeTopicId = $topicId;
    }
    
    public function closeTopic()
    {
        $this->activeTopicId = null;
    }
    
    public function sendThreadComment()
    {
        $this->validate(['threadCommentContent' => 'required|max:1000']);
        
        if (!$this->activeTopicId) return;
        
        Comment::create([
            'post_id' => $this->activeTopicId,
            'user_id' => auth()->id(),
            'content' => $this->threadCommentContent,
        ]);
        
        Post::where('id', $this->activeTopicId)->update(['updated_at' => now()]);

        $this->threadCommentContent = '';
        $this->dispatch('thread-comment-sent');
    }
    
    public function toggleLike($postId)
    {
        $userId = auth()->id();
        $post = Post::findOrFail($postId);
        $like = Like::where('user_id', $userId)->where('post_id', $postId)->first();
        
        if ($like) {
            $like->delete();
            $post->decrement('likes_count');
            $post->touch();
        } else {
            Like::create(['user_id' => $userId, 'post_id' => $postId]);
            $post->increment('likes_count');
            $post->touch();
            
            if ($post->user_id !== $userId) {
                $post->user->notify(new \App\Notifications\AppNotification([
                    'icon' => '❤️',
                    'message' => '<strong>' . auth()->user()->name . '</strong> liked your post.'
                ]));
            }
        }
    }
    
    #[Computed]
    public function posts()
    {
        $query = Post::with(['user', 'likes', 'comments.user']);
        
        // Search filter
        if (!empty($this->search)) {
            $query->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('content', 'like', '%' . $this->search . '%');
        }
        
        // Tag filter
        if ($this->selectedTag !== 'All') {
            if ($this->selectedTag === 'General') {
                $query->where(function($q) {
                    $q->where('content', 'not like', '%#Milestones%')
                      ->where('content', 'not like', '%#Discussions%')
                      ->where('content', 'not like', '%#Questions%');
                });
            } else {
                $query->where('content', 'like', '%#' . $this->selectedTag . '%');
            }
        }
        
        // Pinned posts always at the very top
        $query->orderBy('is_pinned', 'desc');

        // Sort: newest posts are ordered by latest activity (updated_at desc) then created_at desc
        if ($this->sortView === 'newest') {
            $query->orderBy('updated_at', 'desc')->orderBy('created_at', 'desc');
        } else if ($this->sortView === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else if ($this->sortView === 'popular') {
            $query->orderBy('likes_count', 'desc');
        }
        
        return $query->get();
    }
    
    #[Computed]
    public function tagCounts()
    {
        // For a small app, fetching all contents to count is okay. For large apps, use aggregate queries.
        $posts = Post::select('content')->get();
        
        $milestones = 0;
        $discussions = 0;
        $questions = 0;
        
        foreach ($posts as $post) {
            if (str_contains($post->content, '#Milestones')) $milestones++;
            if (str_contains($post->content, '#Discussions')) $discussions++;
            if (str_contains($post->content, '#Questions')) $questions++;
        }
        
        $total = $posts->count();
        $general = $total - ($milestones + $discussions + $questions);
        
        return [
            'All' => $total,
            'Milestones' => $milestones,
            'Discussions' => $discussions,
            'Questions' => $questions,
            'General' => max(0, $general)
        ];
    }
    
    #[Computed]
    public function activeTopic()
    {
        if (!$this->activeTopicId) return null;
        return Post::with(['user', 'comments.user'])->find($this->activeTopicId);
    }
    
    #[Computed]
    public function friends()
    {
        $userId = auth()->id();
        $friendships = Friendship::where('status', 'accepted')
            ->where(function($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('friend_id', $userId);
            })->get();
            
        $friendIds = $friendships->map(function($f) use ($userId) {
            return $f->user_id == $userId ? $f->friend_id : $f->user_id;
        });
        
        $today = Carbon::today()->toDateString();
        $friends = User::whereIn('id', $friendIds)
            ->with(['tasks' => function($q) use ($today) {
                $q->whereDate('date', $today);
            }])
            ->get();

        foreach ($friends as $friend) {
            $tasks = $friend->tasks;
            $total = $tasks->count();
            $completed = $tasks->where('is_completed', true)->count();
            $friend->today_total_tasks = $total;
            $friend->today_completed_tasks = $completed;
            $friend->today_completion_rate = $total > 0 ? (int) round(($completed / $total) * 100) : null;
        }

        return $friends;
    }

    #[Computed]
    public function activeDuels()
    {
        $userId = auth()->id();
        return FriendNudge::where('nudge_type', 'bacok')
            ->whereIn('status', ['accepted', 'pending'])
            ->where('expires_at', '>', now())
            ->where(function($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->get()
            ->keyBy(fn($d) => $d->sender_id === $userId ? $d->receiver_id : $d->sender_id);
    }
    
    #[Computed]
    public function sentRequests()
    {
        $userId = auth()->id();
        return Friendship::where('user_id', $userId)->where('status', 'pending')->pluck('friend_id');
    }
    
    #[Computed]
    public function pendingRequests()
    {
        $userId = auth()->id();
        return Friendship::with('user')
            ->where('friend_id', $userId)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }
    
    #[Computed]
    public function sentRequestsUsers()
    {
        $userId = auth()->id();
        return Friendship::with('friend')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    #[Computed]
    public function activeFocusFriends()
    {
        $userId = auth()->id();
        $friendships = Friendship::where('status', 'accepted')
            ->where(function($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('friend_id', $userId);
            })->get();
            
        $friendIds = $friendships->map(function($f) use ($userId) {
            return $f->user_id == $userId ? $f->friend_id : $f->user_id;
        });

        $friends = User::whereIn('id', $friendIds)->get();
        $active = [];

        foreach ($friends as $friend) {
            $focus = \Illuminate\Support\Facades\Cache::get("user_focus_{$friend->id}");
            if ($focus && !empty($focus['mode'])) {
                $active[] = [
                    'friend' => $friend,
                    'focus' => $focus,
                ];
            }
        }

        return collect($active);
    }

    #[Computed]
    public function weeklyCommunityQuest()
    {
        return \App\Services\CommunityQuestService::getWeeklyQuest(auth()->user());
    }

    public function claimCommunityQuestReward()
    {
        $result = \App\Services\CommunityQuestService::claimReward(auth()->user());
        if ($result['success']) {
            $this->dispatch('toast', message: $result['message'], type: 'success');
            $this->dispatch('play-sound', preset: 'fanfare');
            $this->dispatch('trigger-confetti');
        } else {
            $this->dispatch('toast', message: $result['message'], type: 'error');
        }
    }

    public function cheerFocusFriend($friendId)
    {
        $friend = User::find($friendId);
        if (!$friend) return;

        FriendNudge::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $friend->id,
            'nudge_type' => 'fire',
            'message' => '👏 ' . auth()->user()->name . ' memberikan tepuk bahu dan menyemangati sesi fokusmu!',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->dispatch('toast', message: "Tepuk bahu terkirim ke {$friend->name}! 👏", type: 'success');
    }

    #[Computed]
    public function leaderboard()
    {
        $userId = auth()->id();
        $friends = $this->friends;
        $friendIds = $friends->pluck('id')->toArray();
        $friendIds[] = $userId;

        if (count($friendIds) < 3) {
            $topGlobalIds = User::orderBy('monthly_xp', 'desc')->limit(10)->pluck('id')->toArray();
            $friendIds = array_unique(array_merge($friendIds, $topGlobalIds));
        }

        return User::whereIn('id', $friendIds)
            ->orderBy('monthly_xp', 'desc')
            ->orderBy('xp', 'desc')
            ->limit(10)
            ->get();
    }
    
    #[Computed]
    public function recentChats()
    {
        $userId = auth()->id();
        
        $messages = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        $chatPartners = [];
        $recentChats = [];
        
        foreach ($messages as $msg) {
            $partnerId = $msg->sender_id == $userId ? $msg->receiver_id : $msg->sender_id;
            
            if (!in_array($partnerId, $chatPartners)) {
                $chatPartners[] = $partnerId;
                $recentChats[$partnerId] = [
                    'partner_id' => $partnerId,
                    'latest_message' => $msg,
                    'unread_count' => Message::where('sender_id', $partnerId)->where('receiver_id', $userId)->where('is_read', false)->count()
                ];
            }
        }
        
        foreach ($this->friends as $friend) {
            if (!in_array($friend->id, $chatPartners)) {
                $chatPartners[] = $friend->id;
                $recentChats[$friend->id] = [
                    'partner_id' => $friend->id,
                    'latest_message' => null,
                    'unread_count' => 0
                ];
            }
        }
        
        $users = User::whereIn('id', $chatPartners)->get()->keyBy('id');
        
        $result = [];
        foreach ($chatPartners as $partnerId) {
            if (isset($users[$partnerId])) {
                $result[] = (object) [
                    'user' => $users[$partnerId],
                    'latest_message' => $recentChats[$partnerId]['latest_message'],
                    'unread_count' => $recentChats[$partnerId]['unread_count']
                ];
            }
        }
        
        return $result;
    }
    
    #[Computed]
    public function activeChatFriend()
    {
        if (!$this->activeChatFriendId) return null;
        return User::find($this->activeChatFriendId);
    }
    
    #[Computed]
    public function activeChatMessages()
    {
        if (!$this->activeChatFriendId) return collect();
        $userId = auth()->id();
        return Message::where(function($q) use ($userId) {
                $q->where('sender_id', $userId)->where('receiver_id', $this->activeChatFriendId);
            })
            ->orWhere(function($q) use ($userId) {
                $q->where('sender_id', $this->activeChatFriendId)->where('receiver_id', $userId);
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }
    
    public function openChat($friendId)
    {
        $this->activeChatFriendId = $friendId;
        
        // Mark as read
        Message::where('sender_id', $friendId)
            ->where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);
            
        // The frontend Alpine will switch tab to 'chat'
    }
    
    public function closeChat()
    {
        $this->activeChatFriendId = null;
    }
    
    public function sendChatMessage()
    {
        $this->validate([
            'chatMessageContent' => 'required|string|max:1000'
        ]);
        
        Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $this->activeChatFriendId,
            'content' => $this->chatMessageContent,
        ]);
        
        $this->reset('chatMessageContent');
        $this->dispatch('chat-message-sent');
    }
    
    #[Computed]
    public function suggestions()
    {
        $userId = auth()->id();
        $friendships = Friendship::where(function($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('friend_id', $userId);
            })->get();
            
        $friendIds = $friendships->map(function($f) use ($userId) {
            return $f->user_id == $userId ? $f->friend_id : $f->user_id;
        });
        
        return User::where('id', '!=', $userId)
            ->whereNotIn('id', $friendIds)
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->limit(10)
            ->get();
    }
    
    #[Computed]
    public function availablePeriods()
    {
        $tz = 'Asia/Jakarta';
        $startDate = \Carbon\Carbon::create(2026, 5, 1, 0, 0, 0, $tz)->startOfMonth();
        $now = \Carbon\Carbon::now($tz);

        $monthNames = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];

        if ($this->timeRange === 'weekly') {
            $weeks = [];
            $currW = $now->copy()->startOfWeek();
            $prevW = $now->copy()->subWeek()->startOfWeek();
            while ($currW->greaterThanOrEqualTo($startDate->copy()->startOfWeek())) {
                $endW = $currW->copy()->endOfWeek();
                $key = $currW->format('Y-\WW');
                $startMonthName = $monthNames[$currW->format('m')];
                $endMonthName = $monthNames[$endW->format('m')];
                
                $label = $currW->format('d') . ' ' . $startMonthName . ' - ' . $endW->format('d') . ' ' . $endMonthName . ' ' . $endW->format('Y');
                if ($key === $now->format('Y-\WW')) {
                    $label .= ' (Minggu Ini)';
                    $shortLabel = 'Minggu Ini';
                } elseif ($key === $prevW->format('Y-\WW')) {
                    $shortLabel = 'Minggu Lalu';
                } else {
                    $shortLabel = $currW->format('d') . ' - ' . $endW->format('d') . ' ' . substr($endMonthName, 0, 3);
                }
                $weeks[] = ['key' => $key, 'label' => $label, 'short_label' => $shortLabel, 'is_current' => ($key === $now->format('Y-\WW'))];
                $currW->subWeek();
            }
            return $weeks;
        }

        if ($this->timeRange === 'monthly') {
            $months = [];
            $currM = $now->copy()->startOfMonth();
            while ($currM->greaterThanOrEqualTo($startDate)) {
                $key = $currM->format('Y-m');
                $monthName = $monthNames[$currM->format('m')];
                $label = $monthName . ' ' . $currM->format('Y');
                $shortLabel = $monthName;
                if ($key === $now->format('Y-m')) {
                    $label .= ' (Bulan Ini)';
                    $shortLabel = $monthName . ' (Ini)';
                } elseif ($key === '2026-05') {
                    $label .= ' (Rilis Awal)';
                }
                $months[] = ['key' => $key, 'label' => $label, 'short_label' => $shortLabel, 'is_current' => ($key === $now->format('Y-m'))];
                $currM->subMonth();
            }
            return $months;
        }

        if ($this->timeRange === 'yearly') {
            $years = [];
            $currY = (int) $now->format('Y');
            $startYear = 2026;
            for ($y = $currY; $y >= $startYear; $y--) {
                $label = (string) $y;
                $shortLabel = (string) $y;
                if ($y === $currY) {
                    $label .= ' (Tahun Ini)';
                    $shortLabel = $y . ' (Tahun Ini)';
                }
                $years[] = ['key' => (string)$y, 'label' => $label, 'short_label' => $shortLabel, 'is_current' => ($y === $currY)];
            }
            return $years;
        }

        return [['key' => 'all_time', 'label' => 'All-Time Hall of Fame', 'is_current' => true]];
    }
    
    #[Computed]
    public function leaderboardPlayers()
    {
        $userId = auth()->id();
        $isCurrent = ($this->selectedPeriodKey === $this->getDefaultPeriodKey($this->timeRange));

        $query = User::query();

        if ($this->leaderboardScope === 'friends') {
            $friendships = Friendship::where('status', 'accepted')
                ->where(function($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('friend_id', $userId);
                })->get();
                
            $friendIds = $friendships->map(function($f) use ($userId) {
                return (int) ($f->user_id == $userId ? $f->friend_id : $f->user_id);
            })->all();
            $friendIds[] = (int) $userId;
            $friendIds = array_unique($friendIds);
            
            $query->whereIn('id', $friendIds);
        }

        if ($isCurrent) {
            $xpColumn = match($this->timeRange) {
                'weekly' => 'weekly_xp',
                'monthly' => 'monthly_xp',
                'yearly' => 'xp',
                'all_time' => 'xp',
                default => 'monthly_xp'
            };

            $players = $query->orderBy($xpColumn, 'desc')
                ->orderBy('xp', 'desc')
                ->orderBy('longest_streak', 'desc')
                ->limit(20)
                ->get();

            foreach ($players as $p) {
                $p->period_xp = (int) ($p->{$xpColumn} ?? $p->xp);
            }

            return $players;
        }

        // Historical Period Selection:
        // 1. Check LeaderboardSeasonHistory first
        $historyRecords = \App\Models\LeaderboardSeasonHistory::where('period_type', $this->timeRange)
            ->where('period_key', $this->selectedPeriodKey)
            ->orderBy('rank', 'asc')
            ->orderBy('xp', 'desc')
            ->get();

        if ($historyRecords->isNotEmpty()) {
            if ($this->leaderboardScope === 'friends') {
                $historyRecords = $historyRecords->filter(fn($hr) => in_array($hr->user_id, $friendIds ?? []));
            }

            $userIds = $historyRecords->pluck('user_id')->toArray();
            $usersById = User::whereIn('id', $userIds)->get()->keyBy('id');
            $players = collect();

            foreach ($historyRecords as $hr) {
                if (isset($usersById[$hr->user_id])) {
                    $u = clone $usersById[$hr->user_id];
                    $u->period_xp = (int) $hr->xp;
                    $u->period_rank = (int) $hr->rank;
                    $players->push($u);
                }
            }

            return $players->take(20);
        }

        // 2. Fallback for historical periods if history table hasn't recorded yet:
        $now = now('Asia/Jakarta');
        $isPrevWeek = ($this->timeRange === 'weekly' && $this->selectedPeriodKey === $now->copy()->subWeek()->format('Y-\WW'));
        $isPrevMonth = ($this->timeRange === 'monthly' && $this->selectedPeriodKey === $now->copy()->subMonth()->format('Y-m'));

        $fallbackColumn = $isPrevWeek ? 'prev_week_xp' : ($isPrevMonth ? 'prev_month_xp' : 'prev_year_xp');

        $players = $query->orderBy($fallbackColumn, 'desc')
            ->orderBy('xp', 'desc')
            ->limit(20)
            ->get();

        foreach ($players as $p) {
            $p->period_xp = (int) ($p->{$fallbackColumn} ?? 0);
        }

        return $players;
    }

    #[Computed]
    public function userStanding()
    {
        $userId = auth()->id();
        $players = $this->leaderboardPlayers;

        $index = $players->search(fn($p) => $p->id === $userId);

        if ($index !== false) {
            $rank = $index + 1;
            $user = $players->get($index);
            $userXp = $user->period_xp ?? $user->xp;

            $gapXp = 0;
            $nextPlayer = null;
            if ($index > 0) {
                $prevPlayer = $players->get($index - 1);
                $prevXp = $prevPlayer->period_xp ?? $prevPlayer->xp;
                $gapXp = max(0, $prevXp - $userXp + 1);
                $nextPlayer = $prevPlayer;
            }

            return [
                'rank' => $rank,
                'xp' => $userXp,
                'gap_xp' => $gapXp,
                'next_player' => $nextPlayer,
                'user' => $user,
            ];
        }

        $user = auth()->user();
        if (!$user) return null;
        
        $xpColumn = match($this->timeRange) {
            'weekly' => 'weekly_xp',
            'monthly' => 'monthly_xp',
            'all_time' => 'xp',
            default => 'weekly_xp'
        };

        $userXp = (int) ($user->{$xpColumn} ?? $user->xp ?? 0);
        $higherCount = User::where($xpColumn, '>', $userXp)->count();
        $actualRank = $higherCount + 1;

        return [
            'rank' => $actualRank,
            'xp' => $userXp,
            'gap_xp' => 50,
            'next_player' => null,
            'user' => $user,
        ];
    }

    #[Computed]
    public function promotionProgress()
    {
        $user = auth()->user();
        if (!$user) return null;
        return \App\Services\LeagueService::getPromotionProgress($user);
    }

    public function claimSeasonPromotionReward()
    {
        $user = auth()->user();
        if (!$user) return;

        $currentLeague = \App\Services\LeagueService::getLeagueDetails($user->league);
        $rewardCoins = $currentLeague['reward_coins'] ?? 15;

        $user->coins += $rewardCoins;
        $user->league_season_claimed = true;
        $user->save();

        $this->dispatch('toast', message: "🎉 Hadiah Musim Diklaim! +{$rewardCoins} Koin 🪙", type: 'success');
    }

    public function getDivisionBadge($xp, $user = null)
    {
        if ($user && isset($user->league)) {
            return \App\Services\LeagueService::getLeagueDetails($user->league);
        }
        return $this->getLeagueTierDetails($xp);
    }

    public function getLeagueTierDetails($xp)
    {
        if ($xp >= 3500) {
            return \App\Services\LeagueService::getLeagueDetails('master');
        } elseif ($xp >= 3000) {
            return \App\Services\LeagueService::getLeagueDetails('diamond_1');
        } elseif ($xp >= 2500) {
            return \App\Services\LeagueService::getLeagueDetails('diamond_2');
        } elseif ($xp >= 2000) {
            return \App\Services\LeagueService::getLeagueDetails('gold_1');
        } elseif ($xp >= 1700) {
            return \App\Services\LeagueService::getLeagueDetails('gold_2');
        } elseif ($xp >= 1400) {
            return \App\Services\LeagueService::getLeagueDetails('gold_3');
        } elseif ($xp >= 1150) {
            return \App\Services\LeagueService::getLeagueDetails('silver_1');
        } elseif ($xp >= 900) {
            return \App\Services\LeagueService::getLeagueDetails('silver_2');
        } elseif ($xp >= 700) {
            return \App\Services\LeagueService::getLeagueDetails('silver_3');
        } elseif ($xp >= 500) {
            return \App\Services\LeagueService::getLeagueDetails('bronze_1');
        } elseif ($xp >= 350) {
            return \App\Services\LeagueService::getLeagueDetails('bronze_2');
        } else {
            return \App\Services\LeagueService::getLeagueDetails('bronze_3');
        }
    }

    public function getSeasonCountdown()
    {
        $defaultKey = $this->getDefaultPeriodKey($this->timeRange);
        $isCurrent = ($this->selectedPeriodKey === $defaultKey);

        if (!$isCurrent && $this->timeRange !== 'all_time') {
            return '🏆 Rekap Final Musim (Arsip)';
        }

        $now = now('Asia/Jakarta');
        $weeklyDays = max(1, 7 - $now->dayOfWeek);
        $monthlyDays = max(1, $now->daysInMonth - $now->day + 1);
        $yearlyDays = max(1, $now->copy()->endOfYear()->dayOfYear - $now->dayOfYear + 1);

        return match($this->timeRange) {
            'weekly' => "Resets in {$weeklyDays} days (Senin 12:00 WIB)",
            'monthly' => "Resets in {$monthlyDays} days",
            'yearly' => "Resets in {$yearlyDays} days",
            'all_time' => 'All-Time Hall of Fame 🌟',
            default => "Resets in {$monthlyDays} days"
        };
    }

    #[Computed]
    public function globalLeaderboard()
    {
        return $this->leaderboardPlayers;
    }
    
    #[Computed]
    public function friendsLeaderboard()
    {
        return $this->leaderboardPlayers;
    }
    
    public function getRankBadge($xp)
    {
        $div = $this->getDivisionBadge($xp);
        return '<span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider ' . $div['class'] . '">' . $div['name'] . '</span>';
    }
    public function formatPostContent($content)
    {
        $safeContent = htmlspecialchars($content);
        // Replace #hashtags with styled badges
        return preg_replace('/#(\w+)/', '<span class="inline-block bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/50 px-2 py-0.5 rounded-md text-[11px] font-black tracking-wide uppercase mr-1 shadow-sm mt-1 mb-1 transition-transform hover:scale-105 cursor-pointer">#$1</span>', $safeContent);
    }
}; ?>

<div class="pb-28 sm:pb-8 max-w-4xl mx-auto space-y-5" x-data="{ currentView: @entangle('currentView'), showSearch: false, socialTab: 'dm', chatOpen: false }">
    
    <!-- ================= VIEW 1: SOCIAL HUB (Highlight Community Carousel + Category Pills + Bottom Sheet DMs & Friends) ================= -->
    <div x-show="currentView === 'hub'" class="space-y-5" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        
        <!-- 1. TOP HEADER (Avatar + Name + Search & Privacy Actions) -->
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-3">
                <a href="{{ route('profile') }}" wire:navigate class="relative group cursor-pointer shrink-0">
                    <div class="w-12 h-12 rounded-full ring-2 ring-atoma-blue/30 dark:ring-atoma-cyan/40 p-0.5 bg-white dark:bg-gray-800 overflow-hidden shadow-sm group-hover:scale-105 transition-transform">
                        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-full h-full rounded-full object-cover">
                    </div>
                    <div class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full bg-emerald-500 border-2 border-white dark:border-gray-800"></div>
                </a>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white tracking-tight leading-tight">
                        {{ auth()->user()->name }}
                    </h1>
                    <div class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-gray-400">
                        <span class="text-atoma-blue dark:text-atoma-cyan">Lvl {{ auth()->user()->level }}</span>
                        <span>•</span>
                        <span class="text-orange-500 font-extrabold flex items-center gap-0.5">
                            <span>🔥</span> {{ auth()->user()->current_streak }} {{ __('Hari') }}
                        </span>
                        <span>•</span>
                        <span class="text-gray-400 font-medium hidden sm:inline">{{ __('Social Accountability') }}</span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <!-- Bocoran Rank & Liga Trigger -->
                <button type="button" 
                        @click="$dispatch('open-rank-modal')" 
                        class="h-10 px-3 rounded-2xl bg-indigo-50/90 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/60 border border-indigo-200/80 dark:border-indigo-800/60 flex items-center gap-2 text-indigo-700 dark:text-indigo-300 transition-all cursor-pointer shadow-xs select-none group"
                        title="{{ __('Bocoran Semua Rank & Liga') }}">
                    <x-tier-badge league="{{ auth()->user()->league ?? 'bronze_3' }}" size="xs" class="group-hover:scale-110 transition-transform" />
                    <span class="text-xs font-black hidden xs:inline">{{ __('Bocoran Rank') }}</span>
                </button>

                <!-- Search Toggle Button -->
                <button type="button" 
                        @click="showSearch = !showSearch" 
                        class="w-10 h-10 rounded-2xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 flex items-center justify-center transition-all cursor-pointer shadow-xs"
                        :class="showSearch ? 'bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan ring-2 ring-atoma-blue/30' : ''"
                        title="{{ __('Cari diskusi atau teman') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
                
                <!-- Privacy Settings Button -->
                <button type="button" 
                        wire:click="openPrivacyModal" 
                        class="w-10 h-10 rounded-2xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 flex items-center justify-center transition-all cursor-pointer shadow-xs"
                        title="{{ __('Pengaturan Privasi Profil') }}">
                    <x-icon.gear class="w-5 h-5" />
                </button>
            </div>
        </div>

        <!-- Expandable Search Bar -->
        <div x-show="showSearch" 
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="relative"
             style="display: none;">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input type="text" 
                   wire:model.live.debounce.300ms="search" 
                   placeholder="{{ __('Cari topik diskusi atau nama teman...') }}" 
                   class="w-full pl-10 pr-9 py-2.5 text-xs sm:text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-atoma-blue shadow-sm">
            @if($search)
                <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer">
                    ✕
                </button>
            @endif
        </div>


        <!-- 3. "COMMUNITY" SECTION (Highlight Horizontal Snap Carousel with Direct Entry to Full Community Page) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <button type="button" 
                        wire:click="setView('community')" 
                        @click="currentView = 'community'"
                        class="flex items-center gap-2 group cursor-pointer text-left">
                    <h2 class="text-lg sm:text-xl font-black text-gray-900 dark:text-white tracking-tight group-hover:text-atoma-blue dark:group-hover:text-atoma-cyan transition-colors flex items-center gap-1.5">
                        <span>{{ __('Community Feed') }}</span>
                        <span class="text-atoma-blue dark:text-atoma-cyan text-base font-bold transition-transform group-hover:translate-x-0.5">›</span>
                    </h2>
                    <span class="text-xs font-bold text-gray-400">({{ $this->posts->count() }})</span>
                </button>

                <button type="button" 
                        wire:click="setView('community')" 
                        @click="currentView = 'community'"
                        class="text-xs font-black text-atoma-blue dark:text-atoma-cyan hover:underline flex items-center gap-1 cursor-pointer bg-atoma-ice dark:bg-atoma-blue/20 px-3 py-1.5 rounded-xl border border-atoma-aqua/50 dark:border-atoma-blue/40 shadow-2xs transition-all hover:scale-[1.02]">
                    <span>{{ __('Buka Forum Penuh') }}</span>
                    <span class="text-sm font-bold">➔</span>
                </button>
            </div>

            <!-- Horizontal Snap Highlight Carousel -->
            <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory hide-scrollbar pb-2 pt-1 -mx-2 px-2">
                @forelse($this->posts as $post)
                    @php
                        $commentCount = $post->comments->count();
                        $uniqueParticipants = $post->comments->pluck('user')->push($post->user)->filter()->unique('id')->values();
                        $previewCount = 3;
                        $extraMembers = max(0, $uniqueParticipants->count() - $previewCount);
                        $firstComment = $post->comments->last();
                        
                        $dashColor = match($post->category ?? 'General') {
                            'Milestones' => 'bg-amber-500',
                            'Discussions' => 'bg-emerald-500',
                            'Questions' => 'bg-rose-500',
                            default => 'bg-atoma-cyan'
                        };
                    @endphp

                    <div wire:key="hub-community-card-{{ $post->id }}"
                         wire:click="openTopic({{ $post->id }})"
                         class="snap-start shrink-0 w-64 sm:w-72 bg-white dark:bg-gray-800 rounded-2xl p-4 sm:p-5 border border-gray-100 dark:border-gray-700/80 shadow-sm hover:shadow-md hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 transition-all cursor-pointer group flex flex-col justify-between space-y-3">
                        
                        <div>
                            <div class="flex items-start gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-atoma-blue/10 to-atoma-cyan/20 dark:from-atoma-blue/30 dark:to-atoma-cyan/30 border border-atoma-aqua/50 dark:border-atoma-blue/40 flex items-center justify-center text-xl shrink-0 group-hover:scale-105 transition-transform overflow-hidden relative">
                                    @if($post->user->avatar_url)
                                        <img src="{{ $post->user->avatar_url }}" alt="{{ $post->user->name }}" class="w-full h-full object-cover">
                                    @else
                                        <span>💬</span>
                                    @endif
                                    @if($post->is_pinned)
                                        <span class="absolute top-0.5 right-0.5 text-[8px] bg-atoma-blue text-white font-black px-1 rounded">{{ __('PINNED') }}</span>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="w-4 h-1 rounded-full {{ $dashColor }} mb-1"></div>
                                    <h3 class="text-sm font-black text-gray-900 dark:text-white leading-tight truncate group-hover:text-atoma-blue dark:group-hover:text-atoma-cyan transition-colors">
                                        {{ $post->title ?? __('Untitled Topic') }}
                                    </h3>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 font-medium truncate mt-0.5">
                                        {{ $post->user->name }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button type="button" 
                                            wire:click.stop="toggleBookmark({{ $post->id }})" 
                                            class="p-1 text-gray-300 hover:text-amber-500 transition-colors cursor-pointer"
                                            title="{{ __('Simpan Topik') }}">
                                        <svg class="w-4 h-4 {{ in_array($post->id, $bookmarkedPostIds) ? 'text-amber-500 fill-current' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                                    </button>

                                    @if($commentCount > 0)
                                        <span class="shrink-0 w-5 h-5 rounded-full bg-rose-500 text-white text-[10px] font-black flex items-center justify-center shadow-xs">
                                            {{ $commentCount > 9 ? '9+' : $commentCount }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 line-clamp-2 leading-relaxed">
                                @if($firstComment)
                                    <strong class="text-gray-900 dark:text-white font-bold">{{ $firstComment->user->name }}:</strong> {{ $firstComment->content }}
                                @else
                                    {{ str(strip_tags($post->content))->limit(75) }}
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700/50">
                            <div class="flex items-center -space-x-2">
                                @foreach($uniqueParticipants->take($previewCount) as $member)
                                    <img src="{{ $member->avatar_url }}" alt="{{ $member->name }}" class="w-6 h-6 rounded-full object-cover border-2 border-white dark:border-gray-800 bg-white">
                                @endforeach
                                @if($extraMembers > 0)
                                    <span class="w-6 h-6 rounded-full bg-atoma-blue text-white border-2 border-white dark:border-gray-800 text-[9px] font-black flex items-center justify-center">
                                        +{{ $extraMembers }}
                                    </span>
                                @endif
                            </div>

                            <span class="text-[10px] font-bold text-gray-400">
                                {{ $post->created_at->diffForHumans(null, true, true) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="w-full bg-white dark:bg-gray-800 rounded-3xl p-8 text-center text-gray-400 border border-dashed border-gray-200 dark:border-gray-700">
                        <div class="text-3xl mb-1">💬</div>
                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ __('Belum ada topik diskusi') }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ __('Tekan tombol + di atas untuk membuat topik pertama!') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 4. BOTTOM SHEET SECTION: DIRECT MESSAGES & FRIENDS LIST -->
        <div class="bg-white dark:bg-gray-850 rounded-t-[32px] sm:rounded-3xl p-4 sm:p-6 shadow-xl border border-gray-100 dark:border-gray-700/70 space-y-4">
            
            <!-- Sheet Header & Switcher (Pesan vs Teman) -->
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700/70 pb-3">
                <div class="flex items-center gap-1.5 bg-gray-100 dark:bg-gray-800 p-1 rounded-2xl">
                    <button type="button" 
                            @click="socialTab = 'dm'" 
                            class="px-4 py-2 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5"
                            :class="socialTab === 'dm' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-indigo-300 shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'">
                        <span>💬</span> {{ __('Pesan Langsung') }}
                        @php
                            $totalUnread = collect($this->recentChats)->sum('unread_count');
                        @endphp
                        @if($totalUnread > 0)
                            <span class="w-4 h-4 rounded-full bg-rose-500 text-white text-[10px] font-black flex items-center justify-center">
                                {{ $totalUnread }}
                            </span>
                        @endif
                    </button>

                    <button type="button" 
                            @click="socialTab = 'friends'" 
                            class="px-4 py-2 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5"
                            :class="socialTab === 'friends' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-indigo-300 shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'">
                        <span>👥</span> {{ __('Teman') }} ({{ $this->friends->count() }})
                    </button>
                </div>
            </div>

            <!-- TAB CONTENT 1: DIRECT MESSAGES -->
            <div x-show="socialTab === 'dm'" class="space-y-1 divide-y divide-gray-50 dark:divide-gray-800/60">
                @forelse($this->recentChats as $chat)
                    <button type="button" 
                            wire:click="openChat({{ $chat->user->id }})" 
                            @click="chatOpen = true"
                            class="w-full text-left p-3 rounded-2xl flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors group cursor-pointer">
                        
                        <div class="flex items-center gap-3.5 min-w-0 flex-1">
                            <div class="relative shrink-0">
                                <img src="{{ $chat->user->avatar_url }}" alt="{{ $chat->user->name }}" class="w-12 h-12 rounded-full object-cover border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                @if($chat->unread_count > 0)
                                    <span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-rose-500 border-2 border-white dark:border-gray-850 flex items-center justify-center text-[10px] font-black text-white shadow-xs">
                                        {{ $chat->unread_count > 9 ? '9+' : $chat->unread_count }}
                                    </span>
                                @else
                                    <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full bg-emerald-400 border-2 border-white dark:border-gray-850"></span>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="w-3.5 h-1 rounded-full bg-amber-500 mb-0.5"></div>
                                
                                <div class="flex items-baseline justify-between gap-2">
                                    <h4 class="text-sm font-extrabold text-gray-900 dark:text-gray-100 truncate group-hover:text-atoma-blue dark:group-hover:text-atoma-cyan transition-colors">
                                        {{ $chat->user->name }}
                                    </h4>
                                    @if($chat->latest_message)
                                        <span class="text-[11px] text-gray-400 font-medium shrink-0">
                                            {{ $chat->latest_message->created_at->diffForHumans(null, true, true) }}
                                        </span>
                                    @endif
                                </div>

                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5 {{ $chat->unread_count > 0 ? 'font-bold text-gray-900 dark:text-gray-200' : '' }}">
                                    @if($chat->latest_message)
                                        @if($chat->latest_message->sender_id === auth()->id())
                                            <span class="text-atoma-blue dark:text-atoma-cyan">{{ __('Anda:') }} </span>
                                        @endif
                                        {{ $chat->latest_message->content }}
                                    @else
                                        <span class="italic text-gray-400">{{ __('Ketuk untuk mulai percakapan 👋') }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="text-center py-10 text-gray-400 space-y-2">
                        <div class="text-4xl">💬</div>
                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ __('Belum ada percakapan') }}</p>
                        <p class="text-xs max-w-xs mx-auto">{{ __('Pilih teman dari daftar teman di atas untuk mulai mengobrol atau saling kirim tantangan!') }}</p>
                    </div>
                @endforelse
            </div>

            <!-- TAB CONTENT 2: FRIENDS & SOCIAL ACCOUNTABILITY -->
            <div x-show="socialTab === 'friends'" style="display: none;" class="space-y-5" wire:poll.15s>

                <!-- Friend Sub-Tabs (Clean Pills) -->
                <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar py-0.5 text-xs">
                    <button type="button" wire:click="setFriendSubTab('friends')" class="px-3.5 py-1.5 rounded-xl font-bold transition-all cursor-pointer {{ $friendSubTab === 'friends' ? 'bg-atoma-ice dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-300 ring-1 ring-atoma-aqua/60 dark:ring-indigo-500/40' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        {{ __('Teman') }} ({{ $this->friends->count() }})
                    </button>
                    <button type="button" wire:click="setFriendSubTab('requests')" class="px-3.5 py-1.5 rounded-xl font-bold transition-all cursor-pointer {{ $friendSubTab === 'requests' ? 'bg-atoma-ice dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-300 ring-1 ring-atoma-aqua/60 dark:ring-indigo-500/40' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        {{ __('Permintaan') }} ({{ $this->pendingRequests->count() }})
                    </button>
                    <button type="button" wire:click="setFriendSubTab('sent')" class="px-3.5 py-1.5 rounded-xl font-bold transition-all cursor-pointer {{ $friendSubTab === 'sent' ? 'bg-atoma-ice dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-300 ring-1 ring-atoma-aqua/60 dark:ring-indigo-500/40' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        {{ __('Terkirim') }} ({{ $this->sentRequestsUsers->count() }})
                    </button>
                    <button type="button" wire:click="setFriendSubTab('find')" class="px-3.5 py-1.5 rounded-xl font-bold transition-all cursor-pointer {{ $friendSubTab === 'find' ? 'bg-atoma-ice dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-300 ring-1 ring-atoma-aqua/60 dark:ring-indigo-500/40' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        + {{ __('Cari Teman') }}
                    </button>
                </div>

                <!-- Subtab 1: Friends List (Clean Minimalist Streamlined Rows with Realtime Completion Rate) -->
                @if($friendSubTab === 'friends')
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-700/60 overflow-hidden shadow-2xs">
                        @forelse($this->friends as $friend)
                            <div wire:key="friend-item-{{ $friend->id }}" class="p-3.5 sm:p-4 hover:bg-gray-50/70 dark:hover:bg-gray-750 transition-colors flex flex-col gap-3 group">
                                
                                <!-- Top Row: Friend Profile Info, Duels, Actions -->
                                <div class="flex items-center justify-between gap-3">
                                    <!-- Friend Profile Info (Clickable) -->
                                    <div class="flex items-center gap-3 min-w-0 flex-1 cursor-pointer" wire:click="openFriendProfile({{ $friend->id }})">
                                        <div class="relative shrink-0">
                                            <img src="{{ $friend->avatar_url }}" alt="{{ $friend->name }}" class="w-11 h-11 rounded-full object-cover border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-400 border-2 border-white dark:border-gray-800"></span>
                                        </div>
                                        
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-extrabold text-gray-900 dark:text-white truncate group-hover:text-atoma-blue dark:group-hover:text-atoma-cyan transition-colors">
                                                    {{ $friend->name }}
                                                </h4>
                                                <span class="text-[10px] font-black text-atoma-blue dark:text-indigo-300 bg-atoma-ice dark:bg-indigo-950/60 px-1.5 py-0.5 rounded-md border border-atoma-aqua/50 dark:border-indigo-500/40">
                                                    Lvl {{ $friend->level }}
                                                </span>
                                            </div>
                                            
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-0.5">
                                                <p class="text-xs text-orange-500 font-bold flex items-center gap-1">
                                                    <span>🔥</span> {{ $friend->current_streak }} {{ __('Hari Streak') }}
                                                </p>

                                                @if($duel = $this->activeDuels->get($friend->id))
                                                    @php
                                                        $myScore = $duel->sender_id === auth()->id() ? $duel->sender_task_count : $duel->receiver_task_count;
                                                        $friendScore = $duel->sender_id === auth()->id() ? $duel->receiver_task_count : $duel->sender_task_count;
                                                        $target = $duel->target_task_count ?: 3;
                                                        $remMin = max(0, now()->diffInMinutes($duel->expires_at, false));
                                                        $hoursLeft = intdiv($remMin, 60);
                                                        $timeText = $hoursLeft > 0 ? "{$hoursLeft}j lagi" : "{$remMin}m lagi";
                                                    @endphp
                                                    @if($duel->status === 'accepted')
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-black px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 animate-pulse">
                                                            <span>⚔️</span> {{ __('Duel Berlangsung:') }} {{ $myScore }} vs {{ $friendScore }}/{{ $target }} ({{ $timeText }})
                                                        </span>
                                                    @elseif($duel->status === 'pending')
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30">
                                                            <span>⚔️</span> {{ $duel->sender_id === auth()->id() ? __('Tantangan Dikirim') : __('Tantangan Masuk!') }}
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons: Senggol, Chat, Profile -->
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($this->activeDuels->has($friend->id) && $this->activeDuels->get($friend->id)->status === 'accepted')
                                            <button type="button" 
                                                    wire:click="notifyActiveDuel({{ $friend->id }})" 
                                                    class="h-9 px-2.5 sm:px-3 rounded-xl bg-amber-500/15 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-black text-xs flex items-center gap-1.5 shadow-2xs transition-all cursor-pointer hover:bg-amber-500/25 shrink-0" 
                                                    title="{{ __('Sedang Berduel') }}">
                                                <span class="text-sm leading-none">⚔️</span>
                                                <span class="hidden sm:inline">{{ __('Sedang Berduel') }}</span>
                                            </button>
                                        @else
                                            <button type="button" 
                                                    wire:click="openSenggolModal({{ $friend->id }})" 
                                                    class="h-9 px-2.5 sm:px-3 rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-black text-xs flex items-center gap-1.5 shadow-xs transition-all cursor-pointer hover:scale-105 active:scale-95 shrink-0" 
                                                    title="{{ __('Senggol Bacok!') }}">
                                                <span class="text-sm leading-none">⚡</span>
                                                <span class="hidden sm:inline">{{ __('Senggol') }}</span>
                                            </button>
                                        @endif

                                        <button type="button" 
                                                wire:click="openChat({{ $friend->id }})" 
                                                @click="chatOpen = true" 
                                                class="w-9 h-9 rounded-xl bg-atoma-ice hover:bg-atoma-aqua/40 dark:bg-gray-800 dark:hover:bg-gray-750 text-atoma-blue dark:text-indigo-300 flex items-center justify-center text-sm transition-all cursor-pointer active:scale-95 shadow-2xs shrink-0" 
                                                title="{{ __('Kirim Pesan') }}">
                                            💬
                                        </button>
                                        
                                        <button type="button" 
                                                wire:click="openFriendProfile({{ $friend->id }})" 
                                                class="w-9 h-9 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center justify-center text-sm transition-all cursor-pointer active:scale-95 shrink-0" 
                                                title="{{ __('Lihat Profil') }}">
                                            👤
                                        </button>
                                    </div>
                                </div>

                                <!-- Bottom Row: Realtime Friend Completion Rate Today & Quick Nudge -->
                                <div class="pt-2 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-3 flex-wrap">
                                    <div class="flex items-center gap-2 flex-1 min-w-[200px]">
                                        @if(($friend->today_total_tasks ?? 0) === 0)
                                            <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 font-medium">
                                                <span>📝</span>
                                                <span>{{ __('Belum ada jadwal tugas hari ini') }}</span>
                                            </div>
                                        @else
                                            @php
                                                $rate = $friend->today_completion_rate ?? 0;
                                                $badgeClass = $rate === 100 
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800' 
                                                    : ($rate >= 50 
                                                        ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/80 dark:text-sky-300 border-sky-300 dark:border-sky-800' 
                                                        : ($rate > 0 
                                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border-amber-300 dark:border-amber-800' 
                                                            : 'bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border-rose-300 dark:border-rose-800'));
                                                $barGradient = $rate === 100 
                                                    ? 'from-emerald-500 to-teal-500' 
                                                    : ($rate >= 50 
                                                        ? 'from-sky-500 to-blue-600' 
                                                        : ($rate > 0 
                                                            ? 'from-amber-500 to-orange-500' 
                                                            : 'from-rose-500 to-red-600'));
                                            @endphp
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between text-xs font-black mb-1">
                                                    <span class="text-gray-600 dark:text-gray-300 flex items-center gap-1.5 text-[11px] sm:text-xs">
                                                        <span>🎯</span>
                                                        <span>{{ __('Tugas Hari Ini:') }}</span>
                                                        <span class="font-extrabold text-gray-900 dark:text-white">{{ $friend->today_completed_tasks }}/{{ $friend->today_total_tasks }}</span>
                                                    </span>
                                                    <span class="px-2 py-0.5 rounded-md border text-[10px] sm:text-[11px] font-black {{ $badgeClass }}">
                                                        {{ $rate }}% {{ $rate === 100 ? '✓ Selesai' : '' }}
                                                    </span>
                                                </div>
                                                <div class="w-full bg-gray-150 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                                    <div class="h-full bg-gradient-to-r {{ $barGradient }} rounded-full transition-all duration-500" style="width: {{ $rate }}%"></div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Quick Remind / Congratulate Button -->
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if(($friend->today_total_tasks ?? 0) > 0 && ($friend->today_completion_rate ?? 0) < 100)
                                            <button type="button" 
                                                    wire:click="sendTaskReminder({{ $friend->id }})" 
                                                    class="h-7 px-2.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/70 dark:hover:bg-indigo-900 text-atoma-blue dark:text-indigo-300 border border-atoma-aqua/40 dark:border-indigo-500/40 text-[11px] font-black flex items-center gap-1 transition-all cursor-pointer shadow-2xs hover:scale-105 active:scale-95"
                                                    title="{{ __('Ingatkan teman untuk menuntaskan tugas hari ini') }}">
                                                <span>🔔</span>
                                                <span>{{ __('Ingatkan Tugas') }}</span>
                                            </button>
                                        @elseif(($friend->today_total_tasks ?? 0) > 0 && ($friend->today_completion_rate ?? 0) === 100)
                                            <button type="button" 
                                                    wire:click="executeSenggol({{ $friend->id }}, 'fire', '🎉 Keren banget! Semua tugas hari ini sudah kamu selesaikan 100%!')"
                                                    class="h-7 px-2.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/70 dark:hover:bg-emerald-900 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 text-[11px] font-black flex items-center gap-1 transition-all cursor-pointer shadow-2xs hover:scale-105 active:scale-95"
                                                    title="{{ __('Beri selamat karena tugas hari ini selesai 100%') }}">
                                                <span>👏</span>
                                                <span>{{ __('Tugas Tuntas!') }}</span>
                                            </button>
                                        @else
                                            <button type="button" 
                                                    wire:click="sendTaskReminder({{ $friend->id }})" 
                                                    class="h-7 px-2.5 rounded-lg bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 text-[11px] font-bold flex items-center gap-1 transition-all cursor-pointer shadow-2xs"
                                                    title="{{ __('Ajak teman membuat target tugas hari ini') }}">
                                                <span>💬</span>
                                                <span>{{ __('Sapa Teman') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-10 text-xs text-gray-400">
                                {{ __('Belum ada teman. Tekan "+ Cari Teman" untuk menambah koneksi baru!') }}
                            </div>
                        @endforelse
                    </div>
                @elseif($friendSubTab === 'requests')
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-700/60 overflow-hidden shadow-2xs">
                        @forelse($this->pendingRequests as $req)
                            <div class="p-3.5 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img src="{{ $req->user?->avatar_url ?? asset('images/default-avatar.svg') }}" alt="{{ $req->user?->name ?? 'User' }}" class="w-10 h-10 rounded-full object-cover">
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $req->user?->name ?? __('Pengguna') }}</h4>
                                        <p class="text-[10px] text-gray-400">{{ __('Mengirimkan permintaan pertemanan') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button wire:click="acceptRequest({{ $req->id }})" class="px-3 py-1.5 bg-atoma-blue hover:bg-[#3455dc] text-white text-xs font-black rounded-xl cursor-pointer shadow-2xs">{{ __('Terima') }}</button>
                                    <button wire:click="rejectRequest({{ $req->id }})" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-xl cursor-pointer">{{ __('Tolak') }}</button>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-xs text-gray-400">{{ __('Tidak ada permintaan pertemanan masuk.') }}</div>
                        @endforelse
                    </div>
                @elseif($friendSubTab === 'sent')
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-700/60 overflow-hidden shadow-2xs">
                        @forelse($this->sentRequestsUsers as $sentReq)
                            <div class="p-3.5 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img src="{{ $sentReq->friend?->avatar_url ?? asset('images/default-avatar.svg') }}" alt="{{ $sentReq->friend?->name ?? 'User' }}" class="w-10 h-10 rounded-full object-cover">
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $sentReq->friend?->name ?? __('Pengguna') }}</h4>
                                        <p class="text-[10px] text-gray-400">{{ __('Menunggu konfirmasi...') }}</p>
                                    </div>
                                </div>
                                <button wire:click="cancelRequest({{ $sentReq->id }})" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-xl cursor-pointer">{{ __('Batal') }}</button>
                            </div>
                        @empty
                            <div class="text-center py-8 text-xs text-gray-400">{{ __('Tidak ada permintaan terkirim.') }}</div>
                        @endforelse
                    </div>
                @elseif($friendSubTab === 'find')
                    <div class="space-y-3">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Ketik nama pengguna untuk dicari...') }}" class="w-full text-xs p-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl focus:ring-2 focus:ring-atoma-blue">
                        <div class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-700/60 overflow-hidden shadow-2xs">
                            @forelse($this->suggestions as $sug)
                                <div class="p-3 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <img src="{{ $sug->avatar_url }}" alt="{{ $sug->name }}" class="w-9 h-9 rounded-full object-cover">
                                        <div>
                                            <div class="text-xs font-bold truncate text-gray-900 dark:text-white">{{ $sug->name }}</div>
                                            <div class="text-[10px] text-gray-400">Lvl {{ $sug->level }}</div>
                                        </div>
                                    </div>
                                    <button wire:click="sendRequest({{ $sug->id }})" class="px-3 py-1.5 bg-atoma-blue hover:bg-[#3455dc] text-white text-xs font-black rounded-xl cursor-pointer shadow-2xs">+ {{ __('Tambah') }}</button>
                                </div>
                            @empty
                                <div class="text-center py-8 text-xs text-gray-400">{{ __('Tidak ada pengguna ditemukan.') }}</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>


    <!-- ================= VIEW 2: FULL COMMUNITY FEED PAGE (The Full Feed Page) ================= -->
    <div x-show="currentView === 'community'" style="display: none;" class="space-y-5" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        
        <!-- Header Bar with Back Button -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <button type="button" 
                        wire:click="setView('hub')" 
                        @click="currentView = 'hub'"
                        class="w-10 h-10 rounded-2xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center justify-center transition-all cursor-pointer shrink-0 shadow-xs active:scale-95"
                        title="{{ __('Kembali ke Halaman Sosial Utama') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>💬</span> {{ __('Community Feed') }}
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium">
                        {{ __('Bagikan progres, pencapaian milestone, tanya jawab, dan tips belajar bersama teman!') }}
                    </p>
                </div>
            </div>

            <button wire:click="$set('showCreateTopicModal', true)" class="shrink-0 bg-atoma-blue hover:bg-[#3455dc] text-white px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-black flex items-center gap-2 transition-all shadow-md shadow-atoma-blue/30 hover:shadow-lg cursor-pointer self-start sm:self-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                <span>+ {{ __('Buat Topik Baru') }}</span>
            </button>
        </div>

        <!-- Search Bar -->
        <div class="bg-gray-100 dark:bg-[#1e1f22] rounded-2xl px-4 py-3 flex items-center gap-3 border border-gray-200/80 dark:border-gray-800 focus-within:border-atoma-blue transition-colors shadow-inner">
            <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Cari topik diskusi, pertanyaan, atau milestone...') }}" class="bg-transparent border-0 focus:ring-0 w-full text-xs sm:text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 font-medium p-0">
            @if($search)
                <button wire:click="$set('search', '')" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
            @endif
        </div>

        <!-- Tags Filter & Sort Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 pt-1 border-b border-gray-100 dark:border-gray-800">
            <!-- Sort Dropdown -->
            <div class="relative shrink-0" x-data="{ sortOpen: false }">
                <button @click="sortOpen = !sortOpen" @click.away="sortOpen = false" class="px-3.5 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-[#313338] dark:hover:bg-[#3f4147] text-xs font-bold text-gray-700 dark:text-gray-200 flex items-center gap-1.5 transition-colors border border-gray-200/80 dark:border-gray-700 cursor-pointer">
                    <svg class="w-4 h-4 text-atoma-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>
                    {{ __('Urutan:') }} <span class="capitalize text-atoma-blue dark:text-atoma-cyan">{{ $sortView }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div x-show="sortOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" @click.outside="sortOpen = false" style="display: none;" class="absolute left-0 mt-2 w-44 rounded-xl shadow-xl bg-white dark:bg-[#2b2d31] ring-1 ring-black/5 z-50 overflow-hidden border border-gray-100 dark:border-gray-700 py-1">
                    <button wire:click="updateSort('newest')" @click="sortOpen = false" class="block w-full text-left px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-atoma-ice dark:hover:bg-atoma-blue/20 hover:text-atoma-blue transition-colors">{{ __('Terbaru (Newest)') }}</button>
                    <button wire:click="updateSort('popular')" @click="sortOpen = false" class="block w-full text-left px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-atoma-ice dark:hover:bg-atoma-blue/20 hover:text-atoma-blue transition-colors">{{ __('Terpopuler (Popular)') }}</button>
                    <button wire:click="updateSort('oldest')" @click="sortOpen = false" class="block w-full text-left px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-atoma-ice dark:hover:bg-atoma-blue/20 hover:text-atoma-blue transition-colors">{{ __('Terlama (Oldest)') }}</button>
                </div>
            </div>

            <!-- Tags Filter Row -->
            <div class="flex items-center gap-2 overflow-x-auto hide-scrollbar py-0.5 max-w-full">
                @php
                    $tagsConfig = [
                        'All' => ['icon' => '🌍', 'activeBg' => 'bg-atoma-blue', 'activeText' => 'text-white'],
                        'Milestones' => ['icon' => '🚀', 'activeBg' => 'bg-amber-500', 'activeText' => 'text-white'],
                        'Discussions' => ['icon' => '💬', 'activeBg' => 'bg-emerald-500', 'activeText' => 'text-white'],
                        'Questions' => ['icon' => '❓', 'activeBg' => 'bg-rose-500', 'activeText' => 'text-white'],
                        'General' => ['icon' => '🌟', 'activeBg' => 'bg-atoma-cyan', 'activeText' => 'text-white'],
                    ];
                    $counts = $this->tagCounts;
                @endphp
                
                @foreach($tagsConfig as $tag => $config)
                    <button wire:click="updateTag('{{ $tag }}')" class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-extrabold transition-all duration-300 flex items-center gap-1.5 cursor-pointer {{ $selectedTag === $tag ? $config['activeBg'] . ' ' . $config['activeText'] . ' shadow-md scale-105' : 'bg-gray-100 hover:bg-gray-200 dark:bg-[#2b2d31] dark:hover:bg-[#313338] text-gray-700 dark:text-gray-300' }}">
                        <span>{{ $config['icon'] }}</span>
                        {{ __($tag) }}
                        <span class="{{ $selectedTag === $tag ? 'bg-black/20 text-white' : 'bg-gray-200 dark:bg-[#3f4147] text-gray-500 dark:text-gray-400' }} text-[10px] px-1.5 py-0.5 rounded-full ml-0.5">{{ $counts[$tag] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Full Feed Grid (Topic Cards Grid) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mt-2">
            @forelse($this->posts as $post)
                @php
                    preg_match('/#(\w+)/', $post->content, $matches);
                    $primaryTag = $matches[1] ?? 'General';
                    $primaryTag = ucfirst(strtolower($primaryTag));
                    $isBookmarked = in_array($post->id, $bookmarkedPostIds);
                    $isMine = $post->user_id === auth()->id();
                @endphp
                
                <div wire:key="full-post-card-{{ $post->id }}"
                     wire:click="openTopic({{ $post->id }})" 
                     class="bg-white dark:bg-[#2b2d31] rounded-2xl p-5 border border-gray-200/80 dark:border-[#1f2023] shadow-xs hover:shadow-md hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 transition-all duration-200 cursor-pointer flex flex-col justify-between group relative overflow-hidden">
                    
                    <div>
                        <!-- Tag / Category Header (Top) -->
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-400 uppercase tracking-wider">
                                {{ __($post->category ?? $primaryTag) }}
                            </span>
                            
                            <div class="flex items-center gap-1.5">
                                @if($post->is_pinned)
                                    <span class="text-xs font-black text-atoma-blue dark:text-atoma-cyan flex items-center gap-1">
                                        📌 {{ __('PINNED') }}
                                    </span>
                                @endif

                                @if($isMine || (auth()->user()->email ?? '') === 'admin@atoma.app')
                                    <button type="button" 
                                            wire:click.stop="togglePin({{ $post->id }})" 
                                            class="p-1 text-gray-300 hover:text-atoma-blue transition-colors cursor-pointer"
                                            title="{{ __('Sematkan Topik') }}">
                                        📌
                                    </button>
                                @endif

                                <button type="button" 
                                            wire:click.stop="toggleBookmark({{ $post->id }})" 
                                            class="p-1 text-gray-300 hover:text-amber-500 transition-colors cursor-pointer"
                                            title="{{ __('Simpan Bookmark') }}">
                                    <svg class="w-4 h-4 {{ $isBookmarked ? 'text-amber-500 fill-current' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                                </button>

                                @if($isMine)
                                    <button type="button" 
                                            wire:click.stop="deletePost({{ $post->id }})" 
                                            class="p-1 text-gray-300 hover:text-red-500 transition-colors cursor-pointer"
                                            title="{{ __('Hapus Topik') }}">
                                        ✕
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Topic Title (Middle) -->
                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white leading-snug mb-2 group-hover:text-atoma-blue dark:group-hover:text-atoma-cyan transition-colors">
                            {{ $post->title ?? __('Untitled Topic') }}
                        </h3>

                        <!-- Short Excerpt / Description -->
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 line-clamp-3 leading-relaxed mb-6">
                            {{ str(strip_tags($post->content))->limit(140) }}
                        </p>
                    </div>

                    <!-- Author & Timestamp Footer (Bottom) -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-800/60">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <img src="{{ $post->user->avatar_url }}" alt="{{ $post->user->name }}" class="w-8 h-8 rounded-full object-cover shrink-0 ring-1 ring-gray-100 dark:ring-gray-700 bg-white">
                            <div class="min-w-0">
                                <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate">{{ $post->user->name }}</h4>
                                <p class="text-[10px] text-gray-400">{{ $post->created_at->diffForHumans(null, true, true) }} {{ __('ago') }}</p>
                            </div>
                        </div>
                        
                        @if($post->comments->count() > 0)
                            <div class="flex items-center gap-1 text-xs font-semibold text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800 px-2.5 py-1 rounded-full">
                                <span>💬</span>
                                <span>{{ $post->comments->count() }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-[#252836] rounded-3xl border border-dashed border-gray-200 dark:border-gray-800 p-12 text-center text-gray-500 dark:text-gray-400">
                    <div class="text-4xl mb-2">💬</div>
                    <p class="font-bold text-base text-gray-800 dark:text-white">{{ __('Belum ada topik diskusi') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('Jadilah yang pertama untuk membuat topik baru!') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Mobile FAB -->
        <button wire:click="$set('showCreateTopicModal', true)" class="sm:hidden fixed bottom-20 right-4 z-40 w-12 h-12 bg-atoma-blue hover:bg-[#3455dc] active:scale-95 text-white rounded-full shadow-2xl flex items-center justify-center cursor-pointer transition-transform ring-4 ring-atoma-blue/20" title="{{ __('Buat Topik Baru') }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
        </button>
    </div>

    <!-- ================= MODALS & OVERLAYS (Accessible from both views) ================= -->

    <!-- 1. THREAD DISCUSSION MODAL (Active Topic) -->
    @if($this->activeTopic)
        @php
            $memberCount = $this->activeTopic->comments->pluck('user_id')->push($this->activeTopic->user_id)->unique()->count();
        @endphp
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-gray-900/60 backdrop-blur-xs p-0 sm:p-4" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             wire:poll.3s>
            
            <div class="absolute inset-0" wire:click="closeTopic"></div>

            <div class="relative w-full sm:max-w-2xl bg-white dark:bg-[#1e1f22] rounded-t-[32px] sm:rounded-3xl shadow-2xl max-h-[90vh] sm:max-h-[85vh] flex flex-col overflow-hidden border border-gray-100 dark:border-gray-800 z-10">
                <!-- Drag handle for mobile -->
                <div class="sm:hidden pt-3 pb-1 bg-white dark:bg-[#1e1f22] flex justify-center shrink-0">
                    <div class="w-12 h-1.5 bg-gray-300 dark:bg-gray-700 rounded-full"></div>
                </div>

                <!-- Header -->
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-[#1e1f22] flex items-center justify-between shrink-0 z-10">
                    <div class="flex items-center space-x-3 min-w-0 flex-1">
                        <button wire:click="closeTopic" class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-full transition-colors cursor-pointer shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-gray-900 dark:text-white leading-tight truncate text-base sm:text-lg">{{ $this->activeTopic->title ?? __('Diskusi') }}</h3>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 ml-3">
                        <span class="bg-gray-100 dark:bg-[#2b2d31] text-gray-600 dark:text-gray-300 text-xs font-semibold px-3 py-1 rounded-full border border-gray-200/60 dark:border-gray-700">
                            {{ $memberCount }} {{ __('anggota') }}
                        </span>
                        <button wire:click="closeTopic" class="hidden sm:block p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-full transition-colors cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Messages Stream -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 bg-gray-50/70 dark:bg-[#141517] scroll-smooth"
                     x-data="{
                         scrollToBottom() {
                             this.$nextTick(() => {
                                 this.$el.scrollTo({ top: this.$el.scrollHeight, behavior: 'smooth' });
                             });
                         }
                     }"
                     x-init="
                         this.$el.scrollTop = this.$el.scrollHeight;
                         let observer = new MutationObserver(() => { scrollToBottom(); });
                         observer.observe(this.$el, { childList: true, subtree: true });
                     "
                     @thread-comment-sent.window="scrollToBottom()">
                    <!-- Starter Post -->
                    <div class="flex items-start gap-3">
                        <img src="{{ $this->activeTopic->user->avatar_url }}" alt="{{ $this->activeTopic->user->name ?? 'User' }}" class="w-9 h-9 rounded-full object-cover shrink-0 shadow-xs bg-white">
                        <div class="flex-1 max-w-[85%]">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-xs sm:text-sm text-gray-900 dark:text-gray-100">{{ $this->activeTopic->user->name ?? 'User' }}</span>
                                <span class="text-[11px] font-medium text-gray-400 dark:text-gray-500">{{ $this->activeTopic->created_at->format('H:i') }}</span>
                            </div>
                            <div class="bg-white dark:bg-[#252836] border border-gray-100 dark:border-gray-800 p-4 rounded-2xl rounded-tl-xs text-xs sm:text-sm text-gray-800 dark:text-gray-200 leading-relaxed shadow-xs break-words">
                                {{ $this->activeTopic->content }}
                            </div>
                        </div>
                    </div>

                    <!-- Replies -->
                    @forelse($this->activeTopic->comments as $msg)
                        @php $isMe = $msg->user_id === auth()->id(); @endphp
                        <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} gap-2">
                            @if(!$isMe)
                                <img src="{{ $msg->user->avatar_url }}" alt="{{ $msg->user->name ?? 'User' }}" class="w-9 h-9 rounded-full object-cover shrink-0 shadow-xs mt-0.5 bg-white">
                            @endif
                            <div class="max-w-[80%] flex flex-col {{ $isMe ? 'items-end' : 'items-start' }}">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-bold text-xs {{ $isMe ? 'text-atoma-blue dark:text-atoma-cyan' : 'text-gray-900 dark:text-gray-100' }}">{{ $isMe ? __('Anda') : $msg->user->name }}</span>
                                    <span class="text-[11px] font-medium text-gray-400">{{ $msg->created_at->format('H:i') }}</span>
                                </div>
                                <div class="{{ $isMe ? 'bg-atoma-blue text-white rounded-tr-xs shadow-xs' : 'bg-white dark:bg-[#252836] border border-gray-100 dark:border-gray-800 text-gray-800 dark:text-gray-200 rounded-tl-xs shadow-xs' }} p-3.5 rounded-2xl text-xs sm:text-sm leading-relaxed break-words">
                                    {{ $msg->content }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-gray-400 text-xs font-medium">
                            {{ __('Belum ada tanggapan. Kirim pesan pertama untuk memulai diskusi!') }}
                        </div>
                    @endforelse
                </div>

                <!-- Input -->
                <div class="p-3 sm:p-4 bg-white dark:bg-[#1e1f22] border-t border-gray-100 dark:border-gray-800 shrink-0">
                    <form wire:submit="sendThreadComment" class="flex items-center gap-2 bg-gray-100 dark:bg-[#2b2d31] rounded-full p-1.5 pl-4 border border-transparent focus-within:border-atoma-blue shadow-inner">
                        <input wire:model="threadCommentContent" type="text" placeholder="{{ __('Tulis balasan...') }}" class="flex-1 bg-transparent border-0 focus:ring-0 text-xs sm:text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 py-1.5 px-0">
                        <button type="submit" class="w-9 h-9 rounded-full bg-atoma-blue hover:bg-[#3455dc] text-white flex items-center justify-center transition-all shadow-md disabled:opacity-40 shrink-0 cursor-pointer" {{ empty($threadCommentContent) ? 'disabled' : '' }}>
                            <svg class="w-4 h-4 translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- 2. DIRECT CHAT MODAL / UI -->
    @if($this->activeChatFriend)
        <div class="fixed inset-0 z-50 flex flex-col bg-gray-50 dark:bg-gray-900 sm:items-center sm:justify-center sm:bg-gray-900/60 sm:backdrop-blur-xs p-0 sm:p-4" wire:poll.3s>
            <div class="relative w-full h-full sm:h-[80vh] sm:max-w-2xl bg-white dark:bg-gray-800 sm:rounded-3xl shadow-2xl flex flex-col overflow-hidden border border-gray-100 dark:border-gray-700/60">
                <!-- Chat Header -->
                <div class="px-4 py-3 sm:p-4 border-b border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-800 flex items-center justify-between shrink-0 shadow-sm z-10">
                    <div class="flex items-center space-x-3">
                        <button wire:click="closeChat" @click="chatOpen = false" class="p-2 -ml-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white rounded-full transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <img src="{{ $this->activeChatFriend->avatar_url }}" alt="Avatar" class="h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-gray-700 bg-white">
                        <div>
                            <div class="font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ $this->activeChatFriend->name }}</div>
                            <div class="text-xs text-atoma-blue dark:text-atoma-cyan font-bold">Lvl {{ $this->activeChatFriend->level }}</div>
                        </div>
                    </div>
                    <button wire:click="closeChat" @click="chatOpen = false" class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-full cursor-pointer">✕</button>
                </div>
                
                <!-- Messages Area -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 dark:bg-gray-900 scroll-smooth"
                     x-data="{
                         scrollToBottom() {
                             this.$nextTick(() => {
                                 this.$el.scrollTo({ top: this.$el.scrollHeight, behavior: 'smooth' });
                             });
                         }
                     }"
                     x-init="
                         this.$el.scrollTop = this.$el.scrollHeight;
                         let observer = new MutationObserver(() => { scrollToBottom(); });
                         observer.observe(this.$el, { childList: true, subtree: true });
                     "
                     @chat-message-sent.window="scrollToBottom()">
                    @forelse($this->activeChatMessages as $msg)
                        @php $isMe = $msg->sender_id === auth()->id(); @endphp
                        <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} group">
                            <div class="max-w-[85%] sm:max-w-[75%] {{ $isMe ? 'bg-atoma-blue text-white rounded-2xl rounded-tr-[4px] shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-2xl rounded-tl-[4px] shadow-sm border border-gray-100 dark:border-gray-700' }} px-4 py-2.5 relative">
                                <div class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ $msg->content }}</div>
                                <div class="text-[10px] text-right mt-1 {{ $isMe ? 'text-atoma-ice' : 'text-gray-400' }} flex items-center justify-end font-medium">
                                    {{ $msg->created_at->format('H:i') }}
                                    @if($isMe)
                                        <svg class="w-3.5 h-3.5 ml-1 {{ $msg->is_read ? 'text-atoma-cyan' : 'text-atoma-aqua/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-gray-400">
                            <div class="text-4xl mb-2">👋</div>
                            <p class="font-bold text-gray-700 dark:text-gray-300">{{ __('Katakan Hai ke :name!', ['name' => $this->activeChatFriend->name]) }}</p>
                            <p class="text-xs mt-0.5">{{ __('Mulai percakapan produktif hari ini.') }}</p>
                        </div>
                    @endforelse
                </div>
                
                <!-- Input Area -->
                <div class="p-3 pb-safe border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 shrink-0">
                    <form wire:submit="sendChatMessage" class="flex items-end space-x-2">
                        <div class="flex-1 bg-gray-100 dark:bg-gray-900 rounded-2xl relative flex items-end min-h-[44px]">
                            <textarea wire:model="chatMessageContent" @keydown.enter.exact.prevent="$wire.sendChatMessage()" rows="1" placeholder="{{ __('Ketik pesan (Enter kirim, Shift+Enter baris baru)...') }}" class="w-full bg-transparent border-0 focus:ring-0 text-sm dark:text-white resize-none py-3 px-4 max-h-32 hide-scrollbar"></textarea>
                        </div>
                        <button type="submit" class="p-3 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-full transition-all shadow-md disabled:opacity-50 shrink-0 mb-0.5 cursor-pointer" {{ empty($chatMessageContent) ? 'disabled' : '' }}>
                            <svg class="w-5 h-5 translate-x-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- 3. CREATE TOPIC MODAL -->
    <div x-show="$wire.showCreateTopicModal" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="$wire.showCreateTopicModal = false"></div>
        <div class="bg-white dark:bg-[#313338] rounded-3xl shadow-xl w-full max-w-lg relative z-10 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700/60 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/40">
                <h3 class="text-base font-black text-gray-900 dark:text-white">{{ __('Buat Topik Baru') }}</h3>
                <button @click="$wire.showCreateTopicModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-white cursor-pointer">✕</button>
            </div>
            
            <form wire:submit="createPost" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">{{ __('Kategori Topik') }} <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach([
                            'General' => ['label' => 'General', 'icon' => '🌟', 'color' => 'peer-checked:bg-atoma-blue peer-checked:text-white'],
                            'Milestones' => ['label' => 'Milestones', 'icon' => '🚀', 'color' => 'peer-checked:bg-amber-500 peer-checked:text-white'],
                            'Discussions' => ['label' => 'Discussions', 'icon' => '💬', 'color' => 'peer-checked:bg-emerald-600 peer-checked:text-white'],
                            'Questions' => ['label' => 'Questions', 'icon' => '❓', 'color' => 'peer-checked:bg-rose-600 peer-checked:text-white']
                        ] as $catKey => $catData)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model="postCategory" value="{{ $catKey }}" class="sr-only peer">
                                <div class="px-3 py-2 rounded-xl text-xs font-bold border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 text-center transition-all peer-checked:border-transparent {{ $catData['color'] }} peer-checked:shadow-sm">
                                    <span>{{ $catData['icon'] }}</span>
                                    <div class="mt-0.5 truncate">{{ __($catData['label']) }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">{{ __('Judul Topik') }} <span class="text-red-500">*</span></label>
                    <input wire:model="postTitle" type="text" placeholder="{{ __('Contoh: Tips Produktivitas & Rutinitas Pagi') }}" class="w-full bg-gray-100 dark:bg-gray-900 border-0 focus:ring-2 focus:ring-atoma-blue rounded-xl px-4 py-2.5 text-xs sm:text-sm text-gray-900 dark:text-white" required>
                    @error('postTitle') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">{{ __('Isi Diskusi') }} <span class="text-red-500">*</span></label>
                    <textarea wire:model="postContent" rows="4" placeholder="{{ __('Ceritakan apa yang ingin kamu diskusikan...') }}" class="w-full bg-gray-100 dark:bg-gray-900 border-0 focus:ring-2 focus:ring-atoma-blue rounded-xl px-4 py-2.5 text-xs sm:text-sm text-gray-900 dark:text-white resize-none" required></textarea>
                    @error('postContent') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="$wire.showCreateTopicModal = false" class="px-4 py-2 text-gray-500 hover:text-gray-700 dark:hover:text-white text-xs font-bold cursor-pointer">{{ __('Batal') }}</button>
                    <button type="submit" class="bg-atoma-blue hover:bg-[#3455dc] text-white rounded-xl px-5 py-2.5 text-xs font-black shadow-md shadow-atoma-blue/30 transition-all cursor-pointer">
                        {{ __('Terbitkan Topik') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. FRIEND PROFILE SHOWCASE MODAL -->
    @if($showProfileModal && $this->selectedFriend)
        @php
            $friendUser = $this->selectedFriend;
            $canViewDetail = $friendUser->share_profile_with_friends;
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 w-full max-w-md overflow-hidden relative"
                 @click.away="$wire.closeFriendProfile()">
                <div class="relative h-24 bg-gradient-to-r from-atoma-blue to-atoma-cyan p-4 flex justify-between items-start">
                    <button type="button" wire:click="closeFriendProfile" class="w-8 h-8 rounded-full bg-black/20 text-white flex items-center justify-center font-bold text-xs ml-auto cursor-pointer">✕</button>
                </div>

                <div class="px-6 pb-6 pt-0 relative space-y-4">
                    <div class="flex items-end justify-between -mt-12 mb-2">
                        <div class="w-20 h-20 rounded-2xl p-1 bg-white dark:bg-gray-800 shadow-xl overflow-hidden">
                            <img src="{{ $friendUser->avatar_url }}" alt="{{ $friendUser->name }}" class="w-full h-full rounded-xl object-cover">
                        </div>
                        <span class="px-3 py-1 bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-black text-xs rounded-full border border-atoma-aqua/60 dark:border-atoma-blue/40">
                            Level {{ $friendUser->level }}
                        </span>
                    </div>

                    <div>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white">{{ $friendUser->name }}</h3>
                        <p class="text-xs text-gray-400 mt-0.5">🔥 {{ $friendUser->current_streak }} {{ __('Hari Streak') }}</p>
                    </div>

                    @if($canViewDetail)
                        <!-- Showcase Pencapaian -->
                        @if($friendUser->share_achievements)
                            <div class="bg-gray-50 dark:bg-gray-900/60 rounded-2xl p-4 border border-gray-200/60 dark:border-gray-700/60 space-y-2">
                                <div class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🏆</span> {{ __('Showcase Pencapaian') }}
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-center text-xs">
                                    <div class="bg-white dark:bg-gray-800 p-2.5 rounded-xl border border-gray-100 dark:border-gray-700 font-bold">
                                        <div class="text-atoma-blue dark:text-atoma-cyan font-black text-sm">{{ number_format($friendUser->xp) }}</div>
                                        <div class="text-[10px] text-gray-400">{{ __('Total XP') }}</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 p-2.5 rounded-xl border border-gray-100 dark:border-gray-700 font-bold">
                                        <div class="text-orange-500 font-black text-sm">{{ $friendUser->current_streak }} {{ __('Hari') }}</div>
                                        <div class="text-[10px] text-gray-400">{{ __('Streak Aktif') }}</div>
                                    </div>
                                </div>

                                @if(!empty($friendUser->equipped_badges_details))
                                    <div class="space-y-1.5">
                                        <div class="text-[10px] uppercase font-black tracking-wider text-amber-600 dark:text-amber-400">
                                            {{ __('Rak Pamer Lencana') }} ({{ count($friendUser->equipped_badges_details) }}/3)
                                        </div>
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach($friendUser->equipped_badges_details as $b)
                                                <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300/40 dark:border-amber-500/30 flex flex-col items-center text-center">
                                                    <div class="w-8 h-8 p-0.5 bg-white dark:bg-gray-800 rounded-lg shadow-xs border border-amber-200 dark:border-amber-800/60 flex items-center justify-center mb-1">
                                                        <img src="{{ asset($b['svg_path']) }}" class="w-full h-full object-contain">
                                                    </div>
                                                    <div class="text-[10px] font-black text-gray-900 dark:text-white truncate w-full" title="{{ $b['title'] }}">
                                                        {{ $b['title'] }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Target Hari Ini -->
                        @if($friendUser->share_daily_tasks)
                            @php
                                $friendTasks = $friendUser->tasks()->whereDate('date', now('Asia/Jakarta')->toDateString())->get();
                                $completedCount = $friendTasks->where('is_completed', true)->count();
                                $totalCount = $friendTasks->count();
                            @endphp
                            <div class="bg-gray-50 dark:bg-gray-900/60 rounded-2xl p-4 border border-gray-200/60 dark:border-gray-700/60 space-y-2">
                                <div class="flex items-center justify-between text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider">
                                    <span class="flex items-center gap-1.5"><span>🎯</span> {{ __('Target Hari Ini') }}</span>
                                    <span class="text-atoma-blue dark:text-atoma-cyan">{{ $completedCount }}/{{ $totalCount }}</span>
                                </div>
                                <div class="space-y-1 pt-1">
                                    @forelse($friendTasks as $t)
                                        <div class="text-xs font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2 bg-white dark:bg-gray-800 p-2 rounded-xl border border-gray-100 dark:border-gray-700">
                                            <span class="w-2 h-2 rounded-full {{ $t->is_completed ? 'bg-emerald-500' : 'bg-amber-400' }} shrink-0"></span>
                                            <span class="truncate {{ $t->is_completed ? 'line-through text-gray-400' : '' }}">{{ $t->title }}</span>
                                        </div>
                                    @empty
                                        <div class="text-xs text-gray-400 py-1">{{ __('Tidak ada target tugas untuk hari ini.') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        @endif

                        <!-- Head-to-Head Rivalry Score Card -->
                        @php
                            $h2h = $this->getHeadToHeadScore($friendUser->id);
                        @endphp
                        <div class="bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-red-500/10 dark:from-amber-950/40 dark:via-orange-950/40 dark:to-red-950/40 rounded-2xl p-3.5 border border-orange-200/60 dark:border-orange-900/40 flex items-center justify-between shadow-2xs">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-orange-500/20 text-orange-600 dark:text-orange-400 flex items-center justify-center text-lg font-black shrink-0">
                                    ⚔️
                                </div>
                                <div>
                                    <h5 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider">{{ __('Rekor Rivalitas') }}</h5>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('Total :count kali saling senggol', ['count' => $h2h['total_exchanges']]) }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-black text-orange-600 dark:text-orange-400 font-mono">
                                    {{ __('Kamu :my_wins - :friend_wins :name', ['my_wins' => $h2h['my_wins'], 'friend_wins' => $h2h['friend_wins'], 'name' => $friendUser->name]) }}
                                </span>
                                <span class="text-[9px] font-bold text-gray-400 block uppercase tracking-wider">{{ __('Menang Duel') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            @if($this->activeDuels->has($friendUser->id) && $this->activeDuels->get($friendUser->id)->status === 'accepted')
                                <button type="button" wire:click="notifyActiveDuel({{ $friendUser->id }})" class="flex-1 py-2.5 px-3 rounded-xl text-xs font-black bg-amber-500/15 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition-all shadow-2xs cursor-pointer flex items-center justify-center gap-1.5 hover:bg-amber-500/25">
                                    <span>⚔️</span>
                                    <span>{{ __('Sedang Berduel') }}</span>
                                </button>
                            @else
                                <button type="button" wire:click="openSenggolModal({{ $friendUser->id }})" class="flex-1 py-2.5 px-3 rounded-xl text-xs font-black bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white transition-all shadow-xs cursor-pointer flex items-center justify-center gap-1.5 hover:scale-102 active:scale-98">
                                    <span>⚡</span>
                                    <span>{{ __('Senggol Bacok!') }}</span>
                                </button>
                            @endif
                            <button type="button" wire:click="openChat({{ $friendUser->id }}); closeFriendProfile()" class="flex-1 py-2.5 px-3 rounded-xl text-xs font-black bg-atoma-blue hover:bg-[#3455dc] text-white transition-all shadow-xs cursor-pointer">
                                💬 {{ __('Kirim Pesan') }}
                            </button>
                        </div>
                        <a href="{{ route('user.profile', $friendUser->id) }}" wire:navigate class="w-full py-2 px-3 rounded-xl text-xs font-bold text-center text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750 border border-gray-200 dark:border-gray-700 block transition-all">
                            🌐 {{ __('Buka Halaman Profil Publik Pamer →') }}
                        </a>
                    @else
                        <!-- Private Profile Notice -->
                        <div class="bg-gray-50 dark:bg-gray-900/60 rounded-2xl p-6 text-center border border-gray-200/60 dark:border-gray-700/60 space-y-1.5">
                            <div class="text-3xl">🔒</div>
                            <h4 class="text-sm font-black text-gray-800 dark:text-gray-200">{{ __('Profil Ini Disetel Privat') }}</h4>
                            <p class="text-xs text-gray-500 max-w-xs mx-auto">{{ __('Pengguna ini memilih untuk tidak membagikan detail tugas dan pencapaian.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="openChat({{ $friendUser->id }}); closeFriendProfile()" class="flex-1 py-2.5 px-3 rounded-xl text-xs font-black bg-atoma-blue hover:bg-[#3455dc] text-white transition-all shadow-xs cursor-pointer">
                                💬 {{ __('Kirim Pesan') }}
                            </button>
                            <a href="{{ route('user.profile', $friendUser->id) }}" wire:navigate class="flex-1 py-2.5 px-3 rounded-xl text-xs font-bold text-center text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750 border border-gray-200 dark:border-gray-700 block transition-all">
                                🌐 {{ __('Profil Publik') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- 5. PRIVACY SETTINGS MODAL -->
    @if($showPrivacyModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 w-full max-w-md overflow-hidden relative"
                 @click.away="$wire.closePrivacyModal()">
                
                <div class="p-6 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <x-icon.gear class="w-5 h-5 inline-block shrink-0 text-atoma-blue dark:text-atoma-cyan" />
                            <span>{{ __('Persetujuan & Privasi Profil') }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Kontrol data apa saja yang boleh dilihat oleh temanmu.') }}</p>
                    </div>
                    <button type="button" wire:click="closePrivacyModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 font-bold text-sm cursor-pointer">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <label class="flex items-start justify-between gap-3 p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700/50 cursor-pointer">
                        <div>
                            <div class="text-xs font-black text-gray-900 dark:text-white">{{ __('Bagikan Profil ke Teman') }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Izinkan teman melihat kartu profil & status kondisimu.') }}</div>
                        </div>
                        <input type="checkbox" wire:model="privacyShareProfile" class="rounded text-atoma-blue focus:ring-atoma-blue h-4 w-4 mt-0.5 cursor-pointer">
                    </label>

                    <label class="flex items-start justify-between gap-3 p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700/50 cursor-pointer">
                        <div>
                            <div class="text-xs font-black text-gray-900 dark:text-white">{{ __('Bagikan Progres Tugas Harian') }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Teman dapat melihat jumlah tugas tersisa dan membantumu mengingatkan target harian.') }}</div>
                        </div>
                        <input type="checkbox" wire:model="privacyShareTasks" class="rounded text-atoma-blue focus:ring-atoma-blue h-4 w-4 mt-0.5 cursor-pointer">
                    </label>

                    <label class="flex items-start justify-between gap-3 p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700/50 cursor-pointer">
                        <div>
                            <div class="text-xs font-black text-gray-900 dark:text-white">{{ __('Pamer Pencapaian (Showcase)') }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Showcase streak, level, XP, dan badge divisi liga ke teman.') }}</div>
                        </div>
                        <input type="checkbox" wire:model="privacyShareAchievements" class="rounded text-atoma-blue focus:ring-atoma-blue h-4 w-4 mt-0.5 cursor-pointer">
                    </label>

                    <label class="flex items-start justify-between gap-3 p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700/50 cursor-pointer">
                        <div>
                            <div class="text-xs font-black text-gray-900 dark:text-white">{{ __('Izinkan Colekan & Pengingat') }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Izinkan teman mengirimkan pengingat tugas atau colekan semangat.') }}</div>
                        </div>
                        <input type="checkbox" wire:model="privacyAllowNudges" class="rounded text-atoma-blue focus:ring-atoma-blue h-4 w-4 mt-0.5 cursor-pointer">
                    </label>

                    <div class="flex items-center gap-2 pt-2">
                        <button type="button" wire:click="savePrivacySettings" class="flex-1 py-2.5 px-4 rounded-xl text-xs font-black bg-atoma-blue hover:bg-[#3455dc] text-white transition-all shadow-sm cursor-pointer">
                            💾 {{ __('Simpan Pengaturan') }}
                        </button>
                        <button type="button" wire:click="closePrivacyModal" class="py-2.5 px-4 rounded-xl text-xs font-bold bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 transition-all cursor-pointer">
                            {{ __('Batal') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 6. MODAL: SENGGOL BACOK (Interactive Jurus Senggolan Modal) -->
    @if($showSenggolModal && $this->targetFriendUser)
        @php
            $targetFriend = $this->targetFriendUser;
            $currentCooldown = $this->getCooldownRemainingSeconds($targetFriend->id, $selectedNudgeType);
            $h2hStats = $this->getHeadToHeadScore($targetFriend->id);
        @endphp
        <div class="fixed inset-0 z-[70] overflow-y-auto bg-gray-950/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-200">
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 w-full max-w-lg overflow-hidden relative animate-in zoom-in-95 duration-200 flex flex-col max-h-[90vh]"
                 @click.away="$wire.closeSenggolModal()">
                
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between bg-gradient-to-r from-orange-500/10 via-amber-500/5 to-transparent shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="relative shrink-0">
                            <img src="{{ $targetFriend->avatar_url }}" alt="{{ $targetFriend->name }}" class="w-11 h-11 rounded-full object-cover border-2 border-orange-500/40 shadow-xs">
                            <span class="absolute -bottom-1 -right-1 text-xs">⚡</span>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm sm:text-base font-black text-gray-900 dark:text-white truncate">
                                    {{ __('Senggol :name', ['name' => $targetFriend->name]) }}
                                </h3>
                                <span class="text-[10px] font-black px-2 py-0.5 rounded-lg bg-orange-100 dark:bg-orange-950 text-orange-600 dark:text-orange-400">
                                    Lvl {{ $targetFriend->level }}
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1.5">
                                <span>🔥 {{ $targetFriend->current_streak }} {{ __('Hari Streak') }}</span>
                                <span>•</span>
                                <span>{{ __('Rivalitas: Kamu :my_wins - :friend_wins :name', ['my_wins' => $h2hStats['my_wins'], 'friend_wins' => $h2hStats['friend_wins'], 'name' => $targetFriend->name]) }}</span>
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeSenggolModal" class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xs transition-colors cursor-pointer shrink-0">✕</button>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div class="p-4 sm:p-5 overflow-y-auto space-y-4 flex-1">
                    
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">
                            {{ __('Pilih Jurus Senggolan:') }}
                        </label>

                        <!-- Jurus Options Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            
                            <!-- 1. Senggol Bacok (Duel 24 Jam) -->
                            <div wire:click="setNudgeType('bacok')" 
                                 class="p-3.5 rounded-2xl border-2 transition-all cursor-pointer relative {{ $selectedNudgeType === 'bacok' ? 'border-orange-500 bg-orange-50/60 dark:bg-orange-950/30 shadow-xs' : 'border-gray-200/80 dark:border-gray-700/80 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800' }}">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xl">⚔️</span>
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-red-100 dark:bg-red-950 text-red-600 dark:text-red-400 uppercase tracking-wider">+50 XP</span>
                                </div>
                                <h4 class="text-xs font-black text-gray-900 dark:text-white">{{ __('Senggol Bacok (Duel)') }}</h4>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-snug">{{ __('Tantang duel 24 jam! Siapa yang duluan selesaikan 3 tugas hari ini menang bonus XP.') }}</p>
                            </div>

                            <!-- 2. Senggol Tagih (Alarm Tugas) -->
                            <div wire:click="setNudgeType('task')" 
                                 class="p-3.5 rounded-2xl border-2 transition-all cursor-pointer relative {{ $selectedNudgeType === 'task' ? 'border-orange-500 bg-orange-50/60 dark:bg-orange-950/30 shadow-xs' : 'border-gray-200/80 dark:border-gray-700/80 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800' }}">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xl">⏰</span>
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 uppercase tracking-wider">{{ __('Anti-Mager') }}</span>
                                </div>
                                <h4 class="text-xs font-black text-gray-900 dark:text-white">{{ __('Senggol Tagih') }}</h4>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-snug">{{ __('Kirim alarm pengingat tugas harian yang belum dicentang oleh teman.') }}</p>
                            </div>

                            <!-- 3. Senggol Api (Bakar Semangat) -->
                            <div wire:click="setNudgeType('fire')" 
                                 class="p-3.5 rounded-2xl border-2 transition-all cursor-pointer relative {{ $selectedNudgeType === 'fire' ? 'border-orange-500 bg-orange-50/60 dark:bg-orange-950/30 shadow-xs' : 'border-gray-200/80 dark:border-gray-700/80 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800' }}">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xl">🔥</span>
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-orange-100 dark:bg-orange-950 text-orange-600 dark:text-orange-400 uppercase tracking-wider">{{ __('Booster') }}</span>
                                </div>
                                <h4 class="text-xs font-black text-gray-900 dark:text-white">{{ __('Senggol Api') }}</h4>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-snug">{{ __('Suntikkan kobaran api motivasi agar streak teman tidak putus di tengah jalan.') }}</p>
                            </div>

                            <!-- 4. Senggol Roasting (Tamparan Realita) -->
                            <div wire:click="setNudgeType('roast')" 
                                 class="p-3.5 rounded-2xl border-2 transition-all cursor-pointer relative {{ $selectedNudgeType === 'roast' ? 'border-orange-500 bg-orange-50/60 dark:bg-orange-950/30 shadow-xs' : 'border-gray-200/80 dark:border-gray-700/80 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800' }}">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xl">🥊</span>
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400 uppercase tracking-wider">{{ __('Pedas Lucu') }}</span>
                                </div>
                                <h4 class="text-xs font-black text-gray-900 dark:text-white">{{ __('Senggol Roasting') }}</h4>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-snug">{{ __('Kirim kalimat pedas nan kocak untuk menyadarkan teman dari kebiasaan rebahan.') }}</p>
                            </div>

                            <!-- 5. Senggol Proteksi (Kawal Streak) -->
                            <div wire:click="setNudgeType('shield')" 
                                 class="p-3.5 rounded-2xl border-2 transition-all cursor-pointer relative sm:col-span-2 {{ $selectedNudgeType === 'shield' ? 'border-orange-500 bg-orange-50/60 dark:bg-orange-950/30 shadow-xs' : 'border-gray-200/80 dark:border-gray-700/80 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800' }}">
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="w-6 h-6 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 p-0.5 border border-emerald-300 dark:border-emerald-700/60 flex items-center justify-center">
                                        <img src="{{ asset('images/icons/streak_saver.svg') }}" alt="Streak Shield" class="w-full h-full object-contain">
                                    </div>
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">{{ __('Solid') }}</span>
                                </div>
                                <h4 class="text-xs font-black text-gray-900 dark:text-white">{{ __('Senggol Proteksi (Kawal Streak)') }}</h4>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-snug">{{ __('Kawal teman yang streak-nya terancam gugur hari ini karena belum menyelesaikan tugas.') }}</p>
                            </div>

                        </div>
                    </div>

                    <!-- Roasting Template Picker (Only when 'roast' is active) -->
                    @if($selectedNudgeType === 'roast')
                        <div class="p-3.5 bg-purple-50/60 dark:bg-purple-950/20 rounded-2xl border border-purple-200/60 dark:border-purple-900/40 space-y-2.5 animate-in fade-in duration-150">
                            <label class="block text-xs font-black text-purple-900 dark:text-purple-300">
                                💬 {{ __('Pilih Template Tamparan Realita:') }}
                            </label>
                            <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto">
                                @foreach($roastTemplates as $tmpl)
                                    <button type="button" 
                                            wire:click="selectRoastTemplate('{{ addslashes($tmpl) }}')" 
                                            class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold text-left transition-all cursor-pointer {{ $customRoastMessage === $tmpl ? 'bg-purple-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-purple-400' }}">
                                        "{{ __($tmpl) }}"
                                    </button>
                                @endforeach
                            </div>
                            <div>
                                <textarea wire:model="customRoastMessage" 
                                          rows="2" 
                                          placeholder="{{ __('Atau ketik kata-kata roasting kustom sendiri...') }}" 
                                          class="w-full text-xs rounded-xl bg-white dark:bg-gray-800 border border-purple-200 dark:border-purple-900 p-2.5 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500"></textarea>
                            </div>
                        </div>
                    @endif

                    <!-- Cooldown Warning Notice -->
                    @if($currentCooldown > 0)
                        <div class="p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs font-bold text-amber-700 dark:text-amber-400 flex items-center gap-2">
                            <span>⏳</span>
                            <span>{{ __('Jurus ini sedang cooldown. Dapat digunakan lagi dalam :minutes menit.', ['minutes' => ceil($currentCooldown / 60)]) }}</span>
                        </div>
                    @endif

                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-gray-100 dark:border-gray-700/60 flex items-center gap-2 bg-gray-50 dark:bg-gray-900/60 shrink-0">
                    <button type="button" 
                            wire:click="executeSenggol({{ $targetFriend->id }}, '{{ $selectedNudgeType }}')" 
                            {{ $currentCooldown > 0 ? 'disabled' : '' }}
                            class="flex-1 py-3 px-4 rounded-xl text-xs font-black text-white bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 shadow-md shadow-orange-500/25 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5 hover:scale-102 active:scale-98">
                        <span>⚡</span>
                        <span>{{ __('Luncurkan Senggolan!') }}</span>
                    </button>
                    <button type="button" 
                            wire:click="closeSenggolModal" 
                            class="py-3 px-4 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all cursor-pointer">
                        {{ __('Batal') }}
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- 7. MODAL: KENA SENGGOL! (Incoming Nudge Alert Modal with Screen Shake) -->
    @if($showIncomingNudgeModal && $this->activeIncomingNudge)
        @php
            $incNudge = $this->activeIncomingNudge;
            $sender = $incNudge->sender;
        @endphp
        <div class="fixed inset-0 z-[70] overflow-y-auto bg-gray-950/80 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-200"
             x-data
             x-init="$dispatch('play-sound', { preset: '{{ $incNudge->nudge_type === 'bacok' ? 'slash' : ($incNudge->nudge_type === 'roast' ? 'bonk' : 'chime') }}' })">
            
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border-2 {{ $incNudge->nudge_type === 'bacok' ? 'border-red-500' : ($incNudge->nudge_type === 'roast' ? 'border-purple-500' : 'border-orange-500') }} w-full max-w-md overflow-hidden relative animate-atoma-shake text-center p-6 space-y-4">
            
                <!-- Pulsing Avatar & Icon -->
                <div class="relative w-20 h-20 mx-auto">
                    <img src="{{ $sender->avatar_url }}" alt="{{ $sender->name }}" class="w-20 h-20 rounded-full object-cover border-4 border-white dark:border-gray-700 shadow-xl">
                    <div class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-gradient-to-tr from-orange-500 to-amber-500 text-white flex items-center justify-center text-base shadow-md animate-bounce">
                        {{ match($incNudge->nudge_type) { 'bacok' => '⚔️', 'roast' => '🥊', 'fire' => '🔥', 'shield' => '🛡️', default => '⏰' } }}
                    </div>
                </div>

                <div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider {{ match($incNudge->nudge_type) { 'bacok' => 'bg-red-100 dark:bg-red-950 text-red-600 dark:text-red-400', 'roast' => 'bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400', default => 'bg-orange-100 dark:bg-orange-950 text-orange-600 dark:text-orange-400' } }}">
                        {{ match($incNudge->nudge_type) { 'bacok' => '⚔️ ' . __('TANTANGAN SENGGOL BACOK!'), 'roast' => '🥊 ' . __('TAMPARAN REALITA!'), 'fire' => '🔥 ' . __('KOBARAN API SEMANGAT!'), 'shield' => '🛡️ ' . __('BANTUAN PROTEKSI STREAK!'), default => '⏰ ' . __('ALARM PENGINGAT TUGAS!') } }}
                    </span>
                    <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white mt-2">
                        {{ __('Kamu Disenggol oleh :name!', ['name' => $sender->name]) }}
                    </h3>
                </div>

                <!-- Message Quote Bubble -->
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/70 border border-gray-100 dark:border-gray-700 text-xs font-bold text-gray-800 dark:text-gray-200 italic relative">
                    <span class="text-lg text-orange-400 leading-none mr-1">“</span>
                    {{ $incNudge->message }}
                    <span class="text-lg text-orange-400 leading-none ml-1">”</span>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-2 pt-2">
                    @if($incNudge->nudge_type === 'bacok')
                        <button type="button" 
                                wire:click="respondToDuel({{ $incNudge->id }}, 'accept')" 
                                class="w-full py-3 px-4 rounded-2xl text-xs font-black text-white bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600 shadow-lg shadow-red-500/25 transition-all cursor-pointer flex items-center justify-center gap-1.5 hover:scale-102 active:scale-98">
                            <span>⚔️</span>
                            <span>{{ __('Terima Tantangan Duel! (+50 XP)') }}</span>
                        </button>
                        <button type="button" 
                                wire:click="respondToDuel({{ $incNudge->id }}, 'decline')" 
                                class="w-full py-2.5 px-4 rounded-2xl text-xs font-bold text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all cursor-pointer">
                            {{ __('Nanti Dulu') }}
                        </button>
                    @else
                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    wire:click="acknowledgeIncomingNudge({{ $incNudge->id }}, true)" 
                                    class="flex-1 py-3 px-3 rounded-2xl text-xs font-black text-white bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 shadow-md shadow-orange-500/20 transition-all cursor-pointer flex items-center justify-center gap-1 hover:scale-102 active:scale-98">
                                <span>⚡</span>
                                <span>{{ __('Senggol Balik ⚔️') }}</span>
                            </button>
                            <button type="button" 
                                    @click="$wire.acknowledgeIncomingNudge({{ $incNudge->id }}, false); window.location.href = '/tasks';" 
                                    class="flex-1 py-3 px-3 rounded-2xl text-xs font-black text-white bg-atoma-blue hover:bg-[#3455dc] shadow-md shadow-blue-500/20 transition-all cursor-pointer flex items-center justify-center gap-1 hover:scale-102 active:scale-98">
                                <span>💪</span>
                                <span>{{ __('Buka Tugas') }}</span>
                            </button>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    @endif

</div>
