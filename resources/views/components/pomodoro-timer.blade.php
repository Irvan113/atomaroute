@php
    $userTz = auth()->check() ? (auth()->user()->timezone ?? config('app.timezone', 'Asia/Jakarta')) : config('app.timezone', 'Asia/Jakarta');
    $todayTasks = auth()->check() 
        ? auth()->user()->tasks()->with('subTasks')->whereDate('date', now($userTz)->toDateString())->orderBy('is_completed')->get() 
        : collect();
    $tasksPayload = $todayTasks->map(function($t) {
        return [
            'id' => (string)$t->id,
            'title' => $t->title,
            'is_completed' => (bool)$t->is_completed,
            'estimated_sessions' => $t->estimated_minutes ? max(1, (int)ceil($t->estimated_minutes / 25)) : 1,
            'completed_sessions' => (int)($t->pomodoro_sessions ?? 0),
            'estimated_minutes' => (int)($t->estimated_minutes ?? 25),
            'subtasks' => $t->subTasks->map(fn($st) => [
                'id' => (string)$st->id,
                'title' => $st->title,
                'is_completed' => (bool)$st->is_completed,
            ])->values()->toArray(),
        ];
    })->values();
    $userStreak = auth()->check() ? (int)(auth()->user()->current_streak ?? 0) : 0;
    $dueFlashcardsCount = auth()->check() 
        ? \App\Models\Flashcard::where('user_id', auth()->id())->dueForReview()->count() 
        : 0;
@endphp

