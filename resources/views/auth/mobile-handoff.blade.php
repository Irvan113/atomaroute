<x-guest-layout>
    <div class="text-center space-y-6 max-w-sm mx-auto py-4">
        <!-- Animated Success Icon -->
        <div class="relative inline-flex items-center justify-center">
            <div class="w-20 h-20 rounded-3xl bg-emerald-100 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 border-2 border-emerald-400 dark:border-emerald-600 animate-bounce">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>

        <div>
            <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                {{ __('Login Google Berhasil!') }}
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">
                {{ __('Halo, :name! Mengalihkan akun Anda kembali ke aplikasi ATOMA...', ['name' => $user->name]) }}
            </p>
        </div>

        <!-- Progress Spinner -->
        <div class="flex items-center justify-center gap-2 text-xs font-bold text-atoma-blue">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span>{{ __('Membuka aplikasi otomatis...') }}</span>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3 pt-2">
            <a id="btn-open-app" href="{{ $appDeepLink }}" 
               class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-atoma-blue to-indigo-600 text-white font-black text-sm shadow-xl shadow-atoma-blue/25 hover:opacity-95 transition-all flex items-center justify-center gap-2 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                <span>{{ __('Buka Aplikasi ATOMA') }}</span>
            </a>

            <a href="{{ route('dashboard') }}" 
               class="block text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
                {{ __('Atau lanjutkan di browser web ini →') }}
            </a>
        </div>
    </div>

    <script>
        (function() {
            var deepLink = "{{ $appDeepLink }}";
            // 1. Immediately trigger deep link to bring APK to foreground
            window.location.href = deepLink;

            // 2. Fallback timer if browser delays custom scheme
            setTimeout(function() {
                var btn = document.getElementById('btn-open-app');
                if (btn) {
                    btn.classList.add('ring-4', 'ring-indigo-400/50', 'animate-pulse');
                }
            }, 1200);
        })();
    </script>
</x-guest-layout>
