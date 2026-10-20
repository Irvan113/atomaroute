<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Flashcard;
use App\Models\Journal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    // View state: 'decks' (deck collections overview) or 'cards' (sub-cards inside a deck or all)
    public $currentView = 'decks';
    public $activeDeck = null;
    public $selectedDeck = 'all';
    public $searchQuery = '';
    public $filterJournalId = null;

    // Study Mode State
    public $isStudying = false;
    public $studyCards = [];
    public $currentCardIndex = 0;
    public $isFlipped = false;
    public $studiedCount = 0;
    public $studyComplete = false;
    public $sessionStats = [
        'again' => 0,
        'hard' => 0,
        'good' => 0,
        'easy' => 0,
        'xpEarned' => 0,
    ];

    // Review Launcher Modal State
    public $showReviewLauncher = false;
    public $launcherDeck = 'all';
    public $reviewMode = 'due'; // 'due', 'struggle', 'new', 'all'
    public $reviewBatchSize = 20; // 10, 20, 50, 0 (all)

    // Quick Add Sub-Card Bar
    public $quickFront = '';
    public $quickBack = '';

    // Bulk Import Modal State
    public $showBulkModal = false;
    public $bulkDeckName = 'Umum';
    public $bulkInputText = '';

    // Create New Deck Modal State
    public $showNewDeckModal = false;
    public $newDeckName = '';

    // Create / Edit Single Card Modal State
    public $showCardModal = false;
    public $editingCardId = null;
    public $front = '';
    public $back = '';
    public $deck = 'Umum';
    public $journal_id = null;

    public function mount()
    {
        if (request()->has('journal_id')) {
            $this->filterJournalId = request('journal_id');
            $this->currentView = 'cards';
        }
        if (request()->has('deck')) {
            $this->activeDeck = request('deck');
            $this->selectedDeck = request('deck');
            $this->currentView = 'cards';
        }
        if (request()->has('study') && request('study') == 1) {
            $this->openReviewLauncher($this->activeDeck ?: 'all');
        }
    }

    #[Computed]
    public function decksWithStats()
    {
        $user = auth()->user();
        if (!$user) return [];

        $cards = $user->flashcards()->get();
        $grouped = $cards->groupBy(function ($card) {
            return $card->deck ?: 'Umum';
        });

        $decksData = [];
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($userTz);

        foreach ($grouped as $deckName => $deckCards) {
            $total = $deckCards->count();
            $due = $deckCards->filter(fn($c) => $c->next_review_at && $c->next_review_at <= $now)->count();
            $struggle = $deckCards->filter(fn($c) => (float)($c->ease_factor ?: 2.5) < 2.30 || ($c->repetitions > 0 && $c->interval_days <= 2))->count();
            $new = $deckCards->filter(fn($c) => $c->repetitions === 0)->count();
            $mastered = $deckCards->filter(fn($c) => $c->repetitions >= 4)->count();
            $learning = $deckCards->filter(fn($c) => $c->repetitions > 0 && $c->repetitions < 4)->count();

            $masteryPct = $total > 0 ? (int) round(($mastered / $total) * 100) : 0;

            $decksData[] = [
                'name' => $deckName,
                'total' => $total,
                'due' => $due,
                'struggle' => $struggle,
                'new' => $new,
                'mastered' => $mastered,
                'learning' => $learning,
                'masteryPct' => $masteryPct,
            ];
        }

        usort($decksData, function ($a, $b) {
            if ($b['due'] !== $a['due']) {
                return $b['due'] <=> $a['due'];
            }
            return $b['total'] <=> $a['total'];
        });

        return $decksData;
    }

    #[Computed]
    public function decks()
    {
        $user = auth()->user();
        if (!$user) return [];

        return $user->flashcards()
            ->select('deck')
            ->distinct()
            ->pluck('deck')
            ->filter()
            ->values()
            ->toArray();
    }

    #[Computed]
    public function userJournals()
    {
        $user = auth()->user();
        return $user ? $user->journals()->orderBy('title')->get() : collect();
    }

    #[Computed]
    public function stats()
    {
        $user = auth()->user();
        if (!$user) {
            return ['total' => 0, 'due' => 0, 'struggle' => 0, 'learning' => 0, 'mastered' => 0, 'decksCount' => 0];
        }

        $total = $user->flashcards()->count();
        $due = $user->flashcards()->dueForReview()->count();
        $struggle = $user->flashcards()->struggleCards()->count();
        $mastered = $user->flashcards()->masteredCards()->count();
        $learning = $user->flashcards()->learningCards()->count();

        return [
            'total' => $total,
            'due' => $due,
            'struggle' => $struggle,
            'learning' => $learning,
            'mastered' => $mastered,
            'decksCount' => count($this->decks),
        ];
    }

    #[Computed]
    public function launcherCounts()
    {
        $user = auth()->user();
        if (!$user) {
            return ['due' => 0, 'struggle' => 0, 'new' => 0, 'all' => 0];
        }

        $query = $user->flashcards();
        if ($this->launcherDeck !== 'all') {
            $query->where('deck', $this->launcherDeck);
        }

        return [
            'due' => (clone $query)->dueForReview()->count(),
            'struggle' => (clone $query)->struggleCards()->count(),
            'new' => (clone $query)->newCards()->count(),
            'all' => (clone $query)->count(),
        ];
    }

    #[Computed]
    public function cards()
    {
        $user = auth()->user();
        if (!$user) return collect();

        $query = $user->flashcards()->with('journal');

        if ($this->activeDeck) {
            $query->where('deck', $this->activeDeck);
        } elseif ($this->selectedDeck !== 'all') {
            $query->where('deck', $this->selectedDeck);
        }

        if ($this->filterJournalId) {
            $query->where('journal_id', $this->filterJournalId);
        }

        if (!empty($this->searchQuery)) {
            $q = '%' . $this->searchQuery . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('front', 'like', $q)
                    ->orWhere('back', 'like', $q)
                    ->orWhere('deck', 'like', $q);
            });
        }

        return $query->orderBy('next_review_at', 'asc')->get();
    }

    // View Navigation
    public function openDeckView($deckName)
    {
        $this->activeDeck = $deckName;
        $this->selectedDeck = $deckName;
        $this->currentView = 'cards';
        $this->searchQuery = '';
    }

    public function backToDecks()
    {
        $this->activeDeck = null;
        $this->selectedDeck = 'all';
        $this->currentView = 'decks';
        $this->searchQuery = '';
    }

    public function openCreateDeckModal()
    {
        $this->isStudying = false;
        $this->studyComplete = false;
        $this->newDeckName = '';
        $this->showNewDeckModal = true;
    }

    public function createDeck()
    {
        $this->validate([
            'newDeckName' => 'required|string|max:60',
        ]);

        $deckName = trim($this->newDeckName);
        $this->showNewDeckModal = false;
        $this->newDeckName = '';
        $this->openDeckView($deckName);

        $this->dispatch('toast', message: "Deck \"{$deckName}\" siap! Tambahkan sub-kartu pertamamu di bawah.", type: 'info');
    }

    public function deleteDeck($deckName)
    {
        $user = auth()->user();
        $count = $user->flashcards()->where('deck', $deckName)->count();
        $user->flashcards()->where('deck', $deckName)->delete();

        if ($this->activeDeck === $deckName) {
            $this->backToDecks();
        }

        $this->dispatch('toast', message: "Deck \"{$deckName}\" dan {$count} kartu berhasil dihapus.", type: 'nudge');
    }

    // Quick Inline Add Sub-Card
    public function quickAddSubCard()
    {
        $this->validate([
            'quickFront' => 'required|string|max:500',
            'quickBack' => 'required|string|max:1000',
        ]);

        $deckName = $this->activeDeck ?: ($this->selectedDeck !== 'all' ? $this->selectedDeck : 'Umum');

        auth()->user()->flashcards()->create([
            'front' => trim($this->quickFront),
            'back' => trim($this->quickBack),
            'deck' => $deckName,
            'journal_id' => $this->filterJournalId ?: null,
            'repetitions' => 0,
            'interval_days' => 1,
            'ease_factor' => 2.50,
            'next_review_at' => Carbon::now(),
        ]);

        $this->reset(['quickFront', 'quickBack']);
        $this->dispatch('toast', message: "Sub-kartu baru berhasil ditambahkan ke {$deckName}!", type: 'success');
    }

    // Bulk Import Modal & Logic
    public function openBulkModal($deckName = null)
    {
        $this->isStudying = false;
        $this->studyComplete = false;
        $this->bulkDeckName = $deckName ?: ($this->activeDeck ?: ($this->selectedDeck !== 'all' ? $this->selectedDeck : 'Umum'));
        $this->bulkInputText = '';
        $this->showBulkModal = true;
    }

    public function importBulkCards()
    {
        $this->validate([
            'bulkDeckName' => 'required|string|max:60',
            'bulkInputText' => 'required|string|min:3',
        ]);

        $parsed = Flashcard::parseBulkLines($this->bulkInputText);

        if (empty($parsed)) {
            $this->dispatch('toast', message: 'Format teks tidak terdeteksi. Gunakan "kata = arti" per baris.', type: 'nudge');
            return;
        }

        $deckName = trim($this->bulkDeckName);
        $user = auth()->user();
        $now = Carbon::now();

        DB::transaction(function () use ($user, $parsed, $deckName, $now) {
            foreach ($parsed as $item) {
                $user->flashcards()->create([
                    'front' => $item['front'],
                    'back' => $item['back'],
                    'deck' => $deckName,
                    'repetitions' => 0,
                    'interval_days' => 1,
                    'ease_factor' => 2.50,
                    'next_review_at' => $now,
                ]);
            }
        });

        $count = count($parsed);
        $this->showBulkModal = false;
        $this->bulkInputText = '';

        if ($this->currentView === 'decks') {
            $this->openDeckView($deckName);
        }

        $this->dispatch('toast', message: "Berhasil mengimpor {$count} sub-kartu ke deck \"{$deckName}\"!", type: 'success');
    }

    // Review Launcher & Study Modes
    public function openReviewLauncher($deckName = 'all')
    {
        $this->isStudying = false;
        $this->studyComplete = false;
        $this->studyCards = [];
        $this->currentCardIndex = 0;
        $this->isFlipped = false;

        $this->launcherDeck = !empty($deckName) ? $deckName : ($this->activeDeck ?: 'all');

        $counts = $this->launcherCounts;
        if ($counts['due'] > 0) {
            $this->reviewMode = 'due';
        } elseif ($counts['struggle'] > 0) {
            $this->reviewMode = 'struggle';
        } elseif ($counts['new'] > 0) {
            $this->reviewMode = 'new';
        } else {
            $this->reviewMode = 'all';
        }

        $this->reviewBatchSize = 20;
        $this->showReviewLauncher = true;
    }

    public function startConfiguredStudySession()
    {
        $user = auth()->user();
        $query = $user->flashcards();

        if ($this->launcherDeck !== 'all') {
            $query->where('deck', $this->launcherDeck);
        }

        switch ($this->reviewMode) {
            case 'due':
                $query->dueForReview()->orderBy('next_review_at', 'asc');
                break;
            case 'struggle':
                $query->struggleCards()->orderBy('ease_factor', 'asc');
                break;
            case 'new':
                $query->newCards()->orderBy('id', 'asc');
                break;
            case 'all':
                $query->inRandomOrder();
                break;
        }

        if ($this->reviewBatchSize > 0) {
            $query->limit($this->reviewBatchSize);
        }

        $cards = $query->get();

        if ($cards->isEmpty()) {
            $this->dispatch('toast', message: 'Tidak ada kartu yang cocok untuk mode review ini.', type: 'nudge');
            return;
        }

        $this->studyCards = $cards->values()->toArray();
        $this->currentCardIndex = 0;
        $this->isFlipped = false;
        $this->studiedCount = 0;
        $this->studyComplete = false;
        $this->sessionStats = [
            'again' => 0,
            'hard' => 0,
            'good' => 0,
            'easy' => 0,
            'xpEarned' => 0,
        ];

        $this->showReviewLauncher = false;
        $this->isStudying = true;
    }

    public function startStudySession()
    {
        $this->openReviewLauncher($this->activeDeck ?: ($this->selectedDeck !== 'all' ? $this->selectedDeck : 'all'));
    }

    public function flipCard()
    {
        $this->isFlipped = !$this->isFlipped;
    }

    public function rateCard($rating)
    {
        if (!isset($this->studyCards[$this->currentCardIndex])) return;

        $cardData = $this->studyCards[$this->currentCardIndex];
        $card = auth()->user()->flashcards()->find($cardData['id']);

        if ($card) {
            $card->recordReview($rating);
            $this->studiedCount++;
            
            if (isset($this->sessionStats[$rating])) {
                $this->sessionStats[$rating]++;
            }

            // Gamification EXP reward: +5 XP per card via GamificationService (respecting 50 XP daily cap)
            $awardedXp = \App\Services\GamificationService::awardFlashcardXp(auth()->user(), 1);
            $this->sessionStats['xpEarned'] += $awardedXp;

            // Check if reviewing flashcard triggers today's dynamic hidden mission
            $hiddenReward = \App\Services\GamificationService::checkDailyHiddenMission(auth()->user());
            if ($hiddenReward) {
                $this->dispatch('toast', message: $hiddenReward['message'], type: 'success');
                $this->dispatch('play-sound', preset: 'fanfare');
                $this->dispatch('trigger-confetti');
            }
        }

        $this->isFlipped = false;

        if ($this->currentCardIndex + 1 < count($this->studyCards)) {
            $this->currentCardIndex++;
        } else {
            $this->studyComplete = true;
            $this->dispatch('trigger-confetti');
            $this->dispatch('play-sound', type: 'success');
            $this->dispatch('toast', message: "Sesi review selesai! Kamu telah mempelajari {$this->studiedCount} kartu!", type: 'success');
        }
    }

    public function closeStudy()
    {
        $this->isStudying = false;
        $this->studyComplete = false;
        $this->studyCards = [];
        $this->currentCardIndex = 0;
        $this->isFlipped = false;
    }

    // Modal Create / Edit Single Card
    #[On('open-create-flashcard-modal')]
    public function openCreateModal($journalId = null)
    {
        $this->isStudying = false;
        $this->studyComplete = false;
        $this->reset(['front', 'back', 'editingCardId']);
        $this->deck = $this->activeDeck ?: ($this->selectedDeck !== 'all' ? $this->selectedDeck : 'Umum');
        $this->journal_id = $journalId ?: $this->filterJournalId;
        $this->showCardModal = true;
    }

    public function editCard($id)
    {
        $card = auth()->user()->flashcards()->findOrFail($id);
        $this->editingCardId = $card->id;
        $this->front = $card->front;
        $this->back = $card->back;
        $this->deck = $card->deck;
        $this->journal_id = $card->journal_id;
        $this->showCardModal = true;
    }

    public function saveCard()
    {
        $this->validate([
            'front' => 'required|string',
            'back' => 'required|string',
            'deck' => 'required|string',
        ]);

        if ($this->editingCardId) {
            $card = auth()->user()->flashcards()->findOrFail($this->editingCardId);
            $card->update([
                'front' => $this->front,
                'back' => $this->back,
                'deck' => $this->deck,
                'journal_id' => $this->journal_id ?: null,
            ]);
            $this->dispatch('toast', message: 'Flashcard berhasil diperbarui!', type: 'success');
        } else {
            auth()->user()->flashcards()->create([
                'front' => $this->front,
                'back' => $this->back,
                'deck' => $this->deck,
                'journal_id' => $this->journal_id ?: null,
                'repetitions' => 0,
                'interval_days' => 1,
                'ease_factor' => 2.5,
                'next_review_at' => Carbon::now(),
            ]);
            $this->dispatch('toast', message: 'Flashcard baru berhasil dibuat!', type: 'success');
        }

        $this->reset(['front', 'back', 'editingCardId', 'showCardModal']);
    }

    public function deleteCard($id)
    {
        $card = auth()->user()->flashcards()->findOrFail($id);
        $card->delete();
        $this->dispatch('toast', message: 'Flashcard dihapus.', type: 'nudge');
    }
}; ?>

