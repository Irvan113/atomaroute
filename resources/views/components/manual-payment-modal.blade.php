@php
    $user = auth()->user();
    $userName = $user ? $user->name : 'Pengguna ATOMA';
    $userEmail = $user ? $user->email : '';
    $adminWaNumber = config('services.subscription.admin_whatsapp', '6285263307849');
    $adminWaDisplay = config('services.subscription.admin_whatsapp_display', '0852-6330-7849');
    $rawWaNumber = '085263307849';
@endphp

<div x-data="{
        showModal: false,
        plan: 'monthly',
        copiedField: '',
        openModal(detail) {
            this.plan = (detail && detail.plan) ? detail.plan : 'monthly';
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        copyText(text, field) {
            navigator.clipboard.writeText(text);
            this.copiedField = field;
            setTimeout(() => {
                this.copiedField = '';
            }, 2000);
        },
        get planPrice() {
            return this.plan === 'yearly' ? 'Rp 199.000 / tahun' : 'Rp 29.000 / bulan';
        },
        get planLabel() {
            return this.plan === 'yearly' ? 'ATOMA PRO Tahunan (365 Hari)' : 'ATOMA PRO Bulanan (30 Hari)';
        },
        sendWa() {
            const price = this.planPrice;
            const label = this.planLabel;
            const msg = 'Halo Admin ATOMA, saya mau tanya & aktivasi ' + label + ' (' + price + ').\n\nDetail Akun:\n• Nama: ' + {{ Js::from($userName) }} + '\n• Email: ' + {{ Js::from($userEmail) }} + '\n\nMohon info detail pembayaran dan bantuan untuk aktivasinya ya Admin. Terima kasih!';
            window.open('https://wa.me/{{ $adminWaNumber }}?text=' + encodeURIComponent(msg), '_blank');
        }
     }"
     @open-manual-payment-modal.window="openModal($event.detail)"
     @keydown.escape.window="closeModal()"
     x-show="showModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">

    {{-- Backdrop --}}
    <div x-show="showModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs"></div>

    {{-- Modal Box Container --}}
    <div class="min-h-full flex items-center justify-center p-3 sm:p-5 relative z-10">
        <div @click.away="closeModal()" 
             x-show="showModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 max-w-lg w-full p-6 sm:p-7 shadow-2xl relative my-auto select-none">
            
            {{-- Close Button --}}
            <button @click="closeModal()" 
                    class="absolute top-5 right-5 p-2 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                ✕
            </button>

            {{-- Header --}}
            <div class="flex items-center gap-3.5 mb-5">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-yellow-500 text-white flex items-center justify-center font-black text-2xl shadow-md shadow-amber-500/20 shrink-0">
                    👑
                </div>
                <div>
                    <h3 class="text-lg sm:text-xl font-black text-gray-900 dark:text-white">
                        Aktivasi ATOMA PRO via WhatsApp
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        DM langsung dengan Admin resmi untuk tanya & aktivasi instan.
                    </p>
                </div>
            </div>

            {{-- Plan Selector Toggle inside Modal --}}
            <div class="grid grid-cols-2 gap-2 bg-gray-100 dark:bg-gray-900/70 p-1.5 rounded-2xl border border-gray-200/80 dark:border-gray-700/60 mb-5">
                <button type="button" 
                        @click="plan = 'monthly'"
                        class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-black transition-all cursor-pointer flex flex-col items-center justify-center"
                        :class="plan === 'monthly' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs border border-gray-200/60 dark:border-gray-700/60' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'">
                    <span>Paket Bulanan</span>
                    <span class="text-amber-600 dark:text-amber-400 text-[11px] font-bold">Rp 29.000 / bln</span>
                </button>
                <button type="button" 
                        @click="plan = 'yearly'"
                        class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-black transition-all cursor-pointer flex flex-col items-center justify-center relative"
                        :class="plan === 'yearly' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs border border-gray-200/60 dark:border-gray-700/60' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'">
                    <span class="flex items-center gap-1">
                        <span>Paket Tahunan</span>
                        <span class="px-1 py-0.2 rounded text-[8px] bg-rose-500 text-white font-black uppercase">Hemat 40%</span>
                    </span>
                    <span class="text-amber-600 dark:text-amber-400 text-[11px] font-bold">Rp 199.000 / thn</span>
                </button>
            </div>

            {{-- WhatsApp Contact Highlight Box --}}
            <div class="p-4 rounded-2xl bg-emerald-500/10 dark:bg-emerald-950/30 border border-emerald-500/30 mb-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                            WhatsApp Admin Resmi
                        </span>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-600/15 text-emerald-700 dark:text-emerald-300 font-bold">
                        Online
                    </span>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-white dark:bg-gray-800 border border-emerald-500/20 shadow-xs">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-xs">
                            💬
                        </div>
                        <div>
                            <div class="font-mono font-black text-base sm:text-lg text-gray-900 dark:text-white tracking-wide">
                                {{ $adminWaDisplay }}
                            </div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                WhatsApp Customer Service ATOMA
                            </div>
                        </div>
                    </div>
                    <button type="button" 
                            @click="copyText('{{ $rawWaNumber }}', 'wa')" 
                            class="px-3.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5 shrink-0">
                        <span x-text="copiedField === 'wa' ? '✓ Nomor Tersalin' : '📋 Salin Nomor'"></span>
                    </button>
                </div>
            </div>

            {{-- 3 Step Guide --}}
            <div class="p-3.5 rounded-2xl bg-gray-50/80 dark:bg-gray-900/40 border border-gray-200/60 dark:border-gray-700/60 text-xs text-gray-600 dark:text-gray-300 space-y-1.5 mb-5">
                <div class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1.5 text-xs">
                    <span>💡 Cara Mudah Aktivasi:</span>
                </div>
                <ol class="list-decimal list-inside space-y-1 text-[11px] text-gray-600 dark:text-gray-400">
                    <li>Pilih paket langganan yang Anda inginkan di atas.</li>
                    <li>Klik tombol hijau di bawah untuk langsung membuka chat WhatsApp dengan Admin.</li>
                    <li>Admin akan mengonfirmasi detail pembayaran dan langsung mengaktifkan akun PRO Anda!</li>
                </ol>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-center gap-2.5">
                <button type="button"
                        @click="sendWa()" 
                        class="w-full sm:flex-1 py-3 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/25 transition-all hover:scale-[1.01] active:scale-[0.99] text-center cursor-pointer">
                    <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.173.086.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.072.043.419-.101.824z"/>
                    </svg>
                    <span>Chat / DM Admin via WhatsApp</span>
                </button>

                <button type="button" 
                        @click="closeModal()" 
                        class="w-full sm:w-auto py-3 px-5 rounded-2xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-bold text-xs transition-colors cursor-pointer text-center">
                    Tutup
                </button>
            </div>
            
            <p class="text-[10px] text-gray-400 text-center mt-3">
                ⚡ Akun PRO akan diaktifkan langsung oleh Admin segera setelah konfirmasi.
            </p>
        </div>
    </div>
</div>
