<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ATOMA') }}</title>
        <link rel="icon" type="image/png" href="/images/atoma_icon.png">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#4f46e5">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,700,900" rel="stylesheet" />

        <!-- Dark Mode Persistence (Blocking FOUC) -->
        <script>
            (function() {
                if (localStorage.getItem('darkMode') === 'true' || (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 dark:text-gray-100 antialiased bg-gray-50 dark:bg-gray-950 selection:bg-indigo-500 selection:text-white">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-12 pb-12 sm:pt-0 relative overflow-hidden">
            
            <!-- Background Effects -->
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjIiIGZpbGw9IiM2MzY2ZjEiIGZpbGwtb3BhY2l0eT0iMC4xIi8+PC9zdmc+')] bg-[length:30px_30px]"></div>
                <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob"></div>
                <div class="absolute top-1/3 right-1/4 w-96 h-96 bg-purple-500/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob" style="animation-delay: 2s"></div>
            </div>

            <div class="relative z-10 w-full sm:max-w-md px-8 py-10 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl shadow-2xl border border-gray-200/50 dark:border-gray-800/50 overflow-hidden sm:rounded-[2.5rem]">
                <div class="flex flex-col items-center mb-8">
                    <a href="/" wire:navigate class="group flex flex-col items-center gap-2 select-none">
                        <img src="/images/atoma_icon.png" alt="ATOMA Icon" class="h-16 w-16 object-contain drop-shadow-md group-hover:scale-105 transition-transform">
                        <span class="text-2xl font-black tracking-wider bg-gradient-to-r from-atoma-blue to-atoma-cyan bg-clip-text text-transparent uppercase">
                            ATOMA
                        </span>
                    </a>
                </div>

                {{ $slot }}
            </div>
        </div>

        <style>
            @keyframes blob {
                0% { transform: translate(0px, 0px) scale(1); }
                33% { transform: translate(30px, -50px) scale(1.1); }
                66% { transform: translate(-20px, 20px) scale(0.9); }
                100% { transform: translate(0px, 0px) scale(1); }
            }
            .animate-blob {
                animation: blob 7s infinite;
            }
        </style>
    </body>
</html>
