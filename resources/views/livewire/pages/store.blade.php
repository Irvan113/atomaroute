<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public int $userCoins = 0;
    public string $activeFrame = '';
    public array $unlockedFrames = [];
    public string $activeTheme = 'default';
    public array $unlockedThemes = ['default'];
    public string $activeSound = 'default';
    public array $unlockedSounds = ['default'];
    public string $activeTab = 'all'; // 'all', 'boosters', 'frames', 'themes', 'sounds', 'inventory'

    public function mount()
    {
        $this->refreshUserData();
    }

    public function refreshUserData()
    {
        $user = auth()->user();
        $this->userCoins = $user->coins;
        $this->activeFrame = $user->active_frame ?? '';
        $this->unlockedFrames = $user->unlocked_frames ?? [];
        $this->activeTheme = $user->active_theme ?? 'default';
        $this->unlockedThemes = $user->unlocked_themes ?? ['default'];
        $this->activeSound = $user->active_sound_pack ?? 'default';
        $this->unlockedSounds = $user->unlocked_sound_packs ?? ['default'];
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    // --- 1. BOOSTER & UTILITY ---
    public function buyStreakCoupon()
    {
        $user = auth()->user();
        if ($user->streak_coupons >= 5) {
            $this->dispatch('toast', message: 'Kapasitas Streak Saver sudah maksimal (5/5)!', type: 'error');
            return;
        }

        if ($user->coins >= 500) {
            $user->coins -= 500;
            $user->streak_coupons += 1;
            $user->save();
            
            $this->refreshUserData();
            $this->dispatch('toast', message: 'Berhasil membeli 1x Streak Saver!', type: 'success');
            $this->dispatch('profile-updated');
        } else {
            $this->dispatch('toast', message: 'Koin tidak cukup! Selesaikan tugas untuk kumpulkan koin.', type: 'error');
        }
    }

    public function buyStreakRevive(int $price = 1200)
    {
        $user = auth()->user();
        if ($user->coins < $price) {
            $this->dispatch('toast', message: 'Koin tidak cukup untuk membeli Token Pemulihan Streak!', type: 'error');
            return;
        }

        if ($user->current_streak > 0) {
            $this->dispatch('toast', message: 'Streak kamu saat ini masih aktif! Token ini hanya untuk memulihkan streak yang putus.', type: 'warning');
            return;
        }

        $user->coins -= $price;
        $user->current_streak = max(1, min(7, (int) $user->longest_streak));
        $user->last_task_completed_date = now()->toDateString();
        $user->save();

        $this->refreshUserData();
        $this->dispatch('toast', message: '🔥 Streak berhasil dipulihkan kembali!', type: 'success');
        $this->dispatch('profile-updated');
    }

    // --- 2. AVATAR FRAMES ---
    public function buyFrame($itemId, $price)
    {
        $user = auth()->user();
        if ($user->coins < $price) {
            $this->dispatch('toast', message: 'Koin tidak cukup!', type: 'error');
            return;
        }

        $frames = $user->unlocked_frames ?? [];
        if (in_array($itemId, $frames)) {
            $this->dispatch('toast', message: 'Kamu sudah memiliki frame ini!', type: 'error');
            return;
        }

        $user->coins -= $price;
        $frames[] = $itemId;
        $user->unlocked_frames = $frames;
        $user->save();

        $this->refreshUserData();
        $this->dispatch('toast', message: 'Frame avatar berhasil dibeli!', type: 'success');
        $this->dispatch('profile-updated');
    }

    public function equipFrame($itemId)
    {
        $user = auth()->user();
        $unlocked = $user->unlocked_frames ?? [];
        if (!in_array($itemId, $unlocked)) return;

        if ($user->active_frame === $itemId) {
            $user->active_frame = null;
            $this->activeFrame = '';
            $message = 'Frame avatar dilepas!';
        } else {
            $user->active_frame = $itemId;
            $this->activeFrame = $itemId;
            $message = 'Frame avatar dipasang!';
        }
        
        $user->save();
        $this->refreshUserData();
        $this->dispatch('toast', message: $message, type: 'success');
        $this->dispatch('profile-updated');
    }

    // --- 3. THEMES (COMING SOON) ---
    public function buyTheme($themeId, $price)
    {
        $this->dispatch('toast', message: 'Koleksi tema visual sedang dalam tahap penyempurnaan (Coming Soon)!', type: 'info');
        return;
    }

    public function equipTheme($themeId)
    {
        $unlocked = $this->unlockedThemes;
        if (!in_array($themeId, $unlocked)) return;

        $user = auth()->user();
        $user->active_theme = $themeId;
        $user->save();

        $this->activeTheme = $themeId;
        $this->dispatch('js-apply-theme', theme: $themeId);
        $this->dispatch('toast', message: 'Tema tampilan berhasil dipasang!', type: 'success');
        $this->dispatch('profile-updated');
    }

    // --- 4. SOUND PACKS ---
    public function buySoundPack($soundId, $price)
    {
        $user = auth()->user();
        if ($user->coins < $price) {
            $this->dispatch('toast', message: 'Koin tidak cukup!', type: 'error');
            return;
        }

        $unlocked = $user->unlocked_sound_packs ?? ['default'];
        if (in_array($soundId, $unlocked)) return;

        $user->coins -= $price;
        $unlocked[] = $soundId;
        $user->unlocked_sound_packs = $unlocked;
        $user->save();

        $this->refreshUserData();
        $this->dispatch('toast', message: 'Sound Pack berhasil dibuka!', type: 'success');
        $this->dispatch('profile-updated');
    }

    public function equipSoundPack($soundId)
    {
        $user = auth()->user();
        $unlocked = $user->unlocked_sound_packs ?? ['default'];
        if (!in_array($soundId, $unlocked)) return;

        $user->active_sound_pack = $soundId;
        $user->save();

        $this->activeSound = $soundId;
        $this->dispatch('toast', message: 'Sound Pack berhasil dipasang!', type: 'success');
        $this->dispatch('profile-updated');
    }
}; ?>