<div x-data="pomodoroTimer(@js($tasksPayload), {{ $userStreak }}, {{ $dueFlashcardsCount }})" 
     @toggle-pomodoro.window="toggleShow()"
     @toggle-pip-window.window="togglePip()"
     @start-pomodoro-task.window="startFocusTask($event.detail)"
     @pomodoro-session-logged.window="fetchTodaySessions()"
     class="fixed bottom-20 md:bottom-20 right-2 sm:right-4 md:right-8 left-2 sm:left-auto z-[9999] max-w-[calc(100vw-1rem)] sm:max-w-md pointer-events-none flex justify-center sm:block"
     x-cloak>
    
    <!-- Timer Widget Card (Ultra-Clean, Responsive & Scrollable) -->
    <div x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-24 opacity-0 scale-95"
         x-transition:enter-end="translate-y-0 opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 opacity-100 scale-100"
         x-transition:leave-end="translate-y-24 opacity-0 scale-95"
         style="display: none;"
         class="pointer-events-auto bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl text-gray-900 dark:text-white rounded-3xl shadow-2xl p-4 sm:p-5 w-full sm:w-88 border border-gray-200/90 dark:border-gray-800/90 relative overflow-hidden transition-all max-h-[calc(100vh-6.5rem)] flex flex-col">
        
        <!-- Mobile drag indicator handle -->
        <div class="w-10 h-1 bg-gray-300 dark:bg-gray-700 rounded-full mx-auto mb-2 sm:hidden shrink-0"></div>

        <!-- Settings Panel Overlay -->
        <div x-show="showSettings" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="absolute inset-0 bg-white dark:bg-gray-900 z-30 p-4 sm:p-5 flex flex-col justify-between overflow-hidden shadow-2xl rounded-3xl" style="display: none;">
            <div class="flex justify-between items-center mb-2 shrink-0">
                <h3 class="text-sm font-black text-atoma-blue dark:text-atoma-cyan flex items-center gap-1.5">
                    <x-icon.gear class="w-4 h-4 text-atoma-blue dark:text-atoma-cyan inline-block shrink-0" />
                    <span>Pengaturan Pomodoro</span>
                </h3>
                <button @click="toggleSettings" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 cursor-pointer transition-colors">
                    ✕
                </button>
            </div>
            
            <div class="space-y-3 text-xs overflow-y-auto hide-scrollbar flex-1 pr-1 py-1">
                <div>
                    <label class="block text-gray-800 dark:text-gray-200 mb-1 font-bold">Waktu Fokus (menit)</label>
                    <input type="number" x-model.number="settings.work" min="1" max="120" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-1.5 text-gray-900 dark:text-white font-semibold focus:ring-2 focus:ring-atoma-blue/40 focus:border-atoma-blue">
                </div>
                <div>
                    <label class="block text-gray-800 dark:text-gray-200 mb-1 font-bold">Istirahat Pendek (menit)</label>
                    <input type="number" x-model.number="settings.shortBreak" min="1" max="60" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-1.5 text-gray-900 dark:text-white font-semibold focus:ring-2 focus:ring-atoma-blue/40 focus:border-atoma-blue">
                </div>
                <div>
                    <label class="block text-gray-800 dark:text-gray-200 mb-1 font-bold">Istirahat Panjang (menit)</label>
                    <input type="number" x-model.number="settings.longBreak" min="1" max="60" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-1.5 text-gray-900 dark:text-white font-semibold focus:ring-2 focus:ring-atoma-blue/40 focus:border-atoma-blue">
                </div>
                <div>
                    <label class="block text-gray-800 dark:text-gray-200 mb-1 font-bold">Interval Sesi Panjang</label>
                    <input type="number" x-model.number="settings.interval" min="1" max="12" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-1.5 text-gray-900 dark:text-white font-semibold focus:ring-2 focus:ring-atoma-blue/40 focus:border-atoma-blue">
                </div>
                
                <div class="pt-1 space-y-2">
                    <label class="flex items-center gap-2.5 cursor-pointer text-gray-900 dark:text-gray-100 font-semibold p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200/90 dark:border-gray-700/70 hover:bg-gray-100/90 dark:hover:bg-gray-750 transition-colors">
                        <input type="checkbox" x-model="settings.autoStartBreak" class="rounded text-atoma-blue focus:ring-atoma-blue w-4 h-4 cursor-pointer">
                        <span class="text-xs">Otomatis mulai istirahat</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer text-gray-900 dark:text-gray-100 font-semibold p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200/90 dark:border-gray-700/70 hover:bg-gray-100/90 dark:hover:bg-gray-750 transition-colors">
                        <input type="checkbox" x-model="settings.autoStartWork" class="rounded text-atoma-blue focus:ring-atoma-blue w-4 h-4 cursor-pointer">
                        <span class="text-xs">Otomatis lanjut sesi fokus</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer text-gray-900 dark:text-gray-100 font-semibold p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200/90 dark:border-gray-700/70 hover:bg-gray-100/90 dark:hover:bg-gray-750 transition-colors">
                        <input type="checkbox" x-model="settings.autoPauseMusic" class="rounded text-atoma-blue focus:ring-atoma-blue w-4 h-4 cursor-pointer">
                        <span class="text-xs">Otomatis jeda audio saat istirahat dimulai</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer text-gray-900 dark:text-gray-100 font-semibold p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200/90 dark:border-gray-700/70 hover:bg-gray-100/90 dark:hover:bg-gray-750 transition-colors">
                        <input type="checkbox" x-model="settings.alwaysShowFloat" class="rounded text-atoma-blue focus:ring-atoma-blue w-4 h-4 cursor-pointer">
                        <span class="text-xs">Tampilkan Floating Bubble saat idle (Always-on)</span>
                    </label>
                </div>

                <!-- Cache Storage Management Section -->
                <div class="pt-2.5 border-t border-gray-200 dark:border-gray-700/80">
                    <div class="text-[11px] font-bold text-gray-900 dark:text-gray-100 mb-1.5 flex items-center justify-between">
                        <span class="flex items-center gap-1.5">💾 Cache Audio Offline</span>
                        <span class="text-[10px] font-mono font-bold text-gray-600 dark:text-gray-400" x-text="cacheSizeText"></span>
                    </div>
                    <div class="flex items-center justify-between gap-2 mt-1">
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 font-medium">Penyimpanan lokal browser</span>
                        <button type="button" @click="clearAudioCache" class="text-[10px] bg-red-50 hover:bg-red-100 dark:bg-red-500/20 dark:hover:bg-red-500/30 text-red-600 dark:text-red-400 font-bold px-2.5 py-1 rounded-lg transition-colors cursor-pointer border border-red-200 dark:border-red-500/30">
                            Bersihkan Cache
                        </button>
                    </div>
                </div>

                <!-- Keyboard Shortcuts Cheat Sheet -->
                <div class="pt-2.5 border-t border-gray-200 dark:border-gray-700/80">
                    <div class="text-[11px] font-bold text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-1.5">
                        <span>⌨️</span> Pintasan Keyboard Global
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-[10px] text-gray-800 dark:text-gray-200 font-semibold">
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/80 p-2 rounded-xl border border-gray-200 dark:border-gray-700/70">
                            <span>Play / Jeda</span>
                            <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-[9px] font-mono font-bold text-gray-900 dark:text-gray-100 shadow-2xs">Spasi</kbd>
                        </div>
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/80 p-2 rounded-xl border border-gray-200 dark:border-gray-700/70">
                            <span>Buka / Tutup</span>
                            <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-[9px] font-mono font-bold text-gray-900 dark:text-gray-100 shadow-2xs">Alt+P</kbd>
                        </div>
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/80 p-2 rounded-xl border border-gray-200 dark:border-gray-700/70">
                            <span>Reset Timer</span>
                            <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-[9px] font-mono font-bold text-gray-900 dark:text-gray-100 shadow-2xs">Alt+R</kbd>
                        </div>
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/80 p-2 rounded-xl border border-gray-200 dark:border-gray-700/70">
                            <span>Mode Flowtime</span>
                            <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-[9px] font-mono font-bold text-gray-900 dark:text-gray-100 shadow-2xs">Alt+F</kbd>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 shrink-0">
                <button @click="saveSettings" class="w-full bg-atoma-blue hover:bg-[#3455dc] text-white rounded-xl py-2 font-bold text-xs shadow-lg shadow-atoma-blue/25 cursor-pointer transition-all">Simpan & Terapkan</button>
            </div>
        </div>

        <!-- Flowtime Completion & Break Recommendation Modal -->
        <div x-show="showFlowtimeModal" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="absolute inset-0 bg-white dark:bg-gray-900 z-30 p-4 sm:p-5 flex flex-col justify-between overflow-hidden shadow-2xl rounded-3xl" style="display: none;">
            <div class="flex justify-between items-center mb-2 shrink-0">
                <h3 class="text-sm font-black text-cyan-600 dark:text-cyan-400 flex items-center gap-1.5">
                    <span>🌊</span> Sesi Flowtime Selesai!
                </h3>
                <button @click="showFlowtimeModal = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 cursor-pointer transition-colors">
                    ✕
                </button>
            </div>

            <div class="flex-1 flex flex-col items-center justify-center text-center space-y-3 px-2">
                <div class="w-14 h-14 rounded-full bg-cyan-100 dark:bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 flex items-center justify-center text-2xl font-black shadow-inner">
                    🎉
                </div>
                <div>
                    <h4 class="text-base font-black text-gray-900 dark:text-white">Fokus Luar Biasa!</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Kamu baru saja menyelesaikan sesi deep work selama <span class="font-bold text-cyan-600 dark:text-cyan-400" x-text="`${flowtimeResultMinutes} menit`"></span>.
                    </p>
                </div>

                <div class="w-full bg-cyan-50 dark:bg-cyan-500/10 border border-cyan-200 dark:border-cyan-500/30 p-3 rounded-2xl text-left space-y-1">
                    <div class="text-[11px] font-bold text-cyan-800 dark:text-cyan-300 flex items-center gap-1">
                        <span>☕ Rekomendasi Waktu Istirahat:</span>
                    </div>
                    <div class="text-xl font-black text-cyan-600 dark:text-cyan-400 font-mono" x-text="`${flowtimeRecommendedBreak} Menit`"></div>
                    <div class="text-[9px] text-gray-500 dark:text-gray-400 leading-tight">
                        Waktu istirahat proporsional dihitung agar stamina kognitif dan fokusmu pulih maksimal.
                    </div>
                </div>
            </div>

            <div class="space-y-2 pt-2 shrink-0">
                <button type="button" 
                        @click="startFlowtimeBreak" 
                        class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs py-2.5 rounded-xl transition-all cursor-pointer shadow-md shadow-emerald-600/25 flex items-center justify-center gap-1.5">
                    <span>☕ Mulai Istirahat Sekarang</span>
                    <span x-text="`(${flowtimeRecommendedBreak}m)`"></span>
                </button>
                <button type="button" 
                        @click="showFlowtimeModal = false; setMode('work')" 
                        class="w-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-700 dark:text-gray-300 font-bold text-xs py-2 rounded-xl transition-all cursor-pointer">
                    Lanjut Sesi / Nanti Saja
                </button>
            </div>
        </div>

        <!-- Daily Session Log Overlay Panel -->
        <div x-show="showDailyLogModal" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="absolute inset-0 bg-white dark:bg-gray-900 z-30 p-4 sm:p-5 flex flex-col justify-between overflow-hidden shadow-2xl rounded-3xl" style="display: none;">
            <div class="flex justify-between items-center mb-2 shrink-0">
                <h3 class="text-sm font-black text-atoma-blue dark:text-atoma-cyan flex items-center gap-1.5">
                    <span>📋</span> Riwayat Sesi Hari Ini
                </h3>
                <button @click="showDailyLogModal = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 cursor-pointer transition-colors">
                    ✕
                </button>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-4 gap-1.5 mb-2.5 shrink-0">
                <div class="bg-gray-50 dark:bg-gray-800 p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-center">
                    <div class="text-[9px] text-gray-500 dark:text-gray-400 font-bold">Waktu</div>
                    <div class="text-xs font-black text-gray-900 dark:text-white mt-0.5" x-text="`${dailyLogSummary.total_minutes || todayFocusMinutes}m`"></div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-center">
                    <div class="text-[9px] text-gray-500 dark:text-gray-400 font-bold">Sesi</div>
                    <div class="text-xs font-black text-rose-500 mt-0.5" x-text="dailyLogSummary.total_sessions || todaySessionsCount"></div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-center">
                    <div class="text-[9px] text-gray-500 dark:text-gray-400 font-bold">XP</div>
                    <div class="text-xs font-black text-emerald-600 dark:text-emerald-400 mt-0.5" x-text="`+${dailyLogSummary.total_xp || 0}`"></div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-center">
                    <div class="text-[9px] text-gray-500 dark:text-gray-400 font-bold">Koin</div>
                    <div class="text-xs font-black text-amber-500 mt-0.5" x-text="`+${dailyLogSummary.total_coins || 0}🪙`"></div>
                </div>
            </div>

            <!-- Sessions List -->
            <div class="flex-1 overflow-y-auto hide-scrollbar space-y-1.5 pr-0.5 py-1">
                <template x-if="isLoadingDailyLog">
                    <div class="text-center py-8 text-gray-400 text-xs font-semibold flex items-center justify-center gap-1.5">
                        <span class="animate-spin">⏳</span> Memuat riwayat sesi...
                    </div>
                </template>
                <template x-if="!isLoadingDailyLog && dailySessions.length === 0">
                    <div class="text-center py-8 text-gray-400 text-xs bg-gray-50 dark:bg-gray-800/40 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700/60 p-4">
                        <div class="text-2xl mb-1">🍅</div>
                        Belum ada sesi fokus tercatat hari ini.<br>
                        Mulai sesi fokus pertamamu sekarang!
                    </div>
                </template>
                <template x-for="item in dailySessions" :key="item.id">
                    <div class="bg-gray-50 dark:bg-gray-800/90 p-2 rounded-xl border border-gray-200 dark:border-gray-700/60 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-base" x-text="item.mode === 'flowtime' ? '🌊' : '🍅'"></span>
                            <div class="min-w-0">
                                <div class="text-[11px] font-bold text-gray-900 dark:text-white truncate" x-text="item.task_title ? `📋 ${item.task_title}` : (item.mode === 'flowtime' ? 'Sesi Flowtime' : 'Sesi Fokus')"></div>
                                <div class="text-[9px] text-gray-500 dark:text-gray-400" x-text="`${item.time} • ${item.minutes} Menit`"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="text-[10px] font-black bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-1.5 py-0.5 rounded-md" x-text="`+${item.xp_earned} XP`"></span>
                            <template x-if="item.coins_earned > 0">
                                <span class="text-[10px] font-black bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded-md" x-text="`+${item.coins_earned}🪙`"></span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="pt-2 shrink-0">
                <button type="button" @click="showDailyLogModal = false" class="w-full bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-800 dark:text-gray-200 font-bold text-xs py-2 rounded-xl transition-all cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>

        <!-- Save to Playlist Overlay Panel -->
        <div x-show="showPlaylistModal" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="absolute inset-0 bg-white dark:bg-gray-900 z-30 p-4 sm:p-5 flex flex-col justify-between overflow-hidden shadow-2xl rounded-3xl" style="display: none;">
            <div class="flex justify-between items-center mb-2 shrink-0">
                <h3 class="text-sm font-black text-amber-500 dark:text-amber-400 flex items-center gap-1.5">
                    <span>📁</span> Simpan ke Playlist
                </h3>
                <button @click="showPlaylistModal = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 cursor-pointer transition-colors">
                    ✕
                </button>
            </div>

            <!-- Scrollable Body inside Playlist Modal -->
            <div class="flex-1 overflow-y-auto hide-scrollbar space-y-2.5 pr-0.5 py-1">
                <!-- Target Track Info -->
                <div class="bg-gray-50 dark:bg-gray-800/90 p-2 rounded-2xl border border-gray-200 dark:border-gray-700/60 flex items-center gap-2">
                    <img :src="targetTrackToSave.thumbnail || ytThumbnail" class="w-8 h-8 rounded-lg object-cover shrink-0 border border-gray-200 dark:border-gray-700">
                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] font-black text-gray-900 dark:text-white truncate" x-text="targetTrackToSave.title || ytTitle"></div>
                        <div class="text-[9px] text-gray-500 dark:text-gray-400 truncate" x-text="targetTrackToSave.author || ytAuthor"></div>
                    </div>
                </div>

                <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 flex items-center justify-between">
                    <span>Pilih Playlist / Kategori:</span>
                    <span class="font-mono text-[9px] px-2 py-0.5 rounded-md font-bold" 
                          :class="isPro ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'"
                          x-text="isPro ? '👑 PRO (Unlimited)' : `${playlistFolders.length}/2 Folder (Free)`"></span>
                </div>

                <!-- Folder Selection List with Rename/Edit Support -->
                <div class="space-y-1.5 max-h-44 overflow-y-auto hide-scrollbar pr-0.5">
                    <template x-for="folder in playlistFolders" :key="folder.id">
                        <div class="rounded-xl border transition-all"
                             :class="editingFolderId === folder.id ? 'bg-gray-100 dark:bg-gray-800 border-amber-500/60 p-2 space-y-2' : (isTrackInFolder(folder.id, targetTrackToSave.video_id) ? 'bg-amber-50 dark:bg-amber-500/15 border-amber-300 dark:border-amber-500/50 text-gray-900 dark:text-white' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700/60 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750')">
                            
                            <!-- Regular View Mode -->
                            <div x-show="editingFolderId !== folder.id" class="w-full flex items-center justify-between p-2">
                                <button type="button" 
                                        @click="toggleTrackInFolder(folder.id, targetTrackToSave)" 
                                        class="flex items-center gap-2 min-w-0 flex-1 text-left cursor-pointer">
                                    <span class="text-sm" x-text="folder.icon || '📁'"></span>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold truncate text-gray-900 dark:text-white" x-text="folder.name"></div>
                                        <div class="text-[9px] text-gray-500 dark:text-gray-400" x-text="`${folder.tracks.length} lagu`"></div>
                                    </div>
                                </button>
                                
                                <div class="flex items-center gap-1 shrink-0 font-bold text-xs">
                                    <button type="button" 
                                            @click.stop="startEditFolder(folder)" 
                                            class="p-1 text-gray-400 hover:text-amber-600 dark:hover:text-amber-300 transition-colors cursor-pointer rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700" 
                                            title="Ganti Nama / Icon Folder">
                                        ✏️
                                    </button>
                                    <button type="button" 
                                            @click="toggleTrackInFolder(folder.id, targetTrackToSave)" 
                                            class="cursor-pointer">
                                        <span x-show="isTrackInFolder(folder.id, targetTrackToSave.video_id)" class="text-amber-600 dark:text-amber-400 bg-amber-100 dark:bg-amber-400/20 px-2 py-0.5 rounded-lg font-bold">✓ Tersimpan</span>
                                        <span x-show="!isTrackInFolder(folder.id, targetTrackToSave.video_id)" class="text-gray-500 hover:text-gray-900 dark:hover:text-white px-2 py-0.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700">+ Tambah</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Inline Edit / Rename Mode -->
                            <div x-show="editingFolderId === folder.id" class="space-y-2">
                                <div class="flex items-center justify-between text-[10px] text-amber-500 dark:text-amber-400 font-bold">
                                    <span>✏️ Edit Nama & Icon Folder:</span>
                                    <button type="button" @click="cancelEditFolder" class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-xs">✕</button>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <select x-model="editFolderIcon" class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs py-1 px-1 text-gray-900 dark:text-white">
                                        <option value="📁">📁</option>
                                        <option value="🧠">🧠</option>
                                        <option value="☕">☕</option>
                                        <option value="🎧">🎧</option>
                                        <option value="🎹">🎹</option>
                                        <option value="⚡">⚡</option>
                                        <option value="🌌">🌌</option>
                                        <option value="🌧️">🌧️</option>
                                        <option value="🔥">🔥</option>
                                        <option value="🚀">🚀</option>
                                    </select>
                                    <input type="text" 
                                           x-model="editFolderName" 
                                           @keyup.enter="saveFolderRename" 
                                           placeholder="Nama playlist..." 
                                           class="flex-1 bg-white dark:bg-gray-900 border border-amber-500/50 rounded-lg px-2 py-1 text-xs text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-amber-500">
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <template x-if="playlistFolders.length > 1">
                                        <button type="button" 
                                                @click="deleteFolder(folder.id); cancelEditFolder()" 
                                                class="text-[10px] text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 font-bold flex items-center gap-1 cursor-pointer">
                                            <span>🗑️ Hapus</span>
                                        </button>
                                    </template>
                                    <div class="flex items-center gap-1 ml-auto">
                                        <button type="button" 
                                                @click="cancelEditFolder" 
                                                class="text-[10px] bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 px-2 py-1 rounded-lg cursor-pointer">
                                            Batal
                                        </button>
                                        <button type="button" 
                                                @click="saveFolderRename" 
                                                class="text-[10px] bg-amber-600 hover:bg-amber-500 text-white font-bold px-2.5 py-1 rounded-lg cursor-pointer">
                                            ✓ Simpan
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </template>
                </div>

                <!-- Create New Folder Section in Modal -->
                <div class="pt-1.5 border-t border-gray-200 dark:border-gray-800">
                    <template x-if="!isPro && playlistFolders.length >= maxFolders">
                        <div class="p-2 rounded-xl bg-gradient-to-r from-amber-500/10 via-yellow-500/10 to-amber-500/10 border border-amber-300/70 dark:border-amber-500/30 text-center space-y-1">
                            <div class="text-[11px] font-black text-amber-700 dark:text-amber-300 flex items-center justify-center gap-1">
                                <span>👑</span> Batas Playlist Akun Free (2/2)
                            </div>
                            <p class="text-[10px] text-gray-600 dark:text-gray-400">Upgrade ke <strong>ATOMA PRO</strong> untuk membuat unlimited playlist & folder YouTube!</p>
                            <button type="button" @click="$dispatch('open-pro-modal')" class="text-[10px] font-black bg-amber-500 hover:bg-amber-600 text-white px-3 py-1 rounded-lg transition shadow-sm cursor-pointer inline-flex items-center gap-1">
                                <span>⭐ Buka Unlimited Playlist Pro</span>
                            </button>
                        </div>
                    </template>
                    <template x-if="isPro || playlistFolders.length < maxFolders">
                        <div class="flex items-center gap-1.5">
                            <input type="text" 
                                   x-model="newFolderName" 
                                   @keyup.enter="createFolderFromModal" 
                                   placeholder="Nama playlist baru..." 
                                   class="flex-1 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-2.5 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-amber-500">
                            <button type="button" 
                                    @click="createFolderFromModal" 
                                    class="bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs px-3 py-1.5 rounded-xl transition-colors shrink-0 cursor-pointer">
                                + Buat
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="pt-2 shrink-0">
                <button type="button" @click="showPlaylistModal = false" class="w-full bg-atoma-blue hover:bg-[#3455dc] text-white font-black text-xs py-2 rounded-xl transition-all cursor-pointer shadow-md shadow-atoma-blue/25">
                    Selesai
                </button>
            </div>
        </div>

        <!-- Header Controls & Status (Refined, Balanced, Ultra-Clean) -->
        <div class="flex justify-between items-center mb-3 shrink-0 gap-1.5">
            <div class="flex items-center gap-1.5 min-w-0 flex-1">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" 
                      :class="mode === 'work' ? (isRunning ? 'bg-rose-500 animate-pulse' : 'bg-rose-500') : (mode === 'flowtime' ? (isRunning ? 'bg-cyan-500 animate-pulse' : 'bg-cyan-500') : 'bg-emerald-500')"></span>
                <span class="text-xs font-black tracking-wide text-gray-900 dark:text-white shrink-0" x-text="modeName"></span>
                
                <!-- Session Dots (Pomodoro interval) -->
                <div x-show="mode !== 'flowtime'" class="flex items-center gap-1 shrink-0 ml-0.5" title="Sesi selesai">
                    <template x-for="i in settings.interval" :key="i">
                        <span class="w-1.5 h-1.5 rounded-full transition-colors" 
                              :class="i <= (sessionCount % settings.interval || (sessionCount > 0 && sessionCount % settings.interval === 0 ? settings.interval : 0)) ? 'bg-atoma-blue dark:bg-atoma-cyan' : 'bg-gray-200 dark:bg-gray-700'"></span>
                    </template>
                </div>
            </div>
            
            <div class="flex items-center space-x-1 shrink-0">
                <!-- Daily Log Compact Button -->
                <button @click="openDailyLog" class="p-1.5 bg-atoma-blue/10 hover:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-bold rounded-xl text-xs transition-all flex items-center gap-1 border border-atoma-blue/20 dark:border-atoma-blue/30 cursor-pointer shadow-xs" title="Riwayat Sesi Hari Ini">
                    <span>📋</span>
                    <span x-show="todaySessionsCount > 0" class="text-[9px] bg-atoma-blue text-white dark:bg-atoma-cyan dark:text-gray-900 px-1 py-0.2 rounded-full font-mono font-bold" x-text="todaySessionsCount"></span>
                </button>

                <!-- Float Always-On-Top Compact Button -->
                <button @click="togglePip" class="p-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 font-bold rounded-xl text-xs transition-all flex items-center justify-center border border-amber-500/20 dark:border-amber-500/30 cursor-pointer shadow-xs" title="Always-On-Top Interactive Window (Float OS)">
                    <span>📌</span>
                </button>

                <button @click="toggleSettings" class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer" title="Pengaturan">
                    <x-icon.gear class="w-4 h-4" />
                </button>
                <button @click="closeWidget()" class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer" title="Tutup">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>

        <!-- Productivity Hub Tabs (Pomodoro, Tasks, Quick Notes, Flashcards) -->
        <div class="grid grid-cols-4 gap-1 p-1 bg-gray-100/90 dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/60 shrink-0 mb-2.5">
            <button type="button" 
                    @click="widgetTab = 'pomodoro'"
                    class="py-1.5 px-1 rounded-xl text-[10px] sm:text-[11px] font-black transition-all flex items-center justify-center gap-1 cursor-pointer"
                    :class="widgetTab === 'pomodoro' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'">
                <span>🍅</span>
                <span>Fokus</span>
            </button>
            <button type="button" 
                    @click="widgetTab = 'tasks'"
                    class="py-1.5 px-1 rounded-xl text-[10px] sm:text-[11px] font-black transition-all flex items-center justify-center gap-1 cursor-pointer relative"
                    :class="widgetTab === 'tasks' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'">
                <span>✅</span>
                <span>Tasks</span>
                <span x-show="activeTasks.filter(t => !t.is_completed).length > 0" 
                      class="w-1.5 h-1.5 rounded-full bg-indigo-500" 
                      :title="`${activeTasks.filter(t => !t.is_completed).length} pending tasks`"></span>
            </button>
            <button type="button" 
                    @click="widgetTab = 'notes'"
                    class="py-1.5 px-1 rounded-xl text-[10px] sm:text-[11px] font-black transition-all flex items-center justify-center gap-1 cursor-pointer"
                    :class="widgetTab === 'notes' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'">
                <span>📝</span>
                <span>Notes</span>
            </button>
            <button type="button" 
                    @click="widgetTab = 'flashcards'"
                    class="py-1.5 px-1 rounded-xl text-[10px] sm:text-[11px] font-black transition-all flex items-center justify-center gap-1 cursor-pointer relative"
                    :class="widgetTab === 'flashcards' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'">
                <span>🗂️</span>
                <span>Cards</span>
                @if($dueFlashcardsCount > 0)
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500" title="{{ $dueFlashcardsCount }} kartu perlu di-review"></span>
                @endif
            </button>
        </div>

        <!-- TAB 1: POMODORO TIMER -->
        <div x-show="widgetTab === 'pomodoro'" class="contents">
            <!-- Mode Switcher Tab Bar (Focus | Flow | Break | Long - Pinned at Top) -->
            <div class="bg-gray-100/90 dark:bg-gray-800/90 p-1 rounded-2xl flex items-center shadow-inner border border-gray-200/80 dark:border-gray-700/60 shrink-0 gap-1 mb-2">
            <button type="button" 
                    @click="setMode('work')" 
                    class="flex-1 py-1.5 px-1 rounded-xl text-[11px] font-black transition-all cursor-pointer text-center flex items-center justify-center gap-1"
                    :class="mode === 'work' ? 'bg-rose-500 text-white shadow-md shadow-rose-500/25' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 font-semibold'">
                <span>🍅</span>
                <span>Focus</span>
            </button>
            <button type="button" 
                    @click="setMode('flowtime')" 
                    class="flex-1 py-1.5 px-1 rounded-xl text-[11px] font-black transition-all cursor-pointer text-center flex items-center justify-center gap-1"
                    :class="mode === 'flowtime' ? 'bg-cyan-500 text-white shadow-md shadow-cyan-500/25' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 font-semibold'"
                    title="Mode Flowtime (Deep Work Bebas)">
                <span>🌊</span>
                <span>Flow</span>
            </button>
            <button type="button" 
                    @click="setMode('shortBreak')" 
                    class="flex-1 py-1.5 px-1 rounded-xl text-[11px] font-black transition-all cursor-pointer text-center flex items-center justify-center gap-1"
                    :class="mode === 'shortBreak' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/25' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 font-semibold'">
                <span>☕</span>
                <span>Break</span>
            </button>
            <button type="button" 
                    @click="setMode('longBreak')" 
                    class="flex-1 py-1.5 px-1 rounded-xl text-[11px] font-black transition-all cursor-pointer text-center flex items-center justify-center gap-1"
                    :class="mode === 'longBreak' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/25' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 font-semibold'">
                <span>🛋️</span>
                <span>Long</span>
            </button>
        </div>

        <!-- Scrollable Main Card Body -->
        <div @scroll="updateVideoSlotPosition()" class="overflow-y-auto hide-scrollbar flex-1 pr-0.5 space-y-3">
            
            <!-- Circular Animated Progress Ring with Timer in Center -->
            <div class="flex flex-col items-center justify-center my-2 relative">
                <div class="relative w-48 h-48 flex items-center justify-center">
                    <!-- SVG Circle Ring -->
                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 160 160">
                        <!-- Background Ring -->
                        <circle cx="80" cy="80" r="70" stroke="currentColor" stroke-width="8" class="text-gray-100 dark:text-gray-800" fill="transparent"></circle>
                        <!-- Progress Animated Arc -->
                        <circle cx="80" cy="80" r="70" 
                                stroke="currentColor" 
                                stroke-width="8" 
                                stroke-linecap="round"
                                :class="mode === 'work' ? 'text-rose-500 drop-shadow-[0_0_8px_rgba(244,63,94,0.35)]' : (mode === 'flowtime' ? 'text-cyan-500 drop-shadow-[0_0_8px_rgba(6,182,212,0.35)]' : (mode === 'shortBreak' ? 'text-emerald-500 drop-shadow-[0_0_8px_rgba(16,185,129,0.35)]' : 'text-purple-500 drop-shadow-[0_0_8px_rgba(168,85,247,0.35)]'))"
                                class="transition-all duration-1000 ease-linear"
                                fill="transparent"
                                stroke-dasharray="440"
                                :stroke-dashoffset="440 - (440 * progressPercentage / 100)"></circle>
                    </svg>

                    <!-- Center Content: Countdown & Subtitle -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center select-none px-2">
                        <div class="text-[42px] font-black font-mono tracking-tight text-gray-900 dark:text-white drop-shadow-xs" x-text="formattedTime"></div>
                        <div class="text-[10px] text-gray-500 dark:text-gray-400 font-bold mt-0.5 max-w-[180px] truncate" 
                             x-text="mode === 'flowtime' ? '🌊 Ritme Flowtime Bebas' : (isTaskConnected ? currentSelectedTask.title : 'Tetap fokus & tuntaskan!')"></div>
                        
                        <template x-if="mode === 'flowtime'">
                            <div class="text-[9px] text-cyan-600 dark:text-cyan-400 font-black mt-1 tracking-wide flex items-center gap-1">
                                <span>☕ Saran jeda:</span>
                                <span class="bg-cyan-50 dark:bg-cyan-500/20 px-1.5 py-0.5 rounded-md" x-text="`+${recommendedBreakMinutes}m`"></span>
                            </div>
                        </template>
                        <template x-if="mode !== 'flowtime'">
                            <div class="text-[9px] text-atoma-blue dark:text-atoma-cyan font-black mt-1 tracking-wide" x-text="`${todayFocusMinutes}m fokus hari ini`"></div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Main Timer Controls -->
            <div class="flex justify-center items-center gap-2.5 my-2">
                <!-- Reset / Previous -->
                <button type="button" 
                        @click="resetTimer" 
                        class="p-3 rounded-2xl bg-gray-100 hover:bg-gray-200/90 dark:bg-gray-800/80 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-all active:scale-95 border border-gray-200 dark:border-gray-700/50 cursor-pointer shadow-xs" 
                        title="Reset Waktu (Alt+R)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
                
                <!-- Hero Play/Pause Button -->
                <button type="button" 
                        @click="toggleTimer" 
                        class="px-7 py-3.5 rounded-2xl transition-all font-black text-sm text-white shadow-xl active:scale-95 flex items-center justify-center gap-2 cursor-pointer flex-1 max-w-[160px]"
                        :class="mode === 'work' ? 'bg-rose-500 hover:bg-rose-600 shadow-rose-500/35' : (mode === 'flowtime' ? 'bg-cyan-500 hover:bg-cyan-600 shadow-cyan-500/35' : (mode === 'shortBreak' ? 'bg-emerald-500 hover:bg-emerald-600 shadow-emerald-500/35' : 'bg-purple-600 hover:bg-purple-700 shadow-purple-600/35'))">
                    <span x-show="!isRunning" class="flex items-center gap-1.5">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>
                        Mulai
                    </span>
                    <span x-show="isRunning" class="flex items-center gap-1.5" style="display: none;">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        Jeda
                    </span>
                </button>

                <!-- Selesaikan Sesi (Finish / Complete Early & Claim Progressive XP) -->
                <button type="button" 
                        @click="finishSessionEarly" 
                        class="p-3 rounded-2xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/20 dark:hover:bg-emerald-500/30 text-emerald-700 dark:text-emerald-300 transition-all active:scale-95 border border-emerald-200 dark:border-emerald-500/40 cursor-pointer shadow-xs font-bold text-xs flex items-center gap-1.5" 
                        :title="mode === 'work' ? 'Selesaikan Sesi Fokus Sekarang (Klaim XP Progresif)' : (mode === 'flowtime' ? 'Selesaikan Sesi Flowtime' : 'Selesaikan Istirahat')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-[11px] font-black">Selesai</span>
                </button>
            </div>

            <!-- Micro-Break Wellbeing Card (Active during breaks) -->
            <div x-show="mode === 'shortBreak' || mode === 'longBreak'" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="bg-emerald-50/70 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 rounded-2xl p-3 space-y-1.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-800 dark:text-emerald-300">
                        <span>🧘</span>
                        <span>Saran Istirahat Sehat</span>
                    </div>
                    <button type="button" @click="shuffleBreakTip" class="text-[10px] text-emerald-700 dark:text-emerald-400 hover:underline flex items-center gap-1 cursor-pointer">
                        <span>🎲 Ganti Tips</span>
                    </button>
                </div>
                <div class="text-xs text-gray-800 dark:text-gray-200 font-semibold flex items-start gap-2">
                    <span class="text-base" x-text="currentBreakTip.icon"></span>
                    <div>
                        <div class="font-bold text-emerald-900 dark:text-emerald-200 text-[11px]" x-text="currentBreakTip.title"></div>
                        <div class="text-[10px] text-gray-600 dark:text-gray-400 mt-0.5 leading-snug" x-text="currentBreakTip.description"></div>
                    </div>
                </div>
            </div>

            <!-- Task Focus Integration Selector with Accurate Connection State -->
            <div class="mt-3 pt-2.5 border-t border-gray-100 dark:border-gray-800">
                <div class="flex items-center justify-between gap-1 text-[10px] text-gray-500 dark:text-gray-400 font-bold mb-1">
                    <span class="flex items-center gap-1">
                        <span>🎯</span> Fokus Pada Tugas:
                    </span>
                    <span x-show="isTaskConnected" class="text-atoma-blue dark:text-atoma-cyan font-bold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-atoma-blue dark:bg-atoma-cyan animate-ping"></span>
                        Tersambung
                    </span>
                </div>
                
                <select x-model="selectedTaskId" 
                        @change="onTaskSelect"
                        class="w-full bg-gray-50 dark:bg-gray-800/90 text-gray-800 dark:text-gray-200 text-xs rounded-xl px-2.5 py-2 border border-gray-200 dark:border-gray-700/80 focus:ring-2 focus:ring-atoma-blue/30 focus:border-atoma-blue focus:outline-none truncate font-semibold">
                    <option value="">-- Fokus Bebas / Tanpa Tugas Khusus --</option>
                    <template x-for="task in activeTasks" :key="task.id">
                        <option :value="task.id" x-text="`📋 ${task.title}`" :selected="task.id === selectedTaskId"></option>
                    </template>
                </select>

                <!-- Task Estimation & Inline Mark Done -->
                <div x-show="isTaskConnected && currentSelectedTask" class="mt-2 p-2 bg-atoma-blue/5 dark:bg-atoma-blue/15 border border-atoma-blue/20 dark:border-atoma-blue/30 rounded-xl flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] font-bold text-atoma-blue dark:text-atoma-cyan flex items-center gap-1">
                            <span>🍅</span>
                            <span x-text="`Target: ${currentSelectedTask?.completed_sessions || 0} / ${currentSelectedTask?.estimated_sessions || 1} Sesi`"></span>
                            <span class="text-gray-400 font-normal" x-text="`(~${currentSelectedTask?.estimated_minutes || 25}m)`"></span>
                        </div>
                    </div>
                    <button type="button" 
                            @click="markSelectedTaskDone" 
                            class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-bold transition-all shrink-0 cursor-pointer shadow-xs flex items-center gap-1">
                        <span>✓ Selesai</span>
                    </button>
                </div>
            </div>

            <!-- Collapsible Music & Audio Deck Toggle Bar -->
            <div class="mt-3 pt-2.5 border-t border-gray-100 dark:border-gray-800">
                <button type="button" 
                        @click="showAudioDeck = !showAudioDeck"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-2xl bg-gray-50 hover:bg-gray-100 dark:bg-gray-800/80 dark:hover:bg-gray-800 text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white transition-all text-xs font-bold border border-gray-200 dark:border-gray-700/50 cursor-pointer shadow-xs">
                    <span class="flex items-center gap-1.5">
                        <span>🎵</span> 
                        <span>Audio & Musik Fokus</span>
                        <span x-show="isYtPlaying || isAmbientPlaying" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    </span>
                    <span class="text-[10px] text-gray-400 flex items-center gap-1">
                        <span x-text="showAudioDeck ? 'Tutup' : 'Buka'"></span>
                        <svg class="w-3.5 h-3.5 transform transition-transform" :class="showAudioDeck ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </span>
                </button>

                <!-- Collapsible Music Panel Body -->
                <div x-show="showAudioDeck" 
                     x-collapse
                     class="mt-2.5 space-y-3 pt-1">
                    
                    <!-- Audio Sub-Tabs: YT Music | Ambient Offline -->
                    <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800/90 p-1 rounded-xl text-[10px] sm:text-[11px] border border-gray-200 dark:border-gray-700/60">
                        <button type="button" 
                                @click="musicTab = 'ytmusic'" 
                                :class="musicTab === 'ytmusic' ? 'bg-red-600 text-white font-black shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-semibold'" 
                                class="flex-1 py-1.5 rounded-lg transition-colors cursor-pointer text-center truncate">
                            🔴 YouTube
                        </button>
                        <button type="button" 
                                @click="musicTab = 'ambient'" 
                                :class="musicTab === 'ambient' ? 'bg-cyan-600 text-white font-black shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-semibold'" 
                                class="flex-1 py-1.5 rounded-lg transition-colors cursor-pointer text-center truncate">
                            🌧️ Ambient
                        </button>
                    </div>

                    <!-- 1. Dedicated Offline Ambient Player (100% Offline, Zero Server Bandwidth) -->
                    <div x-show="musicTab === 'ambient'" class="space-y-2">
                        <div class="bg-gray-50 dark:bg-gray-800/90 p-2.5 rounded-2xl border border-gray-200 dark:border-gray-700/60 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl" x-text="currentAmbientPresetObj.icon"></span>
                                    <div>
                                        <div class="text-xs font-black text-gray-900 dark:text-white" x-text="currentAmbientPresetObj.name"></div>
                                        <div class="text-[9px] text-cyan-600 dark:text-cyan-400 font-bold">100% Offline • 0 KB Server</div>
                                    </div>
                                </div>
                                <button type="button" 
                                        @click="toggleAmbientPlayback" 
                                        class="px-3.5 py-1.5 rounded-xl text-white font-bold text-xs transition-all flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
                                        :class="isAmbientPlaying ? 'bg-cyan-600 hover:bg-cyan-500 shadow-cyan-600/30' : 'bg-gray-700 hover:bg-gray-600'">
                                    <!-- Play Icon -->
                                    <svg x-show="!isAmbientPlaying" class="w-3.5 h-3.5 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                        <path d="M6.5 4.25a1.25 1.25 0 0 1 1.9-.99l11.5 7.75a1.25 1.25 0 0 1 0 1.98l-11.5 7.75a1.25 1.25 0 0 1-1.9-.99V4.25z"/>
                                    </svg>
                                    <!-- Pause Icon -->
                                    <svg x-show="isAmbientPlaying" class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M6.75 5.25a1.25 1.25 0 0 1 1.25 1.25v11a1.25 1.25 0 1 1-2.5 0v-11a1.25 1.25 0 0 1 1.25-1.25zm10.5 0a1.25 1.25 0 0 1 1.25 1.25v11a1.25 1.25 0 1 1-2.5 0v-11a1.25 1.25 0 0 1 1.25-1.25z" clip-rule="evenodd"/>
                                    </svg>
                                    <span x-text="isAmbientPlaying ? 'Jeda' : 'Putar'"></span>
                                </button>
                            </div>

                            <!-- Ambient Preset Pills -->
                            <div class="grid grid-cols-2 gap-1 pt-1">
                                <template x-for="p in ambientPresets" :key="p.id">
                                    <button type="button" 
                                            @click="selectAmbientPreset(p.id)"
                                            class="p-1.5 rounded-xl text-[10px] font-bold border transition-all text-left flex items-center gap-1.5 cursor-pointer truncate"
                                            :class="currentAmbientPreset === p.id ? 'bg-cyan-50 dark:bg-cyan-500/20 border-cyan-300 dark:border-cyan-500/50 text-cyan-800 dark:text-cyan-300' : 'bg-white dark:bg-gray-750 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'">
                                        <span x-text="p.icon"></span>
                                        <span class="truncate" x-text="p.name"></span>
                                    </button>
                                </template>
                            </div>

                            <!-- Volume Slider for Ambient -->
                            <div class="flex items-center justify-between gap-2.5 pt-1 border-t border-gray-200/80 dark:border-gray-700/50 text-xs text-gray-500 dark:text-gray-400">
                                <svg class="w-3.5 h-3.5 text-cyan-500 dark:text-cyan-400 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H3.75A1.75 1.75 0 002 9.25v5.5c0 .966.784 1.75 1.75 1.75h2.69l4.5 4.5c.944.945 2.56.276 2.56-1.06V4.06zM15.66 8.34a.75.75 0 011.06 0 4.25 4.25 0 010 5.96.75.75 0 11-1.06-1.06 2.75 2.75 0 000-3.84.75.75 0 010-1.06z"/>
                                </svg>
                                <div class="flex items-center gap-1.5 flex-1">
                                    <input type="range" 
                                           x-model.number="ambientVolume" 
                                           @input="updateAmbientVolume" 
                                           min="0" 
                                           max="100" 
                                           step="1" 
                                           class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-cyan-500">
                                    <span class="text-[10px] font-mono text-gray-600 dark:text-gray-300 w-7 text-right" x-text="`${ambientVolume}%`"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. YouTube Music Stream Deck -->
                    <div x-show="musicTab === 'ytmusic'" class="space-y-2">
                        <!-- Offline Notice if disconnected -->
                        <div x-show="!isOnline" class="p-2 bg-amber-500/10 border border-amber-500/30 rounded-xl text-[10px] text-amber-700 dark:text-amber-400 flex items-center justify-between gap-1">
                            <span>⚠️ Anda sedang offline. YouTube memerlukan koneksi internet.</span>
                            <button type="button" @click="musicTab = 'ambient'" class="font-bold underline cursor-pointer shrink-0">Ke Ambient</button>
                        </div>

                        <!-- Clean Track Info Card with Favorite & Video Toggle -->
                        <div class="bg-gray-50 dark:bg-gray-800/90 p-2.5 rounded-2xl border border-gray-200 dark:border-gray-700/60 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <img :src="ytThumbnail" class="w-9 h-9 rounded-xl object-cover shrink-0 border border-gray-200 dark:border-gray-700">
                                <div class="min-w-0">
                                    <div class="text-[11px] font-black text-gray-900 dark:text-white truncate" x-text="ytTitle"></div>
                                    <div class="text-[9px] text-red-500 dark:text-red-400 font-semibold truncate" x-text="ytAuthor"></div>
                                </div>
                            </div>

                            <div class="flex items-center gap-1 shrink-0">
                                <!-- Open Save to Folder Playlist Modal -->
                                <button type="button" 
                                        @click="openSaveToPlaylistModal(ytVideoId, ytTitle, ytAuthor, ytThumbnail)" 
                                        class="p-1.5 rounded-lg text-xs transition-all cursor-pointer"
                                        :class="isCurrentTrackSaved ? 'bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-500/40' : 'bg-white dark:bg-gray-700/80 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-transparent'"
                                        :title="isCurrentTrackSaved ? 'Atur Folder Playlist' : 'Simpan ke Playlist'">
                                    <span x-text="isCurrentTrackSaved ? '★ Tersimpan' : '☆ Simpan'"></span>
                                </button>

                                <!-- Button to toggle embedded video iframe -->
                                <button type="button" 
                                        @click="showYtVideoFrame = !showYtVideoFrame" 
                                        class="p-1.5 rounded-lg text-xs font-bold cursor-pointer border transition-all flex items-center justify-center"
                                        :class="showYtVideoFrame ? 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/15 border-red-200 dark:border-red-500/40' : 'bg-white dark:bg-gray-700/80 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-300 border-gray-200 dark:border-transparent'"
                                        :title="showYtVideoFrame ? 'Sembunyikan Video' : 'Tampilkan Video'">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="4" width="15" height="14" rx="2" ry="2"/>
                                        <polyline points="17 8 22 5 22 17 17 14"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Embedded YT Video Slot in Deck (Embedded cleanly inside the card) -->
                        <div x-show="showYtVideoFrame" 
                             x-collapse
                             class="relative w-full aspect-video rounded-2xl overflow-hidden bg-black border border-gray-200 dark:border-gray-750 shadow-inner my-2">
                            <div id="atoma-yt-video-slot" class="w-full h-full"></div>
                            <button type="button" 
                                    @click="showYtVideoFrame = false" 
                                    class="absolute top-2 right-2 z-20 bg-black/70 hover:bg-black text-white px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-colors shadow-xs"
                                    title="Sembunyikan Video">✕ Sembunyikan</button>
                        </div>

                        <!-- Search Input -->
                        <div class="flex items-center gap-1.5">
                            <input type="text" 
                                   x-model="ytQuery" 
                                   @keyup.enter="playYtQuery" 
                                   placeholder="Cari judul lagu / paste link YouTube..." 
                                   class="flex-1 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-2.5 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-red-500">
                            <button @click="playYtQuery" type="button" class="bg-red-600 hover:bg-red-500 text-white font-black text-xs px-3 py-1.5 rounded-xl transition-colors shrink-0 cursor-pointer shadow-xs">
                                <span x-show="!isYtSearching">Cari</span>
                                <span x-show="isYtSearching">⏳</span>
                            </button>
                        </div>

                        <!-- Multiple Search Suggestions -->
                        <template x-if="ytResults && ytResults.length > 0">
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-1.5 border border-gray-200 dark:border-gray-700 space-y-1 text-[10px] max-h-36 overflow-y-auto hide-scrollbar shadow-lg">
                                <div class="text-[9px] text-gray-500 dark:text-gray-400 font-bold px-1 flex justify-between items-center">
                                    <span>Pilih Versi Lagu:</span>
                                    <button @click="ytResults = []" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">✕</button>
                                </div>
                                <template x-for="res in ytResults" :key="res.video_id">
                                    <div class="flex items-center justify-between p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        <button @click="loadYtVideo(res.video_id, res.title, res.author, res.thumbnail); ytResults = []" class="flex-1 text-left truncate cursor-pointer mr-1">
                                            <span class="truncate" x-text="`${res.title} (${res.author})`"></span>
                                        </button>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <button @click="openSaveToPlaylistModal(res.video_id, res.title, res.author, res.thumbnail)" class="text-gray-400 hover:text-amber-500 p-0.5" title="Simpan ke Folder Playlist">☆</button>
                                            <button @click="loadYtVideo(res.video_id, res.title, res.author, res.thumbnail); ytResults = []" class="text-red-500 font-bold p-0.5" title="Putar">▶</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Saved Playlist & Folders Section -->
                        <div class="space-y-1.5 pt-1">
                            <!-- Header with Folder Switcher & Create Folder -->
                            <div class="flex items-center justify-between text-[10px] font-bold text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1 text-amber-600 dark:text-amber-400 min-w-0">
                                    <span>📁</span>
                                    <span class="truncate">Folder Playlist</span>
                                </div>
                                <button type="button" @click="openSaveToPlaylistModal(ytVideoId, ytTitle, ytAuthor, ytThumbnail)" class="text-[9px] text-atoma-blue dark:text-atoma-cyan hover:underline font-bold bg-atoma-blue/10 dark:bg-atoma-blue/20 px-2 py-0.5 rounded-lg border border-atoma-blue/20 dark:border-atoma-blue/40 cursor-pointer">
                                    + Kelola Folder
                                </button>
                            </div>

                            <!-- Folder Tabs / Switcher Pills -->
                            <div class="flex gap-1 overflow-x-auto hide-scrollbar pb-1">
                                <template x-for="folder in playlistFolders" :key="folder.id">
                                    <button type="button" 
                                            @click="activeFolderId = folder.id" 
                                            class="px-2.5 py-1 rounded-xl text-[10px] font-bold whitespace-nowrap border transition-all flex items-center gap-1 cursor-pointer shrink-0"
                                            :class="activeFolderId === folder.id ? 'bg-amber-50 dark:bg-amber-500/20 border-amber-300 dark:border-amber-500/60 text-amber-800 dark:text-amber-300 shadow-xs' : 'bg-gray-50 dark:bg-gray-800/80 border-gray-200 dark:border-gray-700/60 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'">
                                        <span x-text="folder.icon || '📁'"></span>
                                        <span x-text="folder.name"></span>
                                        <span class="text-[9px] opacity-70" x-text="`(${folder.tracks.length})`"></span>
                                    </button>
                                </template>
                            </div>

                            <!-- Active Folder Info, Rename & Delete Folder -->
                            <div class="flex items-center justify-between text-[9px] text-gray-500 dark:text-gray-400 px-0.5">
                                <div class="flex items-center gap-1 min-w-0">
                                    <span class="truncate" x-text="`Memutar: ${activeFolder.name}`"></span>
                                    <button type="button" 
                                            @click="openSaveToPlaylistModal(ytVideoId, ytTitle, ytAuthor, ytThumbnail); startEditFolder(activeFolder)" 
                                            class="text-amber-600 dark:text-amber-400 hover:underline cursor-pointer" 
                                            title="Ganti nama folder ini">
                                        [✏️ Ganti Nama]
                                    </button>
                                </div>
                                <template x-if="playlistFolders.length > 1">
                                    <button type="button" @click="deleteFolder(activeFolder.id)" class="text-gray-400 hover:text-red-500 cursor-pointer" title="Hapus folder playlist ini">
                                        Hapus Folder ✕
                                    </button>
                                </template>
                            </div>

                            <!-- Playlist Tracks in Active Folder -->
                            <div class="space-y-1 max-h-36 overflow-y-auto hide-scrollbar pr-0.5">
                                <template x-if="currentFolderTracks.length === 0">
                                    <div class="text-center py-4 text-gray-400 text-[10px] bg-gray-50 dark:bg-gray-800/40 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700/60">
                                        Folder ini masih kosong.<br>Cari lagu & simpan ke folder ini! 🎵
                                    </div>
                                </template>
                                <template x-for="(item, idx) in currentFolderTracks" :key="item.video_id">
                                    <div class="flex items-center justify-between p-1.5 rounded-xl border transition-all"
                                         :class="ytVideoId === item.video_id ? 'bg-rose-50 dark:bg-rose-500/20 border-rose-200 dark:border-rose-500/50 text-rose-900 dark:text-rose-100 font-bold' : 'bg-gray-50 dark:bg-gray-800/80 border-gray-200 dark:border-gray-700/60 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750'">
                                        
                                        <button type="button" 
                                                @click="loadYtVideo(item.video_id, item.title, item.author, item.thumbnail)" 
                                                class="flex items-center gap-2 min-w-0 flex-1 text-left cursor-pointer">
                                            <img :src="item.thumbnail" class="w-6 h-6 rounded-lg object-cover shrink-0 border border-gray-200 dark:border-gray-700">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-[10px] font-black truncate" x-text="item.title"></div>
                                                <div class="text-[8px] text-gray-500 dark:text-gray-400 truncate" x-text="item.author"></div>
                                            </div>
                                        </button>

                                        <div class="flex items-center gap-1 shrink-0 ml-1.5">
                                            <span x-show="ytVideoId === item.video_id" class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-ping mr-1"></span>
                                            <button type="button" 
                                                    @click.stop="openSaveToPlaylistModal(item.video_id, item.title, item.author, item.thumbnail)" 
                                                    class="text-gray-400 hover:text-amber-500 p-1 text-[10px] rounded transition-colors cursor-pointer"
                                                    title="Atur Folder">
                                                📁
                                            </button>
                                            <button type="button" 
                                                    @click.stop="removeTrackFromFolder(activeFolder.id, item.video_id)" 
                                                    class="text-gray-400 hover:text-red-500 p-1 text-[10px] rounded transition-colors cursor-pointer"
                                                    title="Hapus dari folder ini">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Playback & Audio Control Deck -->
                        <!-- Playback & Audio Control Deck -->
                        <div class="bg-gray-50 dark:bg-gray-800/90 p-2.5 rounded-2xl border border-gray-200 dark:border-gray-700/60 space-y-2.5 overflow-hidden">
                            <div class="flex items-center justify-between gap-1 w-full">
                                <!-- Shuffle Button -->
                                <button type="button" 
                                        @click="toggleYtShuffle" 
                                        class="w-8 h-8 rounded-xl transition-all cursor-pointer flex items-center justify-center border shrink-0" 
                                        :class="isYtShuffle ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 shadow-xs' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-200/60 dark:hover:bg-gray-700/60 border-transparent'" 
                                        title="Acak Urutan (Shuffle)">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="16 3 21 3 21 8"></polyline>
                                        <line x1="4" y1="20" x2="21" y2="3"></line>
                                        <polyline points="21 16 21 21 16 21"></polyline>
                                        <line x1="15" y1="15" x2="21" y2="21"></line>
                                        <line x1="4" y1="4" x2="9" y2="9"></line>
                                    </svg>
                                </button>

                                <!-- Prev Track Button -->
                                <button type="button" 
                                        @click="playPrevYtTrack" 
                                        class="w-8 h-8 rounded-xl text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-700/60 active:scale-90 transition-all cursor-pointer flex items-center justify-center shrink-0" 
                                        title="Lagu Sebelumnya">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M6 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1zm12.5 1.5a1 1 0 0 0-1.5-.86l-9 5.5a1 1 0 0 0 0 1.72l9 5.5A1 1 0 0 0 18.5 17.5v-11z"/>
                                    </svg>
                                </button>

                                <!-- Play / Pause Button (Clean Centerpiece Circle) -->
                                <button type="button" 
                                        @click="toggleYtPlayback" 
                                        class="w-9 h-9 rounded-full text-white transition-all flex items-center justify-center cursor-pointer active:scale-95 shadow-md shrink-0"
                                        :class="isYtPlaying ? 'bg-gradient-to-tr from-rose-600 to-red-500 hover:from-rose-500 hover:to-red-400 shadow-rose-500/25' : 'bg-gradient-to-tr from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 shadow-emerald-500/25'"
                                        :title="isYtPlaying ? 'Jeda' : 'Putar'">
                                    <!-- Play Icon -->
                                    <svg x-show="!isYtPlaying" class="w-4 h-4 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                        <path d="M6.5 4.25a1.25 1.25 0 0 1 1.9-.99l11.5 7.75a1.25 1.25 0 0 1 0 1.98l-11.5 7.75a1.25 1.25 0 0 1-1.9-.99V4.25z"/>
                                    </svg>
                                    <!-- Pause Icon -->
                                    <svg x-show="isYtPlaying" class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M6.75 5.25a1.25 1.25 0 0 1 1.25 1.25v11a1.25 1.25 0 1 1-2.5 0v-11a1.25 1.25 0 0 1 1.25-1.25zm10.5 0a1.25 1.25 0 0 1 1.25 1.25v11a1.25 1.25 0 1 1-2.5 0v-11a1.25 1.25 0 0 1 1.25-1.25z" clip-rule="evenodd"/>
                                    </svg>
                                </button>

                                <!-- Next Track Button -->
                                <button type="button" 
                                        @click="playNextYtTrack(false)" 
                                        class="w-8 h-8 rounded-xl text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-700/60 active:scale-90 transition-all cursor-pointer flex items-center justify-center shrink-0" 
                                        title="Lagu Berikutnya">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18 5a1 1 0 0 0-1 1v12a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1zM5.5 6.5a1 1 0 0 1 1.5-.86l9 5.5a1 1 0 0 1 0 1.72l-9 5.5A1 1 0 0 1 5.5 17.5v-11z"/>
                                    </svg>
                                </button>

                                <div class="h-4 w-px bg-gray-200 dark:bg-gray-700 mx-0.5 shrink-0"></div>

                                <!-- Loop / Repeat Mode Button -->
                                <button type="button" 
                                        @click="cycleYtLoop" 
                                        class="w-8 h-8 rounded-xl transition-all cursor-pointer relative flex items-center justify-center border shrink-0" 
                                        :class="ytLoopMode !== 'off' ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 shadow-xs' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-200/60 dark:hover:bg-gray-700/60 border-transparent'" 
                                        :title="ytLoopMode === 'all' ? 'Loop Seluruh Playlist' : (ytLoopMode === 'single' ? 'Ulang Lagu Ini Saja (Repeat 1)' : 'Loop Mati')">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m17 2 4 4-4 4"/>
                                        <path d="M3 11v-1a4 4 0 0 1 4-4h14"/>
                                        <path d="m7 22-4-4 4-4"/>
                                        <path d="M21 13v1a4 4 0 0 1-4 4H3"/>
                                    </svg>
                                    <span x-show="ytLoopMode === 'single'" class="absolute text-[8px] font-black leading-none bg-rose-600 text-white rounded-full w-3.5 h-3.5 flex items-center justify-center -top-1 -right-1 shadow-xs">1</span>
                                </button>

                                <!-- Auto-Next Toggle Button -->
                                <button type="button" 
                                        @click="toggleYtAutoNext" 
                                        class="h-8 px-2 rounded-xl text-[10px] font-bold transition-all cursor-pointer flex items-center gap-1 border shrink-0" 
                                        :class="isYtAutoNext ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 shadow-xs' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-200/60 dark:hover:bg-gray-700/60 border-transparent'" 
                                        title="Otomatis putar lagu berikutnya di playlist saat selesai">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M5.5 6.5a1 1 0 0 1 1.5-.86l9 5.5a1 1 0 0 1 0 1.72l-9 5.5A1 1 0 0 1 5.5 17.5v-11zM18 5a1 1 0 0 0-1 1v12a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1z"/>
                                    </svg>
                                    <span class="text-[9px] font-bold">Auto</span>
                                </button>
                            </div>

                            <!-- Volume Control Bar -->
                            <div class="flex items-center justify-between gap-2.5 pt-1.5 border-t border-gray-200/80 dark:border-gray-700/50 text-xs text-gray-500 dark:text-gray-400">
                                <button type="button" 
                                        @click="toggleYtMute" 
                                        class="p-1 rounded-lg hover:bg-gray-200/60 dark:hover:bg-gray-700/60 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer shrink-0" 
                                        :title="isYtMuted || ytVolume === 0 ? 'Batal Mute' : 'Mute'">
                                    <!-- Speaker Mute Icon -->
                                    <svg x-show="isYtMuted || ytVolume === 0" class="w-4 h-4 text-rose-500" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H3.75A1.75 1.75 0 002 9.25v5.5c0 .966.784 1.75 1.75 1.75h2.69l4.5 4.5c.944.945 2.56.276 2.56-1.06V4.06zM17.22 8.22a.75.75 0 011.06 0l1.72 1.72 1.72-1.72a.75.75 0 111.06 1.06L21.06 11l1.72 1.72a.75.75 0 11-1.06 1.06L20 12.06l-1.72 1.72a.75.75 0 01-1.06-1.06L18.94 11l-1.72-1.72a.75.75 0 010-1.06z"/>
                                    </svg>
                                    <!-- Speaker Low Icon -->
                                    <svg x-show="!isYtMuted && ytVolume > 0 && ytVolume < 50" class="w-4 h-4 text-gray-600 dark:text-gray-300" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H3.75A1.75 1.75 0 002 9.25v5.5c0 .966.784 1.75 1.75 1.75h2.69l4.5 4.5c.944.945 2.56.276 2.56-1.06V4.06zM15.66 8.34a.75.75 0 011.06 0 4.25 4.25 0 010 5.96.75.75 0 11-1.06-1.06 2.75 2.75 0 000-3.84.75.75 0 010-1.06z"/>
                                    </svg>
                                    <!-- Speaker High Icon -->
                                    <svg x-show="!isYtMuted && ytVolume >= 50" class="w-4 h-4 text-gray-700 dark:text-gray-200" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H3.75A1.75 1.75 0 002 9.25v5.5c0 .966.784 1.75 1.75 1.75h2.69l4.5 4.5c.944.945 2.56.276 2.56-1.06V4.06zM17.78 6.22a.75.75 0 011.06 0 8.25 8.25 0 010 11.56.75.75 0 11-1.06-1.06 6.75 6.75 0 000-9.44.75.75 0 010-1.06zm-2.12 2.12a.75.75 0 011.06 0 4.25 4.25 0 010 5.96.75.75 0 11-1.06-1.06 2.75 2.75 0 000-3.84.75.75 0 010-1.06z"/>
                                    </svg>
                                </button>
                                <div class="flex items-center gap-2 flex-1">
                                    <input type="range" 
                                           x-model.number="ytVolume" 
                                           @input="updateYtVolume" 
                                           min="0" 
                                           max="100" 
                                           step="1" 
                                           class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-rose-500 dark:accent-rose-400">
                                    <span class="text-[10px] font-mono text-gray-600 dark:text-gray-300 w-8 text-right shrink-0" x-text="`${isYtMuted ? 0 : ytVolume}%`"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div> <!-- End of scrollable main card body -->
        </div> <!-- End of TAB 1: POMODORO TIMER -->

        <!-- TAB 2: TASKS (Quick Add, Checklist, Connect to Focus) -->
        <div x-show="widgetTab === 'tasks'" class="flex-1 overflow-y-auto hide-scrollbar space-y-2.5 pt-1 pr-0.5" style="display: none;">
            <!-- Quick Add Task Form -->
            <form @submit.prevent="addQuickTask()" class="flex items-center gap-1.5">
                <input type="text" 
                       x-model="quickTaskInput" 
                       placeholder="+ Tambah tugas hari ini..." 
                       class="flex-1 text-xs bg-gray-50 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-atoma-blue"
                       :disabled="isAddingQuickTask">
                <button type="submit" 
                        :disabled="isAddingQuickTask || !quickTaskInput.trim()"
                        class="px-3 py-2 bg-atoma-blue hover:bg-[#3455dc] disabled:opacity-50 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer">
                    <span x-show="!isAddingQuickTask">+</span>
                    <span x-show="isAddingQuickTask">...</span>
                </button>
            </form>

            <!-- Tasks List -->
            <div class="space-y-1.5">
                <template x-for="task in activeTasks" :key="task.id">
                    <div class="p-2.5 rounded-xl border transition-all flex items-start gap-2.5"
                         :class="task.is_completed ? 'bg-gray-50/60 dark:bg-gray-800/40 border-gray-200/60 dark:border-gray-700/40 opacity-70' : (selectedTaskId === task.id ? 'bg-indigo-50/70 dark:bg-indigo-950/30 border-indigo-300 dark:border-indigo-700/60' : 'bg-white dark:bg-gray-800/90 border-gray-200 dark:border-gray-700/70')">
                        
                        <button type="button" 
                                @click="toggleTaskCompletion(task)"
                                class="w-4 h-4 mt-0.5 rounded border flex items-center justify-center text-[10px] font-bold transition-colors cursor-pointer shrink-0"
                                :class="task.is_completed ? 'bg-emerald-500 text-white border-emerald-500' : 'border-gray-300 dark:border-gray-600 hover:border-emerald-500 text-transparent'">
                            ✓
                        </button>

                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-bold break-words" 
                                 :class="task.is_completed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-200'"
                                 x-text="task.title"></div>
                            <div class="text-[10px] text-gray-400 mt-0.5 flex items-center gap-2 flex-wrap">
                                <span x-text="`${task.estimated_minutes || 25}m`"></span>
                                <span x-show="task.completed_sessions > 0" class="text-rose-500 font-semibold" x-text="`🍅 ${task.completed_sessions} sesi`"></span>
                                <button type="button" 
                                        @click="startFocusTask(task.id); widgetTab = 'pomodoro'"
                                        class="text-atoma-blue dark:text-atoma-cyan hover:underline font-semibold cursor-pointer">
                                    ▶ Fokus Ini
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="activeTasks.length === 0" class="text-center py-8 text-xs text-gray-400">
                    <span class="text-xl block mb-1">📋</span>
                    Belum ada tugas hari ini.<br>Ketik di atas untuk menambahkan!
                </div>
            </div>
        </div>

        <!-- TAB 3: NOTES (Quick Scratchpad) -->
        <div x-show="widgetTab === 'notes'" class="flex-1 flex flex-col hide-scrollbar space-y-2 pt-1 pr-0.5" style="display: none;">
            <div class="flex items-center justify-between text-[11px] font-bold text-gray-500 dark:text-gray-400">
                <span>📝 Quick Scratchpad</span>
                <span class="text-[10px] text-emerald-600 dark:text-emerald-400">💾 Tersimpan lokal</span>
            </div>
            <textarea x-model="quickNoteContent" 
                      @input="saveQuickNoteDraft()" 
                      placeholder="Tulis ide, catatan cepat, atau rangkuman saat belajar/bekerja..." 
                      class="flex-1 w-full text-xs p-3 bg-gray-50 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 rounded-2xl text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-atoma-blue resize-none min-h-[160px]"></textarea>
            <div class="flex items-center justify-between gap-2 pt-1 shrink-0">
                <span class="text-[10px] text-gray-400" x-text="`${quickNoteContent.length} karakter`"></span>
                <button type="button" 
                        @click="exportNoteToJournal()" 
                        :disabled="isSavingQuickNote || !quickNoteContent.trim()"
                        class="px-3 py-1.5 bg-atoma-blue hover:bg-[#3455dc] disabled:opacity-50 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer flex items-center gap-1">
                    <span x-show="!isSavingQuickNote">📖 Simpan ke Jurnal</span>
                    <span x-show="isSavingQuickNote">Menyimpan...</span>
                </button>
            </div>
        </div>

        <!-- TAB 4: FLASHCARDS (SRS Spaced Repetition) -->
        <div x-show="widgetTab === 'flashcards'" class="flex-1 overflow-y-auto hide-scrollbar space-y-3 pt-1 pr-0.5" style="display: none;">
            <div class="p-4 rounded-2xl bg-gradient-to-br from-indigo-500/10 via-purple-500/5 to-transparent border border-indigo-200 dark:border-indigo-800/50 text-center">
                <div class="text-2xl mb-1">🗂️</div>
                <h4 class="text-sm font-black text-gray-900 dark:text-white">Flashcards Review</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Metode Spaced Repetition SuperMemo SM-2
                </p>
                
                <div class="my-3.5 p-3 rounded-xl bg-white/80 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 inline-flex items-center gap-3 shadow-xs">
                    <div>
                        <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                            {{ $dueFlashcardsCount }}
                        </div>
                        <div class="text-[10px] font-bold text-gray-500 uppercase">Kartu Perlu Di-Review</div>
                    </div>
                </div>

                <div class="space-y-2">
                    <a href="{{ route('flashcards') }}" 
                       class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-indigo-600/25 transition-all cursor-pointer">
                        <span>🚀 Buka Flashcards Sekarang</span>
                    </a>
                    <p class="text-[10px] text-gray-400">
                        Review rutin mempertajam retensi memori jangka panjang hingga 90%!
                    </p>
                </div>
            </div>
        </div>

    </div> <!-- End of card -->

    <!-- Always on Top: Mini Floating Bubble / Pill (Draggable, Snap-to-Edge, Compact & Expanded) -->
    <div x-show="!show && (isRunning || isYtPlaying || isAmbientPlaying || settings.alwaysShowFloat)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-6 opacity-0 scale-90"
         x-transition:enter-end="translate-y-0 opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 opacity-100 scale-100"
         x-transition:leave-end="translate-y-6 opacity-0 scale-90"
         style="display: none;"
         :style="floatPositionStyle"
         :class="{ 'touch-none cursor-grabbing': isDragging, 'cursor-grab': !isDragging, 'opacity-70 hover:opacity-100': !isRunning && !isYtPlaying && !isAmbientPlaying && settings.alwaysShowFloat }"
         @mousedown="startDrag($event)"
         @touchstart="startDrag($event)"
         class="fixed z-[9999] pointer-events-auto select-none transition-shadow">
        
        <!-- MODE 1: Ultra-Compact Circular Bubble (48px) -->
        <div x-show="isCompact" 
             class="relative w-12 h-12 rounded-full bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200/90 dark:border-gray-800/90 shadow-2xl flex items-center justify-center group hover:scale-105 transition-transform"
             @click="onFloatClick()">
            
            <!-- SVG Circular Progress Ring -->
            <svg class="absolute inset-0 w-full h-full -rotate-90 transform pointer-events-none" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15" fill="none" class="stroke-gray-200 dark:stroke-gray-800" stroke-width="2.5" />
                <circle cx="18" cy="18" r="15" fill="none" 
                        :class="mode === 'work' ? 'stroke-rose-500' : (mode === 'flowtime' ? 'stroke-cyan-500' : 'stroke-emerald-500')" 
                        stroke-width="2.5" 
                        stroke-linecap="round" 
                        stroke-dasharray="94.25" 
                        :stroke-dashoffset="ringOffset"
                        class="transition-[stroke-dashoffset] duration-500 ease-out" />
            </svg>

            <!-- Center Mode Icon with Active Pulse -->
            <div class="flex items-center justify-center relative z-10">
                <span class="text-base select-none" x-text="mode === 'work' ? '🍅' : (mode === 'flowtime' ? '🌊' : '☕')"></span>
                <span x-show="isRunning" class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>

            <!-- Mini Expand Toggle Arrow -->
            <button type="button" 
                    @click.stop="toggleCompactMode()" 
                    class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-atoma-blue text-white flex items-center justify-center text-[10px] shadow-sm hover:scale-110 transition-transform cursor-pointer"
                    title="Perluas ke Mode Pill">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>

        <!-- MODE 2: Expanded Pill with Timer, Controls & Quick Actions -->
        <div x-show="!isCompact"
             class="flex items-center gap-2 px-3 py-2 sm:px-3.5 sm:py-2.5 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200/90 dark:border-gray-800/90 rounded-full shadow-2xl text-xs text-gray-900 dark:text-white group hover:border-atoma-blue/40 transition-all max-w-[calc(100vw-2rem)]">
            
            <!-- Circular Progress Ring around Icon -->
            <div class="relative flex items-center justify-center shrink-0 cursor-grab" title="Tahan & Geser untuk memindahkan">
                <svg class="w-8 h-8 -rotate-90 transform pointer-events-none" viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="15" fill="none" class="stroke-gray-200 dark:stroke-gray-800" stroke-width="3" />
                    <circle cx="18" cy="18" r="15" fill="none" 
                            :class="mode === 'work' ? 'stroke-rose-500' : (mode === 'flowtime' ? 'stroke-cyan-500' : 'stroke-emerald-500')" 
                            stroke-width="3" 
                            stroke-linecap="round" 
                            stroke-dasharray="94.25" 
                            :stroke-dashoffset="ringOffset"
                            class="transition-[stroke-dashoffset] duration-500 ease-out" />
                </svg>
                <span class="absolute text-sm select-none" x-text="mode === 'work' ? '🍅' : (mode === 'flowtime' ? '🌊' : '☕')"></span>
            </div>

            <!-- Info: Countdown, Daily Target, Streak & Title -->
            <div class="flex flex-col min-w-0 pr-1 cursor-pointer" @click="onFloatClick()">
                <div class="flex items-center gap-1.5 font-black text-xs font-mono tracking-tight leading-none">
                    <span x-text="formattedTime" :class="isRunning ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-900 dark:text-white'"></span>
                    
                    <!-- Daily Target Sessions Badge -->
                    <span class="text-[9px] px-1 py-0.2 rounded font-mono font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400" 
                          title="Sesi Fokus Hari Ini"
                          x-text="`${todaySessionsCount}/${settings.interval}`"></span>
                    
                    <!-- Streak Consistency Flame -->
                    <template x-if="userStreak > 0">
                        <span class="text-[9px] text-orange-500 font-bold flex items-center" title="Streak Hari Beruntun">
                            🔥<span x-text="`${userStreak}d`"></span>
                        </span>
                    </template>

                    <!-- Audio Equalizer Animation -->
                    <template x-if="isYtPlaying || isAmbientPlaying">
                        <span class="flex items-end gap-0.5 h-2.5 ml-0.5" title="Memutar Audio">
                            <span class="w-0.5 bg-rose-500 animate-pulse h-2 rounded-full"></span>
                            <span class="w-0.5 bg-rose-500 animate-pulse h-2.5 rounded-full [animation-delay:150ms]"></span>
                            <span class="w-0.5 bg-rose-500 animate-pulse h-1.5 rounded-full [animation-delay:300ms]"></span>
                        </span>
                    </template>
                </div>

                <div class="text-[10px] text-gray-500 dark:text-gray-400 truncate max-w-[110px] sm:max-w-[150px] mt-0.5 font-medium"
                     x-text="isYtPlaying ? ytTitle : (isAmbientPlaying ? currentAmbientName : (selectedTaskTitle || modeName))">
                </div>
            </div>

            <!-- Action Controls Group -->
            <div class="flex items-center gap-1 shrink-0">
                <!-- Quick Audio Previous Track Button (When music/ambient is active or audio loaded) -->
                <template x-if="isYtPlaying || isAmbientPlaying || ytVideoId">
                    <button type="button"
                            @click.stop="playPrevTrack()"
                            class="p-1.5 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-600 dark:text-gray-300 transition-all active:scale-90 cursor-pointer flex items-center justify-center"
                            title="Lagu Sebelumnya">
                        <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24">
                            <path d="M6 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1zm12.5 1.5a1 1 0 0 0-1.5-.86l-9 5.5a1 1 0 0 0 0 1.72l9 5.5A1 1 0 0 0 18.5 17.5v-11z"/>
                        </svg>
                    </button>
                </template>

                <!-- Quick Play/Pause Button -->
                <button type="button"
                        @click.stop="togglePlayPauseFromFloat()"
                        class="p-1.5 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-700 dark:text-gray-200 transition-colors cursor-pointer flex items-center justify-center"
                        :title="(isRunning || isYtPlaying || isAmbientPlaying) ? 'Jeda' : 'Lanjutkan'">
                    <svg x-show="isRunning || isYtPlaying || isAmbientPlaying" class="w-3 h-3 fill-current" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M6.75 5.25a1.25 1.25 0 0 1 1.25 1.25v11a1.25 1.25 0 1 1-2.5 0v-11a1.25 1.25 0 0 1 1.25-1.25zm10.5 0a1.25 1.25 0 0 1 1.25 1.25v11a1.25 1.25 0 1 1-2.5 0v-11a1.25 1.25 0 0 1 1.25-1.25z" clip-rule="evenodd"/>
                    </svg>
                    <svg x-show="!(isRunning || isYtPlaying || isAmbientPlaying)" class="w-3 h-3 fill-current translate-x-0.5" viewBox="0 0 24 24">
                        <path d="M6.5 4.25a1.25 1.25 0 0 1 1.9-.99l11.5 7.75a1.25 1.25 0 0 1 0 1.98l-11.5 7.75a1.25 1.25 0 0 1-1.9-.99V4.25z"/>
                    </svg>
                </button>

                <!-- Quick Audio Next Track Button (When music/ambient is active or audio loaded) -->
                <template x-if="isYtPlaying || isAmbientPlaying || ytVideoId">
                    <button type="button"
                            @click.stop="playNextTrack()"
                            class="p-1.5 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-600 dark:text-gray-300 transition-all active:scale-90 cursor-pointer flex items-center justify-center"
                            title="Lagu Berikutnya">
                        <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24">
                            <path d="M18 5a1 1 0 0 0-1 1v12a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1zM5.5 6.5a1 1 0 0 1 1.5-.86l9 5.5a1 1 0 0 1 0 1.72l-9 5.5A1 1 0 0 1 5.5 17.5v-11z"/>
                        </svg>
                    </button>
                </template>

                <!-- Quick Task Done Button (When task is linked) -->
                <template x-if="selectedTaskId">
                    <button type="button"
                            @click.stop="quickTaskDone()"
                            class="p-1.5 rounded-full bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/20 dark:hover:bg-emerald-500/30 text-emerald-600 dark:text-emerald-400 transition-colors cursor-pointer flex items-center justify-center shadow-xs"
                            title="Selesaikan Tugas Ini (+XP)">
                        <x-icon.check class="w-3 h-3" />
                    </button>
                </template>

                <!-- Quick Audio Mute Button (When music/ambient is active) -->
                <template x-if="isYtPlaying || isAmbientPlaying">
                    <button type="button"
                            @click.stop="toggleAudioMute()"
                            class="p-1.5 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-600 dark:text-gray-300 transition-colors cursor-pointer flex items-center justify-center"
                            :title="isAudioMuted ? 'Nyalakan Suara Audio' : 'Bisukan Suara Audio'">
                        <svg x-show="isAudioMuted" class="w-3 h-3 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
                        <svg x-show="!isAudioMuted" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
                    </button>
                </template>

                <!-- Collapse to Compact Bubble Button -->
                <button type="button"
                        @click.stop="toggleCompactMode()"
                        class="p-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors cursor-pointer"
                        title="Ciutkan ke Bubble Mini">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5"/></svg>
                </button>

                <!-- Full Expand Button -->
                <button type="button"
                        @click.stop="toggleShow()"
                        class="p-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors cursor-pointer"
                        title="Buka Pomodoro Penuh">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Audio for fallback alarm bell -->
    <audio x-ref="alarmSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
