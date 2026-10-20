<div x-data="{
        showReceipt: false,
        tx: {},
        openReceipt(detail) {
            this.tx = detail || {};
            this.showReceipt = true;
        },
        closeReceipt() {
            this.showReceipt = false;
        },
        printReceipt() {
            window.print();
        }
     }"
     @open-receipt-modal.window="openReceipt($event.detail)"
     @keydown.escape.window="closeReceipt()"
     x-show="showReceipt"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">

    {{-- Backdrop --}}
    <div x-show="showReceipt" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs"></div>

    {{-- Modal Box Container --}}
    <div class="min-h-full flex items-center justify-center p-3 sm:p-5 relative z-10">
        <div @click.away="closeReceipt()" 
             x-show="showReceipt"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 max-w-lg w-full p-6 sm:p-8 shadow-2xl relative my-auto select-none print:m-0 print:p-0 print:border-none print:shadow-none">

            {{-- Non-printable controls --}}
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-100 dark:border-gray-700 print:hidden">
                <span class="text-xs font-black uppercase tracking-wider text-gray-400">
                    Bukti Pembayaran Digital
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" 
                            @click="printReceipt()" 
                            class="px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Cetak / PDF</span>
                    </button>
                    <button type="button" 
                            @click="closeReceipt()" 
                            class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer">
                        ✕
                    </button>
                </div>
            </div>

            {{-- Receipt Body --}}
            <div class="space-y-6">
                {{-- Header with Logo --}}
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-yellow-500 text-white flex items-center justify-center font-black text-xl shadow-sm">
                            👑
                        </div>
                        <div>
                            <div class="font-black text-base tracking-tight text-gray-900 dark:text-white uppercase">
                                ATOMA PRODUCTIVITY
                            </div>
                            <div class="text-[10px] text-gray-400 font-medium">
                                atoma.app &bull; Langganan Premium
                            </div>
                        </div>
                    </div>

                    {{-- Verified Stamp --}}
                    <div class="border-2 border-emerald-500 text-emerald-600 dark:text-emerald-400 px-3 py-1 rounded-xl text-center transform rotate-2 font-black text-[10px] uppercase tracking-wider">
                        <div>✓ TERVERIFIKASI</div>
                        <div class="text-[8px] font-bold text-gray-400">LUNAS</div>
                    </div>
                </div>

                {{-- Invoice Info Details --}}
                <div class="grid grid-cols-2 gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800 text-xs">
                    <div>
                        <div class="text-[10px] font-bold text-gray-400 uppercase">No. Referensi Faktur</div>
                        <div class="font-mono font-black text-gray-900 dark:text-white mt-0.5" x-text="tx.reference_no || '-'"></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-gray-400 uppercase">Tanggal Aktivasi</div>
                        <div class="font-medium text-gray-800 dark:text-gray-200 mt-0.5" x-text="tx.date || '-'"></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-gray-400 uppercase">Diterbitkan Untuk</div>
                        <div class="font-bold text-gray-800 dark:text-gray-200 mt-0.5" x-text="tx.user_name || '-'"></div>
                        <div class="text-[11px] text-gray-500" x-text="tx.user_email || '-'"></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-gray-400 uppercase">Metode Pembayaran</div>
                        <div class="font-medium text-gray-800 dark:text-gray-200 mt-0.5 capitalize" x-text="tx.payment_method || 'Transfer Manual'"></div>
                    </div>
                </div>

                {{-- Line Items --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold text-gray-400 uppercase border-b border-gray-100 dark:border-gray-700/60 pb-2">
                        <span>Layanan / Paket</span>
                        <span>Nominal</span>
                    </div>

                    <div class="flex items-start justify-between text-xs py-1">
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white" x-text="tx.plan_name || 'ATOMA PRO'"></div>
                            <div class="text-[11px] text-gray-500 mt-0.5" x-text="'Masa Aktif: ' + (tx.validity || '1 Bulan')"></div>
                        </div>
                        <div class="font-mono font-bold text-gray-900 dark:text-white" x-text="tx.amount || 'Rp 0'"></div>
                    </div>

                    <div class="border-t border-gray-100 dark:border-gray-700/60 pt-3 flex items-center justify-between">
                        <span class="text-xs font-black text-gray-900 dark:text-white uppercase">Total Pembayaran</span>
                        <span class="text-base font-black text-emerald-600 dark:text-emerald-400" x-text="tx.amount || 'Rp 0'"></span>
                    </div>
                </div>

                {{-- Footer Guarantee --}}
                <div class="text-[10px] text-gray-400 text-center border-t border-gray-100 dark:border-gray-700/60 pt-4 space-y-1">
                    <p>Bukti digital ini sah dan diterbitkan secara resmi oleh sistem ATOMA Productivity.</p>
                    <p class="font-medium">Terima kasih atas dukungan Anda dalam mengembangkan ATOMA! 🚀</p>
                </div>
            </div>
        </div>
    </div>
</div>