<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-[#1b3cb8] via-atoma-blue to-[#284fd6] dark:from-gray-850 dark:via-indigo-950/70 dark:to-gray-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl shadow-atoma-blue/20 dark:shadow-none relative overflow-hidden border border-white/20 dark:border-indigo-500/30">
        <div class="absolute -right-8 -bottom-8 w-48 h-48 bg-white/10 dark:bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 relative z-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-white text-[#3E64FF] dark:bg-indigo-900/60 dark:text-indigo-300 dark:border dark:border-indigo-500/40 text-xs font-black mb-3 shadow-sm">
                    <x-icon.brain class="w-3.5 h-3.5 text-atoma-blue dark:text-indigo-400" />
                    <span>Spaced Repetition System (SRS)</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white dark:text-gray-100">Flashcards & Memory Deck</h1>
                <p class="mt-1 text-white/90 dark:text-gray-300 text-xs sm:text-sm max-w-xl font-medium">
                    Kuasai ribuan kosakata, rumus, dan materi dengan sistem Deck & Sub-Flashcard pintar berbasis sains pengulangan berkala.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" 
                        wire:click="openReviewLauncher('all')" 
                        class="px-4 py-2.5 rounded-2xl bg-white text-[#3E64FF] hover:bg-atoma-ice dark:bg-indigo-600 dark:hover:bg-indigo-500 dark:text-white font-black text-xs sm:text-sm shadow-lg dark:shadow-none hover:shadow-xl transition-all cursor-pointer flex items-center gap-2 transform active:scale-95 border border-white/30 dark:border-indigo-400/30">
                    <x-icon.rocket class="w-4 h-4 text-atoma-blue dark:text-white" />
                    <span>Mulai Review ({{ $this->stats['due'] }})</span>
                </button>
                <button type="button" 
                        wire:click="openBulkModal()" 
                        class="px-3.5 py-2.5 rounded-2xl bg-white/15 hover:bg-white/25 dark:bg-gray-800 dark:hover:bg-gray-750 text-white dark:text-gray-200 border border-white/30 dark:border-gray-700 font-bold text-xs sm:text-sm backdrop-blur-xs transition-all cursor-pointer flex items-center gap-1.5"
                        title="Import puluhan kosakata sekaligus">
                    <x-icon.download class="w-3.5 h-3.5" />
                    <span>Import Cepat</span>
                </button>
                <button type="button" 
                        wire:click="openCreateDeckModal" 
                        class="px-3.5 py-2.5 rounded-2xl bg-white/15 hover:bg-white/25 dark:bg-gray-800 dark:hover:bg-gray-750 text-white dark:text-gray-200 border border-white/30 dark:border-gray-700 font-bold text-xs sm:text-sm backdrop-blur-xs transition-all cursor-pointer flex items-center gap-1.5">
                    <x-icon.folder-plus class="w-3.5 h-3.5" />
                    <span>+ Deck</span>
                </button>
                <button type="button" 
                        wire:click="openCreateModal" 
                        class="px-3.5 py-2.5 rounded-2xl bg-white/15 hover:bg-white/25 dark:bg-gray-800 dark:hover:bg-gray-750 text-white dark:text-gray-200 border border-white/30 dark:border-gray-700 font-bold text-xs sm:text-sm backdrop-blur-xs transition-all cursor-pointer flex items-center gap-1.5">
                    <x-icon.plus class="w-3.5 h-3.5" />
                    <span>Buat Kartu</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-2xl shadow-xs border border-gray-100 dark:border-gray-700/80">
            <div class="text-xs font-bold text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                <x-icon.cards class="w-3.5 h-3.5 text-gray-400" />
                <span>Total Sub-Kartu</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white mt-1">
                {{ $this->stats['total'] }}
                <span class="text-xs font-normal text-gray-400 dark:text-gray-500 ml-1">di {{ $this->stats['decksCount'] }} deck</span>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-2xl shadow-xs border border-amber-200 dark:border-amber-900/40 bg-amber-50/20 dark:bg-amber-950/20">
            <div class="text-xs font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                <x-icon.clock class="w-3.5 h-3.5 text-amber-500" />
                <span>Perlu Direview</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ $this->stats['due'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-2xl shadow-xs border border-rose-200 dark:border-rose-900/40 bg-rose-50/20 dark:bg-rose-950/20">
            <div class="text-xs font-bold text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                <x-icon.flame class="w-3.5 h-3.5 text-rose-500" />
                <span>Perlu Latihan (Sulit)</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-rose-700 dark:text-rose-300 mt-1">{{ $this->stats['struggle'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-2xl shadow-xs border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/20 dark:bg-emerald-950/20">
            <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                <x-icon.trophy class="w-3.5 h-3.5 text-emerald-500" />
                <span>Sudah Dikuasai</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">{{ $this->stats['mastered'] }}</div>
        </div>
    </div>

    <!-- View Switcher & Toolbar Bar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-2xl shadow-xs border border-gray-100 dark:border-gray-700/80">
        <div class="flex items-center gap-2">
            @if($currentView === 'cards' && $activeDeck)
                <button type="button" 
                        wire:click="backToDecks" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-750 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all cursor-pointer flex items-center gap-1.5">
                    <x-icon.arrow-left class="w-3.5 h-3.5" />
                    <span>Semua Deck</span>
                </button>
                <div class="h-4 w-px bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs font-black text-gray-900 dark:text-white px-2.5 py-1 rounded-lg bg-atoma-ice dark:bg-indigo-950/50 text-atoma-blue dark:text-indigo-400 border border-atoma-aqua/40 dark:border-indigo-800/50">
                        <x-icon.folder class="w-3.5 h-3.5 text-atoma-blue dark:text-indigo-400" />
                        <span>{{ $activeDeck }}</span>
                    </span>
                    <span class="text-xs text-gray-400 font-medium">({{ count($this->cards) }} sub-kartu)</span>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <button type="button" 
                            wire:click="backToDecks" 
                            class="px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5 {{ $currentView === 'decks' ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-750 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                        <x-icon.folder class="w-3.5 h-3.5" />
                        <span>Koleksi Deck ({{ count($this->decksWithStats) }})</span>
                    </button>
                    <button type="button" 
                            wire:click="$set('currentView', 'cards'); $set('activeDeck', null);" 
                            class="px-4 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5 {{ ($currentView === 'cards' && !$activeDeck) ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-750 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                        <x-icon.cards class="w-3.5 h-3.5" />
                        <span>Semua Sub-Kartu ({{ $this->stats['total'] }})</span>
                    </button>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if($currentView === 'cards' && $activeDeck)
                <button type="button" 
                        wire:click="openReviewLauncher('{{ $activeDeck }}')" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-atoma-blue text-white hover:bg-[#3455dc] transition-all cursor-pointer flex items-center gap-1.5 shadow-xs">
                    <x-icon.target class="w-3.5 h-3.5" />
                    <span>Review Deck Ini</span>
                </button>
                <button type="button" 
                        wire:click="openBulkModal('{{ $activeDeck }}')" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-750 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all cursor-pointer flex items-center gap-1">
                    <x-icon.download class="w-3.5 h-3.5" />
                    <span>+ Import</span>
                </button>
                <button type="button" 
                        wire:confirm="Yakin ingin menghapus seluruh deck {{ $activeDeck }} beserta isinya?"
                        wire:click="deleteDeck('{{ $activeDeck }}')" 
                        class="p-1.5 rounded-xl text-xs font-bold text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 transition-all cursor-pointer" title="Hapus Deck">
                    <x-icon.trash class="w-3.5 h-3.5" />
                </button>
            @endif

            <div class="w-full sm:w-64">
                <input type="text" 
                       wire:model.live.debounce.300ms="searchQuery" 
                       placeholder="Cari kata / kartu / deck..." 
                       class="w-full px-3.5 py-1.5 rounded-xl text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-atoma-blue focus:border-atoma-blue text-gray-900 dark:text-white">
            </div>
        </div>
    </div>

    @if($currentView === 'decks')
        <!-- Deck Collections Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse($this->decksWithStats as $d)
                <div wire:key="deck-card-{{ $d['name'] }}" 
                     class="group bg-white dark:bg-gray-800 rounded-2xl shadow-xs border border-gray-100 dark:border-gray-700/80 p-5 flex flex-col justify-between hover:border-atoma-aqua/60 dark:hover:border-indigo-500/50 hover:shadow-md transition-all">
                    <div>
                        <!-- Header Deck Card -->
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="cursor-pointer flex items-center gap-2" wire:click="openDeckView('{{ $d['name'] }}')">
                                <x-icon.folder class="w-5 h-5 text-atoma-blue dark:text-indigo-400 shrink-0" />
                                <h3 class="text-base font-black text-gray-900 dark:text-white group-hover:text-atoma-blue dark:group-hover:text-indigo-400 transition-colors">
                                    {{ $d['name'] }}
                                </h3>
                            </div>
                            <span class="inline-flex items-center text-[11px] font-black px-2.5 py-0.5 rounded-full bg-atoma-ice dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-300 border border-atoma-aqua/40 dark:border-indigo-800/60">
                                {{ $d['total'] }} Sub-Kartu
                            </span>
                        </div>

                        <!-- Mastery Progress Bar -->
                        <div class="mt-2 mb-4">
                            <div class="flex items-center justify-between text-[11px] font-bold mb-1">
                                <span class="text-gray-500 dark:text-gray-400">Penguasaan Deck</span>
                                <span class="text-emerald-600 dark:text-emerald-400">{{ $d['masteryPct'] }}% ({{ $d['mastered'] }} kartu)</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-atoma-blue to-emerald-500 transition-all duration-300 rounded-full"
                                     style="width: {{ $d['masteryPct'] }}%;"></div>
                            </div>
                        </div>

                        <!-- Sub-cards Status Chips -->
                        <div class="grid grid-cols-2 gap-2 text-[11px]">
                            <div class="flex items-center justify-between p-2 rounded-xl {{ $d['due'] > 0 ? 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 font-bold border border-amber-200/60 dark:border-amber-900/40' : 'bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400' }}">
                                <span class="flex items-center gap-1.5">
                                    <x-icon.clock class="w-3.5 h-3.5 text-amber-500" />
                                    <span>Jatuh Tempo:</span>
                                </span>
                                <span class="font-black">{{ $d['due'] }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-xl {{ $d['struggle'] > 0 ? 'bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 font-bold border border-rose-200/60 dark:border-rose-900/40' : 'bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400' }}">
                                <span class="flex items-center gap-1.5">
                                    <x-icon.flame class="w-3.5 h-3.5 text-rose-500" />
                                    <span>Kartu Sulit:</span>
                                </span>
                                <span class="font-black">{{ $d['struggle'] }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-xl bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400">
                                <span class="flex items-center gap-1.5">
                                    <x-icon.sparkles class="w-3.5 h-3.5 text-blue-500" />
                                    <span>Belum Review:</span>
                                </span>
                                <span class="font-black text-gray-700 dark:text-gray-300">{{ $d['new'] }}</span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-xl bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400">
                                <span class="flex items-center gap-1.5">
                                    <x-icon.trophy class="w-3.5 h-3.5 text-emerald-500" />
                                    <span>Mastered:</span>
                                </span>
                                <span class="font-black text-emerald-600 dark:text-emerald-400">{{ $d['mastered'] }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Deck Actions Footer -->
                    <div class="mt-5 pt-3.5 border-t border-gray-100 dark:border-gray-700/80 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    wire:click="openReviewLauncher('{{ $d['name'] }}')" 
                                    class="px-3 py-1.5 rounded-xl text-xs font-black bg-atoma-blue hover:bg-[#3455dc] text-white transition-all cursor-pointer flex items-center gap-1.5 shadow-xs">
                                <x-icon.rocket class="w-3.5 h-3.5 text-white" />
                                <span>Review ({{ $d['due'] }})</span>
                            </button>
                            <button type="button" 
                                    wire:click="openDeckView('{{ $d['name'] }}')" 
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-750 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition-all cursor-pointer flex items-center gap-1.5">
                                <x-icon.book-open class="w-3.5 h-3.5" />
                                <span>Sub-Kartu</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-1">
                            <button type="button" 
                                    wire:click="openBulkModal('{{ $d['name'] }}')" 
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-atoma-blue hover:bg-gray-100 dark:hover:bg-gray-750 transition-colors cursor-pointer"
                                    title="Import kosakata sekaligus">
                                <x-icon.download class="w-3.5 h-3.5" />
                            </button>
                            <button type="button" 
                                    wire:confirm="Hapus seluruh deck {{ $d['name'] }} beserta isinya?"
                                    wire:click="deleteDeck('{{ $d['name'] }}')" 
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors cursor-pointer"
                                    title="Hapus Deck">
                                <x-icon.trash class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/80">
                    <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-atoma-ice dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-400 flex items-center justify-center shadow-xs">
                        <x-icon.cards class="w-8 h-8" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Belum Ada Deck Flashcard</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto mt-1">
                        Buat deck pertamamu (misal: "Vocabulary English", "Kanji N5", atau "Rumus Matematika") untuk mulai menghafal secara efektif.
                    </p>
                    <div class="mt-5 flex items-center justify-center gap-3">
                        <button type="button" 
                                wire:click="openCreateDeckModal" 
                                class="px-4 py-2 rounded-xl bg-atoma-blue text-white font-bold text-xs shadow-md shadow-atoma-blue/25 hover:bg-[#3455dc] cursor-pointer flex items-center gap-1.5">
                            <x-icon.folder-plus class="w-4 h-4" />
                            <span>Buat Deck Baru</span>
                        </button>
                        <button type="button" 
                                wire:click="openBulkModal()" 
                                class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-750 text-gray-700 dark:text-gray-200 font-bold text-xs hover:bg-gray-200 dark:hover:bg-gray-750 cursor-pointer flex items-center gap-1.5">
                            <x-icon.download class="w-4 h-4" />
                            <span>Import Sekaligus</span>
                        </button>
                    </div>
                </div>
            @endforelse
        </div>

    @else
        <!-- Sub-Cards View Mode -->
        <!-- Fast Inline Quick-Add Sub-Card Bar -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-850 dark:to-indigo-950/40 p-4 sm:p-5 rounded-2xl border border-blue-200/80 dark:border-indigo-800/50 shadow-xs">
            <div class="flex items-center justify-between mb-2.5">
                <div class="flex items-center gap-2">
                    <x-icon.zap class="w-4 h-4 text-atoma-blue dark:text-indigo-400" />
                    <h3 class="text-xs font-black uppercase tracking-wider text-blue-900 dark:text-indigo-200">
                        Tambah Sub-Kartu Cepat ke Deck: <span class="text-atoma-blue dark:text-indigo-400 font-extrabold">{{ $activeDeck ?: ($selectedDeck !== 'all' ? $selectedDeck : 'Umum') }}</span>
                    </h3>
                </div>
                <button type="button" 
                        wire:click="openBulkModal('{{ $activeDeck ?: ($selectedDeck !== 'all' ? $selectedDeck : 'Umum') }}')"
                        class="text-xs font-bold text-atoma-blue hover:text-[#3455dc] dark:text-indigo-400 hover:underline flex items-center gap-1 cursor-pointer">
                    <x-icon.download class="w-3.5 h-3.5" />
                    <span>Tempel Banyak Baris (Bulk Import)</span>
                </button>
            </div>

            <form wire:submit="quickAddSubCard" class="grid grid-cols-1 sm:grid-cols-12 gap-2.5">
                <div class="sm:col-span-5">
                    <input type="text" 
                           wire:model="quickFront" 
                           placeholder="Pertanyaan / Kata Asing (Front)... misal: ubiquitous" 
                           class="w-full px-3.5 py-2 text-xs rounded-xl bg-white dark:bg-gray-900 border border-blue-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue focus:border-atoma-blue text-gray-900 dark:text-white" required>
                    @error('quickFront') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-5">
                    <input type="text" 
                           wire:model="quickBack" 
                           placeholder="Jawaban / Arti (Back)... misal: ada di mana-mana" 
                           class="w-full px-3.5 py-2 text-xs rounded-xl bg-white dark:bg-gray-900 border border-blue-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue focus:border-atoma-blue text-gray-900 dark:text-white" required>
                    @error('quickBack') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" 
                            class="w-full py-2 px-3 rounded-xl bg-atoma-blue hover:bg-[#3455dc] text-white font-bold text-xs shadow-xs hover:shadow-md transition-all cursor-pointer flex items-center justify-center gap-1.5">
                        <x-icon.plus class="w-3.5 h-3.5" />
                        <span>Tambah</span>
                    </button>
                </div>
            </form>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2 flex items-center gap-1">
                <x-icon.info class="w-3.5 h-3.5 text-atoma-blue dark:text-indigo-400 shrink-0" />
                <span><b>Tip Cepat:</b> Tekan <kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 text-[10px] font-mono">Tab</kbd> untuk berpindah kolom, lalu <kbd class="px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 text-[10px] font-mono">Enter</kbd> untuk menyimpan.</span>
            </p>
        </div>

        @if($filterJournalId)
            <div class="flex items-center justify-between bg-atoma-ice dark:bg-atoma-blue/20 border border-atoma-aqua/50 dark:border-atoma-blue/40 rounded-2xl px-4 py-2 text-xs font-bold text-atoma-blue">
                <span class="inline-flex items-center gap-1.5">
                    <x-icon.cards class="w-3.5 h-3.5 text-atoma-blue" />
                    <span>Menampilkan Flashcard terhubung dengan Catatan #{{ $filterJournalId }}</span>
                </span>
                <button type="button" wire:click="$set('filterJournalId', null)" class="text-atoma-blue hover:underline cursor-pointer">
                    Reset Filter
                </button>
            </div>
        @endif

        <!-- Sub-Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($this->cards as $card)
                <div wire:key="flashcard-{{ $card->id }}" 
                     x-data="{ flipped: false }"
                     class="group bg-white dark:bg-gray-800 rounded-2xl shadow-xs border border-gray-100 dark:border-gray-700/80 p-4 sm:p-5 flex flex-col justify-between hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 transition-all">
                    
                    <div>
                        <!-- Top Badges -->
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue border border-atoma-aqua/50 dark:border-atoma-blue/40">
                                <x-icon.folder class="w-3 h-3 text-atoma-blue" />
                                <span>{{ $card->deck }}</span>
                            </span>

                            <!-- Difficulty SRS Pill -->
                            @if($card->repetitions === 0)
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                                    <x-icon.sparkles class="w-2.5 h-2.5" />
                                    <span>Baru</span>
                                </span>
                            @elseif($card->ease_factor < 2.30 || ($card->repetitions > 0 && $card->interval_days <= 2))
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300">
                                    <x-icon.flame class="w-2.5 h-2.5" />
                                    <span>Perlu Latihan</span>
                                </span>
                            @elseif($card->repetitions >= 4)
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    <x-icon.trophy class="w-2.5 h-2.5" />
                                    <span>Dikuasai</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 dark:bg-gray-750 dark:text-gray-300">
                                    <x-icon.book-open class="w-2.5 h-2.5" />
                                    <span>Belajar</span>
                                </span>
                            @endif
                        </div>

                        <!-- Flip Card Content -->
                        <div @click="flipped = !flipped" class="cursor-pointer min-h-[110px] flex flex-col justify-center text-center p-3 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800 transition-all hover:bg-atoma-ice/30">
                            <div x-show="!flipped" class="space-y-1">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block">Pertanyaan / Kata</span>
                                <p class="text-sm font-bold text-gray-900 dark:text-white leading-snug">{{ $card->front }}</p>
                                <span class="inline-flex items-center gap-1 text-[10px] text-atoma-blue font-semibold pt-1">
                                    <span>Klik untuk lihat jawaban</span>
                                    <x-icon.repeat class="w-2.5 h-2.5" />
                                </span>
                            </div>
                            <div x-show="flipped" style="display: none;" class="space-y-1">
                                <span class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest block">Jawaban / Arti</span>
                                <p class="text-xs text-gray-700 dark:text-gray-200 font-medium leading-relaxed whitespace-pre-line">{{ $card->back }}</p>
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-400 font-semibold pt-1">
                                    <span>Klik untuk balik kartu</span>
                                    <x-icon.repeat class="w-2.5 h-2.5" />
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Card Metadata & Actions -->
                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/80 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                        <div class="flex items-center gap-2 font-mono">
                            <span title="Interval Pengulangan" class="inline-flex items-center gap-1">
                                <x-icon.clock class="w-3 h-3 text-gray-400" />
                                <span>{{ $card->interval_days }}h</span>
                            </span>
                            <span>•</span>
                            <span title="Repetisi Selesai" class="inline-flex items-center gap-1">
                                <x-icon.repeat class="w-3 h-3 text-gray-400" />
                                <span>{{ $card->repetitions }}x</span>
                            </span>
                            <span>•</span>
                            <span title="Ease Factor" class="inline-flex items-center gap-1">
                                <x-icon.zap class="w-3 h-3 text-gray-400" />
                                <span>{{ number_format($card->ease_factor ?: 2.5, 2) }}</span>
                            </span>
                        </div>

                        <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                            <button type="button" 
                                    wire:click="editCard({{ $card->id }})" 
                                    class="p-1 text-gray-400 hover:text-atoma-blue rounded-md transition-colors cursor-pointer" title="Edit Kartu">
                                <x-icon.pencil class="w-3.5 h-3.5" />
                            </button>
                            <button type="button" 
                                    wire:click="deleteCard({{ $card->id }})" 
                                    wire:confirm="{{ __('Yakin ingin menghapus kartu flashcard ini?') }}"
                                    class="p-1 text-gray-400 hover:text-red-600 rounded-md transition-colors cursor-pointer" title="Hapus Kartu">
                                <x-icon.trash class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                </div>
            @empty
                <div class="col-span-full text-center py-12 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/80">
                    <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-gray-100 dark:bg-gray-700/60 text-gray-400 flex items-center justify-center">
                        <x-icon.cards class="w-8 h-8" />
                    </div>
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-200">Belum ada Sub-Kartu di Deck Ini</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto mt-1">
                        Gunakan bar input kilat di atas untuk menambahkan kosakata pertama atau lakukan import banyak kartu sekaligus!
                    </p>
                </div>
            @endforelse
        </div>
    @endif

    <!-- Review Pre-Flight Launcher Modal -->
    @if($showReviewLauncher)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm"
             @keydown.window.escape="$wire.set('showReviewLauncher', false)">
            <div class="bg-white dark:bg-gray-900 w-full max-w-xl rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="p-6 sm:p-7 space-y-5">
                    <!-- Title & Header -->
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-2xl bg-atoma-blue/10 dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-400 flex items-center justify-center">
                                <x-icon.target class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-black text-gray-900 dark:text-white">Konfigurasi Sesi Review</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Pilih fokus materi dan jumlah kartu yang ingin direview.</p>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showReviewLauncher', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 cursor-pointer">
                            <x-icon.x class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Target Deck Selector -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                            Pilih Deck Target:
                        </label>
                        <select wire:model.live="launcherDeck" class="w-full px-3.5 py-2 text-xs rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue text-gray-900 dark:text-white font-bold">
                            <option value="all">Semua Deck Gabungan ({{ $this->stats['total'] }} kartu)</option>
                            @foreach($this->decks as $deckOption)
                                <option value="{{ $deckOption }}">{{ $deckOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Focus Review Mode Options -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">
                            Mau Review Apa Saja? (Mode Fokus):
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <!-- Due Mode -->
                            <div wire:click="$set('reviewMode', 'due')"
                                 class="p-3.5 rounded-2xl border-2 cursor-pointer transition-all {{ $reviewMode === 'due' ? 'border-atoma-blue bg-blue-50/50 dark:bg-indigo-950/40 shadow-xs' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 bg-white dark:bg-gray-850' }}">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <x-icon.clock class="w-3.5 h-3.5 text-amber-500" />
                                        <span>Jatuh Tempo (SRS)</span>
                                    </span>
                                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300">
                                        {{ $this->launcherCounts['due'] }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-snug">
                                    Kartu yang sudah waktunya diulang berdasarkan kurva ingatan otak.
                                </p>
                            </div>

                            <!-- Struggle Mode -->
                            <div wire:click="$set('reviewMode', 'struggle')"
                                 class="p-3.5 rounded-2xl border-2 cursor-pointer transition-all {{ $reviewMode === 'struggle' ? 'border-rose-500 bg-rose-50/50 dark:bg-rose-950/40 shadow-xs' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 bg-white dark:bg-gray-850' }}">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <x-icon.flame class="w-3.5 h-3.5 text-rose-500" />
                                        <span>Fokus Kartu Sulit</span>
                                    </span>
                                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300">
                                        {{ $this->launcherCounts['struggle'] }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-snug">
                                    Perbaiki memori pada kartu yang paling sering lupa / sulit diingat.
                                </p>
                            </div>

                            <!-- New Cards Mode -->
                            <div wire:click="$set('reviewMode', 'new')"
                                 class="p-3.5 rounded-2xl border-2 cursor-pointer transition-all {{ $reviewMode === 'new' ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/40 shadow-xs' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 bg-white dark:bg-gray-850' }}">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <x-icon.sparkles class="w-3.5 h-3.5 text-emerald-500" />
                                        <span>Pelajari Kartu Baru</span>
                                    </span>
                                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                                        {{ $this->launcherCounts['new'] }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-snug">
                                    Kuasai sub-kartu baru yang belum pernah direview sebelumnya.
                                </p>
                            </div>

                            <!-- Cram Mode -->
                            <div wire:click="$set('reviewMode', 'all')"
                                 class="p-3.5 rounded-2xl border-2 cursor-pointer transition-all {{ $reviewMode === 'all' ? 'border-purple-500 bg-purple-50/50 dark:bg-purple-950/40 shadow-xs' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 bg-white dark:bg-gray-850' }}">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <x-icon.zap class="w-3.5 h-3.5 text-purple-500" />
                                        <span>Latihan Bebas (Cram)</span>
                                    </span>
                                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300">
                                        {{ $this->launcherCounts['all'] }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-snug">
                                    Uji hafalan secara acak dari semua kartu tanpa menunggu jadwal.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Session Batch Size -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-xs font-bold text-gray-700 dark:text-gray-300">
                                Jumlah Kartu Sesi Ini:
                            </label>
                            <span class="inline-flex items-center gap-1 text-[10px] text-atoma-blue dark:text-indigo-400 font-bold">
                                <x-icon.info class="w-3 h-3" />
                                <span>Mencegah kejenuhan belajar</span>
                            </span>
                        </div>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach([10, 20, 50, 0] as $size)
                                <button type="button" 
                                        wire:click="$set('reviewBatchSize', {{ $size }})"
                                        class="py-2 rounded-xl text-xs font-black transition-all cursor-pointer border {{ $reviewBatchSize === $size ? 'bg-atoma-blue text-white border-atoma-blue shadow-xs' : 'bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-100' }}">
                                    {{ $size === 0 ? 'Semua' : $size . ' Kartu' }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                    <button type="button" wire:click="$set('showReviewLauncher', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                        Batal
                    </button>
                    <button type="button" 
                            wire:click="startConfiguredStudySession" 
                            class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-atoma-blue hover:bg-[#3455dc] shadow-md shadow-atoma-blue/25 cursor-pointer flex items-center gap-2">
                        <x-icon.rocket class="w-4 h-4 text-white" />
                        <span>Mulai Review Sekarang</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Bulk Import Modal -->
    @if($showBulkModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/60 backdrop-blur-sm"
             @keydown.window.escape="$wire.set('showBulkModal', false)">
            <div class="bg-white dark:bg-gray-900 w-full max-w-xl rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-800 overflow-hidden">
                <form wire:submit="importBulkCards">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-atoma-blue/10 dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-400 flex items-center justify-center">
                                    <x-icon.download class="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 class="text-base font-black text-gray-900 dark:text-white">Import Cepat Sub-Flashcard</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Tempel puluhan kosakata sekaligus untuk menghemat waktu.</p>
                                </div>
                            </div>
                            <button type="button" wire:click="$set('showBulkModal', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 cursor-pointer">
                                <x-icon.x class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Target Deck -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Nama Deck Target *
                            </label>
                            <input type="text" 
                                   wire:model="bulkDeckName" 
                                   placeholder="misal: Vocabulary English, Kanji N5, Biologi..." 
                                   class="w-full px-3.5 py-2 text-xs rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue text-gray-900 dark:text-white font-bold" required>
                            @error('bulkDeckName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <!-- Bulk Textarea -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300">
                                    Daftar Kata & Jawaban (Satu Baris Per Kartu) *
                                </label>
                                <span class="text-[10px] text-gray-400">Format: Kata = Arti</span>
                            </div>
                            <textarea wire:model="bulkInputText" 
                                      rows="9" 
                                      placeholder="Contoh format:
apple = apel
book = buku
cat : kucing
dog - anjing
run	berlari" 
                                      class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue text-gray-900 dark:text-white" required></textarea>
                            @error('bulkInputText') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-[11px] text-blue-800 dark:text-blue-300 flex items-start gap-2">
                            <x-icon.info class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                            <span>Pemisah yang didukung otomatis: tanda sama dengan (<code class="font-mono bg-blue-100 dark:bg-blue-900 px-1 rounded">=</code>), titik dua (<code class="font-mono bg-blue-100 dark:bg-blue-900 px-1 rounded">:</code>), strip (<code class="font-mono bg-blue-100 dark:bg-blue-900 px-1 rounded">-</code>), atau Tab.</span>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-3.5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showBulkModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-atoma-blue hover:bg-[#3455dc] shadow-md shadow-atoma-blue/25 cursor-pointer flex items-center gap-1.5">
                            <x-icon.download class="w-4 h-4" />
                            <span>Impor Sub-Kartu</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Create New Deck Modal -->
    @if($showNewDeckModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/60 backdrop-blur-sm"
             @keydown.window.escape="$wire.set('showNewDeckModal', false)">
            <div class="bg-white dark:bg-gray-900 w-full max-w-md rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-800 overflow-hidden">
                <form wire:submit="createDeck">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                            <h3 class="text-base font-black text-gray-900 dark:text-white flex items-center gap-2">
                                <x-icon.folder-plus class="w-5 h-5 text-atoma-blue dark:text-indigo-400" />
                                <span>Buat Deck Baru</span>
                            </h3>
                            <button type="button" wire:click="$set('showNewDeckModal', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 cursor-pointer">
                                <x-icon.x class="w-4 h-4" />
                            </button>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Nama Deck / Topik Materi *
                            </label>
                            <input type="text" 
                                   wire:model="newDeckName" 
                                   placeholder="misal: JLPT N3, Biologi Sel, English Idioms..." 
                                   class="w-full px-3.5 py-2.5 text-xs rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue text-gray-900 dark:text-white font-bold" required autofocus>
                            @error('newDeckName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-3.5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showNewDeckModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-atoma-blue hover:bg-[#3455dc] shadow-md shadow-atoma-blue/25 cursor-pointer">
                            Buat & Buka Deck
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Study Mode Interactive Modal (Full Screen Experience) -->
    @if($isStudying)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/80 backdrop-blur-md" 
             @keydown.window.escape="$wire.closeStudy()"
             @keydown.window.space.prevent="$wire.flipCard()"
             @keydown.window.1="$wire.rateCard('again')"
             @keydown.window.2="$wire.rateCard('hard')"
             @keydown.window.3="$wire.rateCard('good')"
             @keydown.window.4="$wire.rateCard('easy')">
            
            <div class="bg-white dark:bg-gray-900 w-full max-w-2xl rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden flex flex-col">
                
                <!-- Study Header -->
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-icon.brain class="w-5 h-5 text-atoma-blue dark:text-indigo-400" />
                        <span class="font-black text-sm text-gray-900 dark:text-white">Sesi Belajar Flashcard</span>
                        <span class="text-xs font-mono text-atoma-blue dark:text-atoma-cyan font-bold ml-2">
                            {{ $currentCardIndex + 1 }} / {{ count($studyCards) }}
                        </span>
                    </div>

                    <button type="button" wire:click="closeStudy" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 cursor-pointer">
                        <x-icon.x class="w-5 h-5" />
                    </button>
                </div>

                <!-- Study Progress Bar -->
                <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-800">
                    <div class="h-full bg-gradient-to-r from-atoma-blue to-atoma-cyan transition-all duration-300"
                         style="width: {{ count($studyCards) > 0 ? (($currentCardIndex + 1) / count($studyCards)) * 100 : 0 }}%;"></div>
                </div>

                <!-- Card Content Area -->
                <div class="p-6 sm:p-10 flex-1 flex flex-col items-center justify-center min-h-[320px]">
                    @if(!$studyComplete && isset($studyCards[$currentCardIndex]))
                        @php $currentCard = $studyCards[$currentCardIndex]; @endphp
                        
                        <div wire:click="flipCard" 
                             class="w-full h-[280px] sm:h-[320px] bg-gray-50 dark:bg-gray-800/80 rounded-2xl border-2 border-atoma-aqua/60 dark:border-atoma-blue/40 p-5 sm:p-6 flex flex-col cursor-pointer shadow-md hover:shadow-lg transition-all hover:scale-[1.005] relative overflow-hidden group">
                            
                            <div class="flex items-center justify-between w-full mb-2 shrink-0">
                                <div class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-atoma-ice text-atoma-blue dark:bg-indigo-950/60 dark:text-indigo-300 border border-atoma-aqua/50 dark:border-indigo-500/40 flex items-center gap-1">
                                    <x-icon.folder class="w-3 h-3 text-atoma-blue dark:text-indigo-400" />
                                    <span>{{ $currentCard['deck'] }}</span>
                                </div>
                                
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                    {{ !$isFlipped ? __('Pertanyaan / Kata') : __('Jawaban / Arti') }}
                                </span>
                            </div>

                            <!-- Dedicated Scrollable Content Area -->
                            <div class="flex-1 overflow-y-auto my-auto py-2 px-1 flex flex-col items-center justify-center text-center w-full"
                                 style="scrollbar-width: thin; scrollbar-color: rgba(99, 102, 241, 0.3) transparent;">
                                @if(!$isFlipped)
                                    <h2 class="text-base sm:text-xl font-black text-gray-900 dark:text-white max-w-lg leading-relaxed break-words select-none">
                                        {{ $currentCard['front'] }}
                                    </h2>
                                @else
                                    <div class="text-sm sm:text-base font-medium text-gray-800 dark:text-gray-100 max-w-lg leading-relaxed whitespace-pre-line break-words select-text">
                                        {{ $currentCard['back'] }}
                                    </div>
                                @endif
                            </div>

                            <!-- Flip Hint at bottom -->
                            <div class="shrink-0 pt-2 text-center border-t border-gray-150 dark:border-gray-700/50">
                                <p class="inline-flex items-center gap-1.5 text-xs text-atoma-blue dark:text-indigo-400 font-bold">
                                    <x-icon.repeat class="w-3.5 h-3.5" />
                                    <span>{{ !$isFlipped ? __('Klik atau tekan Spasi untuk melihat jawaban') : __('Tekan 1-4 untuk menilai daya ingat') }}</span>
                                </p>
                            </div>
                        </div>

                    @else
                        <!-- Study Completed Celebration -->
                        <div class="text-center py-6">
                            <div class="w-16 h-16 mx-auto mb-3 rounded-3xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center animate-bounce shadow-md">
                                <x-icon.trophy class="w-8 h-8" />
                            </div>
                            <h2 class="text-2xl font-black text-gray-900 dark:text-white">Sesi Review Selesai!</h2>
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-md mx-auto">
                                Hebat! Kamu telah menyelesaikan seluruh kartu review untuk sesi ini. Ingatanmu sekarang semakin tajam!
                            </p>

                            <!-- Session Stats Breakdown -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 max-w-md mx-auto mt-6 text-xs font-bold">
                                <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon.check-circle class="w-3.5 h-3.5 text-emerald-600" />
                                        <span>Bagus:</span>
                                    </span>
                                    <span>{{ $sessionStats['good'] }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon.sparkles class="w-3.5 h-3.5 text-blue-600" />
                                        <span>Mudah:</span>
                                    </span>
                                    <span>{{ $sessionStats['easy'] }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon.alert-triangle class="w-3.5 h-3.5 text-amber-600" />
                                        <span>Sulit:</span>
                                    </span>
                                    <span>{{ $sessionStats['hard'] }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon.repeat class="w-3.5 h-3.5 text-rose-600" />
                                        <span>Lagi:</span>
                                    </span>
                                    <span>{{ $sessionStats['again'] }}</span>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                <x-icon.star class="w-3.5 h-3.5 text-amber-400" />
                                <span>Total XP Diperoleh: +{{ $sessionStats['xpEarned'] }} XP</span>
                            </div>

                            <div class="mt-6 flex items-center justify-center gap-3">
                                <button type="button" 
                                        wire:click="openReviewLauncher('{{ $launcherDeck }}')" 
                                        class="px-5 py-2.5 rounded-xl bg-atoma-blue text-white font-bold text-xs sm:text-sm shadow-md shadow-atoma-blue/25 hover:bg-[#3455dc] cursor-pointer flex items-center gap-1.5">
                                    <x-icon.target class="w-3.5 h-3.5" />
                                    <span>Lanjut Review / Mode Lain</span>
                                </button>
                                <button type="button" 
                                        wire:click="closeStudy" 
                                        class="px-5 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-bold text-xs sm:text-sm hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                                    Kembali ke Deck
                                </button>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Rating Buttons (Only show when flipped) -->
                @if(!$studyComplete)
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-800">
                        @if($isFlipped)
                            <div class="text-center mb-2 text-[11px] font-bold text-gray-500 dark:text-gray-400">
                                Seberapa mudah kamu mengingat kartu ini? (Gunakan angka 1-4 pada keyboard)
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <button type="button" 
                                        wire:click="rateCard('again')" 
                                        class="py-2.5 px-3 rounded-xl bg-red-100 hover:bg-red-200 dark:bg-red-950/60 dark:hover:bg-red-900/80 text-red-700 dark:text-red-300 font-bold text-xs transition-all cursor-pointer flex flex-col items-center">
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon.repeat class="w-3.5 h-3.5 text-red-600" />
                                        <span>Lagi</span>
                                    </span>
                                    <span class="text-[9px] font-normal opacity-80 mt-0.5">Ulangi besok (1)</span>
                                </button>
                                <button type="button" 
                                        wire:click="rateCard('hard')" 
                                        class="py-2.5 px-3 rounded-xl bg-amber-100 hover:bg-amber-200 dark:bg-amber-950/60 dark:hover:bg-amber-900/80 text-amber-700 dark:text-amber-300 font-bold text-xs transition-all cursor-pointer flex flex-col items-center">
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon.alert-triangle class="w-3.5 h-3.5 text-amber-600" />
                                        <span>Sulit</span>
                                    </span>
                                    <span class="text-[9px] font-normal opacity-80 mt-0.5">Interval x1.2 (2)</span>
                                </button>
                                <button type="button" 
                                        wire:click="rateCard('good')" 
                                        class="py-2.5 px-3 rounded-xl bg-emerald-100 hover:bg-emerald-200 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/80 text-emerald-700 dark:text-emerald-300 font-bold text-xs transition-all cursor-pointer flex flex-col items-center">
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon.check-circle class="w-3.5 h-3.5 text-emerald-600" />
                                        <span>Bagus</span>
                                    </span>
                                    <span class="text-[9px] font-normal opacity-80 mt-0.5">Standar SRS (3)</span>
                                </button>
                                <button type="button" 
                                        wire:click="rateCard('easy')" 
                                        class="py-2.5 px-3 rounded-xl bg-blue-100 hover:bg-blue-200 dark:bg-blue-950/60 dark:hover:bg-blue-900/80 text-blue-700 dark:text-blue-300 font-bold text-xs transition-all cursor-pointer flex flex-col items-center">
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon.sparkles class="w-3.5 h-3.5 text-blue-600" />
                                        <span>Mudah</span>
                                    </span>
                                    <span class="text-[9px] font-normal opacity-80 mt-0.5">Interval x2.5 (4)</span>
                                </button>
                            </div>
                        @else
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Pikirkan jawabannya terlebih dahulu...</span>
                                <button type="button" 
                                        wire:click="flipCard" 
                                        class="px-5 py-2 rounded-xl bg-atoma-blue hover:bg-[#3455dc] text-white font-bold text-xs transition-all cursor-pointer shadow-sm flex items-center gap-1.5">
                                    <span>Tampilkan Jawaban</span>
                                    <x-icon.repeat class="w-3 h-3" />
                                    <span>(Spasi)</span>
                                </button>
                            </div>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    @endif

    <!-- Create / Edit Card Modal -->
    @if($showCardModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 w-full max-w-lg rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <form wire:submit="saveCard">
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                @if($editingCardId)
                                    <x-icon.pencil class="w-4 h-4 text-atoma-blue" />
                                    <span>Edit Flashcard</span>
                                @else
                                    <x-icon.plus class="w-4 h-4 text-atoma-blue" />
                                    <span>Buat Flashcard Baru</span>
                                @endif
                            </h3>
                            <button type="button" wire:click="$set('showCardModal', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 cursor-pointer">
                                <x-icon.x class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Front / Question -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Pertanyaan / Sisi Depan (Front) *
                            </label>
                            <textarea wire:model="front" 
                                      rows="3" 
                                      placeholder="Tuliskan pertanyaan, konsep, atau kata kunci..." 
                                      class="w-full px-3.5 py-2 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue focus:border-atoma-blue text-gray-900 dark:text-white" required></textarea>
                            @error('front') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <!-- Back / Answer -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Jawaban / Sisi Belakang (Back) *
                            </label>
                            <textarea wire:model="back" 
                                      rows="4" 
                                      placeholder="Tuliskan penjelasan, jawaban lengkap, atau rumus..." 
                                      class="w-full px-3.5 py-2 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue focus:border-atoma-blue text-gray-900 dark:text-white" required></textarea>
                            @error('back') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Deck -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    Nama Deck / Kategori *
                                </label>
                                <input type="text" 
                                       wire:model="deck" 
                                       placeholder="misal: Umum, Coding, Kuliah..." 
                                       class="w-full px-3.5 py-2 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue focus:border-atoma-blue text-gray-900 dark:text-white" required>
                            </div>

                            <!-- Link to Note -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    Hubungkan ke Catatan (Opsional)
                                </label>
                                <select wire:model="journal_id" class="w-full px-3.5 py-2 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-atoma-blue focus:border-atoma-blue text-gray-900 dark:text-white">
                                    <option value="">-- Tanpa Hubungan --</option>
                                    @foreach($this->userJournals as $j)
                                        <option value="{{ $j->id }}">{{ $j->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-3.5 border-t border-gray-100 dark:border-gray-700 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showCardModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-atoma-blue hover:bg-[#3455dc] shadow-md shadow-atoma-blue/25 cursor-pointer">
                            {{ $editingCardId ? 'Simpan Perubahan' : 'Buat Flashcard' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
