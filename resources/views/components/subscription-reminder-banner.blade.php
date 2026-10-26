@php
    $user = auth()->user();
    if (!$user || $user->is_admin) {
        return;
    }

    $expiresAt = $user->subscription_expires_at;
    if (!$expiresAt) {
        return;
    }

    $now = now();
    $isExpiringSoon = false;
    $isRecentlyExpired = false;
    $remainingText = '';

    if ($user->isPro() && $expiresAt->isFuture()) {
        $days = (int) $now->diffInDays($expiresAt, false);
        $hours = (int) $now->diffInHours($expiresAt, false);

        if ($days <= 3) {
            $isExpiringSoon = true;
            if ($days === 0) {
                $remainingText = $hours > 0 ? "tersisa {$hours} jam lagi" : "berakhir hari ini";
            } else {
                $remainingText = "tersisa {$days} hari lagi (hingga " . $expiresAt->format('d M Y') . ")";
            }
        }
    } elseif ($expiresAt->isPast() && $expiresAt->diffInDays($now) <= 7) {
        $isRecentlyExpired = true;
        $remainingText = "telah berakhir pada " . $expiresAt->format('d M Y') . " (" . $expiresAt->diffForHumans() . ")";
    }
@endphp

@if($isExpiringSoon || $isRecentlyExpired)
    <div x-data="{ dismissed: false }" 
         x-show="!dismissed" 
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="mb-4 select-none [contain:layout] [contain-intrinsic-size:auto_72px]">
        
        @if($isExpiringSoon)
            {{-- Warning Banner for Expiring Soon (H-3) --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-amber-500/15 via-yellow-500/15 to-amber-500/15 dark:from-amber-950/30 dark:via-yellow-950/30 dark:to-amber-950/30 border border-amber-400/40 p-3.5 sm:p-4 text-amber-900 dark:text-amber-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-base shrink-0 shadow-xs">
                        👑
                    </div>
                    <div class="text-xs sm:text-sm min-w-0">
                        <div class="font-black text-amber-900 dark:text-amber-100 flex items-center gap-1.5 flex-wrap">
                            <span>Pengingat Masa Aktif ATOMA PRO</span>
                            <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-black bg-amber-500 text-white uppercase">
                                Segera Berakhir
                            </span>
                        </div>
                        <p class="text-amber-800/80 dark:text-amber-300/80 text-xs mt-0.5">
                            Masa aktif langganan Anda <strong class="text-amber-950 dark:text-amber-100">{{ $remainingText }}</strong>. Perpanjang sekarang agar pembuatan milestone dan konsultasi AI Coach tetap berjalan tanpa hambatan.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                    <button @click="$dispatch('open-manual-payment-modal', { plan: 'monthly' })"
                            class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-black shadow-xs shadow-amber-500/20 transition-all cursor-pointer flex items-center gap-1.5">
                        <span>💬 Perpanjang via Admin</span>
                    </button>
                    <button @click="dismissed = true" 
                            class="p-1.5 text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200 transition-colors cursor-pointer" 
                            title="Tutup pengingat">
                        ✕
                    </button>
                </div>
            </div>
        @elseif($isRecentlyExpired)
            {{-- Alert Banner for Recently Expired --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-500/15 via-red-500/15 to-rose-500/15 dark:from-rose-950/30 dark:via-red-950/30 dark:to-rose-950/30 border border-rose-400/40 p-3.5 sm:p-4 text-rose-900 dark:text-rose-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-rose-500 text-white flex items-center justify-center font-black text-base shrink-0 shadow-xs">
                        🔒
                    </div>
                    <div class="text-xs sm:text-sm min-w-0">
                        <div class="font-black text-rose-900 dark:text-rose-100 flex items-center gap-1.5 flex-wrap">
                            <span>Langganan ATOMA PRO Anda Telah Berakhir</span>
                            <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-black bg-rose-500 text-white uppercase">
                                Kedaluwarsa
                            </span>
                        </div>
                        <p class="text-rose-800/80 dark:text-rose-300/80 text-xs mt-0.5">
                            Paket PRO Anda <strong class="text-rose-950 dark:text-rose-100">{{ $remainingText }}</strong>. Fitur premium seperti 1-klik AI task generator dan multiple milestone saat ini terkunci.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                    <button @click="$dispatch('open-manual-payment-modal', { plan: 'monthly' })"
                            class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-black shadow-xs shadow-rose-600/20 transition-all cursor-pointer flex items-center gap-1.5">
                        <span>💬 Aktifkan Kembali</span>
                    </button>
                    <button @click="dismissed = true" 
                            class="p-1.5 text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-200 transition-colors cursor-pointer" 
                            title="Tutup pengingat">
                        ✕
                    </button>
                </div>
            </div>
        @endif
    </div>
@endif
