<div x-data="achievementModal()" 
     @open-achievements.window="open = true; loadAchievements();"
     @badge-equipped.window="if (stats) { stats.equipped_badge = $event.detail.code; if ($event.detail.equipped_badges) stats.equipped_badges = $event.detail.equipped_badges; if ($event.detail.equipped_badges_details) stats.equipped_badges_details = $event.detail.equipped_badges_details; }">


    <div x-show="open" 
         x-transition.opacity.duration.200ms
         style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-gray-900/80 backdrop-blur-md">
        
        <div @click.away="open = false"
             class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl shadow-2xl p-5 sm:p-7 max-w-3xl w-full border border-gray-200 dark:border-gray-800 relative flex flex-col max-h-[90vh]">
            
            <!-- Close Button -->
            <button @click="open = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 pb-4 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-atoma-blue to-cyan-400 p-0.5 shadow-md flex items-center justify-center">
                        <div class="w-full h-full bg-white dark:bg-gray-900 rounded-[14px] flex items-center justify-center text-xl">
                            🏆
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-black tracking-tight">{{ __('Medali & Pencapaian') }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Koleksi lencana prestasi & konsistensi produktivitasmu.') }}</p>
                    </div>
                </div>

                <!-- Live Statistics Summary -->
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <div class="px-3 py-1.5 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700/60 text-center">
                        <div class="text-[10px] text-gray-400 uppercase font-black tracking-wider">{{ __('Koleksi') }}</div>
                        <div class="text-xs sm:text-sm font-black text-atoma-blue dark:text-atoma-cyan">
                            <span x-text="stats.unlocked"></span> / <span x-text="stats.total"></span> (<span x-text="stats.percentage"></span>%)
                        </div>
                    </div>
                    <div class="px-3 py-1.5 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700/60 text-center">
                        <div class="text-[10px] text-gray-400 uppercase font-black tracking-wider">{{ __('Total XP') }}</div>
                        <div class="text-xs sm:text-sm font-black text-amber-500">
                            +<span x-text="stats.total_xp"></span> XP
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rak Pamer 3 Slot Showcase -->
            <div class="mb-4 p-3 sm:p-4 rounded-2xl bg-gradient-to-r from-amber-500/10 via-yellow-500/5 to-cyan-500/10 dark:from-amber-950/40 dark:via-gray-800/60 dark:to-cyan-950/30 border border-amber-300/40 dark:border-amber-500/30">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-black uppercase tracking-wider text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                            <span>⭐</span> {{ __('Rak Pamer Lencana Profil') }}
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300 border border-amber-300/50">
                            <span x-text="(stats.equipped_badges || []).length"></span> / 3 {{ __('Slot Terisi') }}
                        </span>
                    </div>
                    <span class="text-[11px] text-gray-400 hidden sm:inline">{{ __('Dipamerkan di Profil Publik, Leaderboard & Feed') }}</span>
                </div>

                <!-- 3 Showcase Slots -->
                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                    <template x-for="slotNum in [1, 2, 3]" :key="slotNum">
                        <div class="relative rounded-xl border p-2 sm:p-2.5 flex flex-col items-center justify-between text-center transition-all min-h-[90px] sm:min-h-[100px]"
                             :class="getSlotBadge(slotNum) ? 'bg-white dark:bg-gray-800 border-amber-400/60 dark:border-amber-500/40 shadow-sm ring-1 ring-amber-400/30' : 'bg-gray-50/70 dark:bg-gray-800/30 border-dashed border-gray-300 dark:border-gray-700'">
                            
                            <!-- Slot Header -->
                            <div class="w-full flex items-center justify-between mb-1">
                                <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded"
                                      :class="getSlotBadge(slotNum) ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300' : 'bg-gray-200/70 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                                      x-text="'{{ __('Slot') }} #' + slotNum"></span>
                                
                                <template x-if="getSlotBadge(slotNum)">
                                    <button @click="equipBadge(getSlotBadge(slotNum).code, 'unequip')" 
                                            class="text-gray-400 hover:text-red-500 p-0.5 rounded transition-colors text-[11px] font-black leading-none cursor-pointer"
                                            title="{{ __('Lepas lencana dari slot ini') }}">
                                        ✕
                                    </button>
                                </template>
                            </div>

                            <!-- Content: Badge OR Empty State -->
                            <template x-if="getSlotBadge(slotNum)">
                                <div class="flex flex-col items-center w-full">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 p-0.5 mb-1">
                                        <template x-if="getSlotBadge(slotNum).svg_path">
                                            <img :src="'/' + getSlotBadge(slotNum).svg_path" :alt="getSlotBadge(slotNum).title" class="w-full h-full object-contain">
                                        </template>
                                        <template x-if="!getSlotBadge(slotNum).svg_path">
                                            <span class="text-xl" x-text="getSlotBadge(slotNum).icon"></span>
                                        </template>
                                    </div>
                                    <div class="text-[10px] sm:text-[11px] font-extrabold text-gray-900 dark:text-white truncate w-full" x-text="getSlotBadge(slotNum).title"></div>
                                </div>
                            </template>

                            <template x-if="!getSlotBadge(slotNum)">
                                <div class="flex flex-col items-center justify-center py-1.5 text-gray-400 dark:text-gray-500">
                                    <div class="text-base opacity-60">＋</div>
                                    <span class="text-[9px] sm:text-[10px] font-bold">{{ __('Slot Kosong') }}</span>
                                </div>
                            </template>

                        </div>
                    </template>
                </div>
            </div>

            <!-- Categories Filter Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-2 scrollbar-none shrink-0 text-xs font-black">
                <button @click="selectedCategory = 'all'"
                        :class="selectedCategory === 'all' ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    🌟 {{ __('Semua') }}
                </button>
                <button @click="selectedCategory = 'tasks'"
                        :class="selectedCategory === 'tasks' ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    ⚡ {{ __('Tugas') }}
                </button>
                <button @click="selectedCategory = 'streak'"
                        :class="selectedCategory === 'streak' ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    🔥 Streak
                </button>
                <button @click="selectedCategory = 'focus'"
                        :class="selectedCategory === 'focus' ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    🍅 {{ __('Fokus & Flow') }}
                </button>
                <button @click="selectedCategory = 'milestone'"
                        :class="selectedCategory === 'milestone' ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    🎯 {{ __('Milestone >80%') }}
                </button>
                <button @click="selectedCategory = 'workout'"
                        :class="selectedCategory === 'workout' ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    🏋️ Workout
                </button>
                <button @click="selectedCategory = 'social'"
                        :class="selectedCategory === 'social' ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    🤝 {{ __('Sosial') }}
                </button>
                <button @click="selectedCategory = 'reflection'"
                        :class="selectedCategory === 'reflection' ? 'bg-atoma-blue text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    ✍️ {{ __('Refleksi') }}
                </button>
                <button @click="selectedCategory = 'locked'"
                        :class="selectedCategory === 'locked' ? 'bg-amber-500 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer">
                    🔒 {{ __('Terkunci') }}
                </button>
            </div>

            <!-- Badges Cards Grid (Scrollable) -->
            <div class="flex-1 overflow-y-auto pr-1 mt-3 space-y-3 min-h-[300px]">
                
                <!-- Loading State -->
                <div x-show="loading" class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <svg class="animate-spin h-8 w-8 text-atoma-blue mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-xs">{{ __('Memuat katalog lencana...') }}</span>
                </div>

                <!-- Grid -->
                <div x-show="!loading" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="item in filteredAchievements" :key="item.code">
                        <div :class="{
                                'bg-gradient-to-br from-atoma-ice/70 via-white to-cyan-50/50 dark:from-atoma-dark/80 dark:via-gray-800/90 dark:to-[#0f172a] border-cyan-300/60 dark:border-cyan-500/40 shadow-sm ring-1 ring-cyan-400/20': item.unlocked && !item.is_equipped,
                                'bg-gradient-to-br from-amber-50 via-white to-amber-100/40 dark:from-amber-950/40 dark:via-gray-800 dark:to-gray-900 border-amber-400/70 dark:border-amber-400/60 shadow-md ring-2 ring-amber-400/50': item.is_equipped,
                                'bg-gray-50/80 dark:bg-gray-800/40 border-gray-200 dark:border-gray-800 opacity-75 hover:opacity-100': !item.unlocked
                             }"
                             class="p-4 rounded-2xl border transition-all flex flex-col justify-between relative group">
                            
                            <div class="flex items-start space-x-3.5">
                                <!-- Badge SVG Icon -->
                                <div class="w-14 h-14 shrink-0 rounded-2xl bg-white dark:bg-gray-800/90 p-1.5 shadow-sm border border-gray-100 dark:border-gray-700/70 flex items-center justify-center transform group-hover:scale-105 transition-transform">
                                    <template x-if="item.svg_path">
                                        <img :src="'/' + item.svg_path" :alt="item.title" class="w-full h-full object-contain">
                                    </template>
                                    <template x-if="!item.svg_path">
                                        <span class="text-2xl" x-text="item.icon"></span>
                                    </template>
                                </div>

                                <!-- Text Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="font-extrabold text-xs sm:text-sm truncate text-gray-900 dark:text-white" x-text="item.title"></h4>
                                        <span x-show="item.is_equipped" class="text-[9px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-300 bg-amber-100 dark:bg-amber-950/70 px-2 py-0.5 rounded-md border border-amber-300/40">
                                            ⭐ {{ __('Slot') }} #<span x-text="item.equipped_slot"></span>
                                        </span>
                                        <span x-show="item.unlocked && !item.is_equipped" class="text-[9px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 rounded-md">
                                            {{ __('Terbuka') }}
                                        </span>
                                    </div>
                                    
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-snug" x-text="item.description"></p>
                                    
                                    <!-- Rewards Pill -->
                                    <div class="flex items-center gap-2 mt-2">
                                        <span class="text-[10px] font-bold text-atoma-blue dark:text-cyan-400 bg-atoma-ice dark:bg-atoma-blue/20 px-2 py-0.5 rounded-md">
                                            +<span x-text="item.bonus_xp"></span> XP
                                        </span>
                                        <span x-show="item.bonus_coins" class="text-[10px] font-bold text-amber-500 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-md">
                                            +<span x-text="item.bonus_coins"></span> 🪙
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Section: Progress Bar (If locked) OR Equip Button (If unlocked) -->
                            <div class="mt-3 pt-2.5 border-t border-gray-100 dark:border-gray-800/80">
                                
                                <!-- If Locked: Live Target Progress Bar -->
                                <template x-if="!item.unlocked">
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-[10px] font-bold text-gray-500 dark:text-gray-400">
                                            <span>{{ __('Progres Target') }}</span>
                                            <span>
                                                <span x-text="item.current_value"></span> / <span x-text="item.target_value"></span> <span x-text="item.unit"></span>
                                                (<span x-text="item.progress_percent"></span>%)
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 h-2 rounded-full overflow-hidden relative">
                                            <div class="h-full bg-gradient-to-r from-blue-500 via-cyan-400 to-emerald-400 rounded-full transition-all duration-700 relative"
                                                 :style="'width: ' + item.progress_percent + '%'">
                                                <div class="absolute right-0 top-0 bottom-0 w-1 bg-white rounded-full"></div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- If Unlocked: Equip Action Button -->
                                <template x-if="item.unlocked">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[10px] text-gray-400 truncate">
                                            <span x-show="item.is_equipped">{{ __('Terpasang di Slot') }} #<span x-text="item.equipped_slot"></span></span>
                                            <span x-show="!item.is_equipped">{{ __('Tersedia untuk dipamerkan') }}</span>
                                        </span>
                                        <button @click="equipBadge(item.code)"
                                                :disabled="equipping === item.code"
                                                :class="item.is_equipped 
                                                    ? 'bg-amber-500 hover:bg-red-500 text-white shadow-amber-500/20' 
                                                    : (((stats.equipped_badges || []).length >= 3) 
                                                        ? 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 hover:bg-gray-300 dark:hover:bg-gray-600' 
                                                        : 'bg-gray-100 hover:bg-atoma-blue dark:bg-gray-800 dark:hover:bg-atoma-blue text-gray-700 hover:text-white dark:text-gray-300 dark:hover:text-white')"
                                                class="px-3 py-1 rounded-xl text-[11px] font-black uppercase tracking-wider transition-all shadow-sm cursor-pointer disabled:opacity-50 shrink-0">
                                            <span x-show="equipping === item.code">{{ __('Menyimpan...') }}</span>
                                            <span x-show="equipping !== item.code && item.is_equipped">
                                                ✕ {{ __('Lepas') }} ({{ __('Slot') }} #<span x-text="item.equipped_slot"></span>)
                                            </span>
                                            <span x-show="equipping !== item.code && !item.is_equipped">
                                                <span x-text="(stats.equipped_badges || []).length >= 3 ? '{{ __('Rak Penuh (3/3)') }}' : '{{ __('+ Pasang di Slot') }}'"></span>
                                            </span>
                                        </button>
                                    </div>
                                </template>

                            </div>

                        </div>
                    </template>
                </div>

                <!-- Empty State for Filter -->
                <div x-show="!loading && filteredAchievements.length === 0" class="py-12 text-center text-gray-400">
                    <div class="text-3xl mb-1">🔍</div>
                    <p class="text-xs">{{ __('Tidak ada lencana pada kategori ini.') }}</p>
                </div>

            </div>

        </div>
</div>

<script data-navigate-once>
    const registerAchievementModal = () => {
        if (typeof Alpine !== 'undefined' && !Alpine.data('achievementModal')) {
            Alpine.data('achievementModal', () => ({
                open: false,
                achievements: [],
                stats: { total: 0, unlocked: 0, percentage: 0, total_xp: 0, equipped_badge: null, equipped_badges: [], equipped_badges_details: [] },
                selectedCategory: 'all',
                loading: false,
                equipping: null,

                getSlotBadge(slotNum) {
                    if (!this.stats.equipped_badges_details || !this.stats.equipped_badges_details[slotNum - 1]) {
                        return null;
                    }
                    return this.stats.equipped_badges_details[slotNum - 1];
                },

                async loadAchievements() {
                    this.loading = true;
                    try {
                        const res = await fetch('/api/user-achievements');
                        const data = await res.json();
                        this.achievements = data.achievements || [];
                        this.stats = data.stats || { total: 0, unlocked: 0, percentage: 0, total_xp: 0, equipped_badge: null, equipped_badges: [], equipped_badges_details: [] };
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.loading = false;
                    }
                },

                get filteredAchievements() {
                    if (this.selectedCategory === 'all') {
                        return this.achievements;
                    }
                    if (this.selectedCategory === 'locked') {
                        return this.achievements.filter(a => !a.unlocked);
                    }
                    if (this.selectedCategory === 'unlocked') {
                        return this.achievements.filter(a => a.unlocked);
                    }
                    return this.achievements.filter(a => a.category === this.selectedCategory);
                },

                async equipBadge(code, action = null) {
                    if (this.equipping) return;
                    this.equipping = code;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                        const res = await fetch('/api/user-achievements/equip', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({ code: code, action: action, replace: true })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.stats.equipped_badges = data.equipped_badges || [];
                            this.stats.equipped_badges_details = data.equipped_badges_details || [];
                            this.stats.equipped_badge = data.equipped_badge || null;
                            
                            this.achievements.forEach(a => {
                                const idx = (data.equipped_badges || []).indexOf(a.code);
                                a.is_equipped = (idx !== -1);
                                a.equipped_slot = (idx !== -1) ? (idx + 1) : null;
                            });

                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message, type: 'success' }
                            }));
                            window.dispatchEvent(new CustomEvent('badge-equipped', {
                                detail: { 
                                    code: data.equipped_badge, 
                                    equipped_badges: data.equipped_badges,
                                    details: data.details, 
                                    equipped_badges_details: data.equipped_badges_details 
                                }
                            }));
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message || '{{ __('Gagal mengubah lencana.') }}', type: 'error' }
                            }));
                        }
                    } catch (e) {
                        console.error(e);
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: { message: '{{ __('Terjadi kesalahan jaringan.') }}', type: 'error' }
                        }));
                    } finally {
                        this.equipping = null;
                    }
                }
            }));
        }
    };

    if (window.Alpine) {
        registerAchievementModal();
    } else {
        document.addEventListener('alpine:init', registerAchievementModal);
    }
</script>

