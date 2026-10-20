<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Syarat, Ketentuan & Kebijakan Privasi') }} - ATOMA</title>
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
<body class="antialiased font-sans bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 overflow-x-hidden selection:bg-atoma-blue selection:text-white transition-colors min-h-screen flex flex-col justify-between">

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
                    <span class="text-[10px] sm:text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-normal hidden sm:inline">
                        {{ __('Ketentuan & Privasi') }}
                    </span>
                </a>

                <!-- Nav Links Desktop -->
                <div class="hidden md:flex items-center gap-6 text-sm font-bold text-slate-600 dark:text-slate-300">
                    <a href="/#fitur" class="hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors">{{ __('Fitur Seru') }}</a>
                    <a href="/#download" class="hover:text-atoma-blue dark:hover:text-atoma-cyan transition-colors flex items-center gap-1.5">
                        <x-icon.download class="w-4 h-4 text-emerald-500" />
                        <span>{{ __('Download Aplikasi') }}</span>
                    </a>
                    <a href="{{ route('guide') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 flex items-center gap-1.5 font-extrabold">
                        <x-icon.book-open class="w-4 h-4" />
                        <span>{{ __('Panduan Cara Pakai') }}</span>
                    </a>
                </div>

                <!-- Right Action Buttons: Language Switcher, Dark Mode, & Auth -->
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

                    @auth
                        <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-atoma-blue to-atoma-cyan hover:opacity-95 text-white font-extrabold px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-xl shadow-md shadow-atoma-blue/20 transition-all hover:-translate-y-0.5 text-xs sm:text-sm flex items-center gap-1.5">
                            <span>{{ __('Buka Dashboard') }}</span>
                            <x-icon.arrow-right class="w-4 h-4" />
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="font-bold text-slate-600 hover:text-atoma-blue dark:text-slate-300 dark:hover:text-atoma-cyan transition-colors text-xs sm:text-sm px-2 sm:px-3 py-2">
                            {{ __('Masuk') }}
                        </a>
                        <a href="{{ route('register') }}" class="bg-atoma-blue hover:bg-blue-600 text-white font-extrabold px-3 sm:px-5 py-2 sm:py-2.5 rounded-xl shadow-md shadow-atoma-blue/25 transition-all hover:-translate-y-0.5 text-xs sm:text-sm hidden sm:inline-block">
                            {{ __('Sign Up') }}
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="pt-28 pb-20 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex-1 w-full">
        
        <!-- Header Banner -->
        <div class="text-center max-w-3xl mx-auto mb-14">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-100/80 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 font-black text-xs sm:text-sm mb-5 border border-emerald-200/60 dark:border-emerald-800/60 shadow-xs">
                <x-icon.shield-check class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                <span>{{ __('Komitmen Perlindungan Data & Transparansi') }}</span>
            </div>
            
            <h1 class="text-3xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight mb-4 leading-tight">
                {{ __('Syarat, Ketentuan & Kebijakan Privasi') }}
            </h1>
            
            <p class="text-slate-600 dark:text-slate-400 text-sm sm:text-base font-medium leading-relaxed">
                {{ __('Keamanan dan privasi datamu adalah prioritas mutlak kami. Di ATOMA, datamu adalah milikmu sepenuhnya.') }}
            </p>
            <p class="text-xs text-slate-400 font-mono mt-2">
                {{ __('Terakhir diperbarui') }}: 16 September 2026
            </p>
        </div>

        <!-- Highlight Box: Komitmen Bebas Pengambilan Data -->
        <div class="rounded-3xl p-6 sm:p-8 bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-atoma-blue/10 border-2 border-emerald-500/30 dark:border-emerald-500/20 shadow-xl mb-12 relative overflow-hidden">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                <div class="w-14 h-14 rounded-2xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/25">
                    <x-icon.shield-check class="w-7 h-7 text-white" />
                </div>
                <div class="space-y-1.5 flex-1">
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
                        {{ __('Komitmen Kami: Kami Tidak Menjual atau Mengambil Datamu') }}
                    </h2>
                    <p class="text-slate-600 dark:text-slate-300 text-xs sm:text-sm font-medium leading-relaxed">
                        {{ __('ATOMA dibangun untuk membantu produktivitasmu, bukan mengeksploitasi datamu. Kami tidak menjual data ke broker iklan, tidak melacak aktivitas di luar aplikasi, dan tidak memonetisasi informasi pribadimu. Titik.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- 6 Core Pillars Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-14">
            
            <!-- Pillar 1 -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                    <x-icon.alert-triangle class="w-5 h-5" />
                </div>
                <h3 class="text-base font-black text-slate-900 dark:text-white">
                    {{ __('1. Nol Penjualan Data (Zero Data Selling)') }}
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
                    {{ __('Kami tidak pernah dan tidak akan pernah menjual, menyewakan, atau menukarkan data tugas, riwayat jurnal, maupun profilmu kepada pihak ketiga, biro iklan, atau platform penargetan pemasaran.') }}
                </p>
            </div>

            <!-- Pillar 2 -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <x-icon.file-text class="w-5 h-5" />
                </div>
                <h3 class="text-base font-black text-slate-900 dark:text-white">
                    {{ __('2. Data Murni Penunjang Produktivitas') }}
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
                    {{ __('Kami hanya mencatat data yang kamu buat secara sadar untuk fitur aplikasi: daftar tugas, durasi timer Pomodoro, catatan refleksi harian, poin pengalaman (XP), koin, dan badge game.') }}
                </p>
            </div>

            <!-- Pillar 3 -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <x-icon.lock class="w-5 h-5" />
                </div>
                <h3 class="text-base font-black text-slate-900 dark:text-white">
                    {{ __('3. Keamanan & Enkripsi Kata Sandi') }}
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
                    {{ __('Seluruh transfer data dilindungi oleh enkripsi HTTPS/TLS modern. Kata sandi akun diacak dengan algoritma Bcrypt yang aman, sehingga staf ATOMA sekalipun tidak dapat membaca kata sandi aslimu.') }}
                </p>
            </div>

            <!-- Pillar 4 -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <x-icon.sparkles class="w-5 h-5" />
                </div>
                <h3 class="text-base font-black text-slate-900 dark:text-white">
                    {{ __('4. Transparansi Fitur AI (Gemini AI)') }}
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
                    {{ __('Fitur AI (seperti pembongkar tugas otomatis dan wawasan mingguan) hanya mengirimkan konteks teks saat kamu menekan tombol minta saran. Data tugasmu tidak digunakan untuk melatih model kecerdasan buatan publik.') }}
                </p>
            </div>

            <!-- Pillar 5 -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <x-icon.trash class="w-5 h-5" />
                </div>
                <h3 class="text-base font-black text-slate-900 dark:text-white">
                    {{ __('5. Kendali Penuh & Hak Hapus Akun') }}
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
                    {{ __('Kamu memegang kendali penuh atas akunmu. Kamu bisa menyetel profil ke mode privat, menonaktifkan senggolan teman, atau menghapus akunmu secara permanen kapan saja dari menu Pengaturan Profil.') }}
                </p>
            </div>

            <!-- Pillar 6 -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="w-10 h-10 rounded-xl bg-teal-100 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                    <x-icon.code class="w-5 h-5" />
                </div>
                <h3 class="text-base font-black text-slate-900 dark:text-white">
                    {{ __('6. Aplikasi Desktop & Mobile Bersih') }}
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
                    {{ __('Aplikasi Windows (.exe), Mac (.dmg), dan PWA Mobile kami 100% bebas spyware, tidak memuat program pengintai pihak ketiga, dan hanya memanfaatkan kapabilitas sistem untuk musik di latar belakang dan notifikasi.') }}
                </p>
            </div>

        </div>

        <!-- Detailed Legal Clauses (Clean Structured Breakdown) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800 space-y-8 text-xs sm:text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            
            <section class="space-y-2.5">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-atoma-blue inline-block"></span>
                    {{ __('1. Penerimaan Syarat & Ketentuan') }}
                </h3>
                <p>
                    {{ __('Dengan mendaftar, mengakses, atau menggunakan ATOMA (melalui web, desktop installer, atau PWA mobile), Anda menyetujui untuk terikat dengan syarat dan ketentuan ini. Jika Anda tidak menyetujui sebagian atau seluruh ketentuan ini, Anda dipersilakan untuk tidak melanjutkan penggunaan layanan kami.') }}
                </p>
            </section>

            <section class="space-y-2.5">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-atoma-blue inline-block"></span>
                    {{ __('2. Akun, Keamanan & Autentikasi Google') }}
                </h3>
                <p>
                    {{ __('Anda bertanggung jawab menjaga kerahasiaan kata sandi akun Anda. Saat Anda memilih opsi "Lanjutkan dengan Google", kami hanya menerima informasi profil publik yang disetujui (nama, alamat email, dan foto avatar) untuk menyederhanakan proses masuk tanpa pernah mengetahui atau menyimpan kata sandi akun Google Anda.') }}
                </p>
            </section>

            <section class="space-y-2.5">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-atoma-blue inline-block"></span>
                    {{ __('3. Penggunaan Wajar & Fitur Interaksi Sosial') }}
                </h3>
                <p>
                    {{ __('Fitur sosial seperti Senggol Bacok, Tantangan Duel, dan Forum Komunitas dirancang untuk membangun kebiasaan positif dan saling memotivasi. Pengguna dilarang mengirimkan pesan kebencian, pelecehan, atau konten tidak pantas. Anda selalu memiliki opsi untuk menolak duel atau menonaktifkan notifikasi senggolan melalui pengaturan profil.') }}
                </p>
            </section>

            <section class="space-y-2.5">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-atoma-blue inline-block"></span>
                    {{ __('4. Penghapusan Akun & Retensi Data (Right to be Forgotten)') }}
                </h3>
                <p>
                    {{ __('Ketika Anda memilih untuk menghapus akun Anda di menu Pengaturan Profil, sistem kami akan langsung menghapus akun Anda beserta seluruh relasi tugas, catatan jurnal, riwayat fokus timer, pesan, dan pertemanan secara permanen dari server aktif kami.') }}
                </p>
            </section>

            <section class="space-y-2.5">
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-atoma-blue inline-block"></span>
                    {{ __('5. Hubungi Kami') }}
                </h3>
                <p>
                    {{ __('Jika Anda memiliki pertanyaan seputar syarat penggunaan, kebijakan privasi, atau keamanan data di ATOMA, silakan hubungi tim kami atau gunakan formulir Masukan Pengguna (Feedback) di dalam dashboard aplikasi.') }}
                </p>
            </section>

        </div>

        <!-- Back to Home Button -->
        <div class="text-center mt-12">
            <a href="/" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-sm transition-all hover:-translate-y-0.5 shadow-xs">
                <span>&larr;</span>
                <span>{{ __('Kembali ke Beranda') }}</span>
            </a>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-2.5">
                <img src="/images/atoma_icon.png" alt="ATOMA" class="h-7 w-auto">
                <span class="font-black text-slate-900 dark:text-white uppercase tracking-wider text-sm sm:text-base">ATOMA</span>
                <span class="text-xs text-slate-400">| {{ __('Level Up Your Routine') }}</span>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-5 text-xs font-bold text-slate-500 dark:text-slate-400">
                <a href="/#fitur" class="hover:text-atoma-blue">{{ __('Fitur Seru') }}</a>
                <a href="/#download" class="hover:text-atoma-blue">{{ __('Download Aplikasi') }}</a>
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

    @livewireScripts
</body>
</html>
