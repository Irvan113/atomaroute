{{-- Mindful Overdue Review Tray (Anti-Manja: No silent rollover, conscious triage) --}}
@php
    $pendingOverdueTasks = $this->overdueTasks->where('is_completed', false);
    $pendingOverdueCount = $pendingOverdueTasks->count();
@endphp
@if(\Carbon\Carbon::parse($selectedDate)->isToday() && $pendingOverdueTasks->isNotEmpty())
    <div x-data="{ open: false }" class="mb-5 rounded-2xl border border-amber-200/90 dark:border-amber-800/60 bg-gradient-to-r from-amber-500/10 via-orange-500/5 to-transparent p-4 sm:p-5 shadow-xs transition-all">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg shrink-0 font-bold border border-amber-500/30">
                    ⏰
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h4 class="text-sm sm:text-base font-black text-gray-900 dark:text-white">
                            {{ $pendingOverdueCount }} Tugas Tertunda Perlu Ditinjau
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                            Anti-Manja Review
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Tugas overdue selesai menghasilkan 0 EXP &amp; tidak menambah streak.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" 
                        wire:click="dismissAllOverdue"
                        wire:loading.attr="disabled"
                        wire:confirm="Abaikan semua notifikasi tugas tertunda ini? Tugas akan tetap tersimpan di tanggal aslinya."
                        class="px-3 py-1.5 rounded-xl bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 active:scale-95 text-gray-700 dark:text-gray-200 text-xs font-bold shadow-xs transition-all flex items-center gap-1.5 cursor-pointer">
                    <span>👁️ Abaikan Semua Notif</span>
                </button>
                <button type="button" 
                        @click="open = !open" 
                        class="px-3 py-1.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-bold transition-all flex items-center gap-1 cursor-pointer">
                    <span x-text="open ? 'Tutup Daftar' : 'Tinjau Rinci ({{ $pendingOverdueCount }})'"></span>
                    <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
            </div>
        </div>

        <!-- Overdue Tasks List Accordion -->
        <div x-show="open" x-collapse x-cloak class="mt-4 pt-4 border-t border-amber-200/70 dark:border-amber-800/40 space-y-2.5">
            @foreach($pendingOverdueTasks as $overdue)
                @php
                    $isOverdueDone = (bool)$overdue->is_completed;
                @endphp
                <div class="p-3 rounded-xl {{ $isOverdueDone ? 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-300/80 dark:border-amber-800/60' : 'bg-white/90 dark:bg-gray-800/90 border-gray-200 dark:border-gray-700' }} border flex items-center justify-between gap-3 flex-wrap sm:flex-nowrap transition-all">
                    <div class="flex items-center gap-3 min-w-0">
                        <button type="button" 
                                wire:click="toggleComplete({{ $overdue->id }})"
                                class="w-6 h-6 rounded-md border-2 {{ $isOverdueDone ? 'bg-gradient-to-r from-amber-500 to-orange-500 border-amber-500 text-white shadow-xs' : 'border-amber-400/80 dark:border-amber-600/80 hover:bg-amber-500/20 text-transparent hover:text-amber-500' }} flex items-center justify-center font-bold text-xs transition-all shrink-0 cursor-pointer"
                                title="{{ $isOverdueDone ? 'Batalkan selesai (klik untuk uncheck)' : 'Selesaikan sekarang (0 EXP, tidak menambah streak)' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xs sm:text-sm font-bold {{ $isOverdueDone ? 'line-through text-gray-500 dark:text-gray-400' : 'text-gray-800 dark:text-gray-200' }} truncate">
                                    {{ $overdue->title }}
                                </p>
                                @if($overdue->goal_id && $overdue->goal)
                                    <x-icon.goal-flame class="w-3.5 h-3.5" title="Goal: {{ $overdue->goal->title }}" />
                                @endif
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 shrink-0">
                                    {{ \Carbon\Carbon::parse($overdue->date)->format('d M') }}
                                </span>
                                @if($isOverdueDone)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-gradient-to-r from-amber-500/20 to-orange-500/20 text-orange-700 dark:text-orange-300 border border-orange-500/30">
                                        🟠 Overdue Checklist (0 EXP)
                                    </span>
                                @endif
                                @if($overdue->skip_reason)
                                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700 inline-flex items-center gap-1">
                                        💬 {{ $overdue->skip_reason }}
                                    </span>
                                @endif
                            </div>
                            @if($overdue->category)
                                <p class="text-[11px] text-gray-400 truncate mt-0.5">📂 {{ $overdue->category }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0 ml-auto">
                        @if(!$isOverdueDone)
                            <!-- Tombol Beri Alasan -->
                            <button type="button" 
                                    wire:click="openOverdueReasonModal({{ $overdue->id }})"
                                    class="px-2.5 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-700 dark:text-amber-300 text-xs font-bold transition-all flex items-center gap-1 cursor-pointer"
                                    title="Berikan alasan keterlambatan untuk analisis AI di Insights">
                                <span>💬</span>
                                <span>{{ $overdue->skip_reason ? 'Ubah Alasan' : 'Beri Alasan' }}</span>
                            </button>

                            <!-- Tombol Abaikan (notif hilang, tugas tetap di halaman task tanggal sekian) -->
                            <button type="button" 
                                    wire:click="dismissOverdueTask({{ $overdue->id }})"
                                    class="px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-700/80 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 text-xs font-bold transition-all flex items-center gap-1 cursor-pointer"
                                    title="Abaikan dari notifikasi hari ini (tugas tetap ada di tanggal aslinya)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path></svg>
                                <span>Abaikan</span>
                            </button>
                        @endif

                        <!-- Tombol Hapus -->
                        <button type="button" 
                                wire:click="deleteTask({{ $overdue->id }})"
                                wire:confirm="Hapus tugas tertunda ini? Tugas akan dicatat di Insights sebagai tugas overdue yang dihapus."
                                class="p-1.5 rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition-all cursor-pointer"
                                title="Hapus Tugas">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

{{-- 🧠 Daily Agenda SRS Flashcard Review Integration --}}
@if(\Carbon\Carbon::parse($selectedDate)->isToday() && $this->dueFlashcardsCount > 0)
    <div x-data="{ openReview: false, flipped: false }" 
         class="mb-5 rounded-2xl border border-indigo-200/90 dark:border-indigo-800/60 bg-gradient-to-r from-indigo-500/10 via-purple-500/5 to-transparent p-4 sm:p-5 shadow-xs transition-all">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg shrink-0 font-bold border border-indigo-500/30">
                    🧠
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h4 class="text-sm sm:text-base font-black text-gray-900 dark:text-white">
                            {{ $this->dueFlashcardsCount }} Kartu Flashcard Perlu Diulang (SRS)
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
                            Spaced Repetition
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap mt-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Jadwal review hari ini untuk mengunci memori jangka panjang:
                        </p>
                        @foreach($this->dueFlashcardDecks as $deckName => $cardCount)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-white/80 dark:bg-gray-800/80 border border-indigo-200 dark:border-indigo-900 text-indigo-700 dark:text-indigo-300">
                                🗂️ {{ $deckName }}: {{ $cardCount }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" 
                        @click="openReview = !openReview; flipped = false" 
                        class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs font-bold shadow-xs transition-all flex items-center gap-1.5 cursor-pointer">
                    <span>⚡ Review Kilat</span>
                    <svg class="w-3.5 h-3.5 transition-transform" :class="openReview ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <a href="{{ route('flashcards') }}" wire:navigate
                   class="px-3 py-1.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-bold transition-all flex items-center gap-1 cursor-pointer">
                    <span>Buka Deck Penuh ↗</span>
                </a>
            </div>
        </div>

        <!-- Quick Inline Flashcard Review Section -->
        <div x-show="openReview" x-collapse x-cloak class="mt-4 pt-4 border-t border-indigo-200/70 dark:border-indigo-800/40">
            @php $firstCard = $this->dueFlashcards->first(); @endphp
            @if($firstCard)
                <div class="max-w-xl mx-auto bg-white/95 dark:bg-gray-800/95 border border-indigo-100 dark:border-indigo-900/60 rounded-2xl p-4 sm:p-5 shadow-sm text-center">
                    <div class="flex items-center justify-between text-xs text-gray-400 dark:text-gray-500 mb-2">
                        <span class="font-semibold text-indigo-500">Deck: {{ $firstCard->deck }}</span>
                        <span>Interval: {{ $firstCard->interval_days ?? 1 }}h | Repetisi: {{ $firstCard->repetitions }}x</span>
                    </div>
                    
                    <!-- Front (Question) -->
                    <div class="py-4">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block mb-1">Pertanyaan / Konsep</span>
                        <div class="text-base sm:text-lg font-bold text-gray-900 dark:text-white leading-relaxed">
                            {{ $firstCard->front }}
                        </div>
                    </div>

                    <!-- Back (Answer) -->
                    <div x-show="flipped" x-collapse class="py-3 border-t border-dashed border-gray-200 dark:border-gray-700 mt-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-500 block mb-1">Jawaban / Solusi</span>
                        <div class="text-sm sm:text-base font-semibold text-gray-800 dark:text-gray-200 leading-relaxed bg-indigo-50/50 dark:bg-indigo-950/30 p-3 rounded-xl">
                            {{ $firstCard->back }}
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="mt-4 pt-2">
                        <button x-show="!flipped" type="button" @click="flipped = true"
                                class="w-full py-2 px-4 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 font-bold text-xs transition-colors cursor-pointer">
                            👀 Tampilkan Jawaban (Balik Kartu)
                        </button>

                        <!-- SM-2 Rating Buttons -->
                        <div x-show="flipped" class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <button type="button" 
                                    wire:click="recordQuickFlashcardReview({{ $firstCard->id }}, 'again')"
                                    @click="flipped = false"
                                    class="py-2 px-3 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 border border-red-500/30 text-xs font-bold transition-all cursor-pointer">
                                🔴 Lupa (1h)
                            </button>
                            <button type="button" 
                                    wire:click="recordQuickFlashcardReview({{ $firstCard->id }}, 'hard')"
                                    @click="flipped = false"
                                    class="py-2 px-3 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 text-xs font-bold transition-all cursor-pointer">
                                🟠 Sulit
                            </button>
                            <button type="button" 
                                    wire:click="recordQuickFlashcardReview({{ $firstCard->id }}, 'good')"
                                    @click="flipped = false"
                                    class="py-2 px-3 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 text-xs font-bold transition-all cursor-pointer">
                                🟢 Ingat
                            </button>
                            <button type="button" 
                                    wire:click="recordQuickFlashcardReview({{ $firstCard->id }}, 'easy')"
                                    @click="flipped = false"
                                    class="py-2 px-3 rounded-xl bg-blue-500/10 hover:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-500/30 text-xs font-bold transition-all cursor-pointer">
                                🔵 Mudah
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-4 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    🎉 Semua kartu due review hari ini sudah selesai!
                </div>
            @endif
        </div>
    </div>
@endif
