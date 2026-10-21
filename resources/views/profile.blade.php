<x-app-layout>
    <div class="min-h-screen bg-gray-50/70 dark:bg-gray-900 pb-20 pt-4 sm:pt-6"
         x-data="{
            activeTab: (new URLSearchParams(window.location.search)).get('tab') || 'account',
            setTab(tab) {
                this.activeTab = tab;
                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url);
            },

            // Task Preferences
            inputMode: localStorage.getItem('atoma_task_input_mode') || 'both',
            mobileFab: localStorage.getItem('atoma_task_mobile_fab') || 'center',
            autoHide: localStorage.getItem('atoma_task_autohide') !== 'false',
            subtaskMode: localStorage.getItem('atoma_task_subtask_mode') || 'list',
            saveConfig() {
                localStorage.setItem('atoma_task_input_mode', this.inputMode);
                localStorage.setItem('atoma_task_mobile_fab', this.mobileFab);
                localStorage.setItem('atoma_task_autohide', this.autoHide);
                localStorage.setItem('atoma_task_subtask_mode', this.subtaskMode);
                if (window.Alpine && Alpine.store('taskConfig')) {
                    Alpine.store('taskConfig').inputMode = this.inputMode;
                    Alpine.store('taskConfig').mobileFab = this.mobileFab;
                    Alpine.store('taskConfig').autoHide = this.autoHide;
                    Alpine.store('taskConfig').subtaskMode = this.subtaskMode;
                }
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{{ __('Preferensi tugas berhasil disimpan!') }}', type: 'success' } }));
            },

            // Sound Preferences
            soundEnabled: localStorage.getItem('atoma_sound_enabled') !== 'false',
            soundPreset: localStorage.getItem('atoma_notification_sound') || 'chime',
            soundVolume: parseFloat(localStorage.getItem('atoma_notification_volume') || '0.7'),
            customSoundName: localStorage.getItem('atoma_custom_sound_name') || '',
            customSoundUrl: localStorage.getItem('atoma_custom_sound_url') || '',
            hasCustomSound: !!(localStorage.getItem('atoma_custom_sound_data') || localStorage.getItem('atoma_custom_sound_url')),
            customMode: 'file',
            isProcessing: false,
            optimizationStats: '',
            saveSoundSettings() {
                localStorage.setItem('atoma_sound_enabled', this.soundEnabled);
                localStorage.setItem('atoma_notification_sound', this.soundPreset);
                localStorage.setItem('atoma_notification_volume', this.soundVolume);
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{{ __('Pengaturan suara notifikasi berhasil disimpan!') }}', type: 'success' } }));
            },
            testSound(preset) {
                if (window.AtomaAudio) {
                    window.AtomaAudio.play(preset, this.soundVolume);
                }
            },
            async handleFileUpload(event) {
                const file = event.target.files[0];
                if (!file) return;

                if (file.size > 3.0 * 1024 * 1024) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{{ __('Ukuran file maksimal 3 MB untuk nada notifikasi.') }}', type: 'error' } }));
                    return;
                }
                
                this.isProcessing = true;
                this.optimizationStats = '{{ __('Memproses audio jernih...') }}';

                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    const ctx = new AudioCtx();
                    const arrayBuffer = await file.arrayBuffer();
                    const decoded = await ctx.decodeAudioData(arrayBuffer);
                    const realDuration = decoded.duration;

                    // If file is under 2.5MB, preserve original encoded audio (MP3/WAV/OGG) to maintain 100% sound fidelity & exact duration
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const base64 = e.target.result;
                        localStorage.removeItem('atoma_custom_sound_url');
                        localStorage.setItem('atoma_custom_sound_data', base64);
                        
                        const origKb = (file.size / 1024).toFixed(1);
                        const durSec = realDuration.toFixed(1);
                        const nameStr = `${file.name.substring(0, 18)} (${durSec}s • ${origKb} KB)`;
                        const statsMsg = `✅ Suara jernih original (${durSec}s • ${origKb} KB)`;
                        
                        localStorage.setItem('atoma_custom_sound_name', nameStr);
                        this.customSoundName = nameStr;
                        this.optimizationStats = statsMsg;
                        this.hasCustomSound = true;
                        this.soundPreset = 'custom';
                        this.isProcessing = false;
                        this.saveSoundSettings();
                        this.testSound('custom');
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: `Audio kustom aktif (${durSec} detik)!`, type: 'success' } }));
                    };
                    reader.readAsDataURL(file);
                } catch (err) {
                    console.error('Audio load error:', err);
                    this.isProcessing = false;
                    this.optimizationStats = '';
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{{ __('Gagal membaca file audio. Pastikan format MP3, WAV, atau OGG valid.') }}', type: 'error' } }));
                }
            },
            encodeWAV(samples, sampleRate) {
                const buffer = new ArrayBuffer(44 + samples.length * 2);
                const view = new DataView(buffer);

                const writeString = (view, offset, string) => {
                    for (let i = 0; i < string.length; i++) {
                        view.setUint8(offset + i, string.charCodeAt(i));
                    }
                };

                writeString(view, 0, 'RIFF');
                view.setUint32(4, 36 + samples.length * 2, true);
                writeString(view, 8, 'WAVE');
                writeString(view, 12, 'fmt ');
                view.setUint32(16, 16, true);
                view.setUint16(20, 1, true); // PCM format
                view.setUint16(22, 1, true); // Mono channel
                view.setUint32(24, sampleRate, true);
                view.setUint32(28, sampleRate * 2, true); // Byte rate
                view.setUint16(32, 2, true); // Block align
                view.setUint16(34, 16, true); // Bits per sample
                writeString(view, 36, 'data');
                view.setUint32(40, samples.length * 2, true);

                let offset = 44;
                for (let i = 0; i < samples.length; i++, offset += 2) {
                    const s = Math.max(-1, Math.min(1, samples[i]));
                    view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
                }

                return new Blob([view], { type: 'audio/wav' });
            },
            saveUrlAudio() {
                const url = (this.customSoundUrl || '').trim();
                if (!url || !url.startsWith('http')) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{{ __('Masukkan URL audio yang valid (dimulai dengan http/https)') }}', type: 'error' } }));
                    return;
                }
                localStorage.removeItem('atoma_custom_sound_data');
                localStorage.setItem('atoma_custom_sound_url', url);
                const fileName = url.split('/').pop().split('?')[0] || 'audio-file.mp3';
                const nameStr = 'URL: ' + fileName;
                localStorage.setItem('atoma_custom_sound_name', nameStr);
                this.customSoundName = nameStr;
                this.hasCustomSound = true;
                this.soundPreset = 'custom';
                this.saveSoundSettings();
                this.testSound('custom');
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{{ __('Link audio kustom berhasil disimpan dan diaktifkan!') }}', type: 'success' } }));
            },
            removeCustomAudio() {
                localStorage.removeItem('atoma_custom_sound_data');
                localStorage.removeItem('atoma_custom_sound_url');
                localStorage.removeItem('atoma_custom_sound_name');
                this.customSoundName = '';
                this.customSoundUrl = '';
                this.optimizationStats = '';
                this.hasCustomSound = false;
                if (this.soundPreset === 'custom') {
                    this.soundPreset = 'chime';
                }
                this.saveSoundSettings();
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{{ __('Audio kustom dihapus. Kembali ke Chime.') }}', type: 'nudge' } }));
            }
         }">

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. Header Card (Matching Reference Layout: Avatar, User Details, Last Active, Quick Circular Actions) -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-7 border border-gray-200/80 dark:border-gray-700/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-6 transition-all">
                <div class="flex items-center gap-4 sm:gap-5 min-w-0">
                    <!-- User Avatar with frame styling & Level Indicator -->
                    <div class="relative shrink-0">
                        @php
                            $frameClass = 'border-2 border-gray-200 dark:border-gray-700 shadow-sm';
                            $frameEffect = '';
                            if (auth()->user()->active_frame === 'frame_neon') {
                                $frameClass = 'border-2 border-indigo-400 shadow-[0_0_12px_rgba(99,102,241,0.5)]';
                                $frameEffect = '<div class="absolute inset-0 rounded-full border-2 border-indigo-400 pointer-events-none z-10"></div>';
                            } elseif (auth()->user()->active_frame === 'frame_fire') {
                                $frameClass = 'border-2 border-orange-500 shadow-[0_0_12px_rgba(249,115,22,0.5)]';
                                $frameEffect = '<div class="absolute inset-0 rounded-full border-2 border-orange-500 animate-pulse pointer-events-none z-10"></div>';
                            } elseif (auth()->user()->active_frame === 'frame_gold') {
                                $frameClass = 'border-2 border-amber-400 ring-2 ring-yellow-300/80 shadow-[0_0_15px_rgba(250,204,21,0.6)]';
                                $frameEffect = '<div class="absolute -top-[28%] left-1/2 -translate-x-1/2 w-[55%] z-20 drop-shadow-md pointer-events-none"><img src="' . asset('images/frames/crown_gold.svg') . '" alt="Crown" class="w-full h-auto object-contain"></div><div class="absolute inset-0 rounded-full border-2 border-amber-400 pointer-events-none z-10"></div>';
                            }
                        @endphp
                        
                        <div class="w-16 h-16 sm:w-20 sm:h-20 aspect-square shrink-0 rounded-full relative bg-gray-100 dark:bg-gray-700 {{ $frameClass }} p-0.5">
                            {!! $frameEffect !!}
                            <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-full h-full aspect-square rounded-full object-cover bg-white dark:bg-gray-800">
                        </div>

                        <!-- Level Badge -->
                        <div class="absolute -bottom-1 -right-1 z-30 bg-amber-400 text-yellow-950 font-black text-[11px] px-2 py-0.5 rounded-full border-2 border-white dark:border-gray-800 shadow-xs flex items-center gap-0.5 select-none" title="Level {{ auth()->user()->level }}">
                            <span>⭐</span>
                            <span>{{ auth()->user()->level }}</span>
                        </div>
                    </div>

                    <!-- User Name & Meta Info -->
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white truncate">
                                {{ auth()->user()->name }}
                            </h2>
                            @if(auth()->user()->is_admin || auth()->user()->id === 1)
                                <span class="px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 text-[10px] font-black uppercase tracking-wider border border-indigo-200/80 dark:border-indigo-800/60">
                                    ADMIN
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
                            <span class="truncate max-w-[220px] sm:max-w-xs font-medium">{{ auth()->user()->email }}</span>
                            <span>•</span>
                            <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-semibold">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                {{ __('Aktif sekarang') }}
                            </span>
                        </div>

                        <!-- Gamification Stat Pills -->
                        <div class="flex items-center gap-2 mt-2.5 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/40 text-[11px] font-bold">
                                <span>⚡</span>
                                <span>{{ number_format(auth()->user()->xp) }} XP</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-orange-50/80 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 border border-orange-200/60 dark:border-orange-800/40 text-[11px] font-bold">
                                <span>🔥</span>
                                <span>{{ auth()->user()->longest_streak }} Hari Streak</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/40 text-[11px] font-bold">
                                <span>🎟️</span>
                                <span>{{ auth()->user()->streak_coupons }} Kupon</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-amber-50/80 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/40 text-[11px] font-bold">
                                <span>🪙</span>
                                <span>{{ number_format(auth()->user()->coins) }} Koin</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Quick Circular Action Buttons (Matching Reference Design) -->
                <div class="flex items-center gap-2.5 self-start md:self-center shrink-0">
                    <!-- 1. Public Profile Button -->
                    <a href="{{ route('user.profile', auth()->id()) }}" wire:navigate 
                       class="w-10 h-10 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/80 text-gray-700 dark:text-gray-200 flex items-center justify-center transition-all hover:scale-105 shadow-2xs"
                       title="{{ __('Lihat Profil Publik') }}">
                        <!-- Custom Minimalist Public Profile / External SVG -->
                        <svg class="w-4.5 h-4.5 text-gray-600 dark:text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <polyline points="15 3 21 3 21 9"/>
                            <line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                    </a>

                    <!-- 2. Badges / Achievements Collection Button -->
                    <button type="button" @click="$dispatch('open-achievements')"
                            class="w-10 h-10 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-amber-50 dark:hover:bg-amber-950/40 hover:border-amber-300 text-gray-700 dark:text-gray-200 flex items-center justify-center transition-all hover:scale-105 shadow-2xs cursor-pointer"
                            title="{{ __('Rak & Koleksi Lencana (Prestasi)') }}">
                        <!-- Custom Minimalist Trophy SVG -->
                        <svg class="w-4.5 h-4.5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
                            <path d="M4 22h16"/>
                            <path d="M10 14.66V17a2 2 0 0 1-2 2H7v3h10v-3h-1a2 2 0 0 1-2-2v-2.34"/>
                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/>
                        </svg>
                    </button>

                    @if(auth()->user()->is_admin || auth()->user()->id === 1)
                        @php
                            $adminPendingFeedbacks = class_exists(\App\Models\UserFeedback::class) ? \App\Models\UserFeedback::where('status', 'pending')->count() : 0;
                        @endphp
                        <!-- 3. Admin Mailbox Button -->
                        <a href="{{ route('admin.feedbacks') }}" wire:navigate 
                           class="relative w-10 h-10 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 hover:border-indigo-300 text-gray-700 dark:text-gray-200 flex items-center justify-center transition-all hover:scale-105 shadow-2xs"
                           title="{{ __('Admin Mailbox') }}">
                            <!-- Custom Minimalist Mailbox SVG -->
                            <svg class="w-4.5 h-4.5 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 13V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v7"/>
                                <path d="M3 13v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/>
                                <path d="M3 13h5l2 3h4l2-3h5"/>
                            </svg>
                            @if($adminPendingFeedbacks > 0)
                                <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] font-black flex items-center justify-center animate-pulse">
                                    {{ $adminPendingFeedbacks }}
                                </span>
                            @endif
                        </a>

                        <!-- 4. AI Telemetry Button -->
                        <a href="{{ route('admin.ai-usage') }}" wire:navigate 
                           class="w-10 h-10 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-purple-50 dark:hover:bg-purple-950/40 hover:border-purple-300 text-gray-700 dark:text-gray-200 flex items-center justify-center transition-all hover:scale-105 shadow-2xs"
                           title="{{ __('AI Telemetry & Token Usage') }}">
                            <!-- Custom Minimalist Telemetry SVG -->
                            <svg class="w-4.5 h-4.5 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12h4l3 8 4-16 3 8h4"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>

            <!-- 2. Clean Settings Page Title Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2">
                        <x-icon.gear class="w-7 h-7 text-atoma-blue" />
                        <span>{{ __('Pengaturan') }}</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5 font-medium">
                        {{ __('Kelola profil pribadi, preferensi antarmuka, keamanan akun, dan integrasi AI Anda.') }}
                    </p>
                </div>
            </div>

            <!-- Mobile Horizontal Navigation Tabs (Visible on < lg screens) -->
            <div class="lg:hidden bg-white dark:bg-gray-800 p-2 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-2xs overflow-x-auto hide-scrollbar flex items-center gap-2">
                <button type="button" @click="setTab('account')" 
                        :class="activeTab === 'account' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium'"
                        class="px-3.5 py-2 rounded-xl text-xs whitespace-nowrap transition-all flex items-center gap-2 cursor-pointer shrink-0">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="4.25"/>
                        <path d="M4.5 20.25a7.5 7.5 0 0 1 15 0"/>
                    </svg>
                    <span>{{ __('Profil') }}</span>
                </button>
                <button type="button" @click="setTab('security')" 
                        :class="activeTab === 'security' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium'"
                        class="px-3.5 py-2 rounded-xl text-xs whitespace-nowrap transition-all flex items-center gap-2 cursor-pointer shrink-0">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="11" width="16" height="10" rx="3"/>
                        <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                    </svg>
                    <span>{{ __('Keamanan') }}</span>
                </button>
                <button type="button" @click="setTab('subscription')" 
                        :class="activeTab === 'subscription' ? 'bg-amber-500 text-white font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium'"
                        class="px-3.5 py-2 rounded-xl text-xs whitespace-nowrap transition-all flex items-center gap-1.5 cursor-pointer shrink-0">
                    <span>👑</span>
                    <span>{{ __('Langganan') }}</span>
                    @if(auth()->user()->isPro())
                        <span class="text-[9px] bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 px-1 py-0.2 rounded font-black">PRO</span>
                    @endif
                </button>
                <button type="button" @click="setTab('tasks')" 
                        :class="activeTab === 'tasks' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium'"
                        class="px-3.5 py-2 rounded-xl text-xs whitespace-nowrap transition-all flex items-center gap-2 cursor-pointer shrink-0">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 6h10M18 6h2M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                        <path d="M4 12h2M10 12h10M14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                        <path d="M4 18h14M20 18h0M18 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                    </svg>
                    <span>{{ __('Tugas') }}</span>
                </button>
                <button type="button" @click="setTab('sound')" 
                        :class="activeTab === 'sound' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium'"
                        class="px-3.5 py-2 rounded-xl text-xs whitespace-nowrap transition-all flex items-center gap-2 cursor-pointer shrink-0">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 5L6 9H3v6h3l5 4V5z"/>
                        <path d="M15.5 8.5a5 5 0 0 1 0 7"/>
                    </svg>
                    <span>{{ __('Suara') }}</span>
                </button>
                <button type="button" @click="setTab('mcp')" 
                        :class="activeTab === 'mcp' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium'"
                        class="px-3.5 py-2 rounded-xl text-xs whitespace-nowrap transition-all flex items-center gap-2 cursor-pointer shrink-0">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                        <rect x="6.5" y="6.5" width="11" height="11" rx="2.5"/>
                    </svg>
                    <span>{{ __('AI & MCP') }}</span>
                </button>
            </div>

            <!-- 3. Two-Column Layout (Sidebar Navigation + Main Content Area) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Sidebar Navigation (Desktop lg:col-span-3) -->
                <aside class="hidden lg:block lg:col-span-4 xl:col-span-3 space-y-6 sticky top-20">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-3 sm:p-4 border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-5">
                        
                        <!-- Section: AKUN & KEAMANAN -->
                        <div class="space-y-1">
                            <div class="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                {{ __('Akun & Keamanan') }}
                            </div>

                            <!-- Item: Profil & Avatar -->
                            <button type="button" @click="setTab('account')"
                                    :class="activeTab === 'account' 
                                        ? 'bg-gray-100 dark:bg-gray-700/80 text-gray-900 dark:text-white font-bold shadow-xs' 
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/40 font-medium'"
                                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs sm:text-sm transition-all group cursor-pointer text-left">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4.5 h-4.5 transition-colors" 
                                         :class="activeTab === 'account' ? 'text-indigo-600 dark:text-atoma-cyan' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300'"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="8" r="4.25"/>
                                        <path d="M4.5 20.25a7.5 7.5 0 0 1 15 0"/>
                                    </svg>
                                    <span>{{ __('Profil & Avatar') }}</span>
                                </div>
                                <template x-if="activeTab === 'account'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 dark:bg-atoma-cyan"></span>
                                </template>
                            </button>

                            <!-- Item: Keamanan & Sandi -->
                            <button type="button" @click="setTab('security')"
                                    :class="activeTab === 'security' 
                                        ? 'bg-gray-100 dark:bg-gray-700/80 text-gray-900 dark:text-white font-bold shadow-xs' 
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/40 font-medium'"
                                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs sm:text-sm transition-all group cursor-pointer text-left">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4.5 h-4.5 transition-colors"
                                         :class="activeTab === 'security' ? 'text-indigo-600 dark:text-atoma-cyan' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300'"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="4" y="11" width="16" height="10" rx="3"/>
                                        <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                                        <circle cx="12" cy="16" r="1.25" fill="currentColor"/>
                                    </svg>
                                    <span>{{ __('Kata Sandi & Keamanan') }}</span>
                                </div>
                                <template x-if="activeTab === 'security'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 dark:bg-atoma-cyan"></span>
                                </template>
                            </button>

                            <!-- Item: Paket & Langganan -->
                            <button type="button" @click="setTab('subscription')"
                                    :class="activeTab === 'subscription' 
                                        ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 font-bold border border-amber-300/60 dark:border-amber-700/60 shadow-xs' 
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/40 font-medium'"
                                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs sm:text-sm transition-all group cursor-pointer text-left">
                                <div class="flex items-center gap-3">
                                    <span class="text-base">👑</span>
                                    <span>{{ __('Paket & Langganan') }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    @if(auth()->user()->isPro())
                                        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-gradient-to-r from-amber-500 to-yellow-500 text-white shadow-2xs">
                                            PRO
                                        </span>
                                    @else
                                        <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                            FREE
                                        </span>
                                    @endif
                                </div>
                            </button>
                        </div>

                        <!-- Section: PREFERENSI APLIKASI -->
                        <div class="space-y-1">
                            <div class="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                {{ __('Preferensi Aplikasi') }}
                            </div>

                            <!-- Item: Preferensi Tugas -->
                            <button type="button" @click="setTab('tasks')"
                                    :class="activeTab === 'tasks' 
                                        ? 'bg-gray-100 dark:bg-gray-700/80 text-gray-900 dark:text-white font-bold shadow-xs' 
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/40 font-medium'"
                                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs sm:text-sm transition-all group cursor-pointer text-left">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4.5 h-4.5 transition-colors"
                                         :class="activeTab === 'tasks' ? 'text-indigo-600 dark:text-atoma-cyan' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300'"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 6h10M18 6h2M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                                        <path d="M4 12h2M10 12h10M14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                                        <path d="M4 18h14M20 18h0M18 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                                    </svg>
                                    <span>{{ __('Tampilan Tugas') }}</span>
                                </div>
                                <template x-if="activeTab === 'tasks'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 dark:bg-atoma-cyan"></span>
                                </template>
                            </button>

                            <!-- Item: Notifikasi & Suara -->
                            <button type="button" @click="setTab('sound')"
                                    :class="activeTab === 'sound' 
                                        ? 'bg-gray-100 dark:bg-gray-700/80 text-gray-900 dark:text-white font-bold shadow-xs' 
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/40 font-medium'"
                                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs sm:text-sm transition-all group cursor-pointer text-left">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4.5 h-4.5 transition-colors"
                                         :class="activeTab === 'sound' ? 'text-indigo-600 dark:text-atoma-cyan' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300'"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 5L6 9H3v6h3l5 4V5z"/>
                                        <path d="M15.5 8.5a5 5 0 0 1 0 7"/>
                                        <path d="M18.5 5.5a9 9 0 0 1 0 13"/>
                                    </svg>
                                    <span>{{ __('Notifikasi & Suara') }}</span>
                                </div>
                                <template x-if="activeTab === 'sound'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 dark:bg-atoma-cyan"></span>
                                </template>
                            </button>
                        </div>

                        <!-- Section: INTEGRASI AI -->
                        <div class="space-y-1">
                            <div class="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                {{ __('Integrasi AI') }}
                            </div>

                            <!-- Item: Model Context Protocol (MCP) -->
                            <button type="button" @click="setTab('mcp')"
                                    :class="activeTab === 'mcp' 
                                        ? 'bg-gray-100 dark:bg-gray-700/80 text-gray-900 dark:text-white font-bold shadow-xs' 
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/40 font-medium'"
                                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs sm:text-sm transition-all group cursor-pointer text-left">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4.5 h-4.5 transition-colors"
                                         :class="activeTab === 'mcp' ? 'text-purple-600 dark:text-purple-400' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300'"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                                        <rect x="6.5" y="6.5" width="11" height="11" rx="2.5"/>
                                        <circle cx="12" cy="12" r="2" fill="currentColor"/>
                                    </svg>
                                    <span>{{ __('AI & MCP Protocol') }}</span>
                                </div>
                                <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300">
                                    API
                                </span>
                            </button>
                        </div>

                        <!-- Section: ADMINISTRATOR (If Admin) -->
                        @if(auth()->user()->is_admin || auth()->user()->id === 1)
                            <div class="space-y-1 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                                <div class="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-indigo-500 dark:text-indigo-400 flex items-center justify-between">
                                    <span>{{ __('Administrator') }}</span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                                </div>

                                <a href="{{ route('admin.feedbacks') }}" wire:navigate 
                                   class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs sm:text-sm text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-300 hover:bg-indigo-50/60 dark:hover:bg-indigo-950/40 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4.5 h-4.5 text-gray-400 group-hover:text-indigo-600 dark:text-gray-500 dark:group-hover:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 13V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v7"/>
                                            <path d="M3 13v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/>
                                            <path d="M3 13h5l2 3h4l2-3h5"/>
                                        </svg>
                                        <span>{{ __('Admin Mailbox') }}</span>
                                    </div>
                                    @if($adminPendingFeedbacks > 0)
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white animate-pulse">
                                            {{ $adminPendingFeedbacks }}
                                        </span>
                                    @endif
                                </a>

                                <a href="{{ route('admin.ai-usage') }}" wire:navigate 
                                   class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs sm:text-sm text-gray-600 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-300 hover:bg-purple-50/60 dark:hover:bg-purple-950/40 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4.5 h-4.5 text-gray-400 group-hover:text-purple-600 dark:text-gray-500 dark:group-hover:text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 12h4l3 8 4-16 3 8h4"/>
                                        </svg>
                                        <span>{{ __('AI Telemetry') }}</span>
                                    </div>
                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                </a>
                            </div>
                        @endif

                    </div>
                </aside>

                <!-- Right Main Content Panel (Desktop lg:col-span-8 or xl:col-span-9) -->
                <main class="lg:col-span-8 xl:col-span-9 space-y-6">

                    <!-- TAB 1: Profil & Avatar -->
                    <div x-show="activeTab === 'account'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
                        
                        <!-- 3-Slot Equipped Badges Showcase Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-gray-200/80 dark:border-gray-700/80 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <span>🏅</span>
                                    <span>{{ __('Rak Lencana Pameran Profil') }}</span>
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ __('Pilih hingga 3 lencana pencapaian untuk dipajang di profil publik Anda.') }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                @php
                                    $equippedList = auth()->user()->equipped_badges_details ?? [];
                                @endphp
                                @forelse($equippedList as $idx => $b)
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-300/60 dark:border-amber-500/40 shadow-2xs cursor-pointer transition-transform hover:scale-105"
                                         @click="$dispatch('open-achievements')"
                                         title="Slot #{{ $idx + 1 }}: {{ $b['title'] }} (Klik untuk ubah)">
                                        <div class="w-4.5 h-4.5 shrink-0">
                                            @if(!empty($b['svg_path']))
                                                <img src="{{ asset($b['svg_path']) }}" class="w-full h-full object-contain">
                                            @else
                                                <span class="text-xs">{{ $b['icon'] ?? '🏆' }}</span>
                                            @endif
                                        </div>
                                        <span class="text-xs font-bold text-amber-900 dark:text-amber-200">{{ $b['title'] }}</span>
                                        <span class="text-[9px] font-bold px-1 rounded bg-amber-200/60 dark:bg-amber-900/60 text-amber-950 dark:text-amber-100">#{{ $idx + 1 }}</span>
                                    </div>
                                @empty
                                    <button type="button" @click="$dispatch('open-achievements')" 
                                            class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 px-3.5 py-1.5 rounded-xl hover:bg-indigo-100 transition-all cursor-pointer">
                                        <span>+ {{ __('Pasang Lencana (0/3)') }}</span>
                                    </button>
                                @endforelse

                                @if(count($equippedList) > 0 && count($equippedList) < 3)
                                    <button type="button" @click="$dispatch('open-achievements')" 
                                            class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 dark:text-gray-400 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 px-2.5 py-1.5 rounded-xl transition-all cursor-pointer">
                                        <span>+ {{ __('Tambah') }} ({{ count($equippedList) }}/3)</span>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Profile & Avatar Form Card (Clean Reference Card Design) -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                            <livewire:profile.update-profile-information-form />
                        </div>
                    </div>

                    <!-- TAB 2: Kata Sandi & Keamanan -->
                    <div x-show="activeTab === 'security'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="space-y-6">
                        <!-- Password Update Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                            <livewire:profile.update-password-form />
                        </div>

                        <!-- Danger Zone: Delete Account Card -->
                        <div class="bg-rose-50/40 dark:bg-rose-950/20 rounded-3xl p-6 sm:p-8 border border-rose-200/80 dark:border-rose-900/50 shadow-xs">
                            <livewire:profile.delete-user-form />
                        </div>
                    </div>

                    <!-- TAB 3: Preferensi Tampilan Tugas -->
                    <div x-show="activeTab === 'tasks'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-6">
                            
                            <div class="border-b border-gray-100 dark:border-gray-700/60 pb-5">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight flex items-center gap-2.5">
                                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 6h10M18 6h2M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                                        <path d="M4 12h2M10 12h10M14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                                        <path d="M4 18h14M20 18h0M18 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                                    </svg>
                                    <span>{{ __('Preferensi Tampilan & Interaksi Tugas') }}</span>
                                </h2>
                                <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium">
                                    {{ __('Sesuaikan tata letak formulir penambahan tugas dan tombol aksi di layar desktop maupun mobile.') }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <!-- 1. Input Mode Selection -->
                                <div class="bg-gray-50/80 dark:bg-gray-900/50 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/70 space-y-3">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                        📝 {{ __('Metode Tambah Task Baru') }}
                                    </label>
                                    <div class="space-y-2 text-xs font-semibold text-gray-800 dark:text-gray-200">
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="inputMode === 'both' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="inputMode" value="both" x-model="inputMode" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Tampilkan Keduanya (Modal & Voice)') }}</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="inputMode === 'manual' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="inputMode" value="manual" x-model="inputMode" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Hanya Tombol Form Modal Manual') }}</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="inputMode === 'nlp' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="inputMode" value="nlp" x-model="inputMode" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Hanya Bar Voice & Ketik Cepat') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- 2. Mobile FAB Position -->
                                <div class="bg-gray-50/80 dark:bg-gray-900/50 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/70 space-y-3">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                        📱 {{ __('Posisi Tombol Tambah di Mobile') }}
                                    </label>
                                    <div class="space-y-2 text-xs font-semibold text-gray-800 dark:text-gray-200">
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="mobileFab === 'center' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="mobileFab" value="center" x-model="mobileFab" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Di Tengah Navbar Bawah') }}</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="mobileFab === 'floating' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="mobileFab" value="floating" x-model="mobileFab" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Melayang di Kanan Bawah (FAB +)') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- 3. Auto-Hide Action Buttons -->
                                <div class="bg-gray-50/80 dark:bg-gray-900/50 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/70 space-y-3">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                        👁️ {{ __('Auto-Hide Aksi Task Card') }}
                                    </label>
                                    <div class="space-y-2 text-xs font-semibold text-gray-800 dark:text-gray-200">
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="autoHide === true ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="autoHide" :value="true" x-model="autoHide" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Aktif (Hanya muncul saat hover)') }}</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="autoHide === false ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="autoHide" :value="false" x-model="autoHide" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Selalu Tampilkan') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- 4. Default Subtask Display Mode -->
                                <div class="bg-gray-50/80 dark:bg-gray-900/50 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/70 space-y-3">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                        🎯 {{ __('Tampilan Subtugas Default') }}
                                    </label>
                                    <div class="space-y-2 text-xs font-semibold text-gray-800 dark:text-gray-200">
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="subtaskMode === 'list' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="subtaskMode" value="list" x-model="subtaskMode" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Daftar Penuh (Semua)') }}</span>
                                        </label>
                                        <label class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
                                               :class="subtaskMode === 'step' ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 ring-1 ring-indigo-500/30' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                            <input type="radio" name="subtaskMode" value="step" x-model="subtaskMode" @change="saveConfig()" class="text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ __('Fokus Beruntun (1-per-1)') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: Kustomisasi Suara & Notifikasi -->
                    <div x-show="activeTab === 'sound'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-6">
                            
                            <div class="border-b border-gray-100 dark:border-gray-700/60 pb-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight flex items-center gap-2.5">
                                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 5L6 9H3v6h3l5 4V5z"/>
                                            <path d="M15.5 8.5a5 5 0 0 1 0 7"/>
                                            <path d="M18.5 5.5a9 9 0 0 1 0 13"/>
                                        </svg>
                                        <span>{{ __('Kustomisasi Suara Notifikasi') }}</span>
                                    </h2>
                                    <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium">
                                        {{ __('Pilih nada preset atau unggah file suara kustom sendiri (otomatis dipotong maks 5 detik & dikompres).') }}
                                    </p>
                                </div>

                                <!-- Modern Pill Master Toggle Switch -->
                                <button type="button" 
                                        @click="soundEnabled = !soundEnabled; saveSoundSettings()"
                                        class="flex items-center gap-3 px-3.5 py-2 rounded-2xl border transition-all cursor-pointer"
                                        :class="soundEnabled ? 'bg-indigo-50 dark:bg-indigo-950/50 border-indigo-300 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-700/60 border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400'">
                                    <span class="text-xs font-bold">{{ __('Efek Suara:') }}</span>
                                    <!-- Toggle track & knob -->
                                    <span class="w-10 h-5 flex items-center rounded-full p-0.5 transition-colors duration-200 ease-in-out"
                                          :class="soundEnabled ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'">
                                        <span class="bg-white w-4 h-4 rounded-full shadow-md transform transition-transform duration-200 ease-in-out"
                                              :class="soundEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                    </span>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Preset Options List -->
                                <div class="space-y-2.5">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                        🎵 {{ __('Pilihan Nada Suara:') }}
                                    </label>

                                    <!-- Chime -->
                                    <div class="flex items-center justify-between p-3.5 rounded-2xl border transition-all cursor-pointer"
                                         :class="soundPreset === 'chime' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-500/30' : 'border-gray-200/80 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40'"
                                         @click="soundPreset = 'chime'; saveSoundSettings(); testSound('chime')">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="soundPreset" value="chime" x-model="soundPreset" class="text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 dark:text-white">🔔 {{ __('Chime Lembut (Default)') }}</span>
                                                <p class="text-[11px] text-gray-500">{{ __('Harmonik bel berdering halus dan menenangkan') }}</p>
                                            </div>
                                        </label>
                                        <button type="button" @click.stop="testSound('chime')" class="px-2.5 py-1 text-xs font-bold bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/40 shadow-2xs cursor-pointer flex items-center gap-1">
                                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><polygon points="6 4 20 12 6 20 6 4"/></svg>
                                            <span>{{ __('Uji') }}</span>
                                        </button>
                                    </div>

                                    <!-- Digital Beep -->
                                    <div class="flex items-center justify-between p-3.5 rounded-2xl border transition-all cursor-pointer"
                                         :class="soundPreset === 'digital_beep' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-500/30' : 'border-gray-200/80 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40'"
                                         @click="soundPreset = 'digital_beep'; saveSoundSettings(); testSound('digital_beep')">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="soundPreset" value="digital_beep" x-model="soundPreset" class="text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 dark:text-white">📟 {{ __('Digital Beep') }}</span>
                                                <p class="text-[11px] text-gray-500">{{ __('Dua ketukan elektronik presisi modern') }}</p>
                                            </div>
                                        </label>
                                        <button type="button" @click.stop="testSound('digital_beep')" class="px-2.5 py-1 text-xs font-bold bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/40 shadow-2xs cursor-pointer flex items-center gap-1">
                                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><polygon points="6 4 20 12 6 20 6 4"/></svg>
                                            <span>{{ __('Uji') }}</span>
                                        </button>
                                    </div>

                                    <!-- Pop Bubble -->
                                    <div class="flex items-center justify-between p-3.5 rounded-2xl border transition-all cursor-pointer"
                                         :class="soundPreset === 'pop_bubble' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-500/30' : 'border-gray-200/80 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40'"
                                         @click="soundPreset = 'pop_bubble'; saveSoundSettings(); testSound('pop_bubble')">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="soundPreset" value="pop_bubble" x-model="soundPreset" class="text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 dark:text-white">🫧 {{ __('Pop Bubble') }}</span>
                                                <p class="text-[11px] text-gray-500">{{ __('Gelembung organik akustik yang ceria') }}</p>
                                            </div>
                                        </label>
                                        <button type="button" @click.stop="testSound('pop_bubble')" class="px-2.5 py-1 text-xs font-bold bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/40 shadow-2xs cursor-pointer flex items-center gap-1">
                                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><polygon points="6 4 20 12 6 20 6 4"/></svg>
                                            <span>{{ __('Uji') }}</span>
                                        </button>
                                    </div>

                                    <!-- Marimba -->
                                    <div class="flex items-center justify-between p-3.5 rounded-2xl border transition-all cursor-pointer"
                                         :class="soundPreset === 'marimba' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-500/30' : 'border-gray-200/80 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40'"
                                         @click="soundPreset = 'marimba'; saveSoundSettings(); testSound('marimba')">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="soundPreset" value="marimba" x-model="soundPreset" class="text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 dark:text-white">🪵 {{ __('Marimba Melodic') }}</span>
                                                <p class="text-[11px] text-gray-500">{{ __('Ketukan nada kayu akustik yang hangat') }}</p>
                                            </div>
                                        </label>
                                        <button type="button" @click.stop="testSound('marimba')" class="px-2.5 py-1 text-xs font-bold bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/40 shadow-2xs cursor-pointer flex items-center gap-1">
                                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><polygon points="6 4 20 12 6 20 6 4"/></svg>
                                            <span>{{ __('Uji') }}</span>
                                        </button>
                                    </div>

                                    <!-- Triumphant Fanfare -->
                                    <div class="flex items-center justify-between p-3.5 rounded-2xl border transition-all cursor-pointer"
                                         :class="soundPreset === 'fanfare' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-500/30' : 'border-gray-200/80 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40'"
                                         @click="soundPreset = 'fanfare'; saveSoundSettings(); testSound('fanfare')">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="soundPreset" value="fanfare" x-model="soundPreset" class="text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 dark:text-white">🎺 {{ __('Triumphant Fanfare') }}</span>
                                                <p class="text-[11px] text-gray-500">{{ __('Arpeggio kemenangan untuk selebrasi target') }}</p>
                                            </div>
                                        </label>
                                        <button type="button" @click.stop="testSound('fanfare')" class="px-2.5 py-1 text-xs font-bold bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/40 shadow-2xs cursor-pointer flex items-center gap-1">
                                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><polygon points="6 4 20 12 6 20 6 4"/></svg>
                                            <span>{{ __('Uji') }}</span>
                                        </button>
                                    </div>

                                    <!-- Custom User Sound (Upload / URL) -->
                                    <div class="p-4 rounded-2xl border transition-all space-y-3"
                                         :class="soundPreset === 'custom' ? 'border-sky-500 bg-sky-50/50 dark:bg-sky-950/30 ring-1 ring-sky-500/30' : 'border-gray-200/80 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40'">
                                        <div class="flex items-center justify-between cursor-pointer" @click="soundPreset = 'custom'; saveSoundSettings(); if(hasCustomSound) testSound('custom');">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="radio" name="soundPreset" value="custom" x-model="soundPreset" class="text-sky-600 focus:ring-sky-500">
                                                <div>
                                                    <span class="text-xs font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                                        <span>📁</span> {{ __('Suara Kustom Sendiri (Maks 5s)') }}
                                                    </span>
                                                    <p class="text-[11px] text-gray-500 truncate max-w-[200px] sm:max-w-xs" x-text="customSoundName ? customSoundName : '{{ __('Unggah file audio sendiri atau link') }}'"></p>
                                                </div>
                                            </label>
                                            <template x-if="hasCustomSound">
                                                <div class="flex items-center gap-1.5">
                                                    <button type="button" @click.stop="testSound('custom')" class="px-2.5 py-1 text-xs font-bold bg-white dark:bg-gray-800 text-sky-600 dark:text-sky-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-sky-50 dark:hover:bg-sky-900/40 shadow-2xs cursor-pointer flex items-center gap-1">
                                                        <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><polygon points="6 4 20 12 6 20 6 4"/></svg>
                                                        <span>{{ __('Uji') }}</span>
                                                    </button>
                                                    <button type="button" @click.stop="removeCustomAudio()" class="p-1 text-gray-400 hover:text-red-500 cursor-pointer" title="{{ __('Hapus audio kustom') }}">
                                                        ✕
                                                    </button>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Custom Upload / Input controls -->
                                        <div class="pt-3 border-t border-sky-100 dark:border-sky-900/40 space-y-2.5">
                                            <div class="flex items-center gap-2 text-[11px] font-bold">
                                                <button type="button" @click="customMode = 'file'" class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer" :class="customMode === 'file' ? 'bg-sky-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'">
                                                    📤 {{ __('Upload File Audio') }}
                                                </button>
                                                <button type="button" @click="customMode = 'url'" class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer" :class="customMode === 'url' ? 'bg-sky-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'">
                                                    🔗 {{ __('Link URL Audio') }}
                                                </button>
                                            </div>

                                            <template x-if="customMode === 'file'">
                                                <div>
                                                    <input type="file" accept="audio/*" @change="handleFileUpload($event)" class="block w-full text-xs text-gray-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-100 dark:file:bg-sky-900/60 file:text-sky-700 dark:file:text-sky-300 hover:file:bg-sky-200 cursor-pointer">
                                                </div>
                                            </template>

                                            <template x-if="customMode === 'url'">
                                                <div class="flex items-center gap-2">
                                                    <input type="url" x-model="customSoundUrl" placeholder="https://example.com/suara.mp3" class="flex-1 px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-sky-500">
                                                    <button type="button" @click="saveUrlAudio()" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shrink-0 cursor-pointer">
                                                        {{ __('Simpan Link') }}
                                                    </button>
                                                </div>
                                            </template>

                                            <!-- Audio Optimization Stats Badge -->
                                            <template x-if="isProcessing">
                                                <div class="flex items-center gap-2 text-[11px] text-sky-600 dark:text-sky-400 font-bold animate-pulse pt-1">
                                                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                    <span>{{ __('Memproses & mengompres audio ke 5 detik...') }}</span>
                                                </div>
                                            </template>
                                            <template x-if="optimizationStats && !isProcessing">
                                                <div class="p-2 bg-sky-100/60 dark:bg-sky-900/40 rounded-xl text-[10px] font-bold text-sky-800 dark:text-sky-300 flex items-center justify-between">
                                                    <span x-text="optimizationStats"></span>
                                                    <span class="text-[9px] bg-sky-200 dark:bg-sky-800 px-1.5 py-0.5 rounded-md">{{ __('Maks 5s') }}</span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Silent -->
                                    <div class="flex items-center justify-between p-3.5 rounded-2xl border transition-all cursor-pointer"
                                         :class="soundPreset === 'silent' ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-500/30' : 'border-gray-200/80 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40'"
                                         @click="soundPreset = 'silent'; saveSoundSettings()">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="soundPreset" value="silent" x-model="soundPreset" class="text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 dark:text-white">🔕 {{ __('Hening / Mute') }}</span>
                                                <p class="text-[11px] text-gray-500">{{ __('Hanya notifikasi visual tanpa audio') }}</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Volume & Audio Simulator Preview -->
                                <div class="bg-gray-50/80 dark:bg-gray-900/50 p-6 rounded-3xl border border-gray-200/80 dark:border-gray-700/70 flex flex-col justify-between space-y-6">
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                🎚️ {{ __('Volume Notifikasi') }}
                                            </label>
                                            <span class="text-xs font-bold font-mono text-indigo-600 dark:text-indigo-400" x-text="Math.round(soundVolume * 100) + '%'"></span>
                                        </div>
                                        <input type="range" min="0.05" max="1" step="0.05" x-model="soundVolume" @change="saveSoundSettings(); testSound(soundPreset)" class="w-full accent-indigo-600 h-2 bg-gray-200 dark:bg-gray-700 rounded-lg cursor-pointer">
                                    </div>

                                    <div class="p-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-2xs space-y-3">
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                            <span>💡</span> {{ __('Jadwal Pemicu Suara Notifikasi:') }}
                                        </h4>
                                        <ul class="text-[11px] text-gray-600 dark:text-gray-400 space-y-1.5 list-disc list-inside font-medium">
                                            <li>🔔 <strong>{{ __('10 Menit Sebelum Mulai:') }}</strong> {{ __('Mengingatkan tugas terjadwal.') }}</li>
                                            <li>⚠️ <strong>{{ __('10 Menit Sebelum Deadline:') }}</strong> {{ __('Peringatan batas waktu pengerjaan.') }}</li>
                                            <li>✅ <strong>{{ __('Tugas Selesai:') }}</strong> {{ __('Suara konfirmasi keberhasilan.') }}</li>
                                            <li>🚀 <strong>{{ __('Level Up / Milestone:') }}</strong> {{ __('Suara selebrasi reward XP.') }}</li>
                                        </ul>
                                        <div class="pt-2">
                                            <button type="button" 
                                                    @click="testSound(soundPreset)" 
                                                    class="w-full py-2.5 bg-gray-900 hover:bg-black dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-900 rounded-xl text-xs font-bold shadow-xs transition-all flex items-center justify-center gap-2 cursor-pointer">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><polygon points="6 4 20 12 6 20 6 4"/></svg>
                                                <span>{{ __('Tes Suara Pilihan Sekarang') }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: Paket & Langganan (Subscription & Billing) -->
                    <div x-show="activeTab === 'subscription'" 
                         x-transition:enter="transition ease-out duration-150" 
                         x-transition:enter-start="opacity-0 translate-y-1" 
                         x-transition:enter-end="opacity-100 translate-y-0" 
                         style="display: none;"
                         class="space-y-6"
                         x-data="{ billingCycle: 'yearly' }">
                        
                        <!-- Header Box -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-gray-200/80 dark:border-gray-700/80 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5">
                                <div class="w-12 h-12 rounded-2xl bg-amber-500/15 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl font-bold border border-amber-500/20 shadow-2xs">
                                    👑
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                                        <span>{{ __('Paket & Langganan Akun') }}</span>
                                        @if(auth()->user()->isPro())
                                            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-gradient-to-r from-amber-500 to-yellow-500 text-white shadow-2xs">
                                                PRO AKTIF
                                            </span>
                                        @else
                                            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                FREE STARTER
                                            </span>
                                        @endif
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ __('Kelola status keanggotaan Anda, lihat manfaat eksklusif ATOMA PRO, dan pilih opsi paket terbaik.') }}
                                    </p>
                                </div>
                            </div>

                            @if(!auth()->user()->isPro())
                                <button type="button" 
                                        @click="$dispatch('open-pro-modal')" 
                                        class="px-4 py-2.5 bg-gradient-to-r from-amber-500 via-amber-600 to-yellow-500 hover:from-amber-600 hover:to-yellow-600 text-white font-bold text-xs rounded-xl shadow-md shadow-amber-500/20 active:scale-95 transition-all cursor-pointer flex items-center gap-1.5 shrink-0">
                                    <span>👑</span>
                                    <span>{{ __('Upgrade ke PRO') }}</span>
                                </button>
                            @endif
                        </div>

                        <!-- Current Status & Tier Overview -->
                        @if(auth()->user()->isPro())
                            <!-- PRO ACTIVE CARD -->
                            <div class="relative overflow-hidden bg-gradient-to-br from-amber-50/90 via-white to-yellow-50/60 dark:from-amber-950/30 dark:via-gray-850 dark:to-yellow-950/20 rounded-3xl p-6 sm:p-7 border-2 border-amber-300/80 dark:border-amber-600/50 shadow-md">
                                <div class="absolute -right-8 -top-8 w-36 h-36 bg-amber-400/10 dark:bg-amber-400/5 rounded-full blur-2xl pointer-events-none"></div>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-amber-200/60 dark:border-amber-800/40">
                                    <div>
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 text-xs font-black uppercase tracking-wider mb-2">
                                            <span>✓</span>
                                            <span>{{ __('Paket ATOMA PRO Aktif') }}</span>
                                        </div>
                                        <h4 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white tracking-tight">
                                            {{ __('Semua Fitur Premium Terbuka Penuh') }}
                                        </h4>
                                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                            @if(auth()->user()->is_admin)
                                                🌟 <span class="font-bold">{{ __('Akun Administrator:') }}</span> {{ __('Akses seumur hidup (Lifetime Full Access) ke seluruh fitur dan kapasitas server tanpa batas.') }}
                                            @elseif(auth()->user()->subscription_expires_at)
                                                📅 <span class="font-bold">{{ __('Masa Aktif:') }}</span> {{ __('Berlaku hingga :date (:diff)', ['date' => auth()->user()->subscription_expires_at->format('d M Y'), 'diff' => auth()->user()->subscription_expires_at->diffForHumans()]) }}
                                            @else
                                                🌟 {{ __('Status Langganan Aktif.') }}
                                            @endif
                                        </p>
                                    </div>

                                    <!-- Switch / WhatsApp / Demo Button -->
                                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                                        <button type="button" 
                                                @click="$dispatch('open-manual-payment-modal', { plan: 'monthly' })"
                                                class="px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm shadow-emerald-600/20 transition-all cursor-pointer flex items-center gap-1.5">
                                            <span>💬</span>
                                            <span>{{ __('Perpanjang via Admin') }}</span>
                                        </button>

                                        <form method="POST" action="/api/subscription/toggle-demo">
                                            @csrf
                                            <input type="hidden" name="action" value="downgrade">
                                            <button type="submit" 
                                                    class="px-3.5 py-2.5 rounded-xl text-xs font-bold bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-750 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 transition-all cursor-pointer">
                                                {{ __('Uji Coba Free (Demo)') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Active Benefits Grid -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 pt-5">
                                    <div class="flex items-start gap-3 p-3 rounded-2xl bg-white/80 dark:bg-gray-800/80 border border-amber-200/50 dark:border-amber-800/40">
                                        <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-sm shrink-0">
                                            🎯
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Unlimited Milestones') }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Jalankan banyak target 90 hari sekaligus tanpa batas.') }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 p-3 rounded-2xl bg-white/80 dark:bg-gray-800/80 border border-amber-200/50 dark:border-amber-800/40">
                                        <div class="w-8 h-8 rounded-xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-sm shrink-0">
                                            ✨
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('AI Coach Unlimited') }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Konsultasi bebas & 1-klik generate bulk tasks.') }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 p-3 rounded-2xl bg-white/80 dark:bg-gray-800/80 border border-amber-200/50 dark:border-amber-800/40">
                                        <div class="w-8 h-8 rounded-xl bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-sm shrink-0">
                                            🎵
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Unlimited YouTube Playlist') }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Simpan folder musik fokus tanpa batasan 2 folder.') }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 p-3 rounded-2xl bg-white/80 dark:bg-gray-800/80 border border-amber-200/50 dark:border-amber-800/40">
                                        <div class="w-8 h-8 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-sm shrink-0">
                                            📜
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Sertifikat Resmi & QR Code') }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Verifikasi digital publik untuk LinkedIn & CV.') }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 p-3 rounded-2xl bg-white/80 dark:bg-gray-800/80 border border-amber-200/50 dark:border-amber-800/40">
                                        <div class="w-8 h-8 rounded-xl bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 flex items-center justify-center font-bold text-sm shrink-0">
                                            🛡️
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Graceful Data Freeze') }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Data dan histori target aman selamanya tanpa terhapus.') }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 p-3 rounded-2xl bg-white/80 dark:bg-gray-800/80 border border-amber-200/50 dark:border-amber-800/40">
                                        <div class="w-8 h-8 rounded-xl bg-purple-500/15 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-sm shrink-0">
                                            ⚡
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white">{{ __('Prioritas AI & Server') }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Antrean kilat untuk pembuatan roadmap & optimasi rutinitas.') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- FREE USER UPGRADE BANNER -->
                            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-7 border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-6">
                                <div>
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-black uppercase tracking-wider mb-2">
                                        <span>🌱</span>
                                        <span>{{ __('Paket Aktif: Free Starter') }}</span>
                                    </div>
                                    <h4 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white tracking-tight">
                                        {{ __('Tingkatkan Produktivitas Anda ke Level Tertinggi') }}
                                    </h4>
                                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        {{ __('Buka akses multi-milestone, asisten AI tanpa batas, dan playlist musik YouTube tanpa batasan folder.') }}
                                    </p>
                                </div>

                                <!-- Usage Quota Cards -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-750/50 border border-gray-200/70 dark:border-gray-700/60">
                                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">{{ __('Milestone Berjalan') }}</div>
                                        <div class="text-lg font-black text-gray-900 dark:text-white mt-1">
                                            {{ auth()->user()->skills()->where('status', '!=', 'completed')->count() }} / 1
                                        </div>
                                        <div class="text-[10px] text-amber-600 dark:text-amber-400 font-bold mt-0.5">{{ __('Akun Free terbatas 1 target aktif') }}</div>
                                    </div>

                                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-750/50 border border-gray-200/70 dark:border-gray-700/60">
                                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">{{ __('Folder Musik Pomodoro') }}</div>
                                        <div class="text-lg font-black text-gray-900 dark:text-white mt-1">
                                            Maks. 2 Folder
                                        </div>
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">{{ __('Upgrade untuk simpan banyak playlist') }}</div>
                                    </div>

                                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-750/50 border border-gray-200/70 dark:border-gray-700/60">
                                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">{{ __('AI Coach & Bulk Task') }}</div>
                                        <div class="text-lg font-black text-gray-900 dark:text-white mt-1">
                                            Kuota Standar
                                        </div>
                                        <div class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold mt-0.5">{{ __('PRO: 1-Klik Buat Task Harian Otomatis') }}</div>
                                    </div>
                                </div>

                                <!-- Pricing Box Switcher -->
                                <div class="p-5 sm:p-6 bg-gradient-to-br from-amber-500/10 via-yellow-500/5 to-amber-500/5 rounded-3xl border border-amber-300/60 dark:border-amber-700/50">
                                    <div class="flex items-center justify-center gap-2 mb-5 p-1 bg-white/90 dark:bg-gray-900/90 rounded-2xl max-w-xs mx-auto border border-gray-200/60 dark:border-gray-700/60 shadow-2xs">
                                        <button type="button" 
                                                @click="billingCycle = 'yearly'" 
                                                :class="billingCycle === 'yearly' ? 'bg-amber-500 text-white shadow-xs font-black' : 'text-gray-500 dark:text-gray-400 font-bold'"
                                                class="flex-1 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-center gap-1">
                                            <span>{{ __('Tahunan') }}</span>
                                            <span class="text-[9px] bg-emerald-500 text-white px-1.5 py-0.2 rounded-full font-black">Hemat 40%</span>
                                        </button>
                                        <button type="button" 
                                                @click="billingCycle = 'monthly'" 
                                                :class="billingCycle === 'monthly' ? 'bg-amber-500 text-white shadow-xs font-black' : 'text-gray-500 dark:text-gray-400 font-bold'"
                                                class="flex-1 py-1.5 rounded-xl text-xs transition cursor-pointer font-bold">
                                            {{ __('Bulanan') }}
                                        </button>
                                    </div>

                                    <div class="text-center mb-6">
                                        <div x-show="billingCycle === 'yearly'" class="space-y-1">
                                            <div class="flex items-baseline justify-center gap-1.5">
                                                <span class="text-sm font-bold text-gray-400 line-through">Rp 348.000</span>
                                                <span class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white">Rp 199.000</span>
                                                <span class="text-xs font-bold text-gray-500">/ tahun</span>
                                            </div>
                                            <div class="text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                                                {{ __('Hanya ~Rp 16.500 per bulan • Ditagih per tahun') }}
                                            </div>
                                        </div>

                                        <div x-show="billingCycle === 'monthly'" class="space-y-1" style="display: none;">
                                            <div class="flex items-baseline justify-center gap-1">
                                                <span class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white">Rp 29.000</span>
                                                <span class="text-xs font-bold text-gray-500">/ bulan</span>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                                {{ __('Bebas batalkan kapan saja tanpa komitmen kontrak') }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3 max-w-md mx-auto">
                                        <button type="button" 
                                                @click="$dispatch('open-manual-payment-modal', { plan: billingCycle })"
                                                class="w-full py-3.5 px-6 rounded-2xl font-black text-sm flex items-center justify-center gap-2 shadow-lg transition-all cursor-pointer bg-gradient-to-r from-amber-500 via-amber-600 to-yellow-500 hover:from-amber-600 hover:to-yellow-600 text-white shadow-amber-500/25 hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0">
                                            <span>👑</span>
                                            <span>{{ __('Pilih Paket & Konfirmasi via Admin (WhatsApp)') }}</span>
                                        </button>
                                    </div>

                                    <div class="text-center mt-2.5">
                                        <form method="POST" action="/api/subscription/toggle-demo" class="inline-block">
                                            @csrf
                                            <input type="hidden" name="action" value="upgrade">
                                            <button type="submit" 
                                                    class="text-[11px] text-gray-500 hover:text-amber-600 dark:text-gray-400 dark:hover:text-amber-400 underline cursor-pointer">
                                                {{ __('Simulasikan Langsung Jadi PRO (Demo Testing)') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Feature Comparison Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-4">
                            <h4 class="text-sm font-black uppercase tracking-wider text-gray-800 dark:text-gray-200">
                                {{ __('Perbandingan Fitur Free vs ATOMA PRO') }}
                            </h4>

                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-100 dark:border-gray-700/60 text-gray-400">
                                            <th class="py-3 px-3 font-bold">{{ __('Fitur / Layanan') }}</th>
                                            <th class="py-3 px-3 font-bold text-center w-36">{{ __('Free Starter') }}</th>
                                            <th class="py-3 px-3 font-black text-amber-600 dark:text-amber-400 text-center w-40 bg-amber-500/5 dark:bg-amber-950/20 rounded-t-xl">{{ __('ATOMA PRO 👑') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 text-gray-700 dark:text-gray-300 font-medium">
                                        <tr>
                                            <td class="py-3 px-3 font-bold text-gray-900 dark:text-white">{{ __('Target 90 Days Challenge Aktif') }}</td>
                                            <td class="py-3 px-3 text-center text-gray-500">{{ __('Maks. 1 Target') }}</td>
                                            <td class="py-3 px-3 text-center font-bold text-amber-700 dark:text-amber-300 bg-amber-500/5 dark:bg-amber-950/20">{{ __('Unlimited (Bebas)') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 px-3 font-bold text-gray-900 dark:text-white">{{ __('Konsultasi & Penjadwalan AI Coach') }}</td>
                                            <td class="py-3 px-3 text-center text-gray-500">{{ __('Kuota Dasar (~6/hari)') }}</td>
                                            <td class="py-3 px-3 text-center font-bold text-amber-700 dark:text-amber-300 bg-amber-500/5 dark:bg-amber-950/20">{{ __('Unlimited & Prioritas') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 px-3 font-bold text-gray-900 dark:text-white">{{ __('1-Klik Generate Task Harian AI (Bulk Tasks)') }}</td>
                                            <td class="py-3 px-3 text-center text-gray-400">✕ {{ __('Manual') }}</td>
                                            <td class="py-3 px-3 text-center font-bold text-emerald-600 dark:text-emerald-400 bg-amber-500/5 dark:bg-amber-950/20">✓ {{ __('Tersedia') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 px-3 font-bold text-gray-900 dark:text-white">{{ __('Folder Musik YouTube Pomodoro') }}</td>
                                            <td class="py-3 px-3 text-center text-gray-500">{{ __('Maks. 2 Folder') }}</td>
                                            <td class="py-3 px-3 text-center font-bold text-amber-700 dark:text-amber-300 bg-amber-500/5 dark:bg-amber-950/20">{{ __('Unlimited Playlist') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 px-3 font-bold text-gray-900 dark:text-white">{{ __('Sertifikat Digital Resmi 90 Hari (QR Verifikasi)') }}</td>
                                            <td class="py-3 px-3 text-center text-gray-400">✕ {{ __('Lencana Biasa') }}</td>
                                            <td class="py-3 px-3 text-center font-bold text-emerald-600 dark:text-emerald-400 bg-amber-500/5 dark:bg-amber-950/20">✓ {{ __('Sertifikat LinkedIn') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 px-3 font-bold text-gray-900 dark:text-white">{{ __('Perlindungan Data Jika Langganan Berakhir') }}</td>
                                            <td class="py-3 px-3 text-center text-emerald-600 dark:text-emerald-400">✓ {{ __('Aman') }}</td>
                                            <td class="py-3 px-3 text-center font-bold text-emerald-600 dark:text-emerald-400 bg-amber-500/5 dark:bg-amber-950/20">✓ {{ __('Graceful Freeze') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Payment Methods & Security -->
                        <div class="p-5 bg-gray-50 dark:bg-gray-800/60 rounded-3xl border border-gray-200/80 dark:border-gray-700/80 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-gray-500 dark:text-gray-400">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🔒</span>
                                <span>{{ __('Pembayaran resmi & aman didukung oleh standar transfer perbankan & e-wallet.') }}</span>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap font-bold text-[10px] text-gray-600 dark:text-gray-300">
                                <span class="px-2 py-1 rounded-md bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600">QRIS</span>
                                <span class="px-2 py-1 rounded-md bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600">GoPay / OVO / Dana</span>
                                <span class="px-2 py-1 rounded-md bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600">BCA / Mandiri</span>
                                <span class="px-2 py-1 rounded-md bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600">WhatsApp Admin</span>
                            </div>
                        </div>

                        <!-- Section: Riwayat Langganan & Bukti Aktivasi -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <h4 class="text-sm font-black uppercase tracking-wider text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                        <span>🧾 {{ __('Riwayat Aktivasi & Pembayaran Langganan') }}</span>
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ __('Daftar faktur digital dan bukti sah aktivasi paket ATOMA PRO akun Anda.') }}
                                    </p>
                                </div>
                            </div>

                            @php
                                $transactions = auth()->user()->subscriptionTransactions;
                            @endphp

                            @if($transactions->isNotEmpty())
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-gray-100 dark:border-gray-700/60 text-gray-400 uppercase text-[10px] tracking-wider">
                                                <th class="py-2.5 px-3 font-bold">{{ __('No. Referensi') }}</th>
                                                <th class="py-2.5 px-3 font-bold">{{ __('Paket') }}</th>
                                                <th class="py-2.5 px-3 font-bold">{{ __('Nominal') }}</th>
                                                <th class="py-2.5 px-3 font-bold">{{ __('Masa Berlaku') }}</th>
                                                <th class="py-2.5 px-3 font-bold">{{ __('Metode') }}</th>
                                                <th class="py-2.5 px-3 font-bold text-center">{{ __('Status') }}</th>
                                                <th class="py-2.5 px-3 font-bold text-right">{{ __('Bukti') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                            @foreach($transactions as $tx)
                                                @php
                                                    $validityText = ($tx->starts_at ? $tx->starts_at->format('d/m/Y') : '-') . ' s/d ' . ($tx->expires_at ? $tx->expires_at->format('d/m/Y') : 'Seumur Hidup');
                                                @endphp
                                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                                                    <td class="py-3 px-3 font-mono font-bold text-gray-900 dark:text-white">
                                                        {{ $tx->reference_no }}
                                                    </td>
                                                    <td class="py-3 px-3 font-bold text-gray-800 dark:text-gray-200">
                                                        {{ $tx->plan_name }}
                                                    </td>
                                                    <td class="py-3 px-3 font-bold text-emerald-600 dark:text-emerald-400">
                                                        {{ $tx->formatted_amount }}
                                                    </td>
                                                    <td class="py-3 px-3 text-gray-600 dark:text-gray-300">
                                                        {{ $validityText }}
                                                    </td>
                                                    <td class="py-3 px-3 text-gray-500 capitalize">
                                                        {{ str_replace('_', ' ', $tx->payment_method) }}
                                                    </td>
                                                    <td class="py-3 px-3 text-center">
                                                        @if($tx->isActive())
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                                                {{ __('Lunas & Aktif') }}
                                                            </span>
                                                        @elseif($tx->status === 'revoked')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                                {{ __('Dicabut') }}
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                                {{ __('Kedaluwarsa') }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="py-3 px-3 text-right">
                                                        <button type="button" 
                                                                @click="$dispatch('open-receipt-modal', {
                                                                    reference_no: '{{ $tx->reference_no }}',
                                                                    date: '{{ $tx->created_at->format('d M Y, H:i') }}',
                                                                    user_name: '{{ addslashes(auth()->user()->name) }}',
                                                                    user_email: '{{ addslashes(auth()->user()->email) }}',
                                                                    plan_name: '{{ addslashes($tx->plan_name) }}',
                                                                    amount: '{{ $tx->formatted_amount }}',
                                                                    payment_method: '{{ str_replace('_', ' ', $tx->payment_method) }}',
                                                                    validity: '{{ $validityText }}'
                                                                })"
                                                                class="px-2.5 py-1 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold transition-all cursor-pointer">
                                                            🧾 {{ __('Lihat Bukti') }}
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="py-8 text-center text-xs text-gray-400">
                                    <span class="text-2xl block mb-1">📜</span>
                                    {{ __('Belum ada riwayat transaksi aktivasi di akun ini.') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- TAB 6: Integrasi AI & MCP Server -->
                    <div x-show="activeTab === 'mcp'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                        <livewire:profile.mcp-token-manager />
                    </div>

                </main>
            </div>
        </div>
    </div>
</x-app-layout>
