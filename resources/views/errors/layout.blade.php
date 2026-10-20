<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') - ATOMA</title>
    <link rel="icon" type="image/png" href="/images/atoma_icon.png">
    
    <script>
        if (localStorage.getItem('darkMode') === 'true' || (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />
    
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen flex items-center justify-center p-4 selection:bg-indigo-500 selection:text-white antialiased font-sans">
    <div class="max-w-md w-full text-center space-y-6">
        <a href="/" class="inline-flex items-center gap-2.5 group">
            <img src="/images/atoma_icon.png" alt="ATOMA Logo" class="w-10 h-10 object-contain group-hover:scale-105 transition-transform">
            <span class="text-2xl font-black bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">ATOMA</span>
        </a>

        <div class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl rounded-3xl border border-gray-200/80 dark:border-gray-800 p-8 shadow-xl">
            <div class="text-5xl mb-4">@yield('emoji', '⚠️')</div>
            <h1 class="text-5xl font-black tracking-tight bg-gradient-to-r from-gray-900 via-indigo-900 to-gray-700 dark:from-white dark:via-indigo-200 dark:to-gray-300 bg-clip-text text-transparent mb-2">
                @yield('code', '404')
            </h1>
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2">
                @yield('heading', 'Terjadi Kendala')
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                @yield('message', 'Halaman yang kamu cari tidak dapat ditemukan.')
            </p>

            <div class="flex flex-col sm:flex-row gap-2.5 justify-center">
                <a href="{{ url('/tasks') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-500/20 active:scale-95 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Ke Dashboard Tugas</span>
                </a>
                <button onclick="window.history.back()" type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-700 dark:text-gray-200 text-xs font-semibold active:scale-95 transition-all">
                    <span>Kembali</span>
                </button>
            </div>
        </div>

        <p class="text-[11px] text-gray-400 dark:text-gray-500">
            &copy; {{ date('Y') }} ATOMA · Built with focus &amp; passion.
        </p>
    </div>
</body>
</html>
