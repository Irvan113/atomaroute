<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Dokumentasi Lengkap Alur Kerja & Fitur') }} - ATOMA</title>
    <link rel="icon" type="image/png" href="/images/atoma_icon.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#3E64FF">

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
<body class="antialiased font-sans bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 selection:bg-atoma-blue selection:text-white transition-colors">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border-b border-slate-200/80 dark:border-slate-800/80 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center gap-2.5 group select-none">
                <img src="/images/atoma_icon.png" alt="ATOMA" class="h-9 w-auto object-contain drop-shadow-sm group-hover:scale-105 transition-transform">
                <span class="text-xl font-black tracking-wider bg-gradient-to-r from-atoma-blue to-atoma-cyan bg-clip-text text-transparent uppercase">
                    ATOMA <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-normal">{{ __('Panduan') }}</span>
                </span>
            </a>

            <!-- Right Controls -->
            <div class="flex items-center gap-3">
                <!-- Theme Toggle Button -->
                <button type="button" 
                        onclick="window.toggleDarkMode()" 
                        class="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        title="{{ __('Ganti Mode Terang / Gelap') }}"
                        aria-label="{{ __('Ganti Mode Terang atau Gelap') }}">
                    <x-icon.sun class="w-5 h-5 hidden dark:block text-amber-400" />
                    <svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>

                <a href="/" class="hidden sm:inline-flex text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-atoma-blue dark:hover:text-atoma-cyan px-3 py-2 rounded-xl transition-colors">
                    {{ __('Beranda') }}
                </a>

                @auth
                    <a href="{{ route('dashboard') }}" class="bg-atoma-blue hover:bg-blue-600 text-white font-extrabold text-xs sm:text-sm px-4 py-2.5 rounded-xl shadow-md shadow-atoma-blue/20 transition-all active:scale-95 flex items-center gap-1.5">
                        <span>{{ __('Buka Dashboard') }}</span>
                        <x-icon.arrow-right class="w-4 h-4" />
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-atoma-blue px-3 py-2">
                        {{ __('Masuk') }}
                    </a>
                    <a href="{{ route('register') }}" class="bg-atoma-blue hover:bg-blue-600 text-white font-extrabold text-xs sm:text-sm px-4 py-2.5 rounded-xl shadow-md shadow-atoma-blue/20 transition-all active:scale-95">
                        {{ __('Sign Up') }}
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Header Panduan -->
    <section class="relative py-12 sm:py-16 bg-gradient-to-b from-blue-50/70 via-slate-50 to-slate-50 dark:from-slate-900/50 dark:via-slate-950 dark:to-slate-950 border-b border-slate-200/70 dark:border-slate-800/70">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-100/80 dark:bg-blue-950/60 text-atoma-blue dark:text-atoma-cyan text-xs font-black uppercase tracking-wider mb-4 border border-blue-200/60 dark:border-blue-800/60">
                <x-icon.book-open class="w-4 h-4" />
                <span>{{ __('Dokumentasi Sistem Produktivitas Terpadu') }}</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-slate-900 dark:text-white max-w-3xl mx-auto leading-tight">
                {{ __('Alur Kerja & Panduan Fitur Lengkap') }} <span class="bg-gradient-to-r from-atoma-blue to-atoma-cyan bg-clip-text text-transparent">ATOMA</span>
            </h1>
            <p class="mt-4 text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto font-medium leading-relaxed">
                {{ __('Panduan komprehensif untuk memaksimalkan seluruh modul ATOMA: eksekusi tugas harian terintegrasi, kurikulum belajar 30 hari, sesi fokus terarah, hafalan Spaced Repetition, hingga akuntabilitas sosial.') }}
            </p>

            <!-- Menu Lompat Cepat Per Halaman -->
            <div class="flex flex-wrap items-center justify-center gap-2 mt-8 max-w-4xl mx-auto">
                <a href="#page-task" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-atoma-blue text-xs font-bold shadow-xs border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.target class="w-3.5 h-3.5 text-blue-500" />
                    <span>{{ __('Halaman Tugas & Milestone') }}</span>
                </a>
                <a href="#section-ai-engine" class="px-3.5 py-1.5 rounded-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white hover:from-indigo-700 hover:to-purple-700 text-xs font-black shadow-xs transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.bot class="w-3.5 h-3.5 text-white" />
                    <span>{{ __('AI Super-Engine ATOMA') }}</span>
                </a>
                <a href="#widget-pomodoro" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-rose-500 text-xs font-bold shadow-xs border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.clock class="w-3.5 h-3.5 text-rose-500" />
                    <span>{{ __('Widget Pomodoro & Audio') }}</span>
                </a>
                <a href="#page-note" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-indigo-500 text-xs font-bold shadow-xs border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.file-text class="w-3.5 h-3.5 text-indigo-500" />
                    <span>{{ __('Halaman Note & Keuangan') }}</span>
                </a>
                <a href="#page-flashcard" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-teal-500 text-xs font-bold shadow-xs border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.cards class="w-3.5 h-3.5 text-teal-500" />
                    <span>{{ __('Halaman Flashcard (SRS)') }}</span>
                </a>
                <a href="#page-social" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-purple-500 text-xs font-bold shadow-xs border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.users class="w-3.5 h-3.5 text-purple-500" />
                    <span>{{ __('Halaman Sosial & Kolaborasi') }}</span>
                </a>
                <a href="#page-store" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-emerald-500 text-xs font-bold shadow-xs border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.gift class="w-3.5 h-3.5 text-emerald-500" />
                    <span>{{ __('Halaman Store & Rewards') }}</span>
                </a>
                <a href="#page-profile" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-amber-500 text-xs font-bold shadow-xs border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.gear class="w-3.5 h-3.5 text-amber-500" />
                    <span>{{ __('Profil & Statistik RPG') }}</span>
                </a>
                <a href="#desktop-pwa" class="px-3.5 py-1.5 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-sky-500 text-xs font-bold shadow-xs border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 flex items-center gap-1.5">
                    <x-icon.download class="w-3.5 h-3.5 text-sky-500" />
                    <span>{{ __('Aplikasi Desktop & PWA') }}</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Konten Panduan Utama -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            
            <!-- Sidebar Daftar Isi (Sticky Desktop) -->
            <aside class="hidden lg:block lg:col-span-3">
                <div class="sticky top-28 p-5 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
                    <div class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 px-2">
                        {{ __('Daftar Modul ATOMA') }}
                    </div>
                    <nav class="space-y-1 text-sm font-medium">
                        <a href="#page-task" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-atoma-blue transition-colors">
                            {{ __('1. Halaman Tugas (Tasks)') }}
                        </a>
                        <a href="#sub-add-task" class="block px-5 py-1 text-xs rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors">
                            {{ __('↳ Panduan Input & Dekomposisi') }}
                        </a>
                        <a href="#sub-special-tasks" class="block px-5 py-1 text-xs rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors">
                            {{ __('↳ Modul Kebugaran & Keuangan') }}
                        </a>
                        <a href="#sub-milestone" class="block px-5 py-1 text-xs rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors">
                            {{ __('↳ Kurikulum Milestone 30 Hari') }}
                        </a>
                        <a href="#section-ai-engine" class="block px-3 py-2 rounded-xl text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50/70 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors flex items-center justify-between">
                            <span>{{ __('2. Fitur AI Super-Engine') }}</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200">New</span>
                        </a>
                        <a href="#widget-pomodoro" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-rose-500 transition-colors">
                            {{ __('3. Widget Pomodoro & Audio') }}
                        </a>
                        <a href="#page-note" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-indigo-500 transition-colors">
                            {{ __('4. Halaman Note & Keuangan') }}
                        </a>
                        <a href="#page-flashcard" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-teal-500 transition-colors">
                            {{ __('5. Halaman Flashcard (SRS)') }}
                        </a>
                        <a href="#page-social" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-purple-500 transition-colors">
                            {{ __('6. Halaman Sosial & Kolaborasi') }}
                        </a>
                        <a href="#page-leaderboard" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-amber-500 transition-colors">
                            {{ __('7. Halaman Peringkat (Rank)') }}
                        </a>
                        <a href="#page-store" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-500 transition-colors">
                            {{ __('8. Halaman Toko Hadiah (Store)') }}
                        </a>
                        <a href="#page-profile" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-500 transition-colors">
                            {{ __('9. Halaman Profil & Pengaturan') }}
                        </a>
                        <a href="#desktop-pwa" class="block px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-sky-500 transition-colors">
                            {{ __('10. Aplikasi Desktop & PWA') }}
                        </a>
                    </nav>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                        <a href="/#download" class="w-full py-2 px-3 text-center bg-atoma-blue/10 hover:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                            <x-icon.download class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan" />
                            <span>{{ __('Download File Installer') }}</span>
                        </a>
                    </div>
                </div>
            </aside>

            <!-- Main Articles Stream -->
            <main class="lg:col-span-9 space-y-16">

                <!-- 1. HALAMAN TUGAS (TASKS) & MILESTONE -->
                <article id="page-task" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-8">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <x-icon.target class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-atoma-blue uppercase tracking-wider">{{ __('Halaman Utama') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('1. Halaman Tugas (Tasks) & Fitur Lengkapnya') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                        {{ __('Halaman Tugas (Tasks) merupakan pusat komando produktivitas harian Anda. Modul ini mengintegrasikan eksekusi to-do, time-blocking terstruktur, pelacakan olahraga mendalam, pencatatan transaksi keuangan instan, serta dekomposisi target berbasis kecerdasan buatan.') }}
                    </p>

                    <!-- Navigasi & Tampilan di Halaman Tugas -->
                    <div class="space-y-4">
                        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-2">
                            <h4 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-sm sm:text-base">
                                <x-icon.calendar class="w-4 h-4 text-atoma-blue" />
                                <span>{{ __('A. Navigasi Tanggal & Evaluasi Konsistensi Harian') }}</span>
                            </h4>
                            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ __('Bilah navigasi tanggal horizontal memungkinkan transisi cepat antara target masa lalu, jadwal hari ini, dan perencanaan masa depan. Indikator visual menandai hari-hari dengan tugas aktif atau yang belum terselesaikan, membantu Anda menjaga kesinambungan streak dan mengevaluasi backlog tanpa kehilangan konteks.') }}
                            </p>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-2">
                            <h4 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-sm sm:text-base">
                                <x-icon.check class="w-4 h-4 text-emerald-500" />
                                <span>{{ __('B. Fleksibilitas Struktur Kerja: Mode Timeline vs Mode Kartu') }}</span>
                            </h4>
                            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ __('Sesuaikan cara kerja dengan intensitas hari Anda: Mode Timeline menyajikan urutan kronologis berbasis jam (time-blocking) dari pagi hingga malam untuk mencegah tumpang-tindih jadwal, sedangkan Mode Kartu menyajikan ringkasan padat berbasis prioritas untuk eksekusi cepat.') }}
                            </p>
                        </div>
                    </div>

                    <!-- PANDUAN CARA MENAMBAH TUGAS BARU -->
                    <div id="sub-add-task" class="p-6 sm:p-8 rounded-3xl bg-blue-50/70 dark:bg-blue-950/20 border-2 border-blue-200/80 dark:border-blue-800/50 space-y-5">
                        <div class="flex items-center gap-2.5 text-blue-700 dark:text-blue-300 font-black text-base sm:text-lg">
                            <x-icon.plus class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            <span>{{ __('Panduan Langkah: Input Tugas & Dekomposisi Target') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                            {{ __('ATOMA dirancang untuk memangkas friksi input. Anda dapat menambahkan tugas melalui multi-channel capture, menentukan parameter disiplin waktu, memetakan kategori strategis, hingga memecah target kompleks secara otomatis:') }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Step 1 -->
                            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-blue-100 dark:border-slate-800 space-y-2">
                                <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-xs sm:text-sm">
                                    <span class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/60 flex items-center justify-center text-xs font-black">1</span>
                                    <span>{{ __('1. Multi-Channel Task Capture') }}</span>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed space-y-1">
                                    <p>{{ __('• Bilah Cepat (Quick Add): Ketik tugas langsung pada bilah input dan tekan Enter untuk pencatatan instan.') }}</p>
                                    <p>{{ __('• Formulir Terperinci: Gunakan tombol "+ Task Baru" untuk membuka pengaturan lengkap (jam, durasi, subtugas, dan tag).') }}</p>
                                    <p>{{ __('• Transkripsi Suara (Voice Input): Klik ikon mikrofon untuk mendiktekan tugas dalam bahasa Indonesia; sistem secara akurat mengonversi ucapan menjadi judul tugas tanpa perlu mengetik manual.') }}</p>
                                </div>
                            </div>

                            <!-- Step 2 -->
                            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-blue-100 dark:border-slate-800 space-y-2">
                                <div class="flex items-center gap-2 text-purple-600 dark:text-purple-400 font-bold text-xs sm:text-sm">
                                    <span class="w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-900/60 flex items-center justify-center text-xs font-black">2</span>
                                    <span>{{ __('2. Mode Eksekusi: Zen vs Sistematis') }}</span>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed space-y-1">
                                    <p>{{ __('• Zen Mode: Pendekatan fleksibel tanpa batasan jam kaku. Ideal untuk rutinitas pembentukan kebiasaan, pekerjaan kreatif, atau tugas harian yang tidak terikat waktu tertentu.') }}</p>
                                    <p>{{ __('• Mode Sistematis: Pendekatan disiplin waktu penuh (Time-Blocking). Tentukan Jam Mulai, Batas Waktu (Deadline), dan Estimasi Durasi (Menit) agar terpetakan rapi pada timeline harian.') }}</p>
                                </div>
                            </div>

                            <!-- Step 3 -->
                            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-blue-100 dark:border-slate-800 space-y-2">
                                <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400 font-bold text-xs sm:text-sm">
                                    <span class="w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/60 flex items-center justify-center text-xs font-black">3</span>
                                    <span>{{ __('3. Matriks Prioritas & Kategorisasi Kontekstual') }}</span>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed space-y-1">
                                    <p>{{ __('• Tingkat Prioritas: Alokasikan prioritas Rendah, Normal, atau Tinggi. Prioritas tinggi mencerminkan bobot urgensi dan menghadiahi perolehan EXP karakter yang lebih besar.') }}</p>
                                    <p>{{ __('• Tag Domain: Klasifikasikan ke dalam kategori Kerja, Belajar, Olahraga, Finansial, Kesehatan, atau tag kustom untuk menjaga keseimbangan radar produktivitas Anda.') }}</p>
                                </div>
                            </div>

                            <!-- Step 4 -->
                            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-blue-100 dark:border-slate-800 space-y-2">
                                <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold text-xs sm:text-sm">
                                    <span class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center text-xs font-black">4</span>
                                    <span>{{ __('4. Dekomposisi Target & AI Subtasks') }}</span>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed space-y-1">
                                    <p>{{ __('• Subtugas Bertingkat: Pecah target besar menjadi langkah-langkah konkret yang dapat diselesaikan bertahap.') }}</p>
                                    <p>{{ __('• AI Decomposer: Manfaatkan AI untuk merinci sasaran kompleks menjadi checklist berurutan beserta estimasi waktu dalam sekali klik.') }}</p>
                                    <p>{{ __('• Fokus Satu Titik (Step-by-Step): Tampilkan satu subtugas dalam satu waktu untuk mengeliminasi beban kognitif.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FITUR TUGAS KHUSUS (OLAHRAGA & KEUANGAN) -->
                    <div id="sub-special-tasks" class="space-y-5">
                        <div class="flex items-center gap-2.5 text-slate-900 dark:text-white font-black text-lg sm:text-xl">
                            <x-icon.sparkles class="w-6 h-6 text-amber-500" />
                            <span>{{ __('Fitur Tugas Khusus: Olahraga, Keuangan, & Kebiasaan Rutin') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                            {{ __('Tugas di ATOMA terhubung langsung dengan modul analitik kebugaran, pembukuan keuangan, dan otomasi rutinitas harian:') }}
                        </p>

                        <div class="space-y-4">
                            <!-- 1. Olahraga & Workout Tracker -->
                            <div class="p-6 rounded-3xl bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-transparent border-2 border-amber-300/60 dark:border-amber-700/60 space-y-4">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-2.5 text-amber-700 dark:text-amber-300 font-extrabold text-base sm:text-lg">
                                        <x-icon.activity class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                                        <span>{{ __('Tugas Olahraga (Fitness & Workout Tracker)') }}</span>
                                    </div>
                                    <span class="px-3 py-1 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 font-black text-xs">
                                        {{ __('Modul Kebugaran') }}
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                    {{ __('Memilih kategori "Olahraga" atau "Fitness" secara otomatis mengaktifkan modul pelacak latihan beban dan kebugaran komprehensif:') }}
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                                    <div class="p-3.5 rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-amber-200/60 dark:border-amber-800/40 space-y-1">
                                        <strong class="text-slate-900 dark:text-white flex items-center gap-1.5 font-bold">
                                            <x-icon.file-text class="w-4 h-4 text-amber-500 shrink-0" /> <span>{{ __('Katalog Split Latihan Terstruktur') }}</span>
                                        </strong>
                                        <p class="text-slate-500 dark:text-slate-400 text-xs">
                                            {{ __('Mendukung berbagai metodologi latihan: Gym Split (Push/Pull/Legs, Upper/Lower), Calisthenics (Bodyweight), Kardio, Home Workout, hingga program kustom.') }}
                                        </p>
                                    </div>
                                    <div class="p-3.5 rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-amber-200/60 dark:border-amber-800/40 space-y-1">
                                        <strong class="text-slate-900 dark:text-white flex items-center gap-1.5 font-bold">
                                            <x-icon.zap class="w-4 h-4 text-amber-500 shrink-0" /> <span>{{ __('Builder Gerakan & Preset Cepat') }}</span>
                                        </strong>
                                        <p class="text-slate-500 dark:text-slate-400 text-xs">
                                            {{ __('Akses preset gerakan utama (Bench Press, Squat, Deadlift, Pull Up, dsb.) untuk menyusun rencana latihan harian secara instan.') }}
                                        </p>
                                    </div>
                                    <div class="p-3.5 rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-amber-200/60 dark:border-amber-800/40 space-y-1">
                                        <strong class="text-slate-900 dark:text-white flex items-center gap-1.5 font-bold">
                                            <x-icon.layers class="w-4 h-4 text-amber-500 shrink-0" /> <span>{{ __('Logging Set, Beban, & Intensitas') }}</span>
                                        </strong>
                                        <p class="text-slate-500 dark:text-slate-400 text-xs">
                                            {{ __('Antarmuka latihan interaktif untuk mencatat beban (Kg/Lbs), jumlah repetisi, serta klasifikasi set: Normal, Pemanasan (Warmup), Dropset, hingga Failure.') }}
                                        </p>
                                    </div>
                                    <div class="p-3.5 rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-amber-200/60 dark:border-amber-800/40 space-y-1">
                                        <strong class="text-slate-900 dark:text-white flex items-center gap-1.5 font-bold">
                                            <x-icon.trophy class="w-4 h-4 text-amber-500 shrink-0" /> <span>{{ __('Deteksi Rekor Pribadi (PR) & EXP Tonase') }}</span>
                                        </strong>
                                        <p class="text-slate-500 dark:text-slate-400 text-xs">
                                            {{ __('Sistem mengalkulasi total volume tonase angkatan dan mendeteksi pencapaian rekor beban baru (Personal Record) secara otomatis dengan reward EXP fisik.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Finansial & Catat Transaksi Cepat -->
                            <div class="p-6 rounded-3xl bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-transparent border-2 border-emerald-300/60 dark:border-emerald-700/60 space-y-4">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-2.5 text-emerald-700 dark:text-emerald-300 font-extrabold text-base sm:text-lg">
                                        <x-icon.wallet class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                                        <span>{{ __('Tugas Finansial (Sinkronisasi Arus Kas Otomatis)') }}</span>
                                    </div>
                                    <span class="px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-black text-xs">
                                        {{ __('Otomasi Finansial') }}
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                    {{ __('Integrasikan agenda finansial (seperti pembayaran tagihan, pelunasan invoice, atau anggaran belanja) langsung ke pembukuan arus kas:') }}
                                </p>
                                <div class="p-4 rounded-2xl bg-white/90 dark:bg-slate-900/90 border border-emerald-200/60 dark:border-emerald-800/40 space-y-2 text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                                    <strong class="text-slate-900 dark:text-white font-bold flex items-center gap-2">
                                        <x-icon.message-square class="w-4 h-4 text-emerald-500 shrink-0" /> <span>{{ __('Sinkronisasi Transaksi Seketika (Finance Prompt)') }}</span>
                                    </strong>
                                    <p class="text-slate-500 dark:text-slate-400 text-xs leading-relaxed">
                                        {{ __('Saat tugas berkategori finansial diselesaikan, sistem memunculkan konfirmasi cepat untuk merekam transaksi ke buku kas. Anda cukup memasukkan nominal dan kategori (Pemasukan/Pengeluaran). Data langsung tersinkronisasi ke grafik arus kas pada tab Keuangan tanpa perlu input ulang terpisah.') }}
                                    </p>
                                </div>
                            </div>

                            <!-- 3. Tugas Rutin & Kolaborasi Co-op -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-5 rounded-3xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-2.5">
                                    <div class="flex items-center gap-2 text-slate-900 dark:text-white font-extrabold text-sm sm:text-base">
                                        <x-icon.repeat class="w-5 h-5 text-indigo-500" />
                                        <span>{{ __('Otomasi Tugas Berulang (Recurring Engine)') }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                        {{ __('Bangun kebiasaan konsisten tanpa repot membuat ulang tugas setiap hari. Aktifkan fitur berulang dengan frekuensi harian, mingguan, atau hari kerja (Senin–Jumat). Sistem secara otomatis menjadwalkan siklus berikutnya begitu tugas hari ini dituntaskan.') }}
                                    </p>
                                </div>

                                <div class="p-5 rounded-3xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-2.5">
                                    <div class="flex items-center gap-2 text-slate-900 dark:text-white font-extrabold text-sm sm:text-base">
                                        <x-icon.users class="w-5 h-5 text-purple-500" />
                                        <span>{{ __('Kolaborasi Tugas Bersama (Co-op Accountability)') }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                        {{ __('Delegasikan atau kerjakan sasaran bersama rekan kerja maupun partner belajar. Fitur Co-op memungkinkan pembagian subtugas, pemantauan progres real-time, dan saling menjaga akuntabilitas target bersama.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PANDUAN RINCI MILESTONE -->
                    <div id="sub-milestone" class="p-6 sm:p-8 rounded-3xl bg-gradient-to-br from-indigo-50/80 to-blue-50/80 dark:from-slate-800/80 dark:to-indigo-950/30 border-2 border-indigo-200 dark:border-indigo-800/60 space-y-4">
                        <div class="flex items-center gap-2.5 text-indigo-700 dark:text-indigo-300 font-black text-base sm:text-lg">
                            <x-icon.layers class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                            <span>{{ __('Panduan Rinci: Kurikulum Milestone (Roadmap 30 Hari)') }}</span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                            {{ __('Fitur Milestone dirancang untuk penguasaan keahlian mendalam dalam kurun waktu 30 hari terstruktur (seperti pemrograman, bahasa asing, atau kurikulum profesional):') }}
                        </p>

                        <div class="space-y-3 text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium">
                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900/80 border border-indigo-100 dark:border-slate-800 space-y-1">
                                <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    <span>{{ __('1. Peta Progres Titik Panas (30-Day Matrix Heatmap)') }}</span>
                                </strong>
                                <p class="text-slate-500 dark:text-slate-400 pl-3.5">
                                    {{ __('Visualisasi matriks 30 hari yang memperlihatkan status penyelesaian, modul aktif hari ini, dan target mendatang untuk memantau ritme pembelajaran Anda.') }}
                                </p>
                            </div>

                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900/80 border border-indigo-100 dark:border-slate-800 space-y-1">
                                <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    <span>{{ __('2. Integrasi Sesi Fokus Terjadwal') }}</span>
                                </strong>
                                <p class="text-slate-500 dark:text-slate-400 pl-3.5">
                                    {{ __('Tiap modul harian terhubung langsung dengan timer Pomodoro 25 menit, memungkinkan Anda langsung beralih dari kurikulum teori ke sesi belajar terarah tanpa distraksi.') }}
                                </p>
                            </div>

                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900/80 border border-indigo-100 dark:border-slate-800 space-y-1">
                                <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    <span>{{ __('3. Fase Pemulihan Terjadwal (Rest Day)') }}</span>
                                </strong>
                                <p class="text-slate-500 dark:text-slate-400 pl-3.5">
                                    {{ __('Menjaga konsistensi jangka panjang dengan interval istirahat terencana. Mengonfirmasi hari istirahat tetap mempertahankan kesinambungan streak dan memberikan bonus EXP pemulihan.') }}
                                </p>
                            </div>

                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900/80 border border-indigo-100 dark:border-slate-800 space-y-1">
                                <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    <span>{{ __('4. Sertifikat Digital Kelulusan Resmi') }}</span>
                                </strong>
                                <p class="text-slate-500 dark:text-slate-400 pl-3.5">
                                    {{ __('Penyelesaian 100% dari kurikulum 30 hari membuka Sertifikat Kelulusan Digital resmi berstempel AI yang dapat diunduh sebagai dokumen PDF berkualitas tinggi.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- 2. PUSAT KECERDASAN BUATAN (AI SUPER-ENGINE) -->
                <article id="section-ai-engine" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-indigo-200/80 dark:border-indigo-900/60 shadow-xs space-y-8 relative overflow-hidden">
                    <!-- Glow Accents -->
                    <div class="absolute -top-24 -right-24 w-72 h-72 bg-indigo-500/10 dark:bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-purple-500/10 dark:bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>

                    <!-- Header -->
                    <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-indigo-100 dark:border-indigo-900/40 pb-6">
                        <div class="flex items-center gap-3.5">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-500 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30 shrink-0">
                                <x-icon.bot class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/80 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-[10px] font-black uppercase tracking-wider mb-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                                    <span>{{ __('AI Engine Terintegrasi') }}</span>
                                </div>
                                <h2 class="text-xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                                    {{ __('2. Fitur AI Super-Engine (Smart Automation & Decomposer)') }}
                                </h2>
                                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 mt-1 font-medium">
                                    {{ __('Kecerdasan buatan terapan yang fungsional, berdampak nyata, dan hemat kuota jangka panjang.') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 self-start sm:self-auto">
                            <span class="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-700/50 text-indigo-700 dark:text-indigo-200 text-xs font-mono font-bold flex items-center gap-1.5">
                                <span class="text-emerald-500 dark:text-emerald-400">●</span> 500 RPD Resilient Pool
                            </span>
                        </div>
                    </div>

                    <!-- 4 Pilar Fitur AI Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
                        <!-- 1. AI Task Decomposer -->
                        <div class="p-5 sm:p-6 rounded-3xl bg-slate-50/80 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 transition-all space-y-3.5 group shadow-xs">
                            <div class="flex items-center justify-between">
                                <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <x-icon.sparkles class="w-5 h-5" />
                                </div>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-blue-100 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300">
                                    {{ __('Halaman Tasks') }}
                                </span>
                            </div>
                            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span>AI Task Decomposer</span>
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                                {{ __('Ubah tujuan atau sasaran kerja besar menjadi serangkaian langkah taktis yang terstruktur, lengkap dengan urutan logis, durasi realistis, dan bobot prioritas.') }}
                            </p>
                            <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 space-y-2">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                    <x-icon.info class="w-3.5 h-3.5" />
                                    <span>{{ __('Cara Menggunakan:') }}</span>
                                </div>
                                <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                    {{ __('Buka halaman Tasks') }} &rarr; {{ __('Klik tombol') }} <strong>"AI Template"</strong> &rarr; {{ __('Pilih tab') }} <strong>"AI Decomposer"</strong> &rarr; {{ __('Masukkan tujuan (contoh: "Audit arsitektur microservices")') }} &rarr; {{ __('AI menyusun subtugas logis yang dapat langsung diterapkan ke jadwal harian Anda.') }}
                                </p>
                            </div>
                        </div>

                        <!-- 2. 1-Shot 30-Day Skill Curriculum -->
                        <div class="p-5 sm:p-6 rounded-3xl bg-slate-50/80 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 transition-all space-y-3.5 group shadow-xs">
                            <div class="flex items-center justify-between">
                                <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <x-icon.globe class="w-5 h-5" />
                                </div>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-900/50 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300">
                                    {{ __('Halaman Milestone') }}
                                </span>
                            </div>
                            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span>1-Shot 30-Day Skill Curriculum</span>
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                                {{ __('Dapatkan silabus belajar komprehensif 30 hari untuk keahlian apa pun secara instan. Menghilangkan keraguan kurikulum dan memberikan panduan terarah dari level dasar hingga mahir.') }}
                            </p>
                            <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 space-y-2">
                                <div class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <x-icon.book-open class="w-3.5 h-3.5" />
                                    <span>{{ __('Kurikulum Progresif:') }}</span>
                                </div>
                                <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                    {{ __('Masukkan domain atau topik yang ingin dikuasai. AI merancang 30 modul berjenjang yang terintegrasi dengan sesi latihan harian dan sertifikasi digital akhir.') }}
                                </p>
                            </div>
                        </div>

                        <!-- 3. AI Macro Monthly Capstone -->
                        <div class="p-5 sm:p-6 rounded-3xl bg-slate-50/80 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 transition-all space-y-3.5 group shadow-xs">
                            <div class="flex items-center justify-between">
                                <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <x-icon.activity class="w-5 h-5" />
                                </div>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-purple-100 dark:bg-purple-900/50 border border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300">
                                    {{ __('Halaman Insights') }}
                                </span>
                            </div>
                            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span>Monthly Capstone &amp; Productivity Archetype</span>
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                                {{ __('Analisis retrospektif bulanan yang mendalam. AI mengagregasi volume tugas, total waktu fokus Pomodoro, stabilitas emosional, dan refleksi jurnal harian.') }}
                            </p>
                            <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 space-y-2">
                                <div class="font-bold text-purple-600 dark:text-purple-400 flex items-center gap-1.5">
                                    <x-icon.trophy class="w-3.5 h-3.5" />
                                    <span>{{ __('Archetype & Blueprint:') }}</span>
                                </div>
                                <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                    {{ __('Dapatkan profil arketipe produktivitas Anda, identifikasi pola bottleneck waktu kerja, serta rekomendasi blueprint strategi untuk 30 hari ke depan.') }}
                                </p>
                            </div>
                        </div>

                        <!-- 4. Resilient Architecture & Anti-Token Waste -->
                        <div class="p-5 sm:p-6 rounded-3xl bg-slate-50/80 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 transition-all space-y-3.5 group shadow-xs">
                            <div class="flex items-center justify-between">
                                <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <x-icon.shield-check class="w-5 h-5" />
                                </div>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-900/50 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300">
                                    {{ __('Arsitektur Sistem') }}
                                </span>
                            </div>
                            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span>{{ __('Ramah Free-Tier & Anti Boros Token') }}</span>
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                                {{ __('Arsitektur cerdas yang mengoptimalkan efisiensi komputasi, menjamin ketersediaan layanan tinggi tanpa pemborosan token.') }}
                            </p>
                            <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 space-y-2">
                                <div class="font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                    <x-icon.zap class="w-3.5 h-3.5" />
                                    <span>{{ __('Optimalisasi Komputasi:') }}</span>
                                </div>
                                <div class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400 space-y-1">
                                    <p>• <strong>Zero-Token Micro Heuristics:</strong> {{ __('Kalkulasi analitik harian dieksekusi secara lokal di browser dan server tanpa konsumsi token API (0 latency).') }}</p>
                                    <p>• <strong>500 RPD Model Pool:</strong> {{ __('Pemanfaatan pool model berkapasitas tinggi dengan mekanisme failover otomatis.') }}</p>
                                    <p>• <strong>Telemetri Transparan:</strong> {{ __('Pemantauan konsumsi kuota secara terukur dan efisien di sistem.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- 3. WIDGET POMODORO & MUSIK -->
                <article id="widget-pomodoro" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <x-icon.clock class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-rose-500 uppercase tracking-wider">{{ __('Widget Melayang') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('3. Modul Pomodoro & Audio Fokus Terintegrasi') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                        {{ __('Tingkatkan konsentrasi kerja mendalam tanpa perlu membuka aplikasi audio atau timer eksternal. Widget fokus siap menemani alur kerja Anda kapan saja:') }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 space-y-1.5">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.music class="w-4 h-4 text-rose-500 shrink-0" /> <span>{{ __('Kurasi Musik Lo-Fi & Ambience Alam') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ __('Pilihan streaming audio Lo-Fi dan generator suasana alami (hujan, kafe, ombak) yang terus berputar mulus tanpa jeda saat Anda berpindah antarhalaman di ATOMA.') }}
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 space-y-1.5">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.check-circle class="w-4 h-4 text-emerald-500 shrink-0" /> <span>{{ __('Kompensasi EXP Progresif yang Adil') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ __('Menghargai setiap menit waktu kerja Anda. Menghentikan sesi fokus lebih awal tetap memberikan reward EXP secara proporsional sesuai durasi yang berhasil dijalani.') }}
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 space-y-1.5">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.layers class="w-4 h-4 text-indigo-500 shrink-0" /> <span>{{ __('Overlay Layar Melayang (Float OS PiP)') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ __('Mode Picture-in-Picture menjaga timer fokus dan kendali subtugas tetap terlihat di atas jendela aplikasi kerja lain (seperti VS Code, Word, atau peramban lain).') }}
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 space-y-1.5">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.skip-forward class="w-4 h-4 text-amber-500 shrink-0" /> <span>{{ __('Kontrol Audio Cepat & Pintasan Keyboard') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ __('Navigasi trek audio secara instan melalui widget atau gunakan shortcut keyboard global (Alt + [ dan Alt + ]) tanpa mengalihkan fokus dari pekerjaan utama.') }}
                            </p>
                        </div>
                    </div>
                </article>

                <!-- 4. HALAMAN NOTE & KEUANGAN -->
                <article id="page-note" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <x-icon.file-text class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-indigo-500 uppercase tracking-wider">{{ __('Halaman Catatan & Ledger') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('4. Halaman Note & Manajemen Finansial Terintegrasi') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                        {{ __('Ruang kerja multifungsi yang menggabungkan dokumentasi pemikiran dan pemantauan arus kas dalam satu modul terpadu:') }}
                    </p>

                    <div class="space-y-4 text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium">
                        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-2">
                            <h4 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <x-icon.pencil class="w-4 h-4 text-indigo-500" />
                                <span>{{ __('A. Tab Catatan (Knowledge Base & Eksekusi To-Do)') }}</span>
                            </h4>
                            <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ __('Dokumentasikan riset, notula rapat, dan ide kreatif. Butir-butir checklist di dalam catatan dapat dikonversi langsung menjadi tugas aktif di Halaman Tasks dalam satu langkah.') }}
                            </p>
                        </div>

                        <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-2">
                            <h4 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <x-icon.wallet class="w-4 h-4 text-emerald-500" />
                                <span>{{ __('B. Tab Keuangan (Buku Kas & Analisis Cashflow)') }}</span>
                            </h4>
                            <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ __('Catat pemasukan dan pengeluaran harian dengan klasifikasi kategori jelas. Pantau saldo akumulatif dan tren pengeluaran untuk menjaga kesehatan finansial pribadi Anda.') }}
                            </p>
                        </div>
                    </div>
                </article>

                <!-- 5. HALAMAN FLASHCARD -->
                <article id="page-flashcard" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-teal-100 dark:bg-teal-900/40 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                            <x-icon.cards class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-teal-500 uppercase tracking-wider">{{ __('Metode Pembelajaran Ilmiah') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('5. Halaman Flashcard (Spaced Repetition System)') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                        {{ __('Maksimalkan retensi memori jangka panjang dengan metode ilmiah Spaced Repetition (SRS) dan active recall untuk pembelajaran materi kompleks:') }}
                    </p>

                    <div class="space-y-3 text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium">
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                                <span>{{ __('1. Pengelompokan Materi (Deck Management)') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400 pl-3.5">
                                {{ __('Kelompokkan kartu ke dalam topik spesifik (misal: Kosakata Bahasa Asing, Konsep Arsitektur Perangkat Lunak, atau Terminologi Medis).') }}
                            </p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                                <span>{{ __('2. Formulasi Kartu Aktif (Active Recall)') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400 pl-3.5">
                                {{ __('Susun pertanyaan pada sisi depan dan jawaban esensial pada sisi belakang untuk melatih daya ingat aktif.') }}
                            </p>
                        </div>
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                                <span>{{ __('3. Penjadwalan Algoritmik Cerdas (Interval Review)') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400 pl-3.5">
                                {{ __('Algoritma otomatis menjadwalkan pengulangan berdasarkan tingkat kemudahan recall Anda: kartu sulit akan diulang lebih sering, sementara kartu yang dikuasai dijadwalkan pada interval waktu yang lebih panjang.') }}
                            </p>
                        </div>
                    </div>
                </article>

                <!-- 6. HALAMAN SOSIAL & SENGGOL BACOK -->
                <article id="page-social" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                            <x-icon.users class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-purple-500 uppercase tracking-wider">{{ __('Jejaring Akuntabilitas') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('6. Halaman Sosial, Kolaborasi, & Akuntabilitas Rekan') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                        {{ __('Bangun kedisiplinan konsisten melalui jejaring akuntabilitas sosial, kolaborasi tugas, dan kompetisi sehat bersama rekan:') }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium">
                        <div class="p-4 rounded-2xl bg-purple-50/60 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/40 space-y-1.5">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.zap class="w-4 h-4 text-purple-500 shrink-0" /> <span>{{ __('Fitur Akuntabilitas "Senggol" (Nudge)') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ __('Kirimkan dorongan pengingat cepat atau evaluasi konstruktif kepada rekan belajar yang memiliki tugas tertunda untuk saling menjaga komitmen harian.') }}
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-rose-50/60 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/40 space-y-1.5">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.sword class="w-4 h-4 text-rose-500 shrink-0" /> <span>{{ __('Sprint Produktivitas "Bacok Duel"') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ __('Tantang rekan Anda dalam sprint penyelesaian tugas harian. Selesaikan seluruh target lebih cepat untuk memenangkan bonus EXP dan predikat ketahanan "Kebal Bacok".') }}
                            </p>
                        </div>
                    </div>
                </article>

                <!-- 7. HALAMAN LEADERBOARD -->
                <article id="page-leaderboard" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <x-icon.trophy class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-amber-500 uppercase tracking-wider">{{ __('Peringkat & Liga') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('7. Halaman Leaderboard & Sistem Liga Mingguan') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                        {{ __('Evaluasi konsistensi produktivitas Anda melalui ekosistem kompetisi berjenjang. Peringkat dihitung berdasarkan akumulasi perolehan EXP mingguan dan rekor kesinambungan hari berturut-turut (Day Streak), lengkap dengan mekanisme promosi 12 tier liga mingguan.') }}
                    </p>
                </article>

                <!-- 8. HALAMAN TOKO HADIAH (STORE) -->
                <article id="page-store" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <x-icon.gift class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-emerald-500 uppercase tracking-wider">{{ __('Insentif & Hadiah') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('8. Halaman Store (Insentif & Proteksi Konsistensi)') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                        {{ __('Konversikan koin yang diperoleh dari penyelesaian tugas dan sesi fokus menjadi utilitas fungsional serta personalisasi profil:') }}
                    </p>

                    <ul class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium">
                        <li class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800 space-y-1">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.shield-check class="w-4 h-4 text-amber-500 shrink-0" />
                                <span>{{ __('Kupon Proteksi Streak') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">{{ __('Amankan rekor streak harian Anda agar tidak tereset ketika berhalangan atau sedang dalam perjalanan dinas.') }}</p>
                        </li>
                        <li class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800 space-y-1">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.sparkles class="w-4 h-4 text-indigo-500 shrink-0" />
                                <span>{{ __('Bingkai Avatar Prestisius') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">{{ __('Tampilkan identitas visual unik (Border Neon, Flame, atau Golden Crown) sebagai representasi pencapaian level Anda.') }}</p>
                        </li>
                        <li class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800 space-y-1">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.music class="w-4 h-4 text-blue-500 shrink-0" />
                                <span>{{ __('Audio Sound Pack Eksklusif') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">{{ __('Personalisasi efek audio penyelesaian tugas dengan tema suara kepuasan yang bervariasi.') }}</p>
                        </li>
                    </ul>
                </article>

                <!-- 9. HALAMAN PROFIL & PENGATURAN -->
                <article id="page-profile" class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <x-icon.gear class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-amber-500 uppercase tracking-wider">{{ __('Pengaturan & Metrik') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ __('9. Halaman Profil & Analisis Statistik Karakter') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                        {{ __('Pantau metrik perkembangan diri Anda secara holistik melalui visualisasi statistik berbasis peran (RPG):') }}
                    </p>

                    <div class="space-y-3 text-xs sm:text-sm text-slate-600 dark:text-slate-300 font-medium">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.activity class="w-4 h-4 text-blue-500 shrink-0" />
                                <span>{{ __('Visualisasi Radar 5 Atribut Produktivitas') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ __('Grafik radar dinamis yang merefleksikan 5 dimensi nyata: Kedalaman Fokus, Konsistensi Harian, Stamina Kerja, Agilitas Eksekusi, dan Kolaborasi Sosial.') }}
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1">
                            <strong class="text-slate-900 dark:text-white flex items-center gap-1.5">
                                <x-icon.star class="w-4 h-4 text-amber-500 shrink-0" />
                                <span>{{ __('Sistem Prestige Ascension') }}</span>
                            </strong>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ __('Setelah mencapai level 50, lanjutkan perkembangan ke kasta yang lebih tinggi (Rookie hingga Ascended) untuk membuka status reputasi jangka panjang.') }}
                            </p>
                        </div>
                    </div>
                </article>

                <!-- 10. CARA PASANG DESKTOP & PWA -->
                <article id="desktop-pwa" class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-[2.5rem] p-6 sm:p-10 border border-slate-800 shadow-xl space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-atoma-blue/30 text-atoma-cyan flex items-center justify-center">
                            <x-icon.download class="w-6 h-6" />
                        </div>
                        <div>
                            <span class="text-xs font-bold text-atoma-cyan uppercase tracking-wider">{{ __('Multi-Platform') }}</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-white">{{ __('10. Dukungan Multi-Platform (Desktop & Mobile PWA)') }}</h2>
                        </div>
                    </div>

                    <p class="text-slate-300 leading-relaxed font-medium">
                        {{ __('Seluruh target, catatan, dan progres level Anda tersinkronisasi secara real-time di seluruh perangkat:') }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                        <div class="p-5 rounded-2xl bg-white/5 border border-white/10 space-y-2">
                            <div class="text-base font-bold text-atoma-cyan flex items-center gap-2">
                                <x-icon.download class="w-4 h-4 text-atoma-cyan shrink-0" /> <span>{{ __('Instalasi Mobile PWA (Android & iOS)') }}</span>
                            </div>
                            <ol class="list-decimal list-inside space-y-1 text-xs text-slate-300 leading-relaxed">
                                <li>{{ __('Buka ATOMA melalui peramban ponsel (Chrome atau Safari).') }}</li>
                                <li>{{ __('Android: Buka menu peramban dan pilih "Tambahkan ke Layar Utama" / "Install App".') }}</li>
                                <li>{{ __('iOS: Tekan tombol Bagikan (Share) lalu pilih "Add to Home Screen".') }}</li>
                                <li>{{ __('Aplikasi siap diakses secara cepat layaknya aplikasi native dengan konsumsi penyimpanan minimal.') }}</li>
                            </ol>
                        </div>

                        <div class="p-5 rounded-2xl bg-white/5 border border-white/10 space-y-2">
                            <div class="text-base font-bold text-atoma-cyan flex items-center gap-2">
                                <x-icon.code class="w-4 h-4 text-atoma-cyan shrink-0" /> <span>{{ __('Aplikasi Desktop Native (Windows & macOS)') }}</span>
                            </div>
                            <ol class="list-decimal list-inside space-y-1 text-xs text-slate-300 leading-relaxed">
                                <li>{{ __('Buka beranda ATOMA dan arahkan ke bagian Download Aplikasi.') }}</li>
                                <li>{{ __('Windows: Unduh installer .exe dan jalankan proses instalasi.') }}</li>
                                <li>{{ __('macOS: Unduh installer .dmg dan salin ke direktori Applications.') }}</li>
                                <li>{{ __('Aplikasi beroperasi dengan integrasi System Tray di latar belakang untuk akses cepat.') }}</li>
                            </ol>
                        </div>
                    </div>

                    <div class="pt-4 flex flex-wrap gap-3">
                        <a href="/#download" class="bg-gradient-to-r from-atoma-blue to-atoma-cyan text-white font-black text-sm px-6 py-3 rounded-2xl shadow-lg transition-transform hover:scale-105 flex items-center gap-2">
                            <x-icon.download class="w-4 h-4" />
                            <span>{{ __('Pergi ke Halaman Download &rarr;') }}</span>
                        </a>
                    </div>
                </article>

            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 py-12 text-center">
        <div class="max-w-7xl mx-auto px-4 space-y-3">
            <div class="flex items-center justify-center gap-2">
                <img src="/images/atoma_icon.png" alt="ATOMA" class="h-6 w-auto">
                <span class="font-black text-slate-900 dark:text-white uppercase tracking-wider">ATOMA</span>
                <span class="text-xs text-slate-400">| {{ __('Level Up Your Routine') }}</span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                &copy; {{ date('Y') }} {{ __('ATOMA - Level Up Your Routine.') }}
            </p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
