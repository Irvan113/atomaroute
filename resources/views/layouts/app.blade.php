<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ATOMA') }}</title>
        <link rel="icon" type="image/png" href="/images/atoma_icon.png">
        <link rel="apple-touch-icon" href="/images/icon-192.png">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#3E64FF">
        <meta name="application-name" content="ATOMA">
        <meta name="apple-mobile-web-app-title" content="ATOMA">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="description" content="ATOMA - Gamified Productivity & Focus Platform">
        <meta property="og:title" content="ATOMA - Gamified Productivity & Focus Platform">
        <meta property="og:description" content="Selesaikan tugas, bangun kebiasaan harian, dan fokus lebih dalam bersama ATOMA.">
        <meta property="og:image" content="{{ asset('images/icon-512.png') }}">
        
        <style>
            [x-cloak] { display: none !important; }
            html { color-scheme: light dark; }
            html.dark { color-scheme: dark; background-color: #111827 !important; }
            html:not(.dark) { background-color: #f9fafb !important; }

            /* Theme Classes */
            html[data-theme="cyberpunk"].dark { background-color: #080c14 !important; }
            html[data-theme="cyberpunk"] .bg-indigo-600 { background-color: #06b6d4 !important; }
            html[data-theme="cyberpunk"] .text-indigo-600 { color: #06b6d4 !important; }

            html[data-theme="lofi"].dark { background-color: #1c1917 !important; }
            html[data-theme="lofi"] .bg-indigo-600 { background-color: #d97706 !important; }
            html[data-theme="lofi"] .text-indigo-600 { color: #d97706 !important; }

            html[data-theme="emerald"].dark { background-color: #064e3b !important; }
            html[data-theme="emerald"] .bg-indigo-600 { background-color: #10b981 !important; }
            html[data-theme="emerald"] .text-indigo-600 { color: #10b981 !important; }

            html[data-theme="midnight"].dark { background-color: #000000 !important; }
            html[data-theme="midnight"] .bg-indigo-600 { background-color: #8b5cf6 !important; }
            html[data-theme="midnight"] .text-indigo-600 { color: #a78bfa !important; }

            @keyframes atoma-shake {
                0%, 100% { transform: translateX(0); }
                15%, 45%, 75% { transform: translateX(-8px) rotate(-1deg); }
                30%, 60%, 90% { transform: translateX(8px) rotate(1deg); }
            }
            .animate-atoma-shake {
                animation: atoma-shake 0.5s ease-in-out;
            }
        </style>

        <!-- Dark Mode & Custom Theme Persistence (Blocking FOUC) -->
        <script>
            (function() {
                function applyDarkMode() {
                    if (localStorage.getItem('darkMode') === 'true' || (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                    const activeTheme = localStorage.getItem('activeTheme') || '{{ auth()->user()->active_theme ?? "default" }}';
                    document.documentElement.setAttribute('data-theme', activeTheme);
                }
                
                // Execute immediately before render
                applyDarkMode();

                // Bind to Livewire SPA events (both navigating & navigated)
                document.addEventListener('livewire:navigating', applyDarkMode);
                document.addEventListener('livewire:navigated', applyDarkMode);
                document.addEventListener('livewire:init', applyDarkMode);

                // Global toggle function
                window.toggleDarkMode = function() {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('darkMode', 'false');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('darkMode', 'true');
                    }
                    window.dispatchEvent(new Event('dark-mode-toggled'));
                };

                window.applyAppTheme = function(themeName) {
                    localStorage.setItem('activeTheme', themeName);
                    document.documentElement.setAttribute('data-theme', themeName);
                };
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts (data-navigate-once prevents re-evaluation and memory leaks on wire:navigate) -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js" data-navigate-once></script>
        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js" data-navigate-once></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" data-navigate-once></script>
        
        <script data-navigate-once>
            window.addEventListener('confirm-delete', (e) => {
                let text = "You won't be able to revert this!";
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3E64FF',
                    cancelButtonColor: '#ef4444',
                    confirmButtonText: 'Yes, delete it!',
                    background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                    color: document.documentElement.classList.contains('dark') ? '#f3f4f6' : '#111827',
                }).then((result) => {
                    if (result.isConfirmed) {
                        // We use Livewire's global dispatch to trigger the deletion in the component
                        Livewire.dispatch(e.detail.action, { id: e.detail.id });
                    }
                })
            });
            // Unified Toast Dispatches to the Modern Alpine System
            window.showToastNotification = (detail) => {
                window.dispatchEvent(new CustomEvent('toast', { detail }));
            };
            window.addEventListener('show-toast', (e) => {
                window.dispatchEvent(new CustomEvent('toast', { detail: e.detail }));
            });

            // Confetti Listener
            window.addEventListener('trigger-confetti', () => {
                if (typeof confetti === 'function') {
                    confetti({
                        particleCount: 100,
                        spread: 70,
                        origin: { y: 0.6 }
                    });
                }
            });

            // Livewire Hooks: Intercept wire:confirm with SweetAlert2 & Graceful 419 Handling
            document.addEventListener('livewire:init', () => {
                // Intercept wire:confirm directive with custom dark-mode styled SweetAlert2 modal
                Livewire.hook('directive.init', ({ el, directive }) => {
                    if (directive.name === 'confirm' || directive.value === 'confirm') {
                        const rawMsg = directive.expression || 'Apakah kamu yakin ingin melanjutkan tindakan ini?';
                        el.__livewire_confirm = (action, instead) => {
                            const isDark = document.documentElement.classList.contains('dark');
                            Swal.fire({
                                title: 'Konfirmasi',
                                text: rawMsg.replaceAll('\\n', '\n'),
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3E64FF',
                                cancelButtonColor: isDark ? '#4b5563' : '#9ca3af',
                                confirmButtonText: 'Ya, Lanjutkan',
                                cancelButtonText: 'Batal',
                                reverseButtons: true,
                                focusCancel: true,
                                background: isDark ? '#1f2937' : '#ffffff',
                                color: isDark ? '#f3f4f6' : '#111827',
                                customClass: {
                                    popup: 'rounded-3xl border border-gray-200 dark:border-gray-700 shadow-2xl backdrop-blur-xl',
                                    confirmButton: 'px-5 py-2.5 rounded-xl font-bold text-sm shadow-md cursor-pointer',
                                    cancelButton: 'px-5 py-2.5 rounded-xl font-bold text-sm cursor-pointer'
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    action();
                                } else if (instead) {
                                    instead();
                                }
                            });
                        };
                    }
                });

                // Global Keyboard Shortcuts (N: New Task, P: Toggle Pomodoro, F / ⌘K: Quick Search, ?: Shortcuts Guide)
                window.addEventListener('keydown', (e) => {
                    const target = e.target;
                    const tag = (target.tagName || '').toLowerCase();
                    if (['input', 'textarea', 'select'].includes(tag) || target.isContentEditable || target.closest('[contenteditable="true"]')) {
                        return;
                    }

                    // N / n: New Task
                    if ((e.key === 'n' || e.key === 'N') && !e.ctrlKey && !e.metaKey && !e.altKey) {
                        e.preventDefault();
                        if (window.location.pathname === '/tasks' || window.location.pathname === '/dashboard') {
                            window.dispatchEvent(new CustomEvent('open-new-task-modal'));
                        } else if (window.Livewire) {
                            window.Livewire.navigate('/tasks');
                        }
                    }
                    // P / p: Toggle Pomodoro widget
                    else if ((e.key === 'p' || e.key === 'P') && !e.ctrlKey && !e.metaKey && !e.altKey) {
                        e.preventDefault();
                        window.dispatchEvent(new CustomEvent('toggle-pomodoro-widget'));
                    }
                    // F / f or ⌘K / Ctrl+K: Universal Command Palette
                    else if (((e.key === 'f' || e.key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey) || ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K'))) {
                        e.preventDefault();
                        window.dispatchEvent(new CustomEvent('open-command-palette'));
                    }
                    // ?: Shortcuts cheat sheet toast
                    else if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                        e.preventDefault();
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: {
                                message: '⌨️ Shortcuts: [⌘K / Ctrl+K] Command Palette • [N] Tugas Baru • [P] Pomodoro • [?] Panduan',
                                type: 'info',
                                duration: 5000
                            }
                        }));
                    }
                });

                // Web Audio Synthesizer (Zero Latency & 0 kB Audio Assets)
                window.playHapticSound = function(type = 'click') {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;
                        if (!AudioContext) return;
                        if (!window._atomaAudioCtx) {
                            window._atomaAudioCtx = new AudioContext();
                        }
                        const ctx = window._atomaAudioCtx;
                        if (ctx.state === 'suspended') {
                            ctx.resume();
                        }

                        const now = ctx.currentTime;

                        if (type === 'click' || type === 'pop') {
                            const osc = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.type = 'sine';
                            osc.frequency.setValueAtTime(800, now);
                            osc.frequency.exponentialRampToValueAtTime(120, now + 0.04);
                            gain.gain.setValueAtTime(0.18, now);
                            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.04);
                            osc.connect(gain);
                            gain.connect(ctx.destination);
                            osc.start(now);
                            osc.stop(now + 0.04);
                        } else if (type === 'chime' || type === 'success' || type === 'fanfare') {
                            [523.25, 659.25, 783.99].forEach((freq, idx) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                const start = now + idx * 0.08;
                                osc.type = 'triangle';
                                osc.frequency.setValueAtTime(freq, start);
                                gain.gain.setValueAtTime(0.15, start);
                                gain.gain.exponentialRampToValueAtTime(0.001, start + 0.35);
                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(start);
                                osc.stop(start + 0.35);
                            });
                        }
                    } catch (e) {}
                };

                window.addEventListener('play-sound', (e) => {
                    const preset = e.detail?.preset || e.detail?.type || 'click';
                    window.playHapticSound(preset);
                });

                // Intercept 419 CSRF Expired to reload smoothly without raw browser alert
                Livewire.hook('request', ({ fail }) => {
                    fail(({ status, preventDefault }) => {
                        if (status === 419) {
                            preventDefault();
                            window.location.reload();
                        }
                    });
                });
            });
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <script data-navigate-once>
        document.addEventListener('livewire:initialized', () => {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const currentTz = '{{ session('auto_timezone') }}';
            if (tz && tz !== currentTz) {
                Livewire.dispatch('sync-timezone', { tz: tz });
            }
        });
    </script>
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 flex flex-col md:flex-row h-screen overflow-hidden" 
          x-data="{ sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true' }" 
          x-init="$watch('sidebarCollapsed', val => localStorage.setItem('sidebarCollapsed', val))" 
          @toggle-sidebar.window="sidebarCollapsed = !sidebarCollapsed">

        <!-- Global SPA Navigation Progress Bar (Ultra-Smooth, 0% Backend Overhead) -->
        <div id="atoma-nav-progress" class="fixed top-0 left-0 h-[2.5px] w-0 bg-gradient-to-r from-atoma-blue via-atoma-cyan to-indigo-500 z-[9999] transition-all duration-300 ease-out opacity-0 pointer-events-none shadow-[0_0_8px_rgba(62,100,255,0.7)]"></div>

        <!-- Navigation (Left on Desktop, Bottom on Mobile) -->
        <aside :class="sidebarCollapsed ? 'flex md:hidden' : 'flex md:flex md:w-72 lg:w-80'" class="fixed bottom-0 w-full md:relative border-t md:border-t-0 md:border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0 z-20 flex-col h-[calc(4rem+env(safe-area-inset-bottom,0px))] pb-[env(safe-area-inset-bottom,0px)] md:h-full md:pb-0 transition-all duration-300">
            <livewire:layout.navigation />
        </aside>

        <!-- Main Content (Right on Desktop) -->
        <main class="flex-1 overflow-y-auto overflow-x-hidden w-full relative pb-[calc(4.5rem+env(safe-area-inset-bottom,0px))] md:pb-0 min-w-0 scroll-smooth">
            @auth
                <livewire:layout.top-header />
            @endauth
            
            @if (isset($header))
                <header class="bg-white/50 dark:bg-gray-800/50 backdrop-blur-md sticky top-0 z-10 border-b border-gray-200 dark:border-gray-700">
                    <div class="px-4 py-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                        {{ $header }}
                    </div>
                </header>
            @endif
            
            <div class="p-3 sm:p-5 md:p-6 lg:p-8 max-w-7xl mx-auto w-full min-w-0">
                @auth
                    <x-subscription-reminder-banner />
                @endauth
                {{ $slot }}
            </div>
        </main>

        <!-- Pomodoro Timer Widget (Persisted across wire:navigate) -->
        @persist('pomodoro-timer')
            <x-pomodoro-timer />
        @endpersist
        @auth
            <livewire:pomodoro-reward />
            <livewire:feedback-modal />
            <x-command-palette />
        @endauth

        <!-- Modern Unified Toast Notification System -->
        <div x-data="{
                toasts: [],
                add(detail) {
                    const raw = Array.isArray(detail) ? detail[0] : detail;
                    const message = typeof raw === 'string' ? raw : (raw.message || raw.title || 'Notifikasi');
                    const type = (raw.type || raw.icon || 'info').toLowerCase();
                    const id = Date.now() + Math.random();
                    const duration = raw.duration || 4000;
                    const title = raw.title || null;
                    
                    const toast = {
                        id: id,
                        title: title,
                        message: message,
                        type: type,
                        duration: duration,
                        remaining: duration,
                        paused: false,
                        progress: 100,
                        startTime: Date.now()
                    };

                    this.toasts.push(toast);

                    // Soft subtle audio chime
                    if (window.AtomaAudio && type !== 'silent') {
                        const soundPreset = type === 'success' ? 'chime' : (type === 'nudge' ? 'marimba' : 'gentle_ping');
                        window.AtomaAudio.play(soundPreset, 0.2);
                    }

                    // Countdown interval for smooth progress bar
                    const step = 50;
                    const interval = setInterval(() => {
                        if (!toast.paused) {
                            toast.remaining -= step;
                            toast.progress = Math.max(0, (toast.remaining / toast.duration) * 100);
                            if (toast.remaining <= 0) {
                                clearInterval(interval);
                                this.remove(id);
                            }
                        }
                    }, step);
                    toast.interval = interval;
                },
                remove(id) {
                    const t = this.toasts.find(item => item.id === id);
                    if (t && t.interval) clearInterval(t.interval);
                    this.toasts = this.toasts.filter(item => item.id !== id);
                }
             }" 
             @toast.window="add($event.detail)"
             @show-toast.window="add($event.detail)"
             class="fixed top-4 right-4 sm:top-6 sm:right-6 z-[9999] flex flex-col items-end space-y-3 pointer-events-none max-w-sm w-full px-4 sm:px-0">
            
            <template x-for="toast in toasts" :key="toast.id">
                <div x-show="true"
                     @mouseenter="toast.paused = true"
                     @mouseleave="toast.paused = false"
                     x-transition:enter="transition ease-out duration-300 transform"
                     x-transition:enter-start="opacity-0 translate-y-[-12px] scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-200 transform"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-90 translate-y-[-10px]"
                     class="pointer-events-auto relative w-full overflow-hidden rounded-2xl bg-white/95 dark:bg-gray-850/95 backdrop-blur-xl border shadow-2xl p-4 flex items-start gap-3.5 transition-all"
                     :class="{
                        'border-emerald-200 dark:border-emerald-800/80 text-emerald-950 dark:text-emerald-50': toast.type === 'success',
                        'border-rose-200 dark:border-rose-800/80 text-rose-950 dark:text-rose-50': toast.type === 'error' || toast.type === 'danger',
                        'border-amber-200 dark:border-amber-800/80 text-amber-950 dark:text-amber-50': toast.type === 'warning' || toast.type === 'nudge',
                        'border-indigo-200 dark:border-indigo-800/80 text-gray-900 dark:text-gray-100': toast.type !== 'success' && toast.type !== 'error' && toast.type !== 'danger' && toast.type !== 'warning' && toast.type !== 'nudge'
                     }">

                    <!-- Icon Container -->
                    <div class="rounded-xl p-2 shrink-0 flex items-center justify-center shadow-xs"
                         :class="{
                            'bg-emerald-500 text-white': toast.type === 'success',
                            'bg-rose-500 text-white': toast.type === 'error' || toast.type === 'danger',
                            'bg-amber-500 text-white': toast.type === 'warning' || toast.type === 'nudge',
                            'bg-atoma-blue text-white': toast.type !== 'success' && toast.type !== 'error' && toast.type !== 'danger' && toast.type !== 'warning' && toast.type !== 'nudge'
                         }">
                        <template x-if="toast.type === 'success'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </template>
                        <template x-if="toast.type === 'error' || toast.type === 'danger'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </template>
                        <template x-if="toast.type === 'warning' || toast.type === 'nudge'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </template>
                        <template x-if="toast.type !== 'success' && toast.type !== 'error' && toast.type !== 'danger' && toast.type !== 'warning' && toast.type !== 'nudge'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </template>
                    </div>

                    <!-- Message Body -->
                    <div class="flex-1 min-w-0 pt-0.5">
                        <div class="font-extrabold text-[11px] tracking-wide uppercase opacity-75"
                             x-text="toast.title ? toast.title : (toast.type === 'success' ? 'Berhasil' : (toast.type === 'nudge' ? 'Senggolan Teman' : (toast.type === 'error' || toast.type === 'danger' ? 'Perhatian' : 'Info')))">
                        </div>
                        <p class="font-semibold text-xs sm:text-sm text-gray-800 dark:text-gray-200 mt-0.5 leading-snug break-words" x-text="toast.message"></p>
                    </div>

                    <!-- Close Button -->
                    <button type="button" 
                            @click="remove(toast.id)"
                            class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 p-1 rounded-lg transition-colors cursor-pointer shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>

                    <!-- Auto-dismiss Progress Bar at bottom -->
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-gray-100 dark:bg-gray-800/80">
                        <div class="h-full transition-all ease-linear"
                             :style="`width: ${toast.progress}%;`"
                             :class="{
                                'bg-emerald-500': toast.type === 'success',
                                'bg-rose-500': toast.type === 'error' || toast.type === 'danger',
                                'bg-amber-500': toast.type === 'warning' || toast.type === 'nudge',
                                'bg-atoma-blue': toast.type !== 'success' && toast.type !== 'error' && toast.type !== 'danger' && toast.type !== 'warning' && toast.type !== 'nudge'
                             }"></div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Global Web Audio Synthesizer & Custom Sound Player -->
        <script>
            window.AtomaAudio = {
                ctx: null,
                getContext() {
                    if (!this.ctx) {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (AudioCtx) {
                            this.ctx = new AudioCtx();
                        }
                    }
                    if (this.ctx && this.ctx.state === 'suspended') {
                        this.ctx.resume();
                    }
                    return this.ctx;
                },
                play(presetName = null, volumeMultiplier = null) {
                    const soundEnabled = localStorage.getItem('atoma_sound_enabled') !== 'false';
                    if (!soundEnabled) return;

                    // If presetName is not provided or is a generic task action (success, task_complete, complete),
                    // fallback to the user's configured notification sound (which can be 'custom' or preset).
                    let preset = presetName;
                    if (!preset || ['success', 'task_complete', 'complete', 'default', 'finish'].includes(preset)) {
                        preset = localStorage.getItem('atoma_notification_sound') || 'chime';
                    }
                    if (preset === 'silent' || preset === 'none') return;

                    const baseVol = volumeMultiplier !== null 
                        ? volumeMultiplier 
                        : parseFloat(localStorage.getItem('atoma_notification_volume') || '0.7');

                    const ctx = this.getContext();
                    if (!ctx) return;

                    const now = ctx.currentTime;

                    switch (preset) {
                        case 'chime':
                            // Gentle resonant harmonic bell
                            [587.33, 880, 1174.66].forEach((freq, idx) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                osc.type = 'sine';
                                osc.frequency.setValueAtTime(freq, now + idx * 0.04);

                                gain.gain.setValueAtTime(0.001, now);
                                gain.gain.linearRampToValueAtTime(baseVol * 0.3, now + idx * 0.04 + 0.02);
                                gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.04 + 0.8);

                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(now + idx * 0.04);
                                osc.stop(now + idx * 0.04 + 0.85);
                            });
                            break;

                        case 'digital_beep':
                            // Modern crisp double-beep
                            [0, 0.1].forEach((delay) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                osc.type = 'sine';
                                osc.frequency.setValueAtTime(880, now + delay);

                                gain.gain.setValueAtTime(0.001, now + delay);
                                gain.gain.linearRampToValueAtTime(baseVol * 0.4, now + delay + 0.01);
                                gain.gain.exponentialRampToValueAtTime(0.0001, now + delay + 0.06);

                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(now + delay);
                                osc.stop(now + delay + 0.07);
                            });
                            break;

                        case 'pop_bubble':
                            // Organic water bubble pop
                            const popOsc = ctx.createOscillator();
                            const popGain = ctx.createGain();
                            popOsc.type = 'sine';
                            popOsc.frequency.setValueAtTime(320, now);
                            popOsc.frequency.exponentialRampToValueAtTime(950, now + 0.08);

                            popGain.gain.setValueAtTime(0.001, now);
                            popGain.gain.linearRampToValueAtTime(baseVol * 0.5, now + 0.02);
                            popGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.12);

                            popOsc.connect(popGain);
                            popGain.connect(ctx.destination);
                            popOsc.start(now);
                            popOsc.stop(now + 0.14);
                            break;

                        case 'marimba':
                            // Warm melodic wooden ding chord
                            [523.25, 659.25, 783.99].forEach((freq, idx) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                osc.type = 'triangle';
                                osc.frequency.setValueAtTime(freq, now + idx * 0.06);

                                gain.gain.setValueAtTime(0.001, now);
                                gain.gain.linearRampToValueAtTime(baseVol * 0.4, now + idx * 0.06 + 0.015);
                                gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.06 + 0.5);

                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(now + idx * 0.06);
                                osc.stop(now + idx * 0.06 + 0.55);
                            });
                            break;

                        case 'fanfare':
                        case 'levelup':
                            // Triumphant victory arpeggio: C5 -> E5 -> G5 -> C6
                            [523.25, 659.25, 783.99, 1046.50].forEach((freq, idx) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                osc.type = idx === 3 ? 'triangle' : 'sine';
                                osc.frequency.setValueAtTime(freq, now + idx * 0.08);

                                gain.gain.setValueAtTime(0.001, now + idx * 0.08);
                                gain.gain.linearRampToValueAtTime(baseVol * (idx === 3 ? 0.45 : 0.3), now + idx * 0.08 + 0.02);
                                gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.08 + (idx === 3 ? 0.9 : 0.3));

                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(now + idx * 0.08);
                                osc.stop(now + idx * 0.08 + 0.95);
                            });
                            break;

                        case 'custom':
                            // User uploaded custom audio file or URL
                            const customAudioSrc = localStorage.getItem('atoma_custom_sound_data') || localStorage.getItem('atoma_custom_sound_url');
                            if (customAudioSrc) {
                                try {
                                    const audio = new Audio(customAudioSrc);
                                    audio.volume = Math.min(1.0, Math.max(0.05, baseVol));
                                    audio.play().catch((err) => {
                                        console.warn('Custom audio playback failed, falling back to chime:', err);
                                        this.play('chime', volumeMultiplier);
                                    });
                                } catch (e) {
                                    this.play('chime', volumeMultiplier);
                                }
                            } else {
                                 this.play('chime', volumeMultiplier);
                            }
                            break;

                        case 'slash':
                        case 'sword_clash':
                            // Crisp metallic sword clash / slash
                            [880, 1760, 440].forEach((freq, idx) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                osc.type = idx === 0 ? 'sawtooth' : 'triangle';
                                osc.frequency.setValueAtTime(freq, now + idx * 0.03);
                                osc.frequency.exponentialRampToValueAtTime(110, now + idx * 0.03 + 0.25);

                                gain.gain.setValueAtTime(0.001, now);
                                gain.gain.linearRampToValueAtTime(baseVol * 0.45, now + idx * 0.03 + 0.01);
                                gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.03 + 0.3);

                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(now + idx * 0.03);
                                osc.stop(now + idx * 0.03 + 0.35);
                            });
                            break;

                        case 'bonk':
                            // Playful cartoon pitch-drop bonk
                            const bonkOsc = ctx.createOscillator();
                            const bonkGain = ctx.createGain();
                            bonkOsc.type = 'triangle';
                            bonkOsc.frequency.setValueAtTime(520, now);
                            bonkOsc.frequency.exponentialRampToValueAtTime(90, now + 0.2);

                            bonkGain.gain.setValueAtTime(0.001, now);
                            bonkGain.gain.linearRampToValueAtTime(baseVol * 0.5, now + 0.015);
                            bonkGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.25);

                            bonkOsc.connect(bonkGain);
                            bonkGain.connect(ctx.destination);
                            bonkOsc.start(now);
                            bonkOsc.stop(now + 0.28);
                            break;

                        case 'shield':
                            // Harmonious shield resonate chime
                            [440, 554.37, 659.25, 880].forEach((freq, idx) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                osc.type = 'sine';
                                osc.frequency.setValueAtTime(freq, now);

                                gain.gain.setValueAtTime(0.001, now);
                                gain.gain.linearRampToValueAtTime(baseVol * 0.25, now + 0.02);
                                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.6);

                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(now);
                                osc.stop(now + 0.65);
                            });
                            break;

                        case 'retro_8bit':
                        case 'arcade':
                            // Authentic NES / GameBoy chiptune square-wave arpeggio: C5 -> E5 -> G5 -> C6
                            [523.25, 659.25, 783.99, 1046.50].forEach((freq, idx) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                osc.type = 'square';
                                osc.frequency.setValueAtTime(freq, now + idx * 0.05);

                                gain.gain.setValueAtTime(0.001, now + idx * 0.05);
                                gain.gain.linearRampToValueAtTime(baseVol * 0.35, now + idx * 0.05 + 0.01);
                                gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.05 + 0.09);

                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(now + idx * 0.05);
                                osc.stop(now + idx * 0.05 + 0.1);
                            });
                            break;

                        case 'cyber_synth':
                            // Futuristic laser synth sweep with resonant filter
                            const csOsc = ctx.createOscillator();
                            const csFilter = ctx.createBiquadFilter();
                            const csGain = ctx.createGain();

                            csOsc.type = 'sawtooth';
                            csOsc.frequency.setValueAtTime(220, now);
                            csOsc.frequency.exponentialRampToValueAtTime(880, now + 0.08);
                            csOsc.frequency.exponentialRampToValueAtTime(440, now + 0.25);

                            csFilter.type = 'lowpass';
                            csFilter.Q.value = 8;
                            csFilter.frequency.setValueAtTime(400, now);
                            csFilter.frequency.exponentialRampToValueAtTime(3200, now + 0.07);
                            csFilter.frequency.exponentialRampToValueAtTime(600, now + 0.3);

                            csGain.gain.setValueAtTime(0.001, now);
                            csGain.gain.linearRampToValueAtTime(baseVol * 0.4, now + 0.02);
                            csGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);

                            csOsc.connect(csFilter);
                            csFilter.connect(csGain);
                            csGain.connect(ctx.destination);
                            csOsc.start(now);
                            csOsc.stop(now + 0.38);
                            break;

                        case 'zen_bell':
                        case 'singing_bowl':
                            // Acoustic Tibetan singing bowl harmonic chime
                            [320, 640, 960].forEach((freq, idx) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                osc.type = 'sine';
                                osc.frequency.setValueAtTime(freq, now);

                                gain.gain.setValueAtTime(0.001, now);
                                gain.gain.linearRampToValueAtTime(baseVol * (idx === 0 ? 0.4 : 0.15), now + 0.03);
                                gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.2);

                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start(now);
                                osc.stop(now + 1.25);
                            });
                            break;

                        case 'gentle_ping':
                        default:
                            // Soft subtle ping
                            const pingOsc = ctx.createOscillator();
                            const pingGain = ctx.createGain();
                            pingOsc.type = 'sine';
                            pingOsc.frequency.setValueAtTime(659.25, now); // E5

                            pingGain.gain.setValueAtTime(0.001, now);
                            pingGain.gain.linearRampToValueAtTime(baseVol * 0.35, now + 0.02);
                            pingGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);

                            pingOsc.connect(pingGain);
                            pingGain.connect(ctx.destination);
                            pingOsc.start(now);
                            pingOsc.stop(now + 0.45);
                            break;
                    }
                }
            };
        </script>

        <!-- Unified Native Notification & Platform Bridge (Capacitor Android & Tauri Desktop) -->
        <script>
            window.AtomaPlatform = {
                isCapacitor() {
                    return Boolean(window.Capacitor && (typeof window.Capacitor.isNativePlatform === 'function' ? window.Capacitor.isNativePlatform() : true));
                },
                isTauri() {
                    return Boolean(window.__TAURI_INTERNALS__ || window.__TAURI__);
                },
                getPlatformName() {
                    if (this.isCapacitor()) return 'android';
                    if (this.isTauri()) return 'desktop';
                    return 'web';
                }
            };

            window.AtomaNativeNotification = {
                initialized: false,
                async init() {
                    if (this.initialized) return;
                    this.initialized = true;

                    // 1. Android Capacitor LocalNotifications Channel Setup
                    if (window.AtomaPlatform.isCapacitor() && window.Capacitor?.Plugins?.LocalNotifications) {
                        try {
                            const { LocalNotifications } = window.Capacitor.Plugins;
                            await LocalNotifications.createChannel({
                                id: 'atoma_reminders',
                                name: 'Pengingat & Alarm ATOMA',
                                description: 'Notifikasi tepat waktu untuk deadline tugas dan timer fokus',
                                importance: 5,
                                visibility: 1,
                                sound: 'chime.wav',
                                vibration: true,
                                lights: true,
                                lightColor: '#6366F1'
                            });
                            const perm = await LocalNotifications.checkPermissions();
                            if (perm.display !== 'granted') {
                                await LocalNotifications.requestPermissions();
                            }
                        } catch (e) {
                            console.warn('[ATOMA Native] Capacitor channel init error:', e);
                        }
                    }

                    // 2. Desktop (Tauri) Notification Setup
                    if (window.AtomaPlatform.isTauri()) {
                        try {
                            if (window.__TAURI__?.notification) {
                                const hasPerm = await window.__TAURI__.notification.isPermissionGranted();
                                if (!hasPerm) {
                                    await window.__TAURI__.notification.requestPermission();
                                }
                            }
                        } catch (e) {
                            console.warn('[ATOMA Native] Tauri notification init error:', e);
                        }
                    }
                },

                // Schedule a local notification (e.g. for task deadline or reminder)
                async schedule({ id, title, body, at, sound = 'chime' }) {
                    await this.init();
                    const triggerDate = at instanceof Date ? at : new Date(at);
                    if (isNaN(triggerDate.getTime()) || triggerDate <= new Date()) {
                        return false;
                    }

                    // Android Native Exact Alarm via Capacitor
                    if (window.AtomaPlatform.isCapacitor() && window.Capacitor?.Plugins?.LocalNotifications) {
                        try {
                            const notifId = typeof id === 'number' ? id : (Math.abs(parseInt(id, 10)) || Math.floor(Math.random() * 1000000));
                            await window.Capacitor.Plugins.LocalNotifications.schedule({
                                notifications: [{
                                    id: notifId,
                                    title: title || '⚠️ Pengingat Tugas ATOMA',
                                    body: body || 'Waktunya menyelesaikan target fokus Anda!',
                                    schedule: { at: triggerDate, allowWhileIdle: true },
                                    channelId: 'atoma_reminders',
                                    sound: 'chime.wav',
                                    smallIcon: 'ic_stat_icon_config_sample'
                                }]
                            });
                            return true;
                        } catch (e) {
                            console.warn('[ATOMA Native] Schedule error:', e);
                        }
                    }

                    return false;
                },

                // Cancel scheduled notification
                async cancel(id) {
                    if (window.AtomaPlatform.isCapacitor() && window.Capacitor?.Plugins?.LocalNotifications) {
                        try {
                            const notifId = typeof id === 'number' ? id : (Math.abs(parseInt(id, 10)) || 0);
                            await window.Capacitor.Plugins.LocalNotifications.cancel({
                                notifications: [{ id: notifId }]
                            });
                        } catch (e) {
                            console.warn('[ATOMA Native] Cancel error:', e);
                        }
                    }
                },

                // Trigger instant notification
                async notifyNow({ title, body, sound = 'chime' }) {
                    await this.init();

                    // Play audio effect
                    if (window.AtomaAudio) {
                        window.AtomaAudio.play(sound);
                    }

                    // Android Capacitor
                    if (window.AtomaPlatform.isCapacitor() && window.Capacitor?.Plugins?.LocalNotifications) {
                        try {
                            await window.Capacitor.Plugins.LocalNotifications.schedule({
                                notifications: [{
                                    id: Math.floor(Math.random() * 1000000),
                                    title,
                                    body,
                                    schedule: { at: new Date(Date.now() + 100) },
                                    channelId: 'atoma_reminders'
                                }]
                            });
                            return;
                        } catch (e) {
                            console.warn('[ATOMA Native] Notify error:', e);
                        }
                    }

                    // Desktop Tauri
                    if (window.AtomaPlatform.isTauri() && window.__TAURI__?.notification) {
                        try {
                            window.__TAURI__.notification.sendNotification({ title, body });
                            return;
                        } catch (e) {}
                    }

                    // Standard Web Notification fallback
                    if ('Notification' in window && Notification.permission === 'granted') {
                        new Notification(title, {
                            body,
                            icon: '/images/atoma_icon.png'
                        });
                    }
                }
            };

            // Auto-init on page load or user interaction
            document.addEventListener('DOMContentLoaded', () => {
                window.AtomaNativeNotification.init();
            });
            window.addEventListener('click', () => {
                window.AtomaNativeNotification.init();
            }, { once: true });
        </script>

        <div x-data @play-sound.window="
            if (window.AtomaAudio) {
                const raw = $event.detail;
                const detail = Array.isArray(raw) ? (raw[0] || {}) : (raw || {});
                if (detail.preset && detail.preset !== 'default') {
                    window.AtomaAudio.play(detail.preset, detail.volume);
                } else if (detail.type === 'levelup' || detail.type === 'level_up') {
                    window.AtomaAudio.play('fanfare', detail.volume);
                } else if (detail.type && !['success', 'task_complete', 'complete', 'finish'].includes(detail.type)) {
                    window.AtomaAudio.play(detail.type, detail.volume);
                } else {
                    // Task completion, success notifications, or default -> Play user's custom notification sound
                    window.AtomaAudio.play(null, detail.volume);
                }
            }
        " @schedule-reminder.window="
            if (window.AtomaNativeNotification) {
                const raw = $event.detail;
                const detail = Array.isArray(raw) ? (raw[0] || {}) : (raw || {});
                window.AtomaNativeNotification.schedule(detail);
            }
        " @cancel-reminder.window="
            if (window.AtomaNativeNotification) {
                const raw = $event.detail;
                const detail = Array.isArray(raw) ? (raw[0] || {}) : (raw || {});
                window.AtomaNativeNotification.cancel(detail.id || detail);
            }
        " @native-notify.window="
            if (window.AtomaNativeNotification) {
                const raw = $event.detail;
                const detail = Array.isArray(raw) ? (raw[0] || {}) : (raw || {});
                window.AtomaNativeNotification.notifyNow(detail);
            }
        "></div>
        
        <!-- Streak History Modal -->
        @auth
            <livewire:streak-modal />
        @endauth

        <!-- Initialize Echo for Nudges -->
        @auth
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => {
                    if (window.Echo) {
                        window.Echo.private(`user.{{ auth()->id() }}`)
                            .listen('.nudge.sent', (e) => {
                                window.dispatchEvent(new CustomEvent('toast', {
                                    detail: {
                                        message: e.fromUserName + " nyenggol kamu! Jangan lupa kerjakan To-Do hari ini! 🎯",
                                        type: 'nudge'
                                    }
                                }));
                                // Play a small sound
                                let audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
                                audio.play().catch(() => {});
                            });
                    }
                }, 1000); // Small delay to ensure Echo is loaded via Vite
            });
        </script>

        <x-weekly-infographic-modal />
        <x-achievement-modal />
        <x-achievement-celebration />
        <x-rank-showcase-modal />
        @endauth

        <!-- Global Offline Status Indicator Pill -->
        <div x-data="{ isOffline: !navigator.onLine }"
             @online.window="isOffline = false"
             @offline.window="isOffline = true"
             x-show="isOffline"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="-translate-y-8 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="-translate-y-8 opacity-0"
             x-cloak
             class="fixed top-3 inset-x-0 mx-auto z-[999] w-fit max-w-[90vw] px-4 py-2 bg-amber-500/95 dark:bg-amber-600/95 backdrop-blur-md text-white rounded-full shadow-lg border border-amber-300/40 flex items-center gap-2 text-xs font-bold pointer-events-auto">
            <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
            <span>⚡ Mode Offline Aktif — Catatan & tugas lokal tetap aman</span>
        </div>

        <!-- In-App PWA Install Prompt Banner (Mobile & Desktop) -->
        <div x-data="{ showInstall: false }"
             @pwa-installable.window="showInstall = true"
             @pwa-installed.window="showInstall = false"
             x-show="showInstall"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-6 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-cloak
             class="fixed bottom-4 left-4 z-50 max-w-sm bg-white/95 dark:bg-gray-800/95 backdrop-blur-md p-3 rounded-2xl shadow-2xl border border-atoma-blue/30 flex items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5 min-w-0">
                <img src="/images/icon-192.png" class="w-8 h-8 rounded-xl shrink-0 shadow-xs">
                <div class="min-w-0">
                    <div class="font-bold text-gray-900 dark:text-white truncate">Pasang ATOMA App</div>
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 truncate">Akses cepat, notif & hemat kuota</div>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" 
                        onclick="window.installAtomaPwa()" 
                        class="bg-atoma-blue hover:bg-blue-600 text-white font-bold px-3 py-1.5 rounded-xl shadow-xs transition-all active:scale-95 cursor-pointer">
                    Pasang
                </button>
                <button type="button" 
                        @click="showInstall = false" 
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-lg cursor-pointer">
                    ✕
                </button>
            </div>
        </div>

        <!-- PWA Service Worker & Install Prompt Registration -->
        <script>
            window.deferredPwaPrompt = null;
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                window.deferredPwaPrompt = e;
                window.dispatchEvent(new CustomEvent('pwa-installable'));
            });

            window.installAtomaPwa = async function() {
                if (window.deferredPwaPrompt) {
                    window.deferredPwaPrompt.prompt();
                    const { outcome } = await window.deferredPwaPrompt.userChoice;
                    if (outcome === 'accepted') {
                        window.deferredPwaPrompt = null;
                        window.dispatchEvent(new CustomEvent('pwa-installed'));
                    }
                }
            };

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js').catch(err => {
                        console.log('SW registration failed: ', err);
                    });
                });
            }

            // Global SPA Navigation Progress Bar Listener (Zero Backend Overhead)
            (function() {
                const bar = document.getElementById('atoma-nav-progress');
                if (!bar) return;
                let progressTimer = null;
                document.addEventListener('livewire:navigating', () => {
                    clearTimeout(progressTimer);
                    bar.style.transition = 'width 200ms ease-out, opacity 100ms ease';
                    bar.style.opacity = '1';
                    bar.style.width = '35%';
                    progressTimer = setTimeout(() => {
                        bar.style.width = '75%';
                    }, 150);
                });
                document.addEventListener('livewire:navigated', () => {
                    clearTimeout(progressTimer);
                    bar.style.transition = 'width 150ms ease-out, opacity 250ms ease 150ms';
                    bar.style.width = '100%';
                    bar.style.opacity = '0';
                    setTimeout(() => {
                        bar.style.width = '0%';
                    }, 400);
                });
            })();
        </script>

        {{-- Atoma PRO Subscription Upgrade Modal --}}
        @auth
            <x-pro-upgrade-modal />
            <x-manual-payment-modal />
            <x-digital-receipt-modal />
        @endauth

        {{-- Atoma Performance & Server Telemetry Monitor (Admin Only) --}}
        @if(auth()->check() && (auth()->user()->is_admin || auth()->user()->id === 1))
            <x-performance-monitor />
        @endif
    </body>
</html>