</div>

<script data-navigate-once>
    const registerPomodoro = () => {
        if (typeof Alpine !== 'undefined' && !Alpine.data('pomodoroTimer')) {
            Alpine.data('pomodoroTimer', (initialTasks = [], userStreak = 0, dueFlashcardsCount = 0) => ({
                show: false,
                showSettings: false,
                showAudioDeck: false,
                showYtVideoFrame: false,
                showDailyLogModal: false,
                showFlowtimeModal: false,
                
                // Productivity Hub Tabs ('pomodoro' | 'tasks' | 'notes' | 'flashcards')
                widgetTab: 'pomodoro',
                quickTaskInput: '',
                isAddingQuickTask: false,
                quickNoteContent: localStorage.getItem('atoma_widget_quick_note') || '',
                isSavingQuickNote: false,
                dueFlashcardsCount: dueFlashcardsCount,

                // Mode: 'work' | 'flowtime' | 'shortBreak' | 'longBreak'
                mode: 'work',
                timeLeft: 25 * 60,
                flowtimeSeconds: 0,
                isRunning: false,
                interval: null,
                sessionCount: 0,
                userStreak: userStreak,
                
                // Tasks
                activeTasks: initialTasks,
                selectedTaskId: '',
                selectedTaskTitle: '',
                
                // Float states & audio mute
                isCompact: false,
                isAudioMuted: false,

                // Draggable Float Bubble Coordinates & Physics
                customX: null,
                customY: null,
                dockSide: 'right',
                isDragging: false,
                hasDragged: false,
                dragStartX: 0,
                dragStartY: 0,
                initialX: 0,
                initialY: 0,

                // Canvas PiP Fallback State
                canvasPipActive: false,
                canvasPipVideo: null,
                canvasPipInterval: null,

                // Offline status
                isOnline: navigator.onLine,
                
                // Flowtime complete state
                flowtimeResultMinutes: 0,
                flowtimeRecommendedBreak: 5,
                
                // Daily Session Log
                isLoadingDailyLog: false,
                dailySessions: [],
                dailyLogSummary: { total_sessions: 0, total_minutes: 0, total_xp: 0, total_coins: 0 },
                
                // Micro-break tips
                activeBreakTipIndex: 0,
                breakTips: [
                    { icon: '💧', title: 'Hidrasi Segar', description: 'Ambil dan minum 1-2 gelas air putih untuk menjaga sirkulasi darah & konsentrasi otak.' },
                    { icon: '👀', title: 'Aturan 20-20-20', description: 'Tatap objek berjarak 6 meter selama 20 detik untuk merelaksasi otot fokus mata.' },
                    { icon: '🧘', title: 'Regangkan Leher & Bahu', description: 'Putar bahu ke belakang 5 kali dan miringkan kepala perlahan ke kanan & kiri.' },
                    { icon: '🌬️', title: 'Pernapasan Rileks 4-7-8', description: 'Tarik napas 4 detik, tahan 7 detik, lalu hembuskan napas perlahan selama 8 detik.' },
                    { icon: '🚶', title: 'Jalan Ringan 2 Menit', description: 'Berdirilah dan berjalan santai mengitari ruangan untuk melemaskan persendian panggul.' },
                    { icon: '🪟', title: 'Tatap Cahaya Luar', description: 'Buka jendela atau pandang dedaunan luar ruangan untuk menyegarkan dopamin alami.' },
                ],
                
                // Music tab: 'ytmusic' | 'ambient'
                musicTab: 'ytmusic',
                
                // YouTube Player State & Multi-Folder Playlist
                ytVideoId: '5qap5aO4i9A',
                ytTitle: 'Lofi Beats - Deep Focus',
                ytAuthor: 'Lofi Music',
                ytThumbnail: 'https://i.ytimg.com/vi/5qap5aO4i9A/hqdefault.jpg',
                ytQuery: '',
                ytEmbedUrl: 'https://www.youtube.com/embed/5qap5aO4i9A?enablejsapi=1&autoplay=1',
                ytResults: [],
                isYtSearching: false,
                isYtPlaying: false,
                isYtAutoNext: true,
                isYtShuffle: false,
                ytLoopMode: 'all',
                ytVolume: 80,
                isYtMuted: false,

                // Multi-Folder Playlist State
                isPro: {{ auth()->check() && auth()->user()->isPro() ? 'true' : 'false' }},
                maxFolders: {{ auth()->check() ? auth()->user()->maxPomodoroFolders() : 2 }},
                showPlaylistModal: false,
                targetTrackToSave: {},
                newFolderName: '',
                activeFolderId: 'focus',
                editingFolderId: null,
                editFolderName: '',
                editFolderIcon: '📁',
                playlistFolders: [
                    {
                        id: 'focus',
                        name: 'Deep Focus',
                        icon: '🧠',
                        tracks: [
                            { video_id: '5qap5aO4i9A', title: 'Lofi Beats - Deep Focus', author: 'Lofi Girl', thumbnail: 'https://i.ytimg.com/vi/5qap5aO4i9A/hqdefault.jpg' },
                            { video_id: '2OEL4P1rub0', title: 'Peaceful Piano Focus', author: 'Piano Relaxation', thumbnail: 'https://i.ytimg.com/vi/2OEL4P1rub0/hqdefault.jpg' }
                        ]
                    },
                    {
                        id: 'chill',
                        name: 'Chill & Relax',
                        icon: '☕',
                        tracks: [
                            { video_id: '4xDzrJKXOOY', title: 'Rain & Ambient Beats', author: 'Ambient Sound', thumbnail: 'https://i.ytimg.com/vi/4xDzrJKXOOY/hqdefault.jpg' }
                        ]
                    }
                ],

                // Procedural Ambient Offline Player (Web Audio API)
                isAmbientPlaying: false,
                currentAmbientPreset: 'rain',
                ambientVolume: 70,
                ambientPresets: [
                    { id: 'rain', name: 'Hujan Rintik', icon: '🌧️', type: 'pink_lowpass' },
                    { id: 'ocean', name: 'Ombak Laut', icon: '🌊', type: 'ocean_swell' },
                    { id: 'cafe', name: 'Kafe Santai', icon: '☕', type: 'brown_noise' },
                    { id: 'forest', name: 'Hutan Hening', icon: '🌲', type: 'forest_wind' },
                    { id: 'alpha', name: 'Gelombang 40Hz', icon: '🧠', type: 'binaural_40hz' }
                ],
                ambientAudioCtx: null,
                ambientSourceNodes: [],
                ambientGainNode: null,

                // Mode Persistence Memory (Prevents accidental reset when switching tabs)
                savedWorkTimeLeft: null,
                savedShortBreakTimeLeft: null,
                savedLongBreakTimeLeft: null,

                // Cache Storage Metric
                cacheSizeText: '0 KB',

                // Telemetry
                todayFocusMinutes: 0,
                todaySessionsCount: 0,
                
                settings: {
                    work: 25,
                    shortBreak: 5,
                    longBreak: 15,
                    interval: 4,
                    autoStartBreak: false,
                    autoStartWork: false,
                    autoPauseMusic: true,
                    alwaysShowFloat: true,
                },

                init() {
                    this.loadSettings();
                    this.loadTelemetry();
                    this.loadSavedPlaylist();
                    this.loadYtVolume();
                    this.loadActiveTimerState();
                    this.loadFloatPosition();
                    this.initOfflineCache();
                    this.fetchTodaySessions();

                    this.ensurePermanentAudioHost();
                    this.$nextTick(() => this.updateVideoSlotPosition());

                    window.addEventListener('toggle-pomodoro-widget', () => this.toggleShow());

                    try {
                        const urlParams = new URLSearchParams(window.location.search);
                        if (urlParams.get('action') === 'pomodoro') {
                            this.show = true;
                        }
                    } catch (e) {}

                    window.addEventListener('resize', () => {
                        this.clampFloatPosition();
                        this.updateVideoSlotPosition();
                    });
                    window.addEventListener('scroll', () => {
                        this.updateVideoSlotPosition();
                    }, true);

                    // Global subtask synchronization
                    window.addEventListener('subtask-toggled-global', (e) => {
                        const detail = e.detail || {};
                        const task = this.activeTasks.find(t => String(t.id) === String(detail.taskId));
                        if (task && task.subtasks) {
                            const sub = task.subtasks.find(s => String(s.id) === String(detail.subTaskId));
                            if (sub) {
                                sub.is_completed = detail.is_completed;
                            }
                        }
                        if (window.pipWindow && !window.pipWindow.closed) {
                            this.updatePipContent();
                        }
                    });

                    // Online/Offline detection
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        this.syncPlaylistToServer();
                    });
                    window.addEventListener('offline', () => {
                        this.isOnline = false;
                    });

                    // Keyboard shortcuts listener (global window)
                    window.addEventListener('keydown', (e) => {
                        const tag = (e.target.tagName || '').toLowerCase();
                        if (['input', 'textarea', 'select'].includes(tag) || e.target.isContentEditable) {
                            return;
                        }

                        // Space: toggle play/pause
                        if (e.code === 'Space') {
                            e.preventDefault();
                            this.toggleTimer();
                        }
                        // Alt+P: toggle widget show/hide
                        else if (e.altKey && (e.key === 'p' || e.key === 'P')) {
                            e.preventDefault();
                            this.toggleShow();
                        }
                        // Alt+R: reset timer
                        else if (e.altKey && (e.key === 'r' || e.key === 'R')) {
                            e.preventDefault();
                            this.resetTimer();
                        }
                        // Alt+F: switch to flowtime
                        else if (e.altKey && (e.key === 'f' || e.key === 'F')) {
                            e.preventDefault();
                            this.setMode('flowtime');
                        }
                        // Alt+[ or Alt+ArrowLeft: previous song
                        else if (e.altKey && (e.key === '[' || e.key === 'ArrowLeft')) {
                            e.preventDefault();
                            this.playPrevTrack();
                        }
                        // Alt+] or Alt+ArrowRight: next song
                        else if (e.altKey && (e.key === ']' || e.key === 'ArrowRight')) {
                            e.preventDefault();
                            this.playNextTrack();
                        }
                    });

                    // Hardware Media Keys & OS Media Center Action Handlers
                    if ('mediaSession' in navigator) {
                        try {
                            navigator.mediaSession.setActionHandler('previoustrack', () => this.playPrevTrack());
                            navigator.mediaSession.setActionHandler('nexttrack', () => this.playNextTrack());
                        } catch (e) {}
                    }

                    this.$watch('show', () => {
                        this.saveActiveTimerState();
                        this.$nextTick(() => setTimeout(() => this.updateVideoSlotPosition(), 150));
                    });
                    this.$watch('showYtVideoFrame', () => {
                        this.saveActiveTimerState();
                        this.$nextTick(() => {
                            setTimeout(() => this.updateVideoSlotPosition(), 50);
                            setTimeout(() => this.updateVideoSlotPosition(), 200);
                            setTimeout(() => this.updateVideoSlotPosition(), 400);
                        });
                    });
                    this.$watch('showAudioDeck', () => {
                        this.saveActiveTimerState();
                        this.$nextTick(() => {
                            setTimeout(() => this.updateVideoSlotPosition(), 50);
                            setTimeout(() => this.updateVideoSlotPosition(), 200);
                            setTimeout(() => this.updateVideoSlotPosition(), 400);
                        });
                    });
                    this.$watch('musicTab', () => {
                        this.saveActiveTimerState();
                        this.$nextTick(() => this.updateVideoSlotPosition());
                    });
                    this.$watch('activeFolderId', () => this.saveActiveTimerState());
                    this.$watch('isYtShuffle', () => this.saveActiveTimerState());
                    this.$watch('ytLoopMode', () => this.saveActiveTimerState());
                    this.$watch('isYtAutoNext', () => this.saveActiveTimerState());
                    this.$watch('ytVolume', () => this.saveActiveTimerState());
                    this.$watch('isYtMuted', () => this.saveActiveTimerState());

                    // Livewire 3 wire:navigate persistence hook
                    document.addEventListener('livewire:navigated', () => {
                        this.fetchTodaySessions();
                        this.ensurePermanentAudioHost();
                        this.$nextTick(() => {
                            this.updateVideoSlotPosition();
                        });
                    });

                    window.addEventListener('beforeunload', () => this.saveActiveTimerState());
                    window.addEventListener('pagehide', () => this.saveActiveTimerState());

                    // Listen to YouTube Iframe postMessage events
                    window.addEventListener('message', (event) => {
                        try {
                            let data = event.data;
                            if (typeof data === 'string') {
                                try { data = JSON.parse(data); } catch (e) { return; }
                            }
                            if (!data) return;

                            if (data.event === 'onReady' || data.event === 'initialDelivery') {
                                this.sendYtCommand('addEventListener', ['onStateChange']);
                                this.sendYtListening();
                            }

                            if (data.event === 'infoDelivery' && data.info && data.info.playerState !== undefined) {
                                const state = Number(data.info.playerState);
                                if (state === 0) { // ENDED
                                    this.onYtTrackEnded();
                                } else if (state === 1) { // PLAYING
                                    this.isYtPlaying = true;
                                } else if (state === 2) { // PAUSED
                                    this.isYtPlaying = false;
                                }
                            }
                        } catch (e) {}
                    });
                },

                get currentAmbientName() {
                    const p = this.ambientPresets.find(x => x.id === this.currentAmbientPreset);
                    return p ? (p.icon + ' ' + p.name) : 'Ambient Offline';
                },

                get formattedTime() {
                    if (this.mode === 'flowtime') {
                        const totalSecs = this.flowtimeSeconds;
                        const m = Math.floor(totalSecs / 60);
                        const s = totalSecs % 60;
                        if (m >= 60) {
                            const h = Math.floor(m / 60);
                            const remM = m % 60;
                            return `${h}:${remM.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                        }
                        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                    }

                    const m = Math.floor(this.timeLeft / 60);
                    const s = this.timeLeft % 60;
                    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                },

                get modeName() {
                    if (this.mode === 'work') return 'Focus Time';
                    if (this.mode === 'flowtime') return 'Flowtime';
                    if (this.mode === 'shortBreak') return 'Short Break';
                    return 'Long Break';
                },

                get totalTimeForMode() {
                    if (this.mode === 'work') return this.settings.work * 60;
                    if (this.mode === 'flowtime') return 60 * 60;
                    if (this.mode === 'shortBreak') return this.settings.shortBreak * 60;
                    return this.settings.longBreak * 60;
                },

                get progressPercentage() {
                    if (this.mode === 'flowtime') {
                        return ((this.flowtimeSeconds % 3600) / 3600) * 100;
                    }
                    const total = this.totalTimeForMode;
                    if (total <= 0) return 0;
                    const elapsed = total - this.timeLeft;
                    return Math.min(100, Math.max(0, (elapsed / total) * 100));
                },

                get ringOffset() {
                    return 94.25 - (94.25 * this.progressPercentage / 100);
                },

                get floatPositionStyle() {
                    if (this.customX !== null && this.customY !== null) {
                        return `left: ${this.customX}px; top: ${this.customY}px; bottom: auto; right: auto;`;
                    }
                    return 'bottom: 5rem; right: 1.25rem;';
                },

                get recommendedBreakMinutes() {
                    const mins = Math.floor(this.flowtimeSeconds / 60);
                    return Math.max(3, Math.round(mins / 5));
                },

                get isTaskConnected() {
                    return Boolean(this.selectedTaskId && this.activeTasks.some(t => String(t.id) === String(this.selectedTaskId)));
                },

                get currentSelectedTask() {
                    if (!this.selectedTaskId) return null;
                    return this.activeTasks.find(t => String(t.id) === String(this.selectedTaskId)) || null;
                },

                get currentBreakTip() {
                    return this.breakTips[this.activeBreakTipIndex] || this.breakTips[0];
                },

                shuffleBreakTip() {
                    this.activeBreakTipIndex = (this.activeBreakTipIndex + 1) % this.breakTips.length;
                },

                get currentAmbientPresetObj() {
                    return this.ambientPresets.find(p => p.id === this.currentAmbientPreset) || this.ambientPresets[0];
                },

                setMode(newMode) {
                    // Cache current mode's elapsed time before pausing
                    if (this.mode === 'work') this.savedWorkTimeLeft = this.timeLeft;
                    else if (this.mode === 'shortBreak') this.savedShortBreakTimeLeft = this.timeLeft;
                    else if (this.mode === 'longBreak') this.savedLongBreakTimeLeft = this.timeLeft;

                    this.pauseTimer();
                    this.mode = newMode;
                    if (newMode === 'work') {
                        this.timeLeft = (this.savedWorkTimeLeft !== null && this.savedWorkTimeLeft > 0) 
                            ? this.savedWorkTimeLeft 
                            : (this.settings.work * 60);
                    } else if (newMode === 'flowtime') {
                        if (this.flowtimeSeconds === undefined) this.flowtimeSeconds = 0;
                    } else if (newMode === 'shortBreak') {
                        this.timeLeft = (this.savedShortBreakTimeLeft !== null && this.savedShortBreakTimeLeft > 0)
                            ? this.savedShortBreakTimeLeft
                            : (this.settings.shortBreak * 60);
                        this.shuffleBreakTip();
                    } else {
                        this.timeLeft = (this.savedLongBreakTimeLeft !== null && this.savedLongBreakTimeLeft > 0)
                            ? this.savedLongBreakTimeLeft
                            : (this.settings.longBreak * 60);
                        this.shuffleBreakTip();
                    }
                    this.saveActiveTimerState();
                },

                onTaskSelect(e) {
                    const val = e.target.value;
                    this.selectedTaskId = val ? String(val) : '';
                    const found = this.activeTasks.find(t => String(t.id) === String(val));
                    this.selectedTaskTitle = found ? found.title : '';
                    this.saveActiveTimerState();
                },

                markSelectedTaskDone() {
                    if (!this.selectedTaskId) return;
                    const taskIdToComplete = this.selectedTaskId;
                    
                    if (window.Livewire) {
                        window.Livewire.dispatch('complete-task-from-pomodoro', { taskId: taskIdToComplete });
                    }

                    // Remove task from local list
                    this.activeTasks = this.activeTasks.filter(t => String(t.id) !== String(taskIdToComplete));
                    this.selectedTaskId = '';
                    this.selectedTaskTitle = '';
                },

                // --- Finish Session Early (Progressive XP: 2.5 XP/min, min 1 min) ---
                finishSessionEarly() {
                    if (this.mode === 'flowtime') {
                        this.finishFlowtime();
                        return;
                    }

                    if (this.mode === 'shortBreak' || this.mode === 'longBreak') {
                        this.pauseTimer();
                        this.setMode('work');
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { 
                                message: 'Istirahat selesai lebih awal. Waktunya kembali fokus! 🍅', 
                                type: 'info' 
                            });
                        }
                        return;
                    }

                    // Mode: 'work'
                    this.pauseTimer();
                    const totalSeconds = (this.settings.work || 25) * 60;
                    const elapsedSeconds = Math.max(0, totalSeconds - this.timeLeft);
                    const focusMinutes = Math.floor(elapsedSeconds / 60);

                    if (focusMinutes < 1) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { 
                                message: 'Sesi fokus kurang dari 1 menit tidak mendapatkan XP (minimal 1 menit fokus).', 
                                type: 'info' 
                            });
                        }
                        this.timeLeft = totalSeconds;
                        this.saveActiveTimerState();
                        return;
                    }

                    this.playWebAudioChime();
                    this.triggerHaptic('success');

                    // Auto-pause audio if enabled
                    if (this.settings.autoPauseMusic) {
                        this.autoPauseAudio();
                    }

                    // Dispatch progressive reward to backend (2.5 XP per minute)
                    if (window.Livewire) {
                        const ambient = this.isYtPlaying ? 'youtube' : (this.isAmbientPlaying ? this.currentAmbientPreset : null);
                        window.Livewire.dispatch('pomodoro-completed', {
                            taskId: this.selectedTaskId || null,
                            minutes: focusMinutes,
                            ambientSound: ambient,
                            mode: 'work'
                        });
                    }

                    this.switchMode(focusMinutes);
                    this.fetchTodaySessions();
                    this.show = true;
                    this.saveActiveTimerState();
                },

                // --- Flowtime Actions ---
                finishFlowtime() {
                    this.pauseTimer();
                    const focusMinutes = Math.round(this.flowtimeSeconds / 60);

                    if (focusMinutes < 1) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Sesi Flowtime minimal 1 menit untuk dapat dicatat.', type: 'info' });
                        }
                        this.flowtimeSeconds = 0;
                        this.setMode('flowtime');
                        return;
                    }

                    this.playWebAudioChime();
                    this.todayFocusMinutes += focusMinutes;
                    this.todaySessionsCount++;
                    this.saveTelemetry();

                    // Auto-pause audio if enabled
                    if (this.settings.autoPauseMusic) {
                        this.autoPauseAudio();
                    }

                    // Dispatch reward to backend
                    if (window.Livewire) {
                        const ambient = this.isYtPlaying ? 'youtube' : (this.isAmbientPlaying ? this.currentAmbientPreset : null);
                        window.Livewire.dispatch('pomodoro-completed', {
                            taskId: this.selectedTaskId,
                            minutes: focusMinutes,
                            ambientSound: ambient,
                            mode: 'flowtime'
                        });
                    }

                    this.flowtimeResultMinutes = focusMinutes;
                    this.flowtimeRecommendedBreak = Math.max(3, Math.round(focusMinutes / 5));
                    this.showFlowtimeModal = true;
                    this.flowtimeSeconds = 0;
                    this.saveActiveTimerState();
                },

                startFlowtimeBreak() {
                    this.showFlowtimeModal = false;
                    this.mode = 'shortBreak';
                    this.timeLeft = this.flowtimeRecommendedBreak * 60;
                    this.saveActiveTimerState();
                    this.startTimer();
                },

                // --- Daily Session Log ---
                openDailyLog() {
                    this.showDailyLogModal = true;
                    this.fetchTodaySessions();
                },

                fetchTodaySessions() {
                    this.isLoadingDailyLog = true;
                    fetch('/api/pomodoro/today-sessions')
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.sessions) {
                                this.dailySessions = data.sessions;
                                this.dailyLogSummary = data.summary || {
                                    total_sessions: data.sessions.length,
                                    total_minutes: this.todayFocusMinutes,
                                    total_xp: 0,
                                    total_coins: 0
                                };
                                if (data.summary) {
                                    this.todaySessionsCount = Number(data.summary.total_sessions) || 0;
                                    this.todayFocusMinutes = Number(data.summary.total_minutes) || 0;
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Failed to load today sessions:', err);
                        })
                        .finally(() => {
                            this.isLoadingDailyLog = false;
                        });
                },

                // --- Audio / Auto-Pause Controls ---
                autoPauseAudio() {
                    if (this.isYtPlaying) {
                        this.toggleYtPlayback();
                    }
                    if (this.isAmbientPlaying) {
                        this.toggleAmbientPlayback();
                    }
                },

                // Web Audio API Dual-Tone Chime (Reliable, 100% Offline)
                playWebAudioChime() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        const ctx = new AudioCtx();
                        const now = ctx.currentTime;

                        // Tone 1: 587.33 Hz (D5)
                        const osc1 = ctx.createOscillator();
                        const gain1 = ctx.createGain();
                        osc1.type = 'sine';
                        osc1.frequency.setValueAtTime(587.33, now);
                        gain1.gain.setValueAtTime(0.3, now);
                        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
                        osc1.connect(gain1);
                        gain1.connect(ctx.destination);
                        osc1.start(now);
                        osc1.stop(now + 0.5);

                        // Tone 2: 880 Hz (A5)
                        const osc2 = ctx.createOscillator();
                        const gain2 = ctx.createGain();
                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(880, now + 0.22);
                        gain2.gain.setValueAtTime(0.35, now + 0.22);
                        gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.1);
                        osc2.connect(gain2);
                        gain2.connect(ctx.destination);
                        osc2.start(now + 0.22);
                        osc2.stop(now + 1.1);
                    } catch (e) {
                        console.warn('Web Audio chime error:', e);
                    }
                },

                // Procedural Ambient Synthesizer Engine (0 KB Server, 100% Offline)
                selectAmbientPreset(presetId) {
                    this.currentAmbientPreset = presetId;
                    if (this.isAmbientPlaying) {
                        this.stopAmbientSynth();
                        this.startAmbientSynth();
                    }
                },

                toggleAmbientPlayback() {
                    if (this.isAmbientPlaying) {
                        this.stopAmbientSynth();
                        this.isAmbientPlaying = false;
                    } else {
                        // Pause YT if playing
                        if (this.isYtPlaying) this.toggleYtPlayback();
                        this.startAmbientSynth();
                        this.isAmbientPlaying = true;
                    }
                    this.saveActiveTimerState();
                },

                startAmbientSynth() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        if (!window.atomaAmbientAudioCtx || window.atomaAmbientAudioCtx.state === 'closed') {
                            window.atomaAmbientAudioCtx = new AudioCtx();
                        }
                        this.ambientAudioCtx = window.atomaAmbientAudioCtx;
                        const ctx = this.ambientAudioCtx;
                        if (ctx.state === 'suspended') {
                            ctx.resume();
                        }

                        this.stopAmbientSynth();

                        const masterGain = ctx.createGain();
                        masterGain.gain.setValueAtTime((this.ambientVolume / 100) * 0.4, ctx.currentTime);
                        masterGain.connect(ctx.destination);
                        this.ambientGainNode = masterGain;

                        const preset = this.currentAmbientPreset;

                        if (preset === 'rain') {
                            const buffer = this.generatePinkNoiseBuffer(ctx, 4);
                            const src = ctx.createBufferSource();
                            src.buffer = buffer;
                            src.loop = true;

                            const filter = ctx.createBiquadFilter();
                            filter.type = 'lowpass';
                            filter.frequency.setValueAtTime(1100, ctx.currentTime);

                            src.connect(filter);
                            filter.connect(masterGain);
                            src.start();
                            this.ambientSourceNodes.push(src);
                        } else if (preset === 'ocean') {
                            const buffer = this.generatePinkNoiseBuffer(ctx, 6);
                            const src = ctx.createBufferSource();
                            src.buffer = buffer;
                            src.loop = true;

                            const filter = ctx.createBiquadFilter();
                            filter.type = 'lowpass';
                            filter.frequency.setValueAtTime(800, ctx.currentTime);

                            const swellGain = ctx.createGain();
                            swellGain.gain.setValueAtTime(0.4, ctx.currentTime);

                            const lfo = ctx.createOscillator();
                            lfo.frequency.setValueAtTime(0.1, ctx.currentTime);
                            const lfoGain = ctx.createGain();
                            lfoGain.gain.setValueAtTime(0.3, ctx.currentTime);
                            lfo.connect(lfoGain);
                            lfoGain.connect(swellGain.gain);

                            src.connect(filter);
                            filter.connect(swellGain);
                            swellGain.connect(masterGain);

                            src.start();
                            lfo.start();
                            this.ambientSourceNodes.push(src, lfo);
                        } else if (preset === 'cafe') {
                            const buffer = this.generateBrownNoiseBuffer(ctx, 4);
                            const src = ctx.createBufferSource();
                            src.buffer = buffer;
                            src.loop = true;

                            const filter = ctx.createBiquadFilter();
                            filter.type = 'lowpass';
                            filter.frequency.setValueAtTime(550, ctx.currentTime);

                            src.connect(filter);
                            filter.connect(masterGain);
                            src.start();
                            this.ambientSourceNodes.push(src);
                        } else if (preset === 'forest') {
                            // Gentle woodland wind & canopy breeze (calm forest atmosphere, not rain)
                            const buffer = this.generateBrownNoiseBuffer(ctx, 5);
                            const src = ctx.createBufferSource();
                            src.buffer = buffer;
                            src.loop = true;

                            // Lowpass filter modulated by very slow, soothing wind gusts (0.1Hz)
                            const filter = ctx.createBiquadFilter();
                            filter.type = 'lowpass';
                            filter.frequency.setValueAtTime(260, ctx.currentTime);

                            const windLfo = ctx.createOscillator();
                            windLfo.type = 'sine';
                            windLfo.frequency.setValueAtTime(0.09, ctx.currentTime);

                            const windLfoGain = ctx.createGain();
                            windLfoGain.gain.setValueAtTime(120, ctx.currentTime);
                            windLfo.connect(windLfoGain);
                            windLfoGain.connect(filter.frequency);

                            // Soft gentle high-frequency canopy leaf whisper
                            const leafBuffer = this.generatePinkNoiseBuffer(ctx, 4);
                            const leafSrc = ctx.createBufferSource();
                            leafSrc.buffer = leafBuffer;
                            leafSrc.loop = true;
                            const leafFilter = ctx.createBiquadFilter();
                            leafFilter.type = 'bandpass';
                            leafFilter.frequency.setValueAtTime(1600, ctx.currentTime);
                            leafFilter.Q.setValueAtTime(1.5, ctx.currentTime);
                            const leafGain = ctx.createGain();
                            leafGain.gain.setValueAtTime(0.07, ctx.currentTime);
                            leafSrc.connect(leafFilter);
                            leafFilter.connect(leafGain);
                            leafGain.connect(masterGain);

                            src.connect(filter);
                            filter.connect(masterGain);

                            src.start();
                            windLfo.start();
                            leafSrc.start();
                            this.ambientSourceNodes.push(src, windLfo, leafSrc);
                        } else if (preset === 'alpha') {
                            const carrier = ctx.createOscillator();
                            carrier.type = 'sine';
                            carrier.frequency.setValueAtTime(200, ctx.currentTime);

                            const tremoloGain = ctx.createGain();
                            tremoloGain.gain.setValueAtTime(0.5, ctx.currentTime);

                            const lfo = ctx.createOscillator();
                            lfo.type = 'sine';
                            lfo.frequency.setValueAtTime(40, ctx.currentTime);

                            const lfoAmp = ctx.createGain();
                            lfoAmp.gain.setValueAtTime(0.4, ctx.currentTime);
                            lfo.connect(lfoAmp);
                            lfoAmp.connect(tremoloGain.gain);

                            carrier.connect(tremoloGain);
                            tremoloGain.connect(masterGain);

                            carrier.start();
                            lfo.start();
                            this.ambientSourceNodes.push(carrier, lfo);
                        }
                    } catch (e) {
                        console.error('Ambient synth start error:', e);
                    }
                },

                stopAmbientSynth() {
                    this.ambientSourceNodes.forEach(node => {
                        try { node.stop(); node.disconnect(); } catch (e) {}
                    });
                    this.ambientSourceNodes = [];
                    if (window.atomaAmbientSourceNodes) {
                        window.atomaAmbientSourceNodes.forEach(node => {
                            try { node.stop(); node.disconnect(); } catch (e) {}
                        });
                        window.atomaAmbientSourceNodes = [];
                    }
                },

                updateAmbientVolume() {
                    if (this.ambientGainNode && this.ambientAudioCtx) {
                        this.ambientGainNode.gain.setValueAtTime((this.ambientVolume / 100) * 0.4, this.ambientAudioCtx.currentTime);
                    }
                },

                generatePinkNoiseBuffer(ctx, duration = 4) {
                    const bufferSize = ctx.sampleRate * duration;
                    const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
                    const data = buffer.getChannelData(0);
                    let b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0;
                    for (let i = 0; i < bufferSize; i++) {
                        const white = Math.random() * 2 - 1;
                        b0 = 0.99886 * b0 + white * 0.0555179;
                        b1 = 0.99332 * b1 + white * 0.0750759;
                        b2 = 0.96900 * b2 + white * 0.1538520;
                        b3 = 0.86650 * b3 + white * 0.3104856;
                        b4 = 0.55000 * b4 + white * 0.5329522;
                        b5 = -0.7616 * b5 + white * 0.0168980;
                        data[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + white * 0.5362) * 0.11;
                        b6 = white * 0.115926;
                    }
                    return buffer;
                },

                generateBrownNoiseBuffer(ctx, duration = 4) {
                    const bufferSize = ctx.sampleRate * duration;
                    const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
                    const data = buffer.getChannelData(0);
                    let lastOut = 0.0;
                    for (let i = 0; i < bufferSize; i++) {
                        const white = Math.random() * 2 - 1;
                        data[i] = (lastOut + (0.02 * white)) / 1.02;
                        lastOut = data[i];
                        data[i] *= 3.5;
                    }
                    return buffer;
                },

                // Spotify-Style Offline Cache Management
                async initOfflineCache() {
                    try {
                        if ('caches' in window) {
                            await caches.open('atoma-offline-audio-v1');
                        }
                        this.updateCacheStorageMetric();
                    } catch (e) {}
                },

                async updateCacheStorageMetric() {
                    if ('storage' in navigator && 'estimate' in navigator.storage) {
                        try {
                            const estimate = await navigator.storage.estimate();
                            const usageBytes = estimate.usage || 0;
                            if (usageBytes < 1024 * 1024) {
                                this.cacheSizeText = (usageBytes / 1024).toFixed(1) + ' KB';
                            } else {
                                this.cacheSizeText = (usageBytes / (1024 * 1024)).toFixed(1) + ' MB';
                            }
                        } catch (e) {
                            this.cacheSizeText = 'Tersedia';
                        }
                    }
                },

                async clearAudioCache() {
                    try {
                        if ('caches' in window) {
                            await caches.delete('atoma-offline-audio-v1');
                            await caches.open('atoma-offline-audio-v1');
                        }
                        this.cacheSizeText = '0 KB';
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Cache offline audio berhasil dibersihkan!', type: 'success' });
                        }
                    } catch (e) {
                        console.error('Failed to clear cache:', e);
                    }
                },

                // --- YouTube Controls & Persistent Audio Portal ---
                ensurePermanentAudioHost() {
                    // Clean up any stale host from old implementations if present in body
                    const stale = document.getElementById('atoma-global-yt-host');
                    if (stale) stale.remove();

                    let iframe = document.getElementById('atomaYtIframe');
                    if (!iframe) {
                        let portal = document.getElementById('atoma-permanent-audio-portal');
                        if (!portal) {
                            portal = document.createElement('div');
                            portal.id = 'atoma-permanent-audio-portal';
                            portal.style.cssText = 'position: fixed; z-index: 99998; pointer-events: none;';
                            document.documentElement.appendChild(portal);
                        }
                        
                        iframe = document.createElement('iframe');
                        iframe.id = 'atomaYtIframe';
                        iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
                        iframe.setAttribute('allowfullscreen', 'true');
                        iframe.style.cssText = 'position: fixed; left: -9999px; bottom: -9999px; width: 1px; height: 1px; opacity: 0.001; pointer-events: none; border: 0;';
                        
                        iframe.addEventListener('load', () => {
                            this.sendYtListening();
                            setTimeout(() => this.updateYtVolume(), 600);
                        });
                        
                        portal.appendChild(iframe);
                    }

                    if (!iframe.src || iframe.src === 'about:blank' || !iframe.src.includes('youtube.com')) {
                        if (this.ytEmbedUrl) {
                            iframe.src = this.ytEmbedUrl;
                        }
                    }
                    
                    return iframe;
                },

                updateVideoSlotPosition() {
                    const iframe = document.getElementById('atomaYtIframe');
                    if (!iframe) return;
                    
                    const slot = document.getElementById('atoma-yt-video-slot');
                    if (this.show && this.showAudioDeck && this.showYtVideoFrame && this.musicTab === 'ytmusic' && slot) {
                        const rect = slot.getBoundingClientRect();
                        if (rect.width > 30 && rect.height > 30 && rect.bottom > 0 && rect.top < window.innerHeight) {
                            iframe.style.position = 'fixed';
                            iframe.style.left = `${Math.round(rect.left)}px`;
                            iframe.style.top = `${Math.round(rect.top)}px`;
                            iframe.style.width = `${Math.round(rect.width)}px`;
                            iframe.style.height = `${Math.round(rect.height)}px`;
                            iframe.style.opacity = '1';
                            iframe.style.pointerEvents = 'auto';
                            iframe.style.zIndex = '10001';
                            iframe.style.borderRadius = '1rem';
                            iframe.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.3)';
                            iframe.style.transition = 'none';
                            return;
                        }
                    }
                    
                    // Park off-screen (never display:none so background audio continues)
                    iframe.style.position = 'fixed';
                    iframe.style.left = '-9999px';
                    iframe.style.bottom = '-9999px';
                    iframe.style.top = 'auto';
                    iframe.style.width = '1px';
                    iframe.style.height = '1px';
                    iframe.style.opacity = '0.001';
                    iframe.style.pointerEvents = 'none';
                    iframe.style.zIndex = '-1';
                    iframe.style.borderRadius = '0';
                    iframe.style.boxShadow = 'none';
                },

                sendYtListening() {
                    const iframe = this.ensurePermanentAudioHost();
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage(JSON.stringify({
                            event: 'listening',
                            id: 1,
                            channel: 'widget'
                        }), '*');
                    }
                },

                loadYtVideo(videoId, title, author, thumbnail) {
                    if (!this.isOnline) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Sedang offline — beralih otomatis ke Ambient Offline.', type: 'info' });
                        }
                        this.musicTab = 'ambient';
                        if (!this.isAmbientPlaying) {
                            this.toggleAmbientPlayback();
                        }
                        return;
                    }

                    if (this.isAmbientPlaying) {
                        this.toggleAmbientPlayback();
                    }

                    this.ytVideoId = videoId;
                    this.ytTitle = title || 'YouTube Track';
                    this.ytAuthor = author || 'YouTube';
                    this.ytThumbnail = thumbnail || `https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`;
                    this.ytEmbedUrl = `https://www.youtube.com/embed/${videoId}?enablejsapi=1&autoplay=1&origin=${encodeURIComponent(window.location.origin)}`;

                    const iframe = this.ensurePermanentAudioHost();
                    if (iframe) {
                        iframe.src = this.ytEmbedUrl;
                    }
                    this.isYtPlaying = true;
                    this.saveActiveTimerState();

                    if (window.Livewire && title) {
                        window.Livewire.dispatch('toast', { message: `🎵 ${title}`, type: 'info' });
                    }
                    if ('mediaSession' in navigator) {
                        try {
                            navigator.mediaSession.metadata = new MediaMetadata({
                                title: this.ytTitle,
                                artist: this.ytAuthor,
                                album: 'ATOMA Pomodoro Music',
                                artwork: [{ src: this.ytThumbnail, sizes: '512x512', type: 'image/jpeg' }]
                            });
                        } catch (e) {}
                    }

                    this.$nextTick(() => {
                        this.updateVideoSlotPosition();
                    });

                    // Re-establish API listening and volume sync
                    setTimeout(() => this.sendYtListening(), 600);
                    setTimeout(() => this.updateYtVolume(), 1200);
                    setTimeout(() => this.sendYtListening(), 2000);
                },

                playYtQuery() {
                    if (!this.ytQuery.trim()) return;
                    if (!this.isOnline) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Pencarian YouTube membutuhkan koneksi internet.', type: 'error' });
                        }
                        return;
                    }
                    this.isYtSearching = true;
                    fetch('/api/youtube/search?q=' + encodeURIComponent(this.ytQuery))
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.video_id) {
                                this.loadYtVideo(data.video_id, data.title, data.author, data.thumbnail);
                                this.ytResults = data.results || [];
                            }
                        })
                        .catch(err => {
                            console.error('YouTube search error:', err);
                        })
                        .finally(() => {
                            this.isYtSearching = false;
                        });
                },

                sendYtCommand(func, args = []) {
                    const iframe = this.ensurePermanentAudioHost();
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage(JSON.stringify({
                            event: 'command',
                            func: func,
                            args: args
                        }), '*');
                    }
                },

                toggleYtPlayback() {
                    if (!this.isOnline && !this.isYtPlaying) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'YouTube tidak dapat diputar saat offline. Membuka Ambient Offline.', type: 'info' });
                        }
                        this.musicTab = 'ambient';
                        if (!this.isAmbientPlaying) this.toggleAmbientPlayback();
                        return;
                    }

                    const iframe = this.ensurePermanentAudioHost();

                    if (this.isYtPlaying) {
                        this.sendYtCommand('pauseVideo');
                        this.isYtPlaying = false;
                    } else {
                        if (this.isAmbientPlaying) this.toggleAmbientPlayback();
                        if (iframe && (!iframe.src || iframe.src === 'about:blank' || !iframe.src.includes(this.ytVideoId))) {
                            iframe.src = this.ytEmbedUrl;
                            setTimeout(() => this.sendYtListening(), 600);
                            setTimeout(() => this.updateYtVolume(), 1200);
                        } else {
                            this.sendYtCommand('playVideo');
                        }
                        this.isYtPlaying = true;
                    }
                    this.saveActiveTimerState();
                    this.$nextTick(() => this.updateVideoSlotPosition());
                },

                updateYtVolume() {
                    this.isYtMuted = (this.ytVolume === 0);
                    this.sendYtCommand('unMute');
                    this.sendYtCommand('setVolume', [this.ytVolume]);
                    localStorage.setItem('atoma_yt_volume', this.ytVolume);
                },

                toggleYtMute() {
                    this.isYtMuted = !this.isYtMuted;
                    if (this.isYtMuted) {
                        this.sendYtCommand('mute');
                    } else {
                        if (this.ytVolume === 0) {
                            this.ytVolume = 50;
                            localStorage.setItem('atoma_yt_volume', 50);
                        }
                        this.sendYtCommand('unMute');
                        this.sendYtCommand('setVolume', [this.ytVolume]);
                    }
                    this.saveActiveTimerState();
                },

                loadYtVolume() {
                    const saved = localStorage.getItem('atoma_yt_volume');
                    if (saved !== null) {
                        this.ytVolume = parseInt(saved) || 80;
                    }
                },

                get activeFolder() {
                    return this.playlistFolders.find(f => f.id === this.activeFolderId) || this.playlistFolders[0] || { id: 'default', name: 'Favorit', icon: '📁', tracks: [] };
                },

                get currentFolderTracks() {
                    if (this.activeFolder && this.activeFolder.tracks && this.activeFolder.tracks.length > 0) {
                        if (!this.ytVideoId || this.activeFolder.tracks.some(t => t.video_id === this.ytVideoId)) {
                            return this.activeFolder.tracks;
                        }
                    }
                    if (this.ytVideoId) {
                        const folderWithTrack = this.playlistFolders.find(f => f.tracks && f.tracks.some(t => t.video_id === this.ytVideoId));
                        if (folderWithTrack && folderWithTrack.tracks && folderWithTrack.tracks.length > 0) {
                            return folderWithTrack.tracks;
                        }
                    }
                    if (this.activeFolder && this.activeFolder.tracks && this.activeFolder.tracks.length > 0) {
                        return this.activeFolder.tracks;
                    }
                    const anyFolder = this.playlistFolders.find(f => f.tracks && f.tracks.length > 0);
                    return anyFolder ? anyFolder.tracks : [];
                },

                playNextYtTrack(isAuto = false) {
                    const tracks = this.currentFolderTracks;
                    if (!tracks || tracks.length === 0) {
                        if (this.ytResults && this.ytResults.length > 1) {
                            const curIdx = this.ytResults.findIndex(r => r.video_id === this.ytVideoId);
                            const nextIdx = (curIdx + 1) % this.ytResults.length;
                            const next = this.ytResults[nextIdx];
                            this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                        }
                        return;
                    }

                    if (tracks.length === 1) {
                        this.sendYtCommand('seekTo', [0, true]);
                        this.sendYtCommand('playVideo');
                        this.isYtPlaying = true;
                        return;
                    }

                    if (this.isYtShuffle) {
                        // Exclude current track when possible to avoid repeating the exact same track
                        const candidates = tracks.filter(t => t.video_id !== this.ytVideoId);
                        const pool = candidates.length > 0 ? candidates : tracks;
                        const randIdx = Math.floor(Math.random() * pool.length);
                        const next = pool[randIdx];
                        this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                        return;
                    }

                    const curIdx = tracks.findIndex(item => item.video_id === this.ytVideoId);
                    if (curIdx === -1) {
                        const next = tracks[0];
                        this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                    } else if (curIdx + 1 < tracks.length) {
                        const next = tracks[curIdx + 1];
                        this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                    } else {
                        // Reached end of playlist
                        if (!isAuto || this.ytLoopMode === 'all' || this.isYtAutoNext) {
                            const next = tracks[0];
                            this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                        } else {
                            this.isYtPlaying = false;
                        }
                    }
                },

                playPrevYtTrack() {
                    const tracks = this.currentFolderTracks;
                    if (!tracks || tracks.length === 0) {
                        if (this.ytResults && this.ytResults.length > 1) {
                            const curIdx = this.ytResults.findIndex(r => r.video_id === this.ytVideoId);
                            const prevIdx = (curIdx - 1 + this.ytResults.length) % this.ytResults.length;
                            const prev = this.ytResults[prevIdx];
                            this.loadYtVideo(prev.video_id, prev.title, prev.author, prev.thumbnail);
                        }
                        return;
                    }

                    if (tracks.length === 1) {
                        this.sendYtCommand('seekTo', [0, true]);
                        this.sendYtCommand('playVideo');
                        this.isYtPlaying = true;
                        return;
                    }

                    if (this.isYtShuffle) {
                        const candidates = tracks.filter(t => t.video_id !== this.ytVideoId);
                        const pool = candidates.length > 0 ? candidates : tracks;
                        const randIdx = Math.floor(Math.random() * pool.length);
                        const prev = pool[randIdx];
                        this.loadYtVideo(prev.video_id, prev.title, prev.author, prev.thumbnail);
                        return;
                    }

                    const curIdx = tracks.findIndex(item => item.video_id === this.ytVideoId);
                    if (curIdx > 0) {
                        const prev = tracks[curIdx - 1];
                        this.loadYtVideo(prev.video_id, prev.title, prev.author, prev.thumbnail);
                    } else {
                        const prev = tracks[tracks.length - 1];
                        this.loadYtVideo(prev.video_id, prev.title, prev.author, prev.thumbnail);
                    }
                },

                // --- Universal Audio Track Navigation (Float & PiP & Hardware Media Keys) ---
                playNextTrack() {
                    this.triggerHaptic('light');
                    if (this.isYtPlaying || (this.ytVideoId && !this.isAmbientPlaying)) {
                        this.playNextYtTrack();
                    } else if (this.isAmbientPlaying) {
                        const presets = this.ambientPresets;
                        const curIdx = presets.findIndex(p => p.id === this.currentAmbientPreset);
                        const nextIdx = (curIdx + 1) % presets.length;
                        this.selectAmbientPreset(presets[nextIdx].id);
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { 
                                message: `Suara: ${presets[nextIdx].icon} ${presets[nextIdx].name}`, 
                                type: 'info' 
                            });
                        }
                    } else if (this.currentFolderTracks && this.currentFolderTracks.length > 0) {
                        this.playNextYtTrack();
                    }
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                    this.saveActiveTimerState();
                },

                playPrevTrack() {
                    this.triggerHaptic('light');
                    if (this.isYtPlaying || (this.ytVideoId && !this.isAmbientPlaying)) {
                        this.playPrevYtTrack();
                    } else if (this.isAmbientPlaying) {
                        const presets = this.ambientPresets;
                        const curIdx = presets.findIndex(p => p.id === this.currentAmbientPreset);
                        const prevIdx = (curIdx - 1 + presets.length) % presets.length;
                        this.selectAmbientPreset(presets[prevIdx].id);
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { 
                                message: `Suara: ${presets[prevIdx].icon} ${presets[prevIdx].name}`, 
                                type: 'info' 
                            });
                        }
                    } else if (this.currentFolderTracks && this.currentFolderTracks.length > 0) {
                        this.playPrevYtTrack();
                    }
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                    this.saveActiveTimerState();
                },

                cycleYtLoop() {
                    if (this.ytLoopMode === 'off') {
                        this.ytLoopMode = 'all';
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Loop Seluruh Playlist Aktif', type: 'info' });
                        }
                    } else if (this.ytLoopMode === 'all') {
                        this.ytLoopMode = 'single';
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Ulang 1 Lagu (Repeat 1) Aktif', type: 'info' });
                        }
                    } else {
                        this.ytLoopMode = 'off';
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Loop Dinonaktifkan', type: 'info' });
                        }
                    }
                    this.saveActiveTimerState();
                },

                toggleYtShuffle() {
                    this.isYtShuffle = !this.isYtShuffle;
                    if (window.Livewire) {
                        window.Livewire.dispatch('toast', { 
                            message: this.isYtShuffle ? 'Acak Lagu (Shuffle) Aktif' : 'Acak Lagu (Shuffle) Mati', 
                            type: 'info' 
                        });
                    }
                    this.saveActiveTimerState();
                },

                toggleYtAutoNext() {
                    this.isYtAutoNext = !this.isYtAutoNext;
                    if (window.Livewire) {
                        window.Livewire.dispatch('toast', { 
                            message: this.isYtAutoNext ? 'Auto-Next Aktif' : 'Auto-Next Mati', 
                            type: 'info' 
                        });
                    }
                    this.saveActiveTimerState();
                },

                onYtTrackEnded() {
                    if (this.ytLoopMode === 'single') {
                        // Smoothly restart track from 0 without re-mounting iframe
                        this.sendYtCommand('seekTo', [0, true]);
                        this.sendYtCommand('playVideo');
                        this.isYtPlaying = true;
                    } else if (this.isYtAutoNext || this.ytLoopMode === 'all') {
                        this.playNextYtTrack(true);
                    } else {
                        this.isYtPlaying = false;
                    }
                    this.saveActiveTimerState();
                },

                get isCurrentTrackSaved() {
                    return this.playlistFolders.some(f => f.tracks && f.tracks.some(t => t.video_id === this.ytVideoId));
                },

                openSaveToPlaylistModal(videoId, title, author, thumbnail) {
                    this.targetTrackToSave = {
                        video_id: videoId || this.ytVideoId,
                        title: title || this.ytTitle,
                        author: author || this.ytAuthor,
                        thumbnail: thumbnail || this.ytThumbnail
                    };
                    this.newFolderName = '';
                    this.showPlaylistModal = true;
                },

                isTrackInFolder(folderId, videoId) {
                    const folder = this.playlistFolders.find(f => f.id === folderId);
                    return folder && folder.tracks ? folder.tracks.some(t => t.video_id === videoId) : false;
                },

                toggleTrackInFolder(folderId, track) {
                    if (!track || !track.video_id) return;
                    if (this.isTrackInFolder(folderId, track.video_id)) {
                        this.removeTrackFromFolder(folderId, track.video_id);
                    } else {
                        this.addTrackToFolder(folderId, track);
                    }
                },

                addTrackToFolder(folderId, track) {
                    const folder = this.playlistFolders.find(f => f.id === folderId);
                    if (!folder) return;
                    if (!folder.tracks) folder.tracks = [];
                    if (!folder.tracks.some(t => t.video_id === track.video_id)) {
                        folder.tracks.unshift({
                            video_id: track.video_id,
                            title: track.title || 'YouTube Track',
                            author: track.author || 'YouTube',
                            thumbnail: track.thumbnail || `https://i.ytimg.com/vi/${track.video_id}/hqdefault.jpg`
                        });
                        this.saveSavedPlaylist();
                    }
                },

                removeTrackFromFolder(folderId, videoId) {
                    const folder = this.playlistFolders.find(f => f.id === folderId);
                    if (!folder || !folder.tracks) return;
                    folder.tracks = folder.tracks.filter(t => t.video_id !== videoId);
                    this.saveSavedPlaylist();
                },

                createFolderFromModal() {
                    const name = this.newFolderName.trim();
                    if (!name) return;

                    if (!this.isPro && this.playlistFolders.length >= this.maxFolders) {
                        window.Livewire?.dispatch('toast', { 
                            message: 'Batas Akun Free: Maksimal 2 folder playlist YouTube. Upgrade ke ATOMA PRO untuk membuat folder tanpa batas!', 
                            type: 'warning' 
                        });
                        window.dispatchEvent(new CustomEvent('open-pro-modal'));
                        return;
                    }

                    const icons = ['📁', '🎧', '🧠', '🎹', '☕', '⚡', '🌌', '🌧️'];
                    const randIcon = icons[Math.floor(Math.random() * icons.length)];
                    const newFolder = {
                        id: 'folder_' + Date.now(),
                        name: name,
                        icon: randIcon,
                        tracks: []
                    };

                    if (this.targetTrackToSave && this.targetTrackToSave.video_id) {
                        newFolder.tracks.push({
                            video_id: this.targetTrackToSave.video_id,
                            title: this.targetTrackToSave.title || 'YouTube Track',
                            author: this.targetTrackToSave.author || 'YouTube',
                            thumbnail: this.targetTrackToSave.thumbnail || `https://i.ytimg.com/vi/${this.targetTrackToSave.video_id}/hqdefault.jpg`
                        });
                    }

                    this.playlistFolders.push(newFolder);
                    this.activeFolderId = newFolder.id;
                    this.newFolderName = '';
                    this.saveSavedPlaylist();
                },

                deleteFolder(folderId) {
                    if (this.playlistFolders.length <= 1) return;
                    this.playlistFolders = this.playlistFolders.filter(f => f.id !== folderId);
                    if (this.activeFolderId === folderId) {
                        this.activeFolderId = this.playlistFolders[0].id;
                    }
                    this.saveSavedPlaylist();
                },

                startFocusTask(detail) {
                    if (!detail) return;
                    const taskId = detail.taskId ? String(detail.taskId) : '';
                    if (taskId) {
                        this.selectedTaskId = taskId;
                        this.selectedTaskTitle = detail.taskTitle || '';
                        if (!this.activeTasks.some(t => String(t.id) === taskId)) {
                            this.activeTasks.push({
                                id: taskId,
                                title: detail.taskTitle || 'Tugas',
                                estimated_sessions: 1,
                                completed_sessions: 0,
                                estimated_minutes: 25
                            });
                        }
                    } else {
                        this.selectedTaskId = '';
                        this.selectedTaskTitle = '';
                    }
                    this.show = true;
                    this.mode = 'work';
                    this.timeLeft = this.settings.work * 60;
                    this.saveActiveTimerState();
                },

                startEditFolder(folder) {
                    if (!folder) return;
                    this.editingFolderId = folder.id;
                    this.editFolderName = folder.name;
                    this.editFolderIcon = folder.icon || '📁';
                },

                saveFolderRename() {
                    if (!this.editingFolderId) return;
                    const name = this.editFolderName.trim();
                    if (!name) return;
                    const folder = this.playlistFolders.find(f => f.id === this.editingFolderId);
                    if (folder) {
                        folder.name = name;
                        folder.icon = this.editFolderIcon || '📁';
                        this.saveSavedPlaylist();
                        this.saveActiveTimerState();
                    }
                    this.editingFolderId = null;
                },

                cancelEditFolder() {
                    this.editingFolderId = null;
                },

                saveSavedPlaylist() {
                    localStorage.setItem('atoma_yt_playlist_folders', JSON.stringify(this.playlistFolders));
                    this.syncPlaylistToServer();
                },

                syncPlaylistToServer() {
                    if (!this.isOnline) return;
                    fetch('/api/pomodoro/playlists', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ folders: this.playlistFolders })
                    }).catch(e => console.log('Playlist sync error', e));
                },

                loadSavedPlaylist() {
                    const savedFolders = localStorage.getItem('atoma_yt_playlist_folders');
                    if (savedFolders) {
                        try {
                            this.playlistFolders = JSON.parse(savedFolders);
                            if (!this.isPro && this.playlistFolders.length > this.maxFolders) {
                                this.playlistFolders = this.playlistFolders.slice(0, this.maxFolders);
                            }
                            if (this.playlistFolders.length > 0) {
                                this.activeFolderId = this.playlistFolders[0].id;
                            }
                        } catch (e) {
                            this.playlistFolders = [];
                        }
                    } else {
                        if (this.isOnline) {
                            fetch('/api/pomodoro/playlists')
                                .then(res => res.json())
                                .then(data => {
                                    if (data && data.folders && data.folders.length > 0) {
                                        let loaded = data.folders;
                                        if (!this.isPro && loaded.length > this.maxFolders) {
                                            loaded = loaded.slice(0, this.maxFolders);
                                        }
                                        this.playlistFolders = loaded;
                                        this.activeFolderId = this.playlistFolders[0].id;
                                        localStorage.setItem('atoma_yt_playlist_folders', JSON.stringify(this.playlistFolders));
                                    }
                                }).catch(() => {});
                        }

                        const legacySaved = localStorage.getItem('atoma_yt_saved_playlist');
                        let legacyTracks = [];
                        if (legacySaved) {
                            try { legacyTracks = JSON.parse(legacySaved); } catch (e) {}
                        }

                        this.playlistFolders = [
                            {
                                id: 'focus',
                                name: 'Deep Focus',
                                icon: '🧠',
                                tracks: legacyTracks.length > 0 ? legacyTracks : [
                                    { video_id: '5qap5aO4i9A', title: 'Lofi Beats - Deep Focus', author: 'Lofi Girl', thumbnail: 'https://i.ytimg.com/vi/5qap5aO4i9A/hqdefault.jpg' },
                                    { video_id: '2OEL4P1rub0', title: 'Peaceful Piano Focus', author: 'Piano Relaxation', thumbnail: 'https://i.ytimg.com/vi/2OEL4P1rub0/hqdefault.jpg' }
                                ]
                            },
                            {
                                id: 'chill',
                                name: 'Chill & Relax',
                                icon: '☕',
                                tracks: [
                                    { video_id: '4xDzrJKXOOY', title: 'Rain & Ambient Beats', author: 'Ambient Sound', thumbnail: 'https://i.ytimg.com/vi/4xDzrJKXOOY/hqdefault.jpg' }
                                ]
                            }
                        ];
                        this.activeFolderId = 'focus';
                        this.saveSavedPlaylist();
                    }
                },

                toggleShow() {
                    this.show = !this.show;
                    this.saveActiveTimerState();
                },

                closeWidget() {
                    this.show = false;
                    this.saveActiveTimerState();
                },

                saveActiveTimerState() {
                    const state = {
                        savedDate: new Date().toLocaleDateString('en-CA'),
                        show: this.show,
                        mode: this.mode,
                        isRunning: this.isRunning,
                        timeLeft: this.timeLeft,
                        flowtimeSeconds: this.flowtimeSeconds,
                        targetEndTime: (this.isRunning && this.mode !== 'flowtime') ? (Date.now() + this.timeLeft * 1000) : null,
                        sessionCount: this.sessionCount,
                        selectedTaskId: this.selectedTaskId,
                        selectedTaskTitle: this.selectedTaskTitle,
                        showAudioDeck: this.showAudioDeck,
                        showYtVideoFrame: this.showYtVideoFrame,
                        musicTab: this.musicTab,
                        activeFolderId: this.activeFolderId,
                        ytVideoId: this.ytVideoId,
                        ytTitle: this.ytTitle,
                        ytAuthor: this.ytAuthor,
                        ytThumbnail: this.ytThumbnail,
                        isYtPlaying: this.isYtPlaying,
                        isAmbientPlaying: this.isAmbientPlaying,
                        currentAmbientPreset: this.currentAmbientPreset,
                        isYtShuffle: this.isYtShuffle,
                        ytLoopMode: this.ytLoopMode,
                        isYtAutoNext: this.isYtAutoNext,
                        ytVolume: this.ytVolume,
                        isYtMuted: this.isYtMuted
                    };
                    try {
                        localStorage.setItem('atoma_pomodoro_active_state', JSON.stringify(state));
                    } catch (e) {}
                },

                loadActiveTimerState() {
                    const saved = localStorage.getItem('atoma_pomodoro_active_state');
                    if (!saved) {
                        this.timeLeft = this.settings.work * 60;
                        return;
                    }
                    try {
                        const state = JSON.parse(saved);
                        if (state.show !== undefined) this.show = Boolean(state.show);
                        if (state.mode) this.mode = state.mode;
                        if (state.flowtimeSeconds !== undefined) this.flowtimeSeconds = Number(state.flowtimeSeconds);
                        if (state.sessionCount !== undefined) this.sessionCount = Number(state.sessionCount);
                        
                        // Check if saved selected task actually exists
                        if (state.selectedTaskId && this.activeTasks.some(t => String(t.id) === String(state.selectedTaskId))) {
                            this.selectedTaskId = String(state.selectedTaskId);
                            const found = this.activeTasks.find(t => String(t.id) === String(this.selectedTaskId));
                            this.selectedTaskTitle = found ? found.title : '';
                        } else {
                            this.selectedTaskId = '';
                            this.selectedTaskTitle = '';
                        }

                        if (state.showAudioDeck !== undefined) this.showAudioDeck = Boolean(state.showAudioDeck);
                        if (state.showYtVideoFrame !== undefined) this.showYtVideoFrame = Boolean(state.showYtVideoFrame);
                        if (state.musicTab !== undefined) this.musicTab = state.musicTab;
                        if (state.activeFolderId !== undefined) this.activeFolderId = state.activeFolderId;
                        if (state.ytVideoId) this.ytVideoId = state.ytVideoId;
                        if (state.ytTitle) this.ytTitle = state.ytTitle;
                        if (state.ytAuthor) this.ytAuthor = state.ytAuthor;
                        if (state.ytThumbnail) this.ytThumbnail = state.ytThumbnail;
                        if (state.currentAmbientPreset) this.currentAmbientPreset = state.currentAmbientPreset;
                        if (state.isYtShuffle !== undefined) this.isYtShuffle = Boolean(state.isYtShuffle);
                        if (state.ytLoopMode !== undefined) this.ytLoopMode = state.ytLoopMode;
                        if (state.isYtAutoNext !== undefined) this.isYtAutoNext = Boolean(state.isYtAutoNext);
                        if (state.ytVolume !== undefined) this.ytVolume = Number(state.ytVolume);
                        if (state.isYtMuted !== undefined) this.isYtMuted = Boolean(state.isYtMuted);

                        // Check if day has changed (midnight rollover / new day)
                        const today = new Date().toLocaleDateString('en-CA');
                        const isNewDay = Boolean(state.savedDate && state.savedDate !== today);

                        if (isNewDay) {
                            // Settle yesterday's unfinished work session so yesterday's effort is rewarded
                            if (state.mode === 'work' && state.timeLeft !== undefined) {
                                const totalSecs = (this.settings.work || 25) * 60;
                                const elapsedSecs = totalSecs - state.timeLeft;
                                const elapsedMinutes = Math.floor(elapsedSecs / 60);
                                if (elapsedMinutes >= 1 && window.Livewire) {
                                    window.Livewire.dispatch('pomodoro-completed', {
                                        taskId: state.selectedTaskId || null,
                                        minutes: elapsedMinutes,
                                        ambientSound: state.isYtPlaying ? 'youtube' : (state.isAmbientPlaying ? state.currentAmbientPreset : null),
                                        mode: 'work'
                                    });
                                }
                            }
                            // Fresh day reset: 25 mins full for today
                            this.timeLeft = this.settings.work * 60;
                            this.isRunning = false;
                            this.flowtimeSeconds = 0;
                            this.mode = 'work';
                            this.sessionCount = 0;
                            this.saveActiveTimerState();
                        } else {
                            if (this.mode === 'flowtime') {
                                this.isRunning = false;
                            } else if (state.isRunning && state.targetEndTime) {
                                const now = Date.now();
                                const diff = Math.round((state.targetEndTime - now) / 1000);
                                if (diff > 0) {
                                    this.timeLeft = diff;
                                    this.startTimer();
                                } else {
                                    this.timeLeft = 0;
                                    this.timerComplete();
                                }
                            } else {
                                this.timeLeft = (state.timeLeft !== undefined && state.timeLeft > 0) ? state.timeLeft : this.totalTimeForMode;
                                this.isRunning = false;
                            }
                        }

                        // Restore Audio playback state across tab/navigation/reload
                        if (state.isYtPlaying) {
                            this.isYtPlaying = true;
                            this.ensurePermanentAudioHost();
                            setTimeout(() => {
                                this.sendYtCommand('playVideo');
                                this.sendYtListening();
                                this.updateYtVolume();
                                this.updateVideoSlotPosition();
                            }, 600);
                        }
                        if (state.isAmbientPlaying) {
                            setTimeout(() => {
                                if (!this.isAmbientPlaying) {
                                    this.toggleAmbientPlayback();
                                }
                            }, 400);
                        }
                    } catch (e) {
                        this.timeLeft = this.settings.work * 60;
                    }
                },

                toggleTimer() {
                    if (this.isRunning) {
                        this.pauseTimer();
                    } else {
                        this.startTimer();
                    }
                },

                sendFocusHeartbeat() {
                    if (!this.isRunning) return;
                    if (this.mode !== 'work' && this.mode !== 'flowtime') return;

                    const minutesRemaining = this.mode === 'flowtime' 
                        ? Math.floor(this.flowtimeSeconds / 60) 
                        : Math.max(1, Math.ceil(this.timeLeft / 60));

                    fetch('/api/focus/heartbeat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            mode: this.mode,
                            task_title: this.selectedTaskTitle || 'Sesi Fokus Belajar',
                            minutes_remaining: minutesRemaining
                        })
                    }).catch(() => {});
                },

                stopFocusHeartbeat() {
                    fetch('/api/focus/stop', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    }).catch(() => {});
                },

                startTimer() {
                    if (this.interval) clearInterval(this.interval);
                    this.isRunning = true;
                    this.saveActiveTimerState();
                    this.sendFocusHeartbeat();
                    this.interval = setInterval(() => {
                        if (this.mode === 'flowtime') {
                            this.flowtimeSeconds++;
                            document.title = `(${this.formattedTime}) ATOMA Flow`;
                            if (this.flowtimeSeconds % 5 === 0) {
                                this.saveActiveTimerState();
                            }
                            if (this.flowtimeSeconds % 30 === 0) {
                                this.sendFocusHeartbeat();
                            }
                        } else {
                            if (this.timeLeft > 0) {
                                this.timeLeft--;
                                document.title = `(${this.formattedTime}) ATOMA`;
                                if (this.timeLeft % 5 === 0) {
                                    this.saveActiveTimerState();
                                }
                                if (this.timeLeft % 30 === 0) {
                                    this.sendFocusHeartbeat();
                                }
                            } else {
                                this.timerComplete();
                            }
                        }
                    }, 1000);
                },

                pauseTimer() {
                    this.isRunning = false;
                    if (this.interval) clearInterval(this.interval);
                    this.saveActiveTimerState();
                    this.stopFocusHeartbeat();
                    document.title = 'ATOMA';
                },

                resetTimer() {
                    this.pauseTimer();
                    this.stopFocusHeartbeat();
                    if (this.mode === 'work') this.savedWorkTimeLeft = null;
                    else if (this.mode === 'shortBreak') this.savedShortBreakTimeLeft = null;
                    else if (this.mode === 'longBreak') this.savedLongBreakTimeLeft = null;

                    this.setMode(this.mode);
                    this.saveActiveTimerState();
                    if (window.Livewire) {
                        window.Livewire.dispatch('toast', { message: 'Timer di-reset.', type: 'info' });
                    }
                },

                switchMode(customMinutes = null) {
                    if (this.mode === 'work') {
                        this.sessionCount++;
                        this.todaySessionsCount++;
                        const minutesToAdd = customMinutes !== null ? customMinutes : (this.settings.work || 25);
                        this.todayFocusMinutes += minutesToAdd;
                        this.saveTelemetry();

                        // Auto pause audio if enabled
                        if (this.settings.autoPauseMusic) {
                            this.autoPauseAudio();
                        }

                        if (this.sessionCount % (this.settings.interval || 4) === 0) {
                            this.mode = 'longBreak';
                            this.timeLeft = (this.settings.longBreak || 15) * 60;
                        } else {
                            this.mode = 'shortBreak';
                            this.timeLeft = (this.settings.shortBreak || 5) * 60;
                        }
                        this.shuffleBreakTip();

                        this.saveActiveTimerState();

                        if (this.settings.autoStartBreak) {
                            this.startTimer();
                        }
                    } else {
                        this.mode = 'work';
                        this.timeLeft = (this.settings.work || 25) * 60;
                        this.saveActiveTimerState();
                        if (this.settings.autoStartWork) {
                            this.startTimer();
                        }
                    }
                },

                timerComplete() {
                    this.pauseTimer();
                    this.triggerHaptic('success');
                    
                    // 1. Client-Side Synthesized Chime (Reliable, Zero Network)
                    this.playWebAudioChime();

                    // 2. Play configured notification sound (custom or chosen preset)
                    if (window.AtomaAudio) {
                        window.AtomaAudio.play(null);
                    } else if (this.$refs.alarmSound) {
                        this.$refs.alarmSound.play().catch(e => console.log('Alarm play error', e));
                    }
                    
                    if (window.AtomaNativeNotification) {
                        window.AtomaNativeNotification.notifyNow({
                            title: this.mode === 'work' ? '🎉 Focus Time Selesai!' : '⚡ Istirahat Selesai!',
                            body: this.mode === 'work' ? 'Waktunya istirahat sejenak.' : 'Kembali fokus ke tugasmu!',
                            sound: 'chime'
                        });
                    } else if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                        new Notification(this.mode === 'work' ? '🎉 Focus Time Selesai!' : '⚡ Istirahat Selesai!', {
                            body: this.mode === 'work' ? 'Waktunya istirahat sejenak.' : 'Kembali fokus ke tugasmu!',
                            icon: '/favicon.ico'
                        });
                    }
                    
                    if (this.mode === 'work') {
                        if (window.Livewire) {
                            let ambient = this.isYtPlaying ? 'youtube' : (this.isAmbientPlaying ? this.currentAmbientPreset : null);
                            window.Livewire.dispatch('pomodoro-completed', { 
                                taskId: this.selectedTaskId, 
                                minutes: this.settings.work,
                                ambientSound: ambient,
                                mode: 'work'
                            });
                        }
                    }
                    
                    this.switchMode();
                    this.fetchTodaySessions();
                    this.show = true;
                    this.saveActiveTimerState();
                },

                toggleSettings() {
                    this.showSettings = !this.showSettings;
                },

                saveSettings() {
                    localStorage.setItem('atoma_pomodoro_settings', JSON.stringify(this.settings));
                    this.showSettings = false;
                    this.setMode(this.mode);
                },

                async addQuickTask() {
                    let title = this.quickTaskInput ? this.quickTaskInput.trim() : '';
                    if (!title) return;
                    this.isAddingQuickTask = true;
                    try {
                        const res = await fetch('/api/widget/quick-task', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ title: title })
                        });
                        const data = await res.json();
                        if (data.success && data.task) {
                            this.activeTasks.unshift(data.task);
                            this.quickTaskInput = '';
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message || 'Tugas berhasil ditambahkan!', type: 'success' }
                            }));
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message || 'Gagal menambahkan tugas', type: 'error' }
                            }));
                        }
                    } catch (e) {
                        console.error('Error adding quick task:', e);
                    } finally {
                        this.isAddingQuickTask = false;
                    }
                },

                async toggleTaskCompletion(task) {
                    if (!task) return;
                    task.is_completed = !task.is_completed;
                    try {
                        const res = await fetch('/api/widget/toggle-task', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ task_id: task.id })
                        });
                        const data = await res.json();
                        if (data.success) {
                            if (task.is_completed) {
                                window.dispatchEvent(new CustomEvent('play-sound', { detail: { type: 'success' } }));
                            }
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { 
                                    message: (data.evaluation && data.evaluation.message) ? data.evaluation.message : (data.message || 'Status tugas diperbarui!'), 
                                    type: (data.evaluation && data.evaluation.type) ? data.evaluation.type : 'info' 
                                }
                            }));
                            window.dispatchEvent(new CustomEvent('profile-updated'));
                        }
                    } catch (e) {
                        console.error('Error toggling task:', e);
                    }
                },

                saveQuickNoteDraft() {
                    localStorage.setItem('atoma_widget_quick_note', this.quickNoteContent);
                },

                async exportNoteToJournal() {
                    let text = this.quickNoteContent ? this.quickNoteContent.trim() : '';
                    if (!text) return;
                    this.isSavingQuickNote = true;
                    try {
                        const res = await fetch('/api/widget/quick-note', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ content: text })
                        });
                        const data = await res.json();
                        if (data.success) {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message || 'Catatan tersimpan ke Jurnal!', type: 'success' }
                            }));
                        }
                    } catch (e) {
                        console.error('Error exporting note:', e);
                    } finally {
                        this.isSavingQuickNote = false;
                    }
                },

                loadSettings() {
                    const saved = localStorage.getItem('atoma_pomodoro_settings');
                    if (saved) {
                        try {
                            this.settings = Object.assign(this.settings, JSON.parse(saved));
                        } catch (e) {}
                    }
                },

                saveTelemetry() {
                    const data = {
                        date: new Date().toISOString().split('T')[0],
                        minutes: this.todayFocusMinutes,
                        sessions: this.todaySessionsCount
                    };
                    localStorage.setItem('atoma_pomodoro_telemetry', JSON.stringify(data));
                },

                loadTelemetry() {
                    const saved = localStorage.getItem('atoma_pomodoro_telemetry');
                    if (saved) {
                        try {
                            const parsed = JSON.parse(saved);
                            const today = new Date().toISOString().split('T')[0];
                            if (parsed.date === today) {
                                this.todayFocusMinutes = parsed.minutes || 0;
                                this.todaySessionsCount = parsed.sessions || 0;
                            }
                        } catch (e) {}
                    }
                },

                // Mobile Haptic Feedback
                triggerHaptic(type = 'light') {
                    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
                        try {
                            if (type === 'light') navigator.vibrate(15);
                            else if (type === 'success') navigator.vibrate([30, 50, 60]);
                            else if (type === 'warning') navigator.vibrate([50, 50, 50]);
                        } catch (e) {}
                    }
                },

                // Draggable Floating Bubble with Snap to Edge
                startDrag(e) {
                    if (e.target.closest('button') || e.target.closest('input') || e.target.closest('a')) {
                        return;
                    }

                    const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
                    const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;
                    this.dragStartX = clientX;
                    this.dragStartY = clientY;
                    this.hasDragged = false;

                    const el = e.currentTarget;
                    const rect = el.getBoundingClientRect();
                    this.initialX = rect.left;
                    this.initialY = rect.top;

                    const onMove = (moveEvt) => {
                        const curX = moveEvt.type.startsWith('touch') ? moveEvt.touches[0].clientX : moveEvt.clientX;
                        const curY = moveEvt.type.startsWith('touch') ? moveEvt.touches[0].clientY : moveEvt.clientY;
                        const deltaX = curX - this.dragStartX;
                        const deltaY = curY - this.dragStartY;

                        if (Math.hypot(deltaX, deltaY) > 6) {
                            this.isDragging = true;
                            this.hasDragged = true;
                        }

                        if (this.isDragging) {
                            const floatWidth = el.offsetWidth || 180;
                            const floatHeight = el.offsetHeight || 50;
                            let newX = this.initialX + deltaX;
                            let newY = this.initialY + deltaY;

                            // Keep inside screen viewport (avoid sticky header & bottom nav)
                            newX = Math.max(10, Math.min(window.innerWidth - floatWidth - 10, newX));
                            newY = Math.max(60, Math.min(window.innerHeight - floatHeight - 75, newY));

                            this.customX = newX;
                            this.customY = newY;
                        }
                    };

                    const onEnd = () => {
                        window.removeEventListener('mousemove', onMove);
                        window.removeEventListener('mouseup', onEnd);
                        window.removeEventListener('touchmove', onMove);
                        window.removeEventListener('touchend', onEnd);

                        if (this.isDragging) {
                            this.isDragging = false;
                            // Magnetic snap to nearest edge
                            const floatWidth = el.offsetWidth || 180;
                            const midX = window.innerWidth / 2;
                            if (this.customX + floatWidth / 2 < midX) {
                                this.customX = 16;
                                this.dockSide = 'left';
                            } else {
                                this.customX = window.innerWidth - floatWidth - 16;
                                this.dockSide = 'right';
                            }
                            this.triggerHaptic('light');
                            this.saveFloatPosition();
                        }

                        setTimeout(() => {
                            this.hasDragged = false;
                        }, 150);
                    };

                    window.addEventListener('mousemove', onMove, { passive: false });
                    window.addEventListener('mouseup', onEnd);
                    window.addEventListener('touchmove', onMove, { passive: false });
                    window.addEventListener('touchend', onEnd);
                },

                onFloatClick() {
                    if (this.hasDragged) return;
                    this.triggerHaptic('light');
                    this.toggleShow();
                },

                toggleCompactMode() {
                    this.isCompact = !this.isCompact;
                    try {
                        localStorage.setItem('atoma_pomodoro_compact', this.isCompact ? '1' : '0');
                    } catch (e) {}
                    this.triggerHaptic('light');
                    this.$nextTick(() => {
                        this.clampFloatPosition();
                    });
                },

                saveFloatPosition() {
                    try {
                        localStorage.setItem('atoma_pomodoro_float_pos', JSON.stringify({
                            x: this.customX,
                            y: this.customY,
                            dockSide: this.dockSide
                        }));
                    } catch (e) {}
                },

                loadFloatPosition() {
                    try {
                        const savedCompact = localStorage.getItem('atoma_pomodoro_compact');
                        if (savedCompact !== null) {
                            this.isCompact = savedCompact === '1';
                        }
                        const savedPos = localStorage.getItem('atoma_pomodoro_float_pos');
                        if (savedPos) {
                            const pos = JSON.parse(savedPos);
                            if (typeof pos.x === 'number' && typeof pos.y === 'number') {
                                this.customX = pos.x;
                                this.customY = pos.y;
                                this.dockSide = pos.dockSide || 'right';
                                this.clampFloatPosition();
                            }
                        }
                    } catch (e) {}
                },

                clampFloatPosition() {
                    if (this.customX !== null && this.customY !== null) {
                        const floatWidth = this.isCompact ? 48 : 220;
                        const floatHeight = this.isCompact ? 48 : 50;
                        if (this.dockSide === 'left') {
                            this.customX = 16;
                        } else {
                            this.customX = Math.max(16, window.innerWidth - floatWidth - 16);
                        }
                        this.customY = Math.max(60, Math.min(window.innerHeight - floatHeight - 75, this.customY));
                    }
                },

                togglePlayPauseFromFloat() {
                    this.triggerHaptic('light');
                    this.toggleTimer();
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                },

                toggleAudioMute() {
                    this.triggerHaptic('light');
                    this.isAudioMuted = !this.isAudioMuted;
                    if (this.isYtPlaying) {
                        if (this.isAudioMuted) {
                            this.sendYtCommand('mute');
                        } else {
                            this.sendYtCommand('unMute');
                            this.sendYtCommand('setVolume', [this.ytVolume || 80]);
                        }
                    }
                    if (this.ambientGainNode && this.ambientAudioCtx) {
                        if (this.isAudioMuted) {
                            this.ambientGainNode.gain.setValueAtTime(0, this.ambientAudioCtx.currentTime);
                        } else {
                            this.ambientGainNode.gain.setValueAtTime((this.ambientVolume / 100) * 0.4, this.ambientAudioCtx.currentTime);
                        }
                    }
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                },

                quickTaskDone() {
                    this.triggerHaptic('success');
                    this.markSelectedTaskDone();
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                },

                toggleSubTask(taskId, subTaskId) {
                    this.triggerHaptic('light');
                    if (window.Livewire) {
                        window.Livewire.dispatch('toggle-subtask-from-pomodoro', {
                            taskId: Number(taskId),
                            subTaskId: Number(subTaskId)
                        });
                    }
                    const task = this.activeTasks.find(t => String(t.id) === String(taskId));
                    if (task && task.subtasks) {
                        const sub = task.subtasks.find(s => String(s.id) === String(subTaskId));
                        if (sub) {
                            sub.is_completed = !sub.is_completed;
                        }
                    }
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                },

                // Interactive Document Picture-in-Picture Float Hero + Canvas Fallback
                async togglePip() {
                    if (window.pipWindow && !window.pipWindow.closed) {
                        window.pipWindow.close();
                        window.pipWindow = null;
                        return;
                    }

                    if (this.canvasPipActive) {
                        if (document.pictureInPictureElement) {
                            await document.exitPictureInPicture().catch(() => {});
                        }
                        this.canvasPipActive = false;
                        if (this.canvasPipInterval) {
                            clearInterval(this.canvasPipInterval);
                            this.canvasPipInterval = null;
                        }
                        return;
                    }

                    if ('documentPictureInPicture' in window) {
                        await this.openDocumentPip();
                    } else if (document.pictureInPictureEnabled) {
                        await this.openCanvasPip();
                    } else {
                        alert('Browser kamu belum mendukung Picture-in-Picture API.');
                    }
                },

                async openDocumentPip() {
                    try {
                        const currentTask = this.activeTasks.find(t => String(t.id) === String(this.selectedTaskId));
                        const hasSubtasks = Boolean(currentTask && currentTask.subtasks && currentTask.subtasks.length > 0);
                        const hasAudio = Boolean(this.isYtPlaying || this.isAmbientPlaying || this.ytVideoId);
                        const initialHeight = (hasSubtasks ? 330 : 210) + (hasAudio ? 45 : 0);

                        const pipWin = await window.documentPictureInPicture.requestWindow({
                            width: 340,
                            height: initialHeight
                        });
                        window.pipWindow = pipWin;

                        const isDark = document.documentElement.classList.contains('dark');
                        const bgColor = isDark ? '#0f172a' : '#f8fafc';
                        const textColor = isDark ? '#f8fafc' : '#0f172a';
                        const subColor = isDark ? '#94a3b8' : '#64748b';
                        const secBtnBg = isDark ? '#1e293b' : '#ffffff';
                        const secBtnColor = isDark ? '#cbd5e1' : '#334155';
                        const secBtnBorder = isDark ? '#334155' : '#e2e8f0';

                        const style = pipWin.document.createElement('style');
                        style.textContent = `
                            body {
                                margin: 0; padding: 12px; background: ${bgColor}; color: ${textColor};
                                font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                                display: flex; flex-direction: column; gap: 8px; min-height: 100vh; height: auto;
                                box-sizing: border-box; user-select: none; overflow-y: auto; justify-content: flex-start;
                            }
                            body::-webkit-scrollbar { width: 4px; }
                            body::-webkit-scrollbar-thumb { background: rgba(150, 150, 150, 0.3); border-radius: 4px; }
                            .btn { border: none; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 11px; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; justify-content: center; gap: 4px; }
                            .btn-primary { background: #f43f5e; color: white; box-shadow: 0 4px 12px rgba(244,63,94,0.3); }
                            .btn-secondary { background: ${secBtnBg}; color: ${secBtnColor}; border: 1px solid ${secBtnBorder}; }
                            .card { background: ${secBtnBg}; border: 1px solid ${secBtnBorder}; border-radius: 12px; padding: 8px 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
                            .subtask-item { display: flex; align-items: center; gap: 8px; padding: 4px 6px; border-radius: 6px; cursor: pointer; transition: background 0.15s; }
                            .subtask-item:hover { background: rgba(150, 150, 150, 0.1); }
                        `;
                        pipWin.document.head.appendChild(style);

                        const modeColor = this.mode === 'work' ? '#f43f5e' : (this.mode === 'flowtime' ? '#06b6d4' : '#10b981');

                        pipWin.document.body.innerHTML = `
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span id="pipMode" style="font-weight: 900; font-size: 12px; color: ${modeColor};">🍅 ${this.modeName}</span>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="font-size: 9px; font-weight: 700; color: #f97316;">🔥 ${this.userStreak}d</span>
                                    <span style="font-size: 9px; background: rgba(245,158,11,0.15); color: #d97706; padding: 2px 6px; border-radius: 6px; font-weight: bold;">📌 OS FLOAT</span>
                                </div>
                            </div>

                            <div style="text-align: center; margin: 2px 0;">
                                <div id="pipTime" style="font-size: 40px; font-weight: 900; font-family: monospace; color: ${textColor}; line-height: 1.1;">${this.formattedTime}</div>
                                <div id="pipTask" style="font-size: 11px; color: ${subColor}; font-weight: 600; margin-top: 2px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${this.selectedTaskTitle || 'Tetap fokus & tuntaskan!'}</div>
                            </div>

                            <div style="display: flex; justify-content: center; gap: 6px; align-items: center; flex-wrap: wrap;">
                                <button id="pipReset" class="btn btn-secondary" title="Reset Timer">🔄 Reset</button>
                                <button id="pipToggle" class="btn btn-primary" style="padding: 7px 16px; font-size: 12px;">${this.isRunning ? '⏸ Jeda' : '▶ Mulai'}</button>
                                <button id="pipFinishEarly" class="btn btn-secondary" style="color: #10b981; font-weight: bold;" title="Selesaikan Sesi (+XP)">✓ Selesai</button>
                                ${this.selectedTaskId ? '<button id="pipQuickDone" class="btn btn-secondary" style="color: #6366f1;" title="Tandai Tugas Selesai">📋 Tugas</button>' : ''}
                            </div>

                            <!-- Audio Section Card -->
                            <div id="pipAudioSection" class="card" style="display: ${this.isYtPlaying || this.isAmbientPlaying || this.ytVideoId ? 'block' : 'none'};">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px;">
                                    <div style="display: flex; align-items: center; gap: 6px; min-width: 0; flex: 1;">
                                        <span style="font-size: 12px;">🎵</span>
                                        <span id="pipAudioTitle" style="font-size: 10px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            ${this.isYtPlaying ? this.ytTitle : (this.isAmbientPlaying ? this.currentAmbientName : (this.ytTitle || 'Audio'))}
                                        </span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 3px; flex-shrink: 0;">
                                        <button id="pipAudioPrev" class="btn btn-secondary" style="padding: 3px 6px; font-size: 10px;" title="Lagu Sebelumnya">⏮</button>
                                        <button id="pipAudioNext" class="btn btn-secondary" style="padding: 3px 6px; font-size: 10px;" title="Lagu Berikutnya">⏭</button>
                                        <button id="pipAudioMute" class="btn btn-secondary" style="padding: 3px 8px; font-size: 10px;">
                                            ${this.isAudioMuted ? '🔇 Bisu' : '🔊 Suara'}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Subtasks Section Card -->
                            <div id="pipSubtasksSection" class="card" style="display: none; max-height: 120px; overflow-y: auto;">
                                <div id="pipSubtasksHeader" style="font-size: 10px; font-weight: 700; color: ${subColor}; margin-bottom: 4px;">📝 Subtasks</div>
                                <div id="pipSubtasksList" style="display: flex; flex-direction: column; gap: 2px;"></div>
                            </div>
                        `;

                        pipWin.document.getElementById('pipToggle').onclick = () => {
                            this.toggleTimer();
                            this.updatePipContent();
                        };
                        pipWin.document.getElementById('pipReset').onclick = () => {
                            this.resetTimer();
                            this.updatePipContent();
                        };
                        const fe = pipWin.document.getElementById('pipFinishEarly');
                        if (fe) {
                            fe.onclick = () => {
                                this.finishSessionEarly();
                                this.updatePipContent();
                            };
                        }
                        const qd = pipWin.document.getElementById('pipQuickDone');
                        if (qd) {
                            qd.onclick = () => {
                                this.quickTaskDone();
                                this.updatePipContent();
                            };
                        }
                        const prevBtn = pipWin.document.getElementById('pipAudioPrev');
                        if (prevBtn) {
                            prevBtn.onclick = () => {
                                this.playPrevTrack();
                                this.updatePipContent();
                            };
                        }
                        const nextBtn = pipWin.document.getElementById('pipAudioNext');
                        if (nextBtn) {
                            nextBtn.onclick = () => {
                                this.playNextTrack();
                                this.updatePipContent();
                            };
                        }
                        const muteBtn = pipWin.document.getElementById('pipAudioMute');
                        if (muteBtn) {
                            muteBtn.onclick = () => {
                                this.toggleAudioMute();
                                this.updatePipContent();
                            };
                        }

                        this.updatePipContent();

                        const updatePipInterval = setInterval(() => {
                            if (pipWin.closed) {
                                clearInterval(updatePipInterval);
                                window.pipWindow = null;
                                return;
                            }
                            this.updatePipContent();
                        }, 500);

                    } catch (err) {
                        console.log('PiP Error:', err);
                    }
                },

                updatePipContent() {
                    const pipWin = window.pipWindow;
                    if (!pipWin || pipWin.closed) return;

                    const doc = pipWin.document;
                    const timeEl = doc.getElementById('pipTime');
                    const modeEl = doc.getElementById('pipMode');
                    const toggleBtn = doc.getElementById('pipToggle');
                    const taskEl = doc.getElementById('pipTask');
                    const audioSection = doc.getElementById('pipAudioSection');
                    const audioTitle = doc.getElementById('pipAudioTitle');
                    const audioMuteBtn = doc.getElementById('pipAudioMute');
                    const subtasksContainer = doc.getElementById('pipSubtasksList');
                    const subtaskHeader = doc.getElementById('pipSubtasksHeader');
                    const subtasksSection = doc.getElementById('pipSubtasksSection');

                    if (timeEl) timeEl.textContent = this.formattedTime;
                    if (taskEl) taskEl.textContent = this.selectedTaskTitle || 'Tetap fokus & tuntaskan!';
                    if (modeEl) {
                        const modeIcon = this.mode === 'work' ? '🍅' : (this.mode === 'flowtime' ? '🌊' : '☕');
                        modeEl.textContent = `${modeIcon} ${this.modeName}`;
                        modeEl.style.color = this.mode === 'work' ? '#f43f5e' : (this.mode === 'flowtime' ? '#06b6d4' : '#10b981');
                    }
                    if (toggleBtn) {
                        toggleBtn.textContent = this.isRunning ? '⏸ Jeda' : '▶ Mulai';
                        toggleBtn.style.background = this.mode === 'work' ? '#f43f5e' : (this.mode === 'flowtime' ? '#06b6d4' : '#10b981');
                    }
                    const finishBtn = doc.getElementById('pipFinishEarly');
                    if (finishBtn) {
                        finishBtn.title = this.mode === 'work' ? 'Selesaikan Sesi Fokus (+XP)' : (this.mode === 'flowtime' ? 'Selesaikan Flowtime' : 'Selesaikan Istirahat');
                    }

                    if (audioSection) {
                        if (this.isYtPlaying || this.isAmbientPlaying || this.ytVideoId) {
                            audioSection.style.display = 'block';
                            if (audioTitle) {
                                audioTitle.textContent = this.isYtPlaying ? this.ytTitle : (this.isAmbientPlaying ? this.currentAmbientName : (this.ytTitle || 'Audio'));
                            }
                            if (audioMuteBtn) {
                                audioMuteBtn.textContent = this.isAudioMuted ? '🔇 Bisu' : '🔊 Suara';
                                audioMuteBtn.style.color = this.isAudioMuted ? '#f43f5e' : 'inherit';
                            }
                        } else {
                            audioSection.style.display = 'none';
                        }
                    }

                    const currentTask = this.activeTasks.find(t => String(t.id) === String(this.selectedTaskId));
                    const hasActiveSubtasks = Boolean(currentTask && currentTask.subtasks && currentTask.subtasks.length > 0);

                    // Dynamic PiP window height auto-fit
                    if (pipWin.resizeTo) {
                        const hasAudio = Boolean(this.isYtPlaying || this.isAmbientPlaying || this.ytVideoId);
                        let targetHeight = 210;
                        if (hasAudio) targetHeight += 45;
                        if (hasActiveSubtasks) targetHeight += 120;
                        try {
                            if (Math.abs(pipWin.outerHeight - targetHeight) > 20) {
                                pipWin.resizeTo(340, targetHeight);
                            }
                        } catch (e) {}
                    }

                    if (subtasksContainer && subtaskHeader && subtasksSection) {
                        if (hasActiveSubtasks) {
                            subtasksSection.style.display = 'block';
                            const completedCount = currentTask.subtasks.filter(s => s.is_completed).length;
                            subtaskHeader.textContent = `📝 Subtasks (${completedCount}/${currentTask.subtasks.length})`;
                            
                            const currentSubCount = subtasksContainer.children.length;
                            if (currentSubCount !== currentTask.subtasks.length) {
                                subtasksContainer.innerHTML = '';
                                currentTask.subtasks.forEach(sub => {
                                    const item = doc.createElement('label');
                                    item.className = 'subtask-item';
                                    item.id = 'pip-sub-label-' + sub.id;
                                    item.innerHTML = `
                                        <input type="checkbox" id="pip-sub-cb-${sub.id}" ${sub.is_completed ? 'checked' : ''} style="cursor: pointer; accent-color: #f43f5e;" />
                                        <span id="pip-sub-text-${sub.id}" style="flex: 1; min-width: 0; font-size: 11px; font-weight: 500; ${sub.is_completed ? 'text-decoration: line-through; opacity: 0.6;' : ''}">${sub.title}</span>
                                    `;
                                    const cb = item.querySelector('input');
                                    cb.onchange = (e) => {
                                        e.preventDefault();
                                        this.toggleSubTask(currentTask.id, sub.id);
                                    };
                                    subtasksContainer.appendChild(item);
                                });
                            } else {
                                currentTask.subtasks.forEach(sub => {
                                    const cb = doc.getElementById('pip-sub-cb-' + sub.id);
                                    const txt = doc.getElementById('pip-sub-text-' + sub.id);
                                    if (cb && cb.checked !== Boolean(sub.is_completed)) {
                                        cb.checked = Boolean(sub.is_completed);
                                    }
                                    if (txt) {
                                        txt.style.textDecoration = sub.is_completed ? 'line-through' : 'none';
                                        txt.style.opacity = sub.is_completed ? '0.6' : '1';
                                    }
                                });
                            }
                        } else {
                            subtasksSection.style.display = 'none';
                        }
                    }
                },

                async openCanvasPip() {
                    let canvas = document.getElementById('pomodoro-pip-canvas');
                    if (!canvas) {
                        canvas = document.createElement('canvas');
                        canvas.id = 'pomodoro-pip-canvas';
                        canvas.width = 360;
                        canvas.height = 200;
                        canvas.style.display = 'none';
                        document.body.appendChild(canvas);
                    }
                    const ctx = canvas.getContext('2d');

                    let video = document.getElementById('pomodoro-pip-video');
                    if (!video) {
                        video = document.createElement('video');
                        video.id = 'pomodoro-pip-video';
                        video.muted = true;
                        video.autoplay = true;
                        video.playsInline = true;
                        video.style.display = 'none';
                        document.body.appendChild(video);
                    }

                    const drawFrame = () => {
                        const isDark = document.documentElement.classList.contains('dark');
                        ctx.fillStyle = isDark ? '#0f172a' : '#ffffff';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);

                        const modeColor = this.mode === 'work' ? '#f43f5e' : (this.mode === 'flowtime' ? '#06b6d4' : '#10b981');
                        ctx.fillStyle = modeColor;
                        ctx.font = 'bold 15px system-ui, sans-serif';
                        ctx.fillText(`${this.mode === 'work' ? '🍅' : (this.mode === 'flowtime' ? '🌊' : '☕')} ${this.modeName}`, 20, 35);

                        ctx.fillStyle = isDark ? '#94a3b8' : '#64748b';
                        ctx.font = 'bold 12px system-ui, sans-serif';
                        ctx.fillText(`Sesi: ${this.todaySessionsCount}/${this.settings.interval}`, canvas.width - 95, 35);

                        ctx.fillStyle = isDark ? '#f8fafc' : '#0f172a';
                        ctx.font = 'bold 52px monospace';
                        ctx.textAlign = 'center';
                        ctx.fillText(this.formattedTime, canvas.width / 2, 110);

                        ctx.fillStyle = isDark ? '#1e293b' : '#e2e8f0';
                        if (ctx.roundRect) {
                            ctx.beginPath();
                            ctx.roundRect(20, 135, canvas.width - 40, 8, 4);
                            ctx.fill();
                        } else {
                            ctx.fillRect(20, 135, canvas.width - 40, 8);
                        }

                        ctx.fillStyle = modeColor;
                        const fillW = Math.max(0, Math.min(canvas.width - 40, (canvas.width - 40) * (this.progressPercentage / 100)));
                        if (ctx.roundRect) {
                            ctx.beginPath();
                            ctx.roundRect(20, 135, fillW, 8, 4);
                            ctx.fill();
                        } else {
                            ctx.fillRect(20, 135, fillW, 8);
                        }

                        ctx.fillStyle = isDark ? '#cbd5e1' : '#475569';
                        ctx.font = 'bold 12px system-ui, sans-serif';
                        const taskText = this.selectedTaskTitle ? `🎯 ${this.selectedTaskTitle}` : (this.isRunning ? 'Sedang Berjalan...' : 'Dijeda');
                        ctx.fillText(taskText.length > 35 ? taskText.slice(0, 35) + '...' : taskText, canvas.width / 2, 172);

                        ctx.textAlign = 'start';
                    };

                    drawFrame();
                    try {
                        const stream = canvas.captureStream(10);
                        video.srcObject = stream;
                        await video.play();
                        await video.requestPictureInPicture();
                        this.canvasPipActive = true;
                        this.canvasPipInterval = setInterval(drawFrame, 500);

                        video.addEventListener('leavepictureinpicture', () => {
                            this.canvasPipActive = false;
                            if (this.canvasPipInterval) {
                                clearInterval(this.canvasPipInterval);
                                this.canvasPipInterval = null;
                            }
                        }, { once: true });
                    } catch (err) {
                        console.error('Canvas PiP error:', err);
                    }
                }
            }));
        }
    };

    if (window.Alpine) {
        registerPomodoro();
    } else {
        document.addEventListener('alpine:init', registerPomodoro);
    }
</script>