<div class="min-h-screen bg-gray-50/70 dark:bg-gray-900 pb-24 overflow-y-auto"
     x-data="{
        previewTimer: null,
        previewMsg: '',
        previewTheme(themeName) {
            if (window.applyAppTheme) {
                const originalTheme = '{{ auth()->user()->active_theme ?? 'default' }}';
                window.applyAppTheme(themeName);
                this.previewMsg = 'Mencoba tema ' + themeName + ' (5 detik)...';
                if (this.previewTimer) clearTimeout(this.previewTimer);
                this.previewTimer = setTimeout(() => {
                    window.applyAppTheme(originalTheme);
                    this.previewMsg = '';
                }, 5000);
            }
        },
        playSound(preset) {
            if (window.AtomaAudio) {
                window.AtomaAudio.play(preset);
            }
        }
     }">

    <!-- Floating Theme Preview Banner -->
    <div x-show="previewMsg" 
         x-transition
         style="display: none;"
         class="fixed top-20 left-1/2 -translate-x-1/2 z-50 bg-gray-900/90 text-white backdrop-blur-md px-4 py-2 rounded-2xl shadow-2xl border border-white/20 text-xs font-black flex items-center gap-2">
        <span class="animate-spin">⏳</span>
        <span x-text="previewMsg"></span>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 sm:pt-10 space-y-8">
        
        <!-- STORE HEADER BANNER -->
        <div class="relative rounded-3xl overflow-hidden bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-amber-400 via-yellow-400 to-amber-500 p-0.5 shadow-lg flex items-center justify-center shrink-0">
                    <div class="w-full h-full bg-white dark:bg-gray-900 rounded-[14px] flex items-center justify-center text-3xl sm:text-4xl">
                        🪙
                    </div>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">
                            Reward Store ATOMA
                        </h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 dark:bg-amber-950/70 text-amber-800 dark:text-amber-300 border border-amber-300/40">
                            Toko Gamifikasi
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium max-w-xl">
                        Tukarkan koin hasil kerja kerasmu dengan booster produktivitas, frame avatar, tema tampilan, dan sound pack eksklusif.
                    </p>
                </div>
            </div>

            <!-- Balances & Active Status Block -->
            <div class="flex items-center gap-3 shrink-0 flex-wrap">
                <!-- Coins Pill -->
                <div class="px-4 py-3 rounded-2xl bg-gray-900 dark:bg-black border border-gray-800 dark:border-gray-700 shadow-lg text-center min-w-[130px]">
                    <div class="text-[10px] text-gray-400 uppercase font-black tracking-wider mb-0.5">Koin Kamu</div>
                    <div class="text-xl sm:text-2xl font-black text-amber-400 flex items-center justify-center gap-1 font-mono">
                        {{ number_format($userCoins) }} <span class="text-base">🪙</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- STORE CATEGORY & INVENTORY NAVIGATION TABS -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none text-xs sm:text-sm font-black">
            <button wire:click="setTab('all')" 
                    class="px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'all' ? 'bg-atoma-blue text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200/80 dark:border-gray-700/80' }}">
                <span>🌟</span> Semua Item
            </button>
            <button wire:click="setTab('boosters')" 
                    class="px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'boosters' ? 'bg-atoma-blue text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200/80 dark:border-gray-700/80' }}">
                <img src="{{ asset('images/icons/streak_saver.svg') }}" alt="Streak" class="w-4 h-4 object-contain inline-block">
                <span>Utilitas Streak</span>
            </button>
            <button wire:click="setTab('frames')" 
                    class="px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'frames' ? 'bg-atoma-blue text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200/80 dark:border-gray-700/80' }}">
                <span>🖼️</span> Frame Avatar
            </button>
            <button wire:click="setTab('themes')" 
                    class="px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'themes' ? 'bg-atoma-blue text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200/80 dark:border-gray-700/80' }}">
                <span>🎨</span> Tema Tampilan
                <span class="px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 border border-amber-300/40">Coming Soon</span>
            </button>
            <button wire:click="setTab('sounds')" 
                    class="px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'sounds' ? 'bg-atoma-blue text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200/80 dark:border-gray-700/80' }}">
                <span>🔊</span> Sound Packs
            </button>
            
            <div class="h-6 w-px bg-gray-300 dark:bg-gray-700 mx-1 shrink-0"></div>

            <button wire:click="setTab('inventory')" 
                    class="px-4 py-2.5 rounded-2xl transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'inventory' ? 'bg-purple-600 text-white shadow-md' : 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/60 border border-purple-200 dark:border-purple-800/60' }}">
                <span>🎒</span> Inventori Saya
                <span class="px-1.5 py-0.2 rounded-full bg-purple-200 dark:bg-purple-900 text-[10px] font-black">
                    {{ count($unlockedFrames) + count($unlockedThemes) + count($unlockedSounds) }}
                </span>
            </button>
        </div>

        <!-- TAB CONTENT: INVENTORI SAYA -->
        @if($activeTab === 'inventory')
            <div class="space-y-6">
                <!-- Active Wardrobe Summary Header -->
                <div class="p-6 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🪞</span>
                            <div>
                                <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white">Perlengkapan Aktif Kamu</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Ringkasan kosmetik dan utilitas yang sedang terpasang di profilmu.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- 1. Active Frame -->
                        <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-750 border border-gray-200/70 dark:border-gray-700 flex flex-col items-center text-center justify-between min-h-[140px]">
                            <div class="text-[10px] uppercase font-black tracking-wider text-indigo-600 dark:text-indigo-400 mb-1">Frame Avatar</div>
                            @if($activeFrame)
                                <div class="relative w-12 h-12 aspect-square shrink-0 my-1 flex items-center justify-center">
                                    @if($activeFrame === 'frame_gold')
                                        <div class="absolute -top-[28%] left-1/2 -translate-x-1/2 w-[55%] z-20 pointer-events-none drop-shadow-md">
                                            <img src="{{ asset('images/frames/crown_gold.svg') }}" alt="Crown" class="w-full h-auto object-contain">
                                        </div>
                                        <img src="{{ auth()->user()->avatar_url }}" class="w-12 h-12 aspect-square rounded-full border-2 border-amber-400 ring-2 ring-yellow-300/80 shadow-[0_0_12px_rgba(245,158,11,0.8)] object-cover bg-white">
                                    @elseif($activeFrame === 'frame_neon')
                                        <img src="{{ auth()->user()->avatar_url }}" class="w-12 h-12 aspect-square rounded-full border-2 border-cyan-400 ring-2 ring-cyan-400/50 shadow-[0_0_10px_rgba(6,182,212,0.85)] object-cover bg-white">
                                    @elseif($activeFrame === 'frame_fire')
                                        <img src="{{ auth()->user()->avatar_url }}" class="w-12 h-12 aspect-square rounded-full border-2 border-orange-500 ring-2 ring-amber-500/50 shadow-[0_0_10px_rgba(249,115,22,0.85)] animate-pulse object-cover bg-white">
                                    @endif
                                </div>
                                <div class="text-xs font-black text-gray-900 dark:text-white capitalize mb-2">
                                    {{ str_replace('frame_', '', $activeFrame) }} Frame
                                </div>
                                <button wire:click="equipFrame('{{ $activeFrame }}')" class="px-3 py-1 rounded-xl text-[11px] font-black bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-950/60 dark:text-red-300 transition-all cursor-pointer">
                                    ✕ Lepas Frame
                                </button>
                            @else
                                <div class="text-xs text-gray-400 my-auto">Belum ada frame terpasang</div>
                                <button wire:click="setTab('frames')" class="px-3 py-1 rounded-xl text-[11px] font-black bg-atoma-blue text-white transition-all cursor-pointer">
                                    Pilih Frame
                                </button>
                            @endif
                        </div>

                        <!-- 2. Active Theme -->
                        <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-750 border border-gray-200/70 dark:border-gray-700 flex flex-col items-center text-center justify-between min-h-[140px]">
                            <div class="text-[10px] uppercase font-black tracking-wider text-cyan-600 dark:text-cyan-400 mb-1">Tema Tampilan</div>
                            <div class="text-xs font-black text-gray-900 dark:text-white capitalize mb-2">
                                {{ $activeTheme }}
                            </div>
                            <button wire:click="setTab('themes')" class="px-3 py-1 rounded-xl text-[11px] font-black bg-gray-200 dark:bg-gray-650 hover:bg-gray-300 text-gray-800 dark:text-gray-200 transition-all cursor-pointer">
                                Ganti Tema
                            </button>
                        </div>

                        <!-- 3. Active Sound Pack -->
                        <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-750 border border-gray-200/70 dark:border-gray-700 flex flex-col items-center text-center justify-between min-h-[140px]">
                            <div class="text-[10px] uppercase font-black tracking-wider text-purple-600 dark:text-purple-400 mb-1">Sound Pack</div>
                            <div class="text-xs font-black text-gray-900 dark:text-white capitalize mb-1">
                                {{ str_replace('_', ' ', $activeSound) }}
                            </div>
                            <div class="flex items-center gap-1.5 mt-auto">
                                <button @click="playSound('{{ $activeSound }}')" class="p-1.5 rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300 text-xs font-bold" title="Tes Suara">
                                    ▶ Tes
                                </button>
                                <button wire:click="setTab('sounds')" class="px-2.5 py-1 rounded-xl text-[11px] font-black bg-gray-200 dark:bg-gray-650 hover:bg-gray-300 text-gray-800 dark:text-gray-200 transition-all cursor-pointer">
                                    Ganti
                                </button>
                            </div>
                        </div>

                        <!-- 4. Consumable Status -->
                        <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-750 border border-gray-200/70 dark:border-gray-700 flex flex-col items-center text-center justify-between min-h-[140px]">
                            <div class="text-[10px] uppercase font-black tracking-wider text-emerald-600 dark:text-emerald-400 mb-1">Streak Saver</div>
                            <div class="flex items-center gap-1.5 text-lg font-black text-gray-900 dark:text-white font-mono my-auto">
                                <img src="{{ asset('images/icons/streak_saver.svg') }}" alt="Streak Saver" class="w-5 h-5 object-contain inline-block">
                                <span>{{ auth()->user()->streak_coupons }} / 5</span>
                            </div>
                            <button wire:click="setTab('boosters')" class="px-3 py-1 rounded-xl text-[11px] font-black bg-emerald-600 text-white transition-all cursor-pointer">
                                Beli Kupon
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Unlocked Wardrobe Collection List -->
                <div class="p-6 rounded-3xl bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shadow-md space-y-4">
                    <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white">
                        Daftar Koleksi yang Dimiliki
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Frames owned -->
                        @foreach($unlockedFrames as $frameId)
                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-750 border border-gray-200/70 dark:border-gray-700 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">🖼️</span>
                                    <div>
                                        <div class="text-xs font-black text-gray-900 dark:text-white capitalize">{{ str_replace('frame_', '', $frameId) }} Frame</div>
                                        <div class="text-[10px] text-gray-400">Frame Avatar</div>
                                    </div>
                                </div>
                                <button wire:click="equipFrame('{{ $frameId }}')"
                                        class="px-3 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $activeFrame === $frameId ? 'bg-amber-500 text-white shadow-xs' : 'bg-gray-200 dark:bg-gray-650 hover:bg-atoma-blue hover:text-white text-gray-800 dark:text-gray-200' }}">
                                    {{ $activeFrame === $frameId ? 'Terpasang ✨' : 'Pasang' }}
                                </button>
                            </div>
                        @endforeach

                        <!-- Themes owned -->
                        @foreach($unlockedThemes as $themeId)
                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-750 border border-gray-200/70 dark:border-gray-700 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">🎨</span>
                                    <div>
                                        <div class="text-xs font-black text-gray-900 dark:text-white capitalize">{{ $themeId }} Theme</div>
                                        <div class="text-[10px] text-gray-400">Tema Tampilan</div>
                                    </div>
                                </div>
                                <button @click="applyAppTheme('{{ $themeId }}'); $wire.equipTheme('{{ $themeId }}')"
                                        class="px-3 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $activeTheme === $themeId ? 'bg-amber-500 text-white shadow-xs' : 'bg-gray-200 dark:bg-gray-650 hover:bg-atoma-blue hover:text-white text-gray-800 dark:text-gray-200' }}">
                                    {{ $activeTheme === $themeId ? 'Terpasang ✨' : 'Pasang' }}
                                </button>
                            </div>
                        @endforeach

                        <!-- Sounds owned -->
                        @foreach($unlockedSounds as $soundId)
                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-750 border border-gray-200/70 dark:border-gray-700 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <button @click="playSound('{{ $soundId }}')" class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 flex items-center justify-center font-bold text-xs" title="Tes Suara">
                                        ▶
                                    </button>
                                    <div>
                                        <div class="text-xs font-black text-gray-900 dark:text-white capitalize">{{ str_replace('_', ' ', $soundId) }}</div>
                                        <div class="text-[10px] text-gray-400">Sound Pack</div>
                                    </div>
                                </div>
                                <button wire:click="equipSoundPack('{{ $soundId }}')"
                                        class="px-3 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer {{ $activeSound === $soundId ? 'bg-amber-500 text-white shadow-xs' : 'bg-gray-200 dark:bg-gray-650 hover:bg-atoma-blue hover:text-white text-gray-800 dark:text-gray-200' }}">
                                    {{ $activeSound === $soundId ? 'Terpasang ✨' : 'Pasang' }}
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <!-- STORE CATALOG GRID -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                
                <!-- ================= 1. BOOSTER & UTILITY ================= -->
                @if(in_array($activeTab, ['all', 'boosters']))
                    
                    <!-- Item: Streak Saver -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center relative overflow-hidden">
                                <div class="w-24 h-24 drop-shadow-xl group-hover:scale-110 transition-transform duration-300 flex items-center justify-center p-2">
                                    <img src="{{ asset('images/icons/streak_saver.svg') }}" alt="Streak Saver" class="w-full h-full object-contain">
                                </div>
                                <div class="absolute top-4 left-4 bg-emerald-950/40 backdrop-blur-md rounded-full px-2.5 py-0.5 text-emerald-200 font-bold text-[10px] border border-emerald-300/30 uppercase tracking-wider">
                                    Common
                                </div>
                                <div class="absolute top-4 right-4 bg-white/20 backdrop-blur-md rounded-full px-3 py-1 text-white font-black text-[11px] border border-white/20">
                                    Maks 5 Kupon
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Streak Saver</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        500 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Melindungi streak dari reset! Otomatis membekukan hari jika kamu melewatkan target harian.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            @php $couponDisabled = $userCoins < 500 || auth()->user()->streak_coupons >= 5; @endphp
                            <button wire:click="buyStreakCoupon" 
                                    class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $couponDisabled ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-500/20' }}"
                                    {{ $couponDisabled ? 'disabled' : '' }}>
                                {{ $userCoins < 500 ? 'Koin Kurang (Butuh 500 🪙)' : (auth()->user()->streak_coupons >= 5 ? 'Kapasitas Penuh (5/5)' : 'Beli untuk 500 🪙') }}
                            </button>
                        </div>
                    </div>

                    <!-- Item: Token Pemulihan Streak -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gradient-to-br from-rose-500 to-amber-500 flex items-center justify-center relative overflow-hidden">
                                <div class="text-6xl drop-shadow-md group-hover:scale-110 transition-transform duration-300">💖</div>
                                <div class="absolute top-4 left-4 bg-rose-950/40 backdrop-blur-md rounded-full px-2.5 py-0.5 text-rose-200 font-bold text-[10px] border border-rose-300/30 uppercase tracking-wider">
                                    Legendary
                                </div>
                                <div class="absolute top-4 right-4 bg-white/20 backdrop-blur-md rounded-full px-3 py-1 text-white font-black text-[11px] border border-white/20">
                                    Penyelamat
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Token Pemulih Streak</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        1,200 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Menghidupkan kembali streak kamu yang sempat putus dan langsung menyalakan apimu kembali!
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            @php $reviveDisabled = $userCoins < 1200; @endphp
                            <button wire:click="buyStreakRevive(1200)" 
                                    class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $reviveDisabled ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' : 'bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white shadow-md' }}"
                                    {{ $reviveDisabled ? 'disabled' : '' }}>
                                {{ $reviveDisabled ? 'Koin Kurang (Butuh 1,200 🪙)' : 'Pulihkan Streak (1,200 🪙)' }}
                            </button>
                        </div>
                    </div>

                @endif

                <!-- ================= 2. FRAME AVATAR ================= -->
                @if(in_array($activeTab, ['all', 'frames']))
                    
                    <!-- Frame: Neon Glow -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <!-- Live Avatar Preview Deck -->
                            <div class="h-44 bg-gray-950 flex flex-col items-center justify-center relative overflow-hidden">
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-indigo-900/50 via-gray-950 to-gray-950"></div>
                                <div class="relative z-10 w-20 h-20 rounded-full border-2 border-indigo-400 shadow-[0_0_20px_rgba(99,102,241,0.8)] p-0.5 group-hover:scale-105 transition-transform duration-300">
                                    <img src="{{ auth()->user()->avatar_url }}" alt="Avatar" class="w-full h-full rounded-full object-cover bg-white">
                                </div>
                                <div class="absolute top-4 left-4 bg-indigo-950/40 backdrop-blur-md rounded-full px-2.5 py-0.5 text-indigo-200 font-bold text-[10px] border border-indigo-300/30 uppercase tracking-wider">
                                    Rare
                                </div>
                                <div class="absolute top-4 right-4 bg-white/10 backdrop-blur-md rounded-full px-3 py-1 text-white font-bold text-[11px] border border-white/10">
                                    Live Preview
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Neon Glow Frame</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        1,000 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Aura biru neon futuristik yang bersinar cerah mengelilingi foto profilmu di profil &amp; leaderboard.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            @if(in_array('frame_neon', $unlockedFrames))
                                <button wire:click="equipFrame('frame_neon')" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $activeFrame === 'frame_neon' ? 'bg-amber-500 text-white shadow-xs' : 'bg-gray-100 hover:bg-indigo-600 hover:text-white dark:bg-gray-700 text-gray-800 dark:text-gray-200' }}">
                                    {{ $activeFrame === 'frame_neon' ? 'Terpasang ✨ (Klik untuk Lepas)' : 'Pasang di Profil' }}
                                </button>
                            @else
                                @php $neonDisabled = $userCoins < 1000; @endphp
                                <button wire:click="buyFrame('frame_neon', 1000)" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $neonDisabled ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-md' }}"
                                        {{ $neonDisabled ? 'disabled' : '' }}>
                                    {{ $neonDisabled ? 'Koin Kurang (Butuh 1,000 🪙)' : 'Beli Frame (1,000 🪙)' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Frame: Fire Ring -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gray-950 flex flex-col items-center justify-center relative overflow-hidden">
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-orange-950/60 via-gray-950 to-gray-950"></div>
                                <div class="relative z-10 w-20 h-20 rounded-full border-4 border-orange-500 shadow-[0_0_20px_rgba(249,115,22,0.8)] animate-pulse p-0.5 group-hover:scale-105 transition-transform duration-300">
                                    <img src="{{ auth()->user()->avatar_url }}" alt="Avatar" class="w-full h-full rounded-full object-cover bg-white">
                                </div>
                                <div class="absolute top-4 left-4 bg-orange-950/40 backdrop-blur-md rounded-full px-2.5 py-0.5 text-orange-200 font-bold text-[10px] border border-orange-300/30 uppercase tracking-wider">
                                    Epic
                                </div>
                                <div class="absolute top-4 right-4 bg-white/10 backdrop-blur-md rounded-full px-3 py-1 text-white font-bold text-[11px] border border-white/10">
                                    Live Preview
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Fire Ring Frame</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        2,500 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Lingkaran api menyala-nyala yang berdenyut garang. Tunjukkan ke semua orang bahwa kamu sedang membara!
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            @if(in_array('frame_fire', $unlockedFrames))
                                <button wire:click="equipFrame('frame_fire')" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $activeFrame === 'frame_fire' ? 'bg-amber-500 text-white shadow-xs' : 'bg-gray-100 hover:bg-orange-600 hover:text-white dark:bg-gray-700 text-gray-800 dark:text-gray-200' }}">
                                    {{ $activeFrame === 'frame_fire' ? 'Terpasang ✨ (Klik untuk Lepas)' : 'Pasang di Profil' }}
                                </button>
                            @else
                                @php $fireDisabled = $userCoins < 2500; @endphp
                                <button wire:click="buyFrame('frame_fire', 2500)" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $fireDisabled ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' : 'bg-orange-600 hover:bg-orange-500 text-white shadow-md' }}"
                                        {{ $fireDisabled ? 'disabled' : '' }}>
                                    {{ $fireDisabled ? 'Koin Kurang (Butuh 2,500 🪙)' : 'Beli Frame (2,500 🪙)' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Frame: Gold Crown (Kaisar) -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gray-950 flex flex-col items-center justify-center relative overflow-hidden">
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-amber-900/60 via-gray-950 to-gray-950"></div>
                                <div class="relative z-10 w-20 h-20 aspect-square shrink-0 rounded-full border-4 border-amber-400 ring-2 ring-yellow-300/80 shadow-[0_0_25px_rgba(250,204,21,0.8)] p-0.5 group-hover:scale-105 transition-transform duration-300">
                                    <div class="absolute -top-[28%] left-1/2 -translate-x-1/2 w-[55%] z-20 pointer-events-none drop-shadow-xl">
                                        <img src="{{ asset('images/frames/crown_gold.svg') }}" alt="Crown" class="w-full h-auto object-contain">
                                    </div>
                                    <img src="{{ auth()->user()->avatar_url }}" alt="Avatar" class="w-full h-full aspect-square rounded-full object-cover bg-white">
                                </div>
                                <div class="absolute top-4 left-4 bg-amber-950/40 backdrop-blur-md rounded-full px-2.5 py-0.5 text-amber-200 font-bold text-[10px] border border-amber-300/30 uppercase tracking-wider">
                                    Legendary
                                </div>
                                <div class="absolute top-4 right-4 bg-white/10 backdrop-blur-md rounded-full px-3 py-1 text-white font-bold text-[11px] border border-white/10">
                                    Live Preview
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Mahkota Emas Kaisar</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        3,500 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Mahkota emas kerajaan dengan pendaran keemasan solar berkilau. Simbol supremasi penguasa leaderboard!
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            @if(in_array('frame_gold', $unlockedFrames))
                                <button wire:click="equipFrame('frame_gold')" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $activeFrame === 'frame_gold' ? 'bg-amber-500 text-white shadow-xs' : 'bg-gray-100 hover:bg-amber-500 hover:text-white dark:bg-gray-700 text-gray-800 dark:text-gray-200' }}">
                                    {{ $activeFrame === 'frame_gold' ? 'Terpasang ✨ (Klik untuk Lepas)' : 'Pasang di Profil' }}
                                </button>
                            @else
                                @php $goldDisabled = $userCoins < 3500; @endphp
                                <button wire:click="buyFrame('frame_gold', 3500)" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $goldDisabled ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' : 'bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-600 hover:to-yellow-600 text-yellow-950 shadow-md font-black' }}"
                                        {{ $goldDisabled ? 'disabled' : '' }}>
                                    {{ $goldDisabled ? 'Koin Kurang (Butuh 3,500 🪙)' : 'Beli Frame (3,500 🪙)' }}
                                </button>
                            @endif
                        </div>
                    </div>

                @endif

                <!-- ================= 3. TEMA TAMPILAN ================= -->
                @if(in_array($activeTab, ['all', 'themes']))
                    
                    <!-- Coming Soon Banner for Themes -->
                    <div class="col-span-full bg-gradient-to-r from-purple-950/60 via-slate-900 to-indigo-950/60 border border-purple-500/30 rounded-3xl p-5 sm:p-6 mb-2 flex items-center justify-between gap-4 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-purple-500/20 border border-purple-400/30 flex items-center justify-center text-2xl shrink-0">
                                🔒
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                    <h3 class="text-base sm:text-lg font-black text-white">Koleksi Tema Tampilan</h3>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400/20 text-amber-300 border border-amber-400/30">
                                        Coming Soon
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-300 font-medium">
                                    Koleksi tema visual sedang disempurnakan agar tampilan ATOMA lebih estetik dan memukau. Kamu tetap bisa mencoba intip 5 detik!
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Theme: Cyberpunk Neon -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gradient-to-br from-cyan-900 via-gray-900 to-pink-950 flex items-center justify-center relative overflow-hidden">
                                <div class="text-6xl drop-shadow-[0_0_15px_rgba(6,182,212,0.8)] group-hover:scale-110 transition-transform duration-300">🏙️</div>
                                <div class="absolute top-4 left-4 bg-gray-900/60 backdrop-blur-md rounded-full px-2.5 py-0.5 text-amber-300 font-bold text-[10px] border border-amber-300/30 uppercase tracking-wider flex items-center gap-1">
                                    <span>🔒</span> Coming Soon
                                </div>
                                <button @click="previewTheme('cyberpunk')" class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-full px-2.5 py-1 text-white font-bold text-[10px] border border-white/30 transition-all cursor-pointer">
                                    👁️ Intip 5s
                                </button>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Cyberpunk Neon</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        1,500 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Suasana futuristik metropolitan beraksen Electric Cyan &amp; Neon Pink yang tajam dan berkarakter.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            <button disabled class="w-full py-3 rounded-xl font-black text-xs sm:text-sm bg-gray-100 text-gray-400 dark:bg-gray-700/60 dark:text-gray-400 cursor-not-allowed border border-gray-200 dark:border-gray-600 flex items-center justify-center gap-2">
                                <span>🔒</span>
                                <span>Segera Hadir (Coming Soon)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Theme: Lofi Sunset -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gradient-to-br from-amber-900 via-rose-950 to-stone-900 flex items-center justify-center relative overflow-hidden">
                                <div class="text-6xl drop-shadow-[0_0_15px_rgba(245,158,11,0.8)] group-hover:scale-110 transition-transform duration-300">🌅</div>
                                <div class="absolute top-4 left-4 bg-gray-900/60 backdrop-blur-md rounded-full px-2.5 py-0.5 text-amber-300 font-bold text-[10px] border border-amber-300/30 uppercase tracking-wider flex items-center gap-1">
                                    <span>🔒</span> Coming Soon
                                </div>
                                <button @click="previewTheme('lofi')" class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-full px-2.5 py-1 text-white font-bold text-[10px] border border-white/30 transition-all cursor-pointer">
                                    👁️ Intip 5s
                                </button>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Lofi Sunset</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        1,200 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Nuansa hangat senja yang damai dan menenangkan untuk sesi belajar maupun deep work.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            <button disabled class="w-full py-3 rounded-xl font-black text-xs sm:text-sm bg-gray-100 text-gray-400 dark:bg-gray-700/60 dark:text-gray-400 cursor-not-allowed border border-gray-200 dark:border-gray-600 flex items-center justify-center gap-2">
                                <span>🔒</span>
                                <span>Segera Hadir (Coming Soon)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Theme: Emerald Forest -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gradient-to-br from-emerald-950 via-teal-900 to-slate-900 flex items-center justify-center relative overflow-hidden">
                                <div class="text-6xl drop-shadow-[0_0_15px_rgba(16,185,129,0.8)] group-hover:scale-110 transition-transform duration-300">🌲</div>
                                <div class="absolute top-4 left-4 bg-gray-900/60 backdrop-blur-md rounded-full px-2.5 py-0.5 text-amber-300 font-bold text-[10px] border border-amber-300/30 uppercase tracking-wider flex items-center gap-1">
                                    <span>🔒</span> Coming Soon
                                </div>
                                <button @click="previewTheme('emerald')" class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-full px-2.5 py-1 text-white font-bold text-[10px] border border-white/30 transition-all cursor-pointer">
                                    👁️ Intip 5s
                                </button>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Emerald Forest</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        1,200 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Kesegaran hijau hutan zamrud alami untuk ketenangan mata dan stamina produktivitas tinggi.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            <button disabled class="w-full py-3 rounded-xl font-black text-xs sm:text-sm bg-gray-100 text-gray-400 dark:bg-gray-700/60 dark:text-gray-400 cursor-not-allowed border border-gray-200 dark:border-gray-600 flex items-center justify-center gap-2">
                                <span>🔒</span>
                                <span>Segera Hadir (Coming Soon)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Theme: Midnight AMOLED -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-black flex items-center justify-center relative overflow-hidden border-b border-purple-900/40">
                                <div class="text-6xl drop-shadow-[0_0_20px_rgba(139,92,246,0.8)] group-hover:scale-110 transition-transform duration-300">🌌</div>
                                <div class="absolute top-4 left-4 bg-gray-900/60 backdrop-blur-md rounded-full px-2.5 py-0.5 text-amber-300 font-bold text-[10px] border border-amber-300/30 uppercase tracking-wider flex items-center gap-1">
                                    <span>🔒</span> Coming Soon
                                </div>
                                <button @click="previewTheme('midnight')" class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-full px-2.5 py-1 text-white font-bold text-[10px] border border-white/30 transition-all cursor-pointer">
                                    👁️ Intip 5s
                                </button>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Midnight AMOLED</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        1,400 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Warna hitam pekat murni (Pure Black #000) dengan aksen violet futuristik. Sangat hemat baterai &amp; ramah mata malam hari.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            <button disabled class="w-full py-3 rounded-xl font-black text-xs sm:text-sm bg-gray-100 text-gray-400 dark:bg-gray-700/60 dark:text-gray-400 cursor-not-allowed border border-gray-200 dark:border-gray-600 flex items-center justify-center gap-2">
                                <span>🔒</span>
                                <span>Segera Hadir (Coming Soon)</span>
                            </button>
                        </div>
                    </div>

                @endif

                <!-- ================= 4. SOUND PACKS ================= -->
                @if(in_array($activeTab, ['all', 'sounds']))
                    
                    <!-- Sound Pack: Retro 8-Bit Arcade -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gradient-to-br from-indigo-900 via-purple-950 to-pink-900 flex items-center justify-center relative overflow-hidden">
                                <div class="text-6xl drop-shadow-[0_0_15px_rgba(168,85,247,0.8)] group-hover:scale-110 transition-transform duration-300">🕹️</div>
                                <div class="absolute top-4 left-4 bg-purple-950/40 backdrop-blur-md rounded-full px-2.5 py-0.5 text-purple-200 font-bold text-[10px] border border-purple-300/30 uppercase tracking-wider">
                                    Rare
                                </div>
                                <button @click="playSound('retro_8bit')" class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-full px-2.5 py-1 text-white font-bold text-[10px] border border-white/30 transition-all cursor-pointer flex items-center gap-1">
                                    <span>▶</span> Tes Suara
                                </button>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Retro 8-Bit Chiptune</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        800 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Efek arpeggio chiptune konsol retro 8-bit yang renyah dan penuh nostalgi saat tugasmu diselesaikan.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            @if(in_array('retro_8bit', $unlockedSounds))
                                <button wire:click="equipSoundPack('retro_8bit')" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $activeSound === 'retro_8bit' ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-200' }}">
                                    {{ $activeSound === 'retro_8bit' ? 'Suara Aktif 🔊' : 'Pasang Sound Pack' }}
                                </button>
                            @else
                                @php $retroDisabled = $userCoins < 800; @endphp
                                <button wire:click="buySoundPack('retro_8bit', 800)" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $retroDisabled ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' : 'bg-purple-600 hover:bg-purple-500 text-white shadow-md' }}"
                                        {{ $retroDisabled ? 'disabled' : '' }}>
                                    {{ $retroDisabled ? 'Koin Kurang (Butuh 800 🪙)' : 'Beli Sound (800 🪙)' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Sound Pack: Cyberpunk Synth -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gradient-to-br from-cyan-950 via-slate-900 to-indigo-950 flex items-center justify-center relative overflow-hidden">
                                <div class="text-6xl drop-shadow-[0_0_15px_rgba(6,182,212,0.8)] group-hover:scale-110 transition-transform duration-300">🔮</div>
                                <div class="absolute top-4 left-4 bg-cyan-950/40 backdrop-blur-md rounded-full px-2.5 py-0.5 text-cyan-200 font-bold text-[10px] border border-cyan-300/30 uppercase tracking-wider">
                                    Epic
                                </div>
                                <button @click="playSound('cyber_synth')" class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-full px-2.5 py-1 text-white font-bold text-[10px] border border-white/30 transition-all cursor-pointer flex items-center gap-1">
                                    <span>▶</span> Tes Suara
                                </button>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Cyberpunk Synth</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        1,000 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Sapuan frekuensi laser sintetis futuristik dengan resonansi filter sweep modern berkelas tinggi.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            @if(in_array('cyber_synth', $unlockedSounds))
                                <button wire:click="equipSoundPack('cyber_synth')" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $activeSound === 'cyber_synth' ? 'bg-cyan-600 text-white shadow-md shadow-cyan-500/30' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-200' }}">
                                    {{ $activeSound === 'cyber_synth' ? 'Suara Aktif 🔊' : 'Pasang Sound Pack' }}
                                </button>
                            @else
                                @php $synthDisabled = $userCoins < 1000; @endphp
                                <button wire:click="buySoundPack('cyber_synth', 1000)" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $synthDisabled ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' : 'bg-cyan-600 hover:bg-cyan-500 text-white shadow-md' }}"
                                        {{ $synthDisabled ? 'disabled' : '' }}>
                                    {{ $synthDisabled ? 'Koin Kurang (Butuh 1,000 🪙)' : 'Beli Sound (1,000 🪙)' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Sound Pack: Zen Bell (Singing Bowl) -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden group hover:shadow-xl transition-all flex flex-col justify-between">
                        <div>
                            <div class="h-44 bg-gradient-to-br from-amber-900 via-stone-900 to-emerald-950 flex items-center justify-center relative overflow-hidden">
                                <div class="text-6xl drop-shadow-[0_0_15px_rgba(245,158,11,0.8)] group-hover:scale-110 transition-transform duration-300">🔔</div>
                                <div class="absolute top-4 left-4 bg-amber-950/40 backdrop-blur-md rounded-full px-2.5 py-0.5 text-amber-200 font-bold text-[10px] border border-amber-300/30 uppercase tracking-wider">
                                    Rare
                                </div>
                                <button @click="playSound('zen_bell')" class="absolute top-4 right-4 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-full px-2.5 py-1 text-white font-bold text-[10px] border border-white/30 transition-all cursor-pointer flex items-center gap-1">
                                    <span>▶</span> Tes Suara
                                </button>
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Genta Zen Harmoni</h3>
                                    <div class="bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 font-black px-2.5 py-1 rounded-xl text-xs border border-amber-300/50 flex items-center font-mono">
                                        900 🪙
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed mb-4">
                                    Denting singing bowl Tibet yang tenang dan beresonansi panjang untuk pikiran rileks dan jernih.
                                </p>
                            </div>
                        </div>

                        <div class="p-6 pt-0">
                            @if(in_array('zen_bell', $unlockedSounds))
                                <button wire:click="equipSoundPack('zen_bell')" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $activeSound === 'zen_bell' ? 'bg-amber-600 text-white shadow-md shadow-amber-500/30' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-200' }}">
                                    {{ $activeSound === 'zen_bell' ? 'Suara Aktif 🔊' : 'Pasang Sound Pack' }}
                                </button>
                            @else
                                @php $zenDisabled = $userCoins < 900; @endphp
                                <button wire:click="buySoundPack('zen_bell', 900)" 
                                        class="w-full py-3 rounded-xl font-black text-xs sm:text-sm transition-all cursor-pointer {{ $zenDisabled ? 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed' : 'bg-amber-600 hover:bg-amber-500 text-white shadow-md' }}"
                                        {{ $zenDisabled ? 'disabled' : '' }}>
                                    {{ $zenDisabled ? 'Koin Kurang (Butuh 900 🪙)' : 'Beli Sound (900 🪙)' }}
                                </button>
                            @endif
                        </div>
                    </div>

                @endif

            </div>
        @endif

    </div>
</div>
