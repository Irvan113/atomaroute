<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ATOMA - {{ __('Jadikan Hari-Harimu Lebih Produktif & Menyenangkan') }}</title>
    <link rel="icon" type="image/png" href="/images/atoma_icon.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#3E64FF">
    <meta name="description" content="ATOMA - Gamified Productivity & Focus Platform. Selesaikan tugas, bangun streak kebiasaan, Spaced Repetition flashcards, dan raih potensi terbaikmu setiap hari.">

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="ATOMA - Gamified Productivity & Focus Platform">
    <meta property="og:description" content="Selesaikan tugas, bangun kebiasaan harian, Spaced Repetition flashcards, dan rasakan kepuasan produktivitas harian bersama ATOMA.">
    <meta property="og:image" content="{{ asset('images/icon-512.png') }}">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="ATOMA - Gamified Productivity & Focus Platform">
    <meta name="twitter:description" content="Selesaikan tugas, bangun kebiasaan harian, Spaced Repetition flashcards, dan rasakan kepuasan produktivitas harian bersama ATOMA.">
    <meta name="twitter:image" content="{{ asset('images/icon-512.png') }}">

    <!-- Dark Mode & Custom Theme Persistence (Blocking FOUC) -->
    <script>
        (function() {
            function applyDarkMode() {
                if (localStorage.getItem('darkMode') === 'true' || (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
            applyDarkMode();

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
        })();
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased font-sans bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 overflow-x-hidden selection:bg-atoma-blue selection:text-white transition-colors">

    <!-- Navbar -->
    <nav class="fixed w-full z-50 transition-all duration-300 bg-white/85 dark:bg-slate-950/85 backdrop-blur-xl border-b border-slate-200/70 dark:border-slate-800/70">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 sm:h-20">
                <!-- Brand Logo -->
                <a href="/" class="flex items-center gap-2.5 sm:gap-3 group select-none">
                    <img src="/images/atoma_icon.png" alt="ATOMA Icon" class="h-9 sm:h-10 w-auto object-contain drop-shadow-sm group-hover:scale-105 transition-transform">
                    <span class="text-xl sm:text-2xl font-black tracking-wider bg-gradient-to-r from-atoma-blue to-atoma-cyan bg-clip-text text-transparent uppercase">
                        ATOMA
                    </span>
                </a>

                <!-- Nav Links Desktop -->
                <div class="hidden md:flex items-center gap-6 text-sm font-bold text-slate-600 dark:text-slate-300">
                    <a href="#fitur" class="hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors">{{ __('Fitur Seru') }}</a>
                    <a href="#download" class="hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors flex items-center gap-1.5">
                        <x-icon.download class="w-4 h-4 text-emerald-500" />
                        <span>{{ __('Download Aplikasi') }}</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 font-extrabold">.exe & .dmg</span>
                    </a>
                    <a href="{{ route('guide') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 flex items-center gap-1.5 font-extrabold">
                        <x-icon.book-open class="w-4 h-4" />
                        <span>{{ __('Panduan Cara Pakai') }}</span>
                    </a>
                    <a href="{{ route('terms') }}" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-1.5 font-extrabold">
                        <x-icon.shield-check class="w-4 h-4 text-emerald-500" />
                        <span>{{ __('Syarat & Privasi') }}</span>
                    </a>
                </div>

                <!-- Right Action Buttons -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Language Selector (ID / EN) -->
                    <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl p-0.5 text-xs font-extrabold border border-slate-200 dark:border-slate-700">
                        <a href="{{ route('lang.switch', 'id') }}" 
                           class="px-2 py-1 rounded-lg transition-all {{ app()->getLocale() === 'id' ? 'bg-white dark:bg-slate-700 text-atoma-blue shadow-xs font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900' }}">
                            ID
                        </a>
                        <a href="{{ route('lang.switch', 'en') }}" 
                           class="px-2 py-1 rounded-lg transition-all {{ app()->getLocale() === 'en' ? 'bg-white dark:bg-slate-700 text-atoma-blue shadow-xs font-black' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900' }}">
                            EN
                        </a>
                    </div>

                    <!-- Dark Mode Toggle -->
                    <button type="button" 
                            onclick="window.toggleDarkMode()" 
                            class="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                            title="{{ __('Ganti Mode Terang / Gelap') }}"
                            aria-label="{{ __('Ganti Mode Terang atau Gelap') }}">
                        <x-icon.sun class="w-5 h-5 hidden dark:block text-amber-400" />
                        <svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                    </button>

                    @if (Route::has('login'))
                        @auth
                            <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-atoma-blue to-atoma-cyan hover:opacity-95 text-white font-extrabold px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-xl shadow-md shadow-atoma-blue/20 transition-all hover:-translate-y-0.5 text-xs sm:text-sm flex items-center gap-1.5">
                                <span>{{ __('Buka Dashboard') }}</span>
                                <x-icon.arrow-right class="w-4 h-4" />
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="font-bold text-slate-600 hover:text-atoma-blue dark:text-slate-300 dark:hover:text-atoma-cyan transition-colors text-xs sm:text-sm px-2 sm:px-3 py-2">
                                {{ __('Masuk') }}
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="bg-atoma-blue hover:bg-blue-600 text-white font-extrabold px-3.5 sm:px-6 py-2 sm:py-2.5 rounded-xl shadow-md shadow-atoma-blue/25 transition-all hover:-translate-y-0.5 text-xs sm:text-sm">
                                    {{ __('Sign Up') }}
                                </a>
                            @endif
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative min-h-[90vh] flex items-center justify-center pt-28 pb-14 overflow-hidden">
        <!-- Background Ambient Glow -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjIiIGZpbGw9IiM2MzY2ZjEiIGZpbGwtb3BhY2l0eT0iMC4wNiIvPjwvc3ZnPg==')] bg-[length:32px_32px]"></div>
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-atoma-blue/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-pulse"></div>
            <div class="absolute top-1/3 right-1/4 w-96 h-96 bg-atoma-cyan/20 rounded-full mix-blend-multiply filter blur-3xl opacity-60"></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <!-- Badge Pengumuman -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-100/80 dark:bg-blue-950/60 text-atoma-blue dark:text-atoma-cyan font-extrabold text-xs sm:text-sm mb-6 border border-blue-200/60 dark:border-blue-800/60 shadow-xs">
                <x-icon.sparkles class="w-4 h-4 text-atoma-blue dark:text-atoma-cyan" />
                <span>{{ __('Bebas ribet: Buka langsung di web, pasang di HP, atau download di PC') }}</span>
            </div>
            
            <h1 class="text-4xl sm:text-6xl md:text-7xl font-black tracking-tight mb-6 leading-[1.15] text-slate-900 dark:text-white max-w-5xl mx-auto">
                {{ __('Bikin Rencana Harianmu') }} <br class="hidden sm:block"/>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-atoma-blue via-indigo-500 to-atoma-cyan">{{ __('Lebih Terarah, Fokus, & Nyata.') }}</span>
            </h1>
            
            <p class="mt-4 max-w-2xl text-base sm:text-xl text-slate-600 dark:text-slate-300 mx-auto font-medium mb-10 leading-relaxed">
                {{ __('Platform produktivitas lengkap untuk mengelola tugas, rutinitas harian, kurikulum belajar 30 hari, dan sesi fokus terpadu dalam satu alur kerja yang rapi.') }}
            </p>
            
            <!-- Tombol Aksi Utama -->
            <div class="flex flex-col sm:flex-row gap-3.5 justify-center max-w-xl mx-auto">
                <a href="{{ route('register') }}" class="bg-gradient-to-r from-atoma-blue to-atoma-cyan hover:opacity-95 text-white font-black text-base px-8 py-4 rounded-2xl shadow-xl shadow-atoma-blue/25 transition-all hover:-translate-y-0.5 active:scale-95 flex items-center justify-center gap-2">
                    <span>{{ __('Sign Up') }}</span>
                    <x-icon.arrow-right class="w-5 h-5" />
                </a>
                <a href="#download" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-800 dark:text-white border border-slate-200 dark:border-slate-700 font-extrabold text-base px-6 py-4 rounded-2xl shadow-xs transition-all flex items-center justify-center gap-2">
                    <x-icon.download class="w-5 h-5 text-emerald-500" />
                    <span>{{ __('Download Aplikasi') }}</span>
                </a>
                <a href="{{ route('guide') }}" class="bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60 font-extrabold text-base px-6 py-4 rounded-2xl transition-all flex items-center justify-center gap-2">
                    <x-icon.book-open class="w-5 h-5" />
                    <span>{{ __('Cara Pakai') }}</span>
                </a>
            </div>

            <!-- Kartu Pratinjau Tampilan Dashboard -->
            <div class="mt-14 max-w-4xl mx-auto rounded-3xl p-3 sm:p-5 bg-gradient-to-b from-white/80 to-white/40 dark:from-slate-800/60 dark:to-slate-900/40 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-700/60 shadow-2xl">
                <div class="rounded-2xl bg-slate-900 p-4 sm:p-6 text-left text-white shadow-inner overflow-hidden relative">
                    <!-- Top Toolbar Mockup -->
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-rose-500 inline-block"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                            <span class="text-xs font-mono text-slate-400 ml-2">atoma.app/dashboard</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-400 bg-emerald-950/60 px-2.5 py-0.5 rounded-lg border border-emerald-800/40">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>{{ __('Manajemen Tugas & Rutinitas') }}</span>
                        </div>
                    </div>

                    <!-- Mini Widgets Preview -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div class="p-3.5 rounded-xl bg-slate-800/80 border border-slate-700/70 space-y-2">
                            <div class="text-slate-300 font-bold flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <x-icon.flame class="w-4 h-4 text-orange-500" />
                                    <span>{{ __('Konsistensi Harian') }}</span>
                                </span>
                                <span class="text-orange-400 font-black">{{ __('7 Hari Berturut') }}</span>
                            </div>
                            <div class="w-full bg-slate-700 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-gradient-to-r from-amber-400 to-orange-500 h-full rounded-full" style="width: 85%"></div>
                            </div>
                            <div class="text-[10px] text-slate-400">{{ __('Streak terlindungi kupon proteksi') }}</div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-800/80 border border-slate-700/70 space-y-2">
                            <div class="text-slate-300 font-bold flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <x-icon.clock class="w-4 h-4 text-rose-400" />
                                    <span>{{ __('Timer Fokus & Musik') }}</span>
                                </span>
                                <span class="text-rose-400 font-black font-mono">25:00</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-[11px] text-slate-200">
                                <x-icon.music class="w-3.5 h-3.5 text-atoma-cyan animate-pulse inline-block" />
                                <span class="truncate">{{ __('Lofi Beats & Hujan Santai') }}</span>
                            </div>
                            <div class="text-[10px] text-emerald-400 font-semibold">{{ __('+2.5 XP per menit fokus') }}</div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-800/80 border border-slate-700/70 space-y-2">
                            <div class="text-slate-300 font-bold flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <x-icon.trophy class="w-4 h-4 text-amber-400" />
                                    <span>{{ __('Pangkat Profil') }}</span>
                                </span>
                                <span class="text-atoma-cyan font-black">Tier 3 Elite</span>
                            </div>
                            <div class="flex items-center gap-1 text-[11px] text-slate-200">
                                <span>Level 24</span>
                                <span class="text-slate-400">• 1,850 / 2,000 XP</span>
                            </div>
                            <div class="text-[10px] text-indigo-400 font-semibold">{{ __('Avatar Frame Neon Aktif') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PUSAT DOWNLOAD RESMI (.EXE & .DMG & PWA) -->
    <section id="download" class="py-20 bg-gradient-to-b from-slate-50 to-blue-50/50 dark:from-slate-950 dark:to-slate-900/60 border-y border-slate-200/80 dark:border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-xs font-black uppercase tracking-wider mb-3 border border-emerald-200 dark:border-emerald-800/60">
                <x-icon.download class="w-3.5 h-3.5" />
                <span>{{ __('Download & Pasang Aplikasi') }}</span>
            </div>
            <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
                {{ __('Pilih Cara Pakai yang Paling Nyaman Buat Kamu') }}
            </h2>
            <p class="mt-3 text-slate-600 dark:text-slate-400 max-w-2xl mx-auto font-medium text-sm sm:text-base">
                {{ __('Bisa dibuka langsung di browser, dipasang di layar HP, atau diunduh ke laptop agar tetap menyala di background saat jendela diminimize.') }}
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12 text-left max-w-5xl mx-auto">
                <!-- Windows (.exe) Card -->
                <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-7 border-2 border-slate-200 dark:border-slate-800 shadow-xl hover:border-atoma-blue dark:hover:border-atoma-cyan transition-all flex flex-col justify-between group">
                    <div class="space-y-4">
                        <div class="w-14 h-14 rounded-2xl bg-blue-100 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M0 3.449L9.75 2.1v9.451H0m10.949-9.602L24 0v11.4H10.949M0 12.6h9.75v9.451L0 20.699M10.949 12.6H24V24l-12.9-1.801"/></svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-mono font-bold text-slate-400 uppercase">{{ __('Untuk Laptop / PC Windows') }}</span>
                            <h3 class="text-xl font-black text-slate-900 dark:text-white mt-0.5">Windows App (.exe)</h3>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
                            {{ __('Installer praktis untuk Windows 10 & 11. Sangat ringan (~15 MB), tidak membebani RAM, dan tetap aktif di pojok taskbar saat di-minimize.') }}
                        </p>
                        <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300 font-medium">
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('Tetap jalan di latar belakang (System Tray)') }}</span>
                            </li>
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('Jendela melayang di atas aplikasi lain (Always-on-Top)') }}</span>
                            </li>
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('Notifikasi pengingat fokus langsung di PC') }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-8 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" 
                                onclick="window.installDesktopApp('windows')" 
                                class="w-full py-3 px-4 bg-atoma-blue hover:bg-blue-600 text-white rounded-2xl font-black text-sm text-center shadow-lg shadow-atoma-blue/20 transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <span>{{ __('Pasang App Windows') }}</span>
                            <x-icon.download class="w-4 h-4" />
                        </button>
                        <div class="text-[10px] text-center text-slate-400 mt-2 font-mono">{{ __('PWA Desktop • Langsung Terpasang') }}</div>
                    </div>
                </div>

                <!-- macOS (.dmg) Card -->
                <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-7 border-2 border-slate-200 dark:border-slate-800 shadow-xl hover:border-indigo-500 dark:hover:border-indigo-400 transition-all flex flex-col justify-between group">
                    <div class="space-y-4">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.37c.61-.75 1.04-1.8 0.92-2.85-.9.04-2 .6-2.65 1.34-.56.63-1.05 1.68-.92 2.71 1.01.08 2.04-.45 2.65-1.2"/></svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-mono font-bold text-slate-400 uppercase">{{ __('Untuk Macbook / Mac Desktop') }}</span>
                            <h3 class="text-xl font-black text-slate-900 dark:text-white mt-0.5">macOS App (PWA)</h3>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
                            {{ __('Installer native untuk Mac (M1/M2/M3/M4 Apple Silicon & Intel). Sangat hemat baterai karena memakai engine bawaan Mac.') }}
                        </p>
                        <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300 font-medium">
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('Ikon status di Menu Bar atas & Dock') }}</span>
                            </li>
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('Musik Lo-Fi tetap nyala di background') }}</span>
                            </li>
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('Shortcut keyboard untuk kendali cepat') }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-8 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" 
                                onclick="window.installDesktopApp('mac')" 
                                class="w-full py-3 px-4 bg-slate-900 dark:bg-indigo-600 hover:bg-slate-800 dark:hover:bg-indigo-500 text-white rounded-2xl font-black text-sm text-center shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <span>{{ __('Pasang App Mac') }}</span>
                            <x-icon.download class="w-4 h-4" />
                        </button>
                        <div class="text-[10px] text-center text-slate-400 mt-2 font-mono">{{ __('PWA Desktop • Langsung Terpasang') }}</div>
                    </div>
                </div>

                <!-- PWA Card -->
                <div class="bg-gradient-to-br from-indigo-50/70 to-blue-50/70 dark:from-slate-800/80 dark:to-slate-900/80 rounded-[2.5rem] p-7 border-2 border-indigo-200/80 dark:border-indigo-800/60 shadow-xl flex flex-col justify-between group">
                    <div class="space-y-4">
                        <div class="w-14 h-14 rounded-2xl bg-atoma-blue text-white flex items-center justify-center group-hover:scale-105 transition-transform shadow-md shadow-atoma-blue/30">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-mono font-bold text-atoma-blue uppercase">{{ __('Langsung Pasang di HP') }}</span>
                            <h3 class="text-xl font-black text-slate-900 dark:text-white mt-0.5">PWA Mobile (Android & iPhone)</h3>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                            {{ __('Tidak perlu download file besar dari toko aplikasi. Cukup buka di browser HP Anda, lalu pasang ke layar utama dengan 1 ketukan.') }}
                        </p>
                        <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300 font-medium">
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('0 MB Memori (Sangat hemat penyimpanan)') }}</span>
                            </li>
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('Bisa diakses cepat lewat ikon di Home Screen') }}</span>
                            </li>
                            <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">
                                <x-icon.check class="w-4 h-4 shrink-0" />
                                <span>{{ __('Otomatis sinkron dengan akun di laptop') }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-8 pt-4 border-t border-indigo-100 dark:border-slate-800 space-y-2">
                        <button onclick="if(window.installAtomaPwa){window.installAtomaPwa()}else{window.location.href='/register'}" 
                                class="w-full py-3 px-4 bg-gradient-to-r from-atoma-blue to-atoma-cyan hover:opacity-95 text-white rounded-2xl font-black text-sm text-center shadow-lg shadow-atoma-blue/25 transition-all cursor-pointer flex items-center justify-center gap-2">
                            <span>{{ __('Pasang ke Layar HP Sekarang') }}</span>
                        </button>
                        <a href="{{ route('dashboard') }}" class="block text-center text-xs font-bold text-atoma-blue hover:underline">
                            {{ __('Atau buka langsung di browser →') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FITUR UTAMA BERFOKUS PADA FUNGSIONALITAS -->
    <section id="fitur" class="py-24 bg-white dark:bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-xs font-bold uppercase tracking-wider text-atoma-blue">{{ __('Fitur Unggulan') }}</span>
                <h2 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight mt-1">
                    {{ __('Semua Alat Produktivitas yang Kamu Butuhkan') }}
                </h2>
                <p class="text-slate-500 dark:text-slate-400 font-medium text-sm sm:text-base max-w-2xl mx-auto mt-2">
                    {{ __('Dirancang fungsional untuk membantumu mengeksekusi rencana, membangun kebiasaan disiplin, menguasai keahlian baru, dan menjaga fokus setiap hari.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-7">
                <!-- 1. Task (Manajemen Tugas) -->
                <div class="p-7 rounded-[2rem] bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <x-icon.target class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">{{ __('Manajemen Tugas Multi-Fungsi') }}</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ __('Bukan sekadar checklist biasa. Pecah target rumit dengan bantuan AI Decomposer, pilih mode santai (Zen) atau terstruktur (Sistematis), atur kebiasaan berulang (Recurring), serta integrasikan langsung dengan pencatatan latihan olahraga (set, beban, tonase) dan arus kas finansial.') }}
                    </p>
                </div>

                <!-- 2. Milestone (Roadmap 30 Hari) -->
                <div class="p-7 rounded-[2rem] bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <x-icon.layers class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">{{ __('Roadmap & Kurikulum 30 Hari') }}</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ __('Bagi sasaran besar jangka panjang menjadi kurikulum terstruktur 30 hari yang realistis. Dilengkapi pembagian modul harian otomatis via AI, jadwal hari istirahat berkah XP (rest day), pemantauan progres bertahap, hingga kelulusan berstempel Sertifikat Digital Mastery.') }}
                    </p>
                </div>

                <!-- 3. Sistem Level Up (Gamifikasi RPG) -->
                <div class="p-7 rounded-[2rem] bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <x-icon.sparkles class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">{{ __('Sistem Level Up & Prestise RPG') }}</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ __('Ubah produktivitas nyata menjadi perkembangan karakter game. Dapatkan EXP dan Koin dari setiap tugas dan sesi fokus untuk menaikkan level, pantau Radar Chart 5 Atribut (Fokus, Konsistensi, Stamina, Sosial, Disiplin), serta lakukan Prestige Ascension untuk naik ke kasta lebih tinggi.') }}
                    </p>
                </div>

                <!-- 4. Pomodoro (Timer Fokus & Audio) -->
                <div class="p-7 rounded-[2rem] bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <x-icon.clock class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">{{ __('Timer Pomodoro & Audio Terpadu') }}</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ __('Bekerja lebih konsentrasi tanpa perlu membuka aplikasi musik terpisah. Dilengkapi streaming Lo-Fi YouTube dan audio suara alam yang tidak pernah terputus saat bernavigasi, mode jendela melayang (PiP) di atas aplikasi lain, serta reward EXP progresif yang adil per menit.') }}
                    </p>
                </div>

                <!-- 5. Sosial (Kolaborasi & Akuntabilitas) -->
                <div class="p-7 rounded-[2rem] bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <x-icon.users class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">{{ __('Kolaborasi Sosial & Akuntabilitas') }}</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ __('Jaga motivasi dan konsistensi bersama rekan. Kerjakan proyek kelompok bersama lewat fitur Co-op Task, saling ingatkan teman yang menunda lewat fitur Senggol (Nudge), tantang duel produktivitas harian berhadiah bonus EXP, dan bersaing sehat di Leaderboard 12 divisi liga.') }}
                    </p>
                </div>

                <!-- 6. Flashcard (Spaced Repetition System) -->
                <div class="p-7 rounded-[2rem] bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-teal-100 dark:bg-teal-900/40 text-teal-600 dark:text-teal-400 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                        <x-icon.cards class="w-6 h-6" />
                    </div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">{{ __('Kartu Belajar Spaced Repetition (SRS)') }}</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ __('Kuasai materi kuliah, kosakata bahasa asing, rumus, atau konsep penting secara permanen. Didukung algoritma ilmiah Spaced Repetition yang secara otomatis menjadwalkan interval pengulangan optimal (H+1, H+3, H+7) berdasarkan tingkat ingatanmu agar retensi memori maksimal.') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- BANNER AJAKAN KE HALAMAN GUIDE LENGKAP -->
    <section class="py-14 bg-gradient-to-r from-atoma-blue via-indigo-600 to-atoma-cyan text-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
            <div class="space-y-1.5 max-w-2xl">
                <span class="px-3 py-1 rounded-full bg-white/20 text-white text-xs font-black uppercase tracking-wider">{{ __('Buku Panduan Pengguna') }}</span>
                <h3 class="text-2xl sm:text-3xl font-black tracking-tight">{{ __('Mau Tahu Cara Kerja Lengkap Setiap Halaman di ATOMA?') }}</h3>
                <p class="text-white/85 text-xs sm:text-sm font-medium leading-relaxed">
                    {{ __('Kami buatkan panduan santai per halaman (Halaman Tugas, Milestone, Pomodoro, Note, Flashcard, hingga Toko Hadiah) agar kamu bisa langsung mahir menggunakannya.') }}
                </p>
            </div>
            <a href="{{ route('guide') }}" class="bg-white hover:bg-slate-100 text-slate-900 font-black text-sm sm:text-base px-7 py-3.5 rounded-2xl shadow-xl transition-all hover:scale-105 shrink-0 flex items-center gap-2">
                <x-icon.book-open class="w-4 h-4 text-atoma-blue" />
                <span>{{ __('Baca Panduan Lengkap') }}</span>
                <x-icon.arrow-right class="w-4 h-4" />
            </a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-2.5">
                <img src="/images/atoma_icon.png" alt="ATOMA" class="h-7 w-auto">
                <span class="font-black text-slate-900 dark:text-white uppercase tracking-wider text-sm sm:text-base">ATOMA</span>
                <span class="text-xs text-slate-400">| {{ __('Level Up Your Routine') }}</span>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-5 text-xs font-bold text-slate-500 dark:text-slate-400">
                <a href="#fitur" class="hover:text-atoma-blue">{{ __('Fitur') }}</a>
                <a href="#download" class="hover:text-atoma-blue">{{ __('Download Aplikasi') }}</a>
                <a href="{{ route('guide') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Panduan Cara Pakai') }}</a>
                <a href="{{ route('terms') }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">{{ __('Syarat & Privasi') }}</a>
                <a href="{{ route('login') }}" class="hover:text-atoma-blue">{{ __('Masuk') }}</a>
                <a href="{{ route('register') }}" class="hover:text-atoma-blue">{{ __('Sign Up') }}</a>
            </div>

            <div class="text-xs text-slate-400">
                &copy; {{ date('Y') }} ATOMA. {{ __('Semua hak cipta dilindungi undang-undang.') }}
            </div>
        </div>
    </footer>

    <script>
        window.deferredPwaPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            window.deferredPwaPrompt = e;
        });

        window.installAtomaPwa = async function() {
            if (window.deferredPwaPrompt) {
                window.deferredPwaPrompt.prompt();
                await window.deferredPwaPrompt.userChoice;
                window.deferredPwaPrompt = null;
            } else {
                alert("{{ __('Untuk memasang ATOMA di ponsel:\n1. Buka situs ini di browser Chrome atau Safari di ponsel Anda.\n2. Buka menu browser dan pilih \'Tambahkan ke Layar Utama\' (Add to Home Screen).\n3. ATOMA langsung siap dibuka dari layar ponsel Anda.') }}");
            }
        };

        window.installDesktopApp = async function(platform) {
            if (window.deferredPwaPrompt) {
                window.deferredPwaPrompt.prompt();
                await window.deferredPwaPrompt.userChoice;
                window.deferredPwaPrompt = null;
            } else {
                alert("{{ __('Pasang ATOMA Desktop:\n\nATOMA mendukung instalasi instan PWA Desktop tanpa perlu unduh installer berat!\n\n1. Buka situs ini di Google Chrome atau Microsoft Edge.\n2. Klik ikon \'Pasang Aplikasi\' di sebelah kanan kolom alamat (URL bar) atas.\n3. ATOMA langsung terpasang di Desktop & Start Menu Anda dengan performa ringan!') }}");
            }
        };

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => {
                    console.log('SW registration failed: ', err);
                });
            });
        }
    </script>

    @livewireScripts
</body>
</html>
