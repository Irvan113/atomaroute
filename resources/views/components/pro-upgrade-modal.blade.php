<div x-data="{ 
        show: false,
        billingCycle: 'yearly',
        isUpgrading: false,
        openModal() { this.show = true; },
        closeModal() { this.show = false; }
    }"
    @open-pro-modal.window="openModal()"
    @keydown.escape.window="closeModal()"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    style="display: none;">

    <!-- Backdrop -->
    <div x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/60 backdrop-blur-sm z-10 flex items-center justify-center p-4">

        <!-- Modal Card -->
        <div x-show="show" 
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             @click.outside="closeModal()"
             class="bg-white dark:bg-gray-900 rounded-3xl shadow-2xl max-w-xl w-full max-h-[92vh] overflow-y-auto border border-amber-300/60 dark:border-amber-500/30 p-6 sm:p-8 relative">

            <!-- Close Button -->
            <button @click="closeModal()" class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded-full transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <!-- Header Badge -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gradient-to-r from-amber-500/15 via-yellow-500/15 to-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-400/30 text-xs font-black mb-3">
                    <span>👑</span>
                    <span>ATOMA PRO SUBSCRIPTION</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">
                    Buka Potensi Maksimal Tanpa Batas
                </h2>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-md mx-auto">
                    Akses 90 Days Challenge, AI Coach tanpa kuota, dan playlist YouTube Pomodoro tak terbatas.
                </p>
            </div>

            <!-- Features Grid -->
            <div class="space-y-3 mb-6 bg-gray-50 dark:bg-gray-800/60 rounded-2xl p-4 border border-gray-100 dark:border-gray-800">
                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 font-bold text-xs mt-0.5">
                        🎯
                    </div>
                    <div>
                        <div class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <span>Unlimited Milestones & 90 Days Challenge</span>
                            <span class="text-[9px] bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-1.5 py-0.2 rounded font-bold">Pro</span>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Jalankan program belajar, olahraga, & habit sekaligus (Free terbatas 1 milestone).</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-xl bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0 font-bold text-xs mt-0.5">
                        🎵
                    </div>
                    <div>
                        <div class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <span>Unlimited YouTube Folders & Custom Playlist</span>
                            <span class="text-[9px] bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-1.5 py-0.2 rounded font-bold">Pro</span>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Buat folder musik fokus sebanyak yang kamu mau untuk Pomodoro (Free dibatasi 2 folder).</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 font-bold text-xs mt-0.5">
                        ✨
                    </div>
                    <div>
                        <div class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <span>AI Coach Unlimited & Smart Bulk Tasks</span>
                            <span class="text-[9px] bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-1.5 py-0.2 rounded font-bold">Pro</span>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Konsultasi tak terbatas dengan AI Coach dan 1-klik generate task harian otomatis.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 font-bold text-xs mt-0.5">
                        📜
                    </div>
                    <div>
                        <div class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <span>Sertifikat Digital Resmi & Verifikasi Publik</span>
                            <span class="text-[9px] bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-1.5 py-0.2 rounded font-bold">Pro</span>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Sertifikat kelulusan 90 hari dengan QR Code unik untuk dipajang di LinkedIn & CV.</p>
                    </div>
                </div>
            </div>

            <!-- Billing Cycle Switcher -->
            <div class="flex items-center justify-center gap-2 mb-6 p-1 bg-gray-100 dark:bg-gray-800 rounded-2xl max-w-xs mx-auto">
                <button type="button" 
                        @click="billingCycle = 'monthly'" 
                        :class="billingCycle === 'monthly' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm font-black' : 'text-gray-500 dark:text-gray-400 font-bold'"
                        class="flex-1 py-1.5 rounded-xl text-xs transition cursor-pointer">
                    Bulanan
                </button>
                <button type="button" 
                        @click="billingCycle = 'yearly'" 
                        :class="billingCycle === 'yearly' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm font-black' : 'text-gray-500 dark:text-gray-400 font-bold'"
                        class="flex-1 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-center gap-1">
                    <span>Tahunan</span>
                    <span class="text-[9px] bg-emerald-500 text-white px-1.5 py-0.2 rounded-full font-black">Hemat 40%</span>
                </button>
            </div>

            <!-- Price Display -->
            <div class="text-center mb-6">
                <div x-show="billingCycle === 'yearly'" class="space-y-1">
                    <div class="flex items-baseline justify-center gap-1">
                        <span class="text-sm font-bold text-gray-400 line-through">Rp 348.000</span>
                        <span class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white">Rp 199.000</span>
                        <span class="text-xs font-bold text-gray-500">/ tahun</span>
                    </div>
                    <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold">
                        Hanya ~Rp 16.500 per bulan • Ditagih tahunan
                    </div>
                </div>

                <div x-show="billingCycle === 'monthly'" class="space-y-1" style="display: none;">
                    <div class="flex items-baseline justify-center gap-1">
                        <span class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white">Rp 29.000</span>
                        <span class="text-xs font-bold text-gray-500">/ bulan</span>
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                        Bebas batalkan kapan saja tanpa komitmen
                    </div>
                </div>
            </div>

            <!-- Current User Status & Action Buttons -->
            @php
                $user = auth()->user();
                $isUserPro = $user ? $user->isPro() : false;
            @endphp

            @if($isUserPro)
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-700/60 rounded-2xl text-center mb-4">
                    <div class="text-xs font-black text-emerald-700 dark:text-emerald-300 flex items-center justify-center gap-1.5">
                        <span>✓</span> Kamu Sedang Menikmati Paket ATOMA PRO Aktif!
                    </div>
                    @if($user->subscription_expires_at)
                        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5">
                            Berlaku hingga: {{ $user->subscription_expires_at->format('d M Y') }}
                        </div>
                    @endif
                </div>
            @endif

            <div class="space-y-2.5">
                @if(!$isUserPro)
                    <button type="button" 
                            @click="closeModal(); $dispatch('open-manual-payment-modal', { plan: billingCycle })"
                            class="w-full py-3.5 px-4 rounded-2xl font-black text-sm flex items-center justify-center gap-2 shadow-lg transition-all cursor-pointer bg-gradient-to-r from-amber-500 via-amber-600 to-yellow-500 hover:from-amber-600 hover:to-yellow-600 text-white shadow-amber-500/25 hover:shadow-xl hover:-translate-y-0.5">
                        <span>👑</span>
                        <span>Pilih Paket & Konfirmasi via Admin (WhatsApp)</span>
                    </button>
                @else
                    <button type="button" 
                            @click="closeModal(); $dispatch('open-manual-payment-modal', { plan: billingCycle })"
                            class="w-full py-3.5 px-4 rounded-2xl font-black text-sm flex items-center justify-center gap-2 shadow-lg transition-all cursor-pointer bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/25">
                        <span>💬</span>
                        <span>Perpanjang Paket via WhatsApp Admin</span>
                    </button>
                @endif

                <form method="POST" action="/api/subscription/toggle-demo" class="text-center">
                    @csrf
                    <input type="hidden" name="action" value="{{ $isUserPro ? 'downgrade' : 'upgrade' }}">
                    <button type="submit" 
                            class="text-[11px] text-gray-500 dark:text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 underline cursor-pointer">
                        {{ $isUserPro ? 'Beralih ke Free Starter (Simulasi Demo)' : 'Simulasikan Langsung Jadi PRO (Simulasi Demo)' }}
                    </button>
                </form>

                <button type="button" @click="closeModal()" class="w-full py-2 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 font-bold transition cursor-pointer">
                    Nanti Saja
                </button>
            </div>
        </div>
    </div>
</div>
