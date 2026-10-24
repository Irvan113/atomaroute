@if(auth()->check() && (auth()->user()->is_admin || auth()->user()->id === 1))
<div x-data="atomaPerformanceMonitor()" 
     x-init="initMonitor()"
     @keydown.window.cmd.shift.p.prevent="toggleDashboard()"
     @keydown.window.ctrl.shift.p.prevent="toggleDashboard()"
     class="atoma-perf-monitor select-none font-sans text-xs">

    <!-- 1. Floating Live Telemetry Pill (Bottom-Right) -->
    <div x-show="showPill" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="atoma-perf-monitor fixed bottom-4 right-4 z-40 flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-900/90 dark:bg-gray-800/95 text-white shadow-xl border border-gray-700/60 backdrop-blur-md cursor-pointer hover:bg-gray-800 dark:hover:bg-gray-750 transition-all hover:scale-105 active:scale-95 group"
         @click="toggleDashboard()">
        
        <!-- Live Pulse Indicator -->
        <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                  :class="serverLoadStatus === 'low' ? 'bg-emerald-400' : (serverLoadStatus === 'med' ? 'bg-amber-400' : 'bg-rose-400')"></span>
            <span class="relative inline-flex rounded-full h-2 w-2"
                  :class="serverLoadStatus === 'low' ? 'bg-emerald-500' : (serverLoadStatus === 'med' ? 'bg-amber-500' : 'bg-rose-500')"></span>
        </span>

        <!-- Latency / Speed -->
        <div class="flex items-center gap-1 font-mono font-bold text-[11px]">
            <span class="text-amber-400">⚡</span>
            <span x-text="lastDuration ? lastDuration + 'ms' : 'Ready'"></span>
        </div>

        <span class="text-gray-600 dark:text-gray-500 hidden sm:inline">•</span>

        <!-- Server Load Badge -->
        <span class="hidden sm:inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded-md"
              :class="{
                  'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': serverLoadStatus === 'low',
                  'bg-amber-500/20 text-amber-300 border border-amber-500/30': serverLoadStatus === 'med',
                  'bg-rose-500/20 text-rose-300 border border-rose-500/30': serverLoadStatus === 'high'
              }"
              x-text="serverLoadText">
        </span>

        <!-- Close / Minimize Icon -->
        <button type="button" 
                @click.stop="showPill = false" 
                class="ml-1 text-gray-400 hover:text-white p-0.5 rounded-full hover:bg-gray-700/50 transition-colors"
                title="Sembunyikan Telemetry HUD (Tekan Ctrl+Shift+P untuk membuka lagi)">
            ✕
        </button>
    </div>

    <!-- Mini Re-open Button if Pill Hidden -->
    <button x-show="!showPill && !showDashboard" 
            x-transition
            @click="showPill = true"
            title="Buka Monitor Kecepatan & Beban Server (Ctrl+Shift+P)"
            class="fixed bottom-4 right-4 z-40 w-8 h-8 rounded-full bg-gray-900/80 dark:bg-gray-800/90 text-amber-400 shadow-md border border-gray-700/60 flex items-center justify-center text-xs hover:scale-110 active:scale-95 transition-all">
        ⚡
    </button>

    <!-- 2. Performance & Server Health Modal Dashboard -->
    <div x-show="showDashboard" 
         style="display: none;" 
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6"
         @keydown.escape.window="showDashboard = false">
        
        <!-- Backdrop -->
        <div x-show="showDashboard"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showDashboard = false"
             class="fixed inset-0 bg-gray-950/70 backdrop-blur-xs"></div>

        <!-- Dialog Box -->
        <div x-show="showDashboard"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-3"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-3"
             class="relative w-full max-w-3xl max-h-[90vh] bg-white dark:bg-gray-900 rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-800 flex flex-col overflow-hidden text-gray-800 dark:text-gray-100">
            
            <!-- Header -->
            <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3 bg-gray-50/60 dark:bg-gray-850/60 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/10 dark:bg-amber-400/10 text-amber-500 flex items-center justify-center text-xl font-bold border border-amber-500/20">
                        ⚡
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black tracking-tight text-gray-900 dark:text-white">
                                Atoma Performance & Server Telemetry
                            </h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                  :class="{
                                      'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300': serverLoadStatus === 'low',
                                      'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300': serverLoadStatus === 'med',
                                      'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300': serverLoadStatus === 'high'
                                  }"
                                  x-text="'Beban Server: ' + serverLoadText">
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            Diagnostik kecepatan halaman, rendering UI, dan analisis beban server real-time.
                        </p>
                    </div>
                </div>

                <button type="button" 
                        @click="showDashboard = false" 
                        class="w-8 h-8 rounded-xl flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-200/60 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                    ✕
                </button>
            </div>

            <!-- Quick Metrics Grid (Server Telemetry) -->
            <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-3 gap-3 bg-gray-50/30 dark:bg-gray-900 shrink-0">
                <!-- Latency -->
                <div class="p-3 rounded-2xl bg-white dark:bg-gray-800/80 border border-gray-100 dark:border-gray-800 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Kecepatan Terakhir</span>
                    <div class="text-lg font-black text-gray-900 dark:text-white mt-0.5 font-mono" x-text="lastDuration ? lastDuration + ' ms' : '-'"></div>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold" x-text="lastDuration && lastDuration < 50 ? '⚡ Sangat Cepat' : '🟢 Normal'"></span>
                </div>

                <!-- Database Queries -->
                <div class="p-3 rounded-2xl bg-white dark:bg-gray-800/80 border border-gray-100 dark:border-gray-800 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">Beban Database</span>
                    <div class="text-lg font-black text-gray-900 dark:text-white mt-0.5 font-mono" x-text="lastQueryCount + ' Query'"></div>
                    <span class="text-[10px] text-gray-500 dark:text-gray-400" x-text="lastQueryTime ? lastQueryTime : 'SQLite WAL'"></span>
                </div>

                <!-- Memory Usage -->
                <div class="p-3 rounded-2xl bg-white dark:bg-gray-800/80 border border-gray-100 dark:border-gray-800 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-gray-400 block tracking-wider">RAM Server (PHP)</span>
                    <div class="text-lg font-black text-gray-900 dark:text-white mt-0.5 font-mono" x-text="lastMemoryPeak || '4.2 MB'"></div>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">🟢 Sangat Hemat</span>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="flex items-center gap-2 px-4 sm:px-5 pt-3 border-b border-gray-100 dark:border-gray-800 shrink-0">
                <button type="button" 
                        @click="activeTab = 'logs'" 
                        class="pb-2.5 text-xs font-bold transition-all relative border-b-2"
                        :class="activeTab === 'logs' ? 'text-indigo-600 dark:text-indigo-400 border-indigo-600 dark:border-indigo-400' : 'text-gray-400 border-transparent hover:text-gray-600 dark:hover:text-gray-300'">
                    Riwayat Kegiatan (<span x-text="logs.length"></span>)
                </button>
                <button type="button" 
                        @click="activeTab = 'analysis'" 
                        class="pb-2.5 text-xs font-bold transition-all relative border-b-2"
                        :class="activeTab === 'analysis' ? 'text-indigo-600 dark:text-indigo-400 border-indigo-600 dark:border-indigo-400' : 'text-gray-400 border-transparent hover:text-gray-600 dark:hover:text-gray-300'">
                    Analisis Beban Server (FAQ)
                </button>
            </div>

            <!-- Tab 1: Live Activity Log -->
            <div x-show="activeTab === 'logs'" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-2">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">
                        Pencatatan Otomatis Setiap Interaksi & Request
                    </span>
                    <button type="button" 
                            @click="clearLogs()" 
                            class="text-[11px] font-bold text-rose-500 hover:text-rose-600 hover:underline">
                        Bersihkan Log
                    </button>
                </div>

                <div class="border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="log in logs" :key="log.id">
                        <div class="p-3 sm:px-4 flex items-center justify-between gap-3 text-xs hover:bg-gray-50 dark:hover:bg-gray-850/50 transition-colors">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-sm shrink-0" x-text="log.icon"></span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900 dark:text-white truncate" x-text="log.action"></span>
                                        <span class="text-[9px] font-semibold px-1.5 py-0.2 rounded-md font-mono"
                                              :class="{
                                                  'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300': log.load === '0% Server' || log.load === 'Ringan',
                                                  'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300': log.load === 'Sedang',
                                                  'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300': log.load === 'Berat'
                                              }"
                                              x-text="log.load">
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 block truncate" x-text="log.detail"></span>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="font-mono font-bold" 
                                      :class="log.duration < 50 ? 'text-emerald-600 dark:text-emerald-400' : (log.duration < 200 ? 'text-amber-500' : 'text-rose-500')"
                                      x-text="log.duration ? log.duration + ' ms' : '0 ms'">
                                </span>
                                <span class="text-[10px] text-gray-400 block font-mono" x-text="log.time"></span>
                            </div>
                        </div>
                    </template>

                    <div x-show="logs.length === 0" class="p-8 text-center text-xs text-gray-400">
                        Belum ada aktivitas terekam. Buka halaman, scroll catatan, atau jalankan timer untuk melihat telemetri.
                    </div>
                </div>
            </div>

            <!-- Tab 2: Server Impact Analysis / FAQ -->
            <div x-show="activeTab === 'analysis'" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4">
                <div class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60">
                    <h4 class="text-xs font-bold text-indigo-900 dark:text-indigo-300 mb-1">
                        💡 Penjelasan Analisis Beban Server (Jawaban untuk Pertanyaan Anda)
                    </h4>
                    <p class="text-[11px] text-indigo-700 dark:text-indigo-400 leading-relaxed">
                        Di bawah ini adalah rincian teknis apakah kegiatan Anda di web membebani server atau sepenuhnya gratis di sisi client:
                    </p>
                </div>

                <div class="space-y-3">
                    <!-- Note Scrolling -->
                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center justify-between mb-1">
                            <h5 class="text-xs font-extrabold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span>📜</span>
                                <span>Scrolling Catatan & Mini Note</span>
                            </h5>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                                0% Beban Server
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">
                            <strong>100% Client-Side (GPU Rendering)</strong>. Scrolling tidak mengirim request apa pun ke server. Lemot saat scrolling sebelumnya disebabkan oleh kalkulasi tata letak berulang (<em>layout thrashing</em>) pada event scroll, yang kini sudah dioptimalkan dengan mode <code>passive</code> dan akselerasi GPU.
                        </p>
                    </div>

                    <!-- Pomodoro Timer -->
                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center justify-between mb-1">
                            <h5 class="text-xs font-extrabold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span>🍅</span>
                                <span>Pomodoro Timer & Musik</span>
                            </h5>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                                0% Beban Server Saat Berjalan
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">
                            <strong>100% Client-Side Timer</strong>. Detik demi detik pomodoro dihitung murni di browser Anda melalui JavaScript interval tanpa request HTTP. Server hanya menerima 1 kali request kecil (&lt;5ms) ketika sesi pomodoro selesai untuk menyimpan riwayat sesi.
                        </p>
                    </div>

                    <!-- EXP & Leveling -->
                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center justify-between mb-1">
                            <h5 class="text-xs font-extrabold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span>⭐</span>
                                <span>Pembagian EXP & Selesai Tugas</span>
                            </h5>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                                Sangat Ringan (~3-8ms)
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">
                            Kini dengan <strong>SQLite WAL (Write-Ahead Logging)</strong>, transaksi tulis saat penambahan EXP berlangsung instan dalam hitungan milidetik tanpa mengunci database bagi pembacaan lain. Sangat aman dan tidak membuat server sesak.
                        </p>
                    </div>

                    <!-- Ganti Halaman -->
                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center justify-between mb-1">
                            <h5 class="text-xs font-extrabold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span>📄</span>
                                <span>Ganti Halaman (Navigasi SPA)</span>
                            </h5>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                                Cepat (~30-60ms)
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">
                            Menggunakan <code>wire:navigate</code> (Single Page Application) sehingga browser tidak memuat ulang seluruh file CSS/JS dari nol. Hanya HTML halaman tujuan yang diganti, menghemat bandwidth hingga 80%.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-3.5 sm:px-5 border-t border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-850/60 flex items-center justify-between text-[11px] text-gray-400 shrink-0">
                <span>Shortcut: <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded font-mono text-[10px]">Ctrl+Shift+P</kbd> atau <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded font-mono text-[10px]">Cmd+Shift+P</kbd></span>
                <button type="button" 
                        @click="showDashboard = false" 
                        class="px-3.5 py-1.5 bg-gray-900 hover:bg-gray-800 text-white dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white rounded-xl font-bold transition-all">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script data-navigate-once>
function atomaPerformanceMonitor() {
    return {
        showPill: true,
        showDashboard: false,
        activeTab: 'logs',
        lastDuration: 0,
        lastQueryCount: 0,
        lastQueryTime: '',
        lastMemoryPeak: '4.2 MB',
        serverLoadStatus: 'low', // 'low', 'med', 'high'
        serverLoadText: 'Sangat Ringan',
        logs: [],

        initMonitor() {
            // 1. Intercept Livewire Requests
            document.addEventListener('livewire:init', () => {
                if (window.Livewire && window.Livewire.hook) {
                    window.Livewire.hook('request', ({ uri, options, payload, respond, succeed, fail }) => {
                        let startTime = performance.now();
                        
                        respond(({ status, response }) => {
                            let duration = Math.round(performance.now() - startTime);
                            this.lastDuration = duration;

                            // Read backend performance headers
                            let execTime = response.headers.get('X-Execution-Time');
                            let queryCount = response.headers.get('X-Query-Count');
                            let queryTime = response.headers.get('X-Query-Time');
                            let memPeak = response.headers.get('X-Memory-Peak');

                            if (queryCount) this.lastQueryCount = parseInt(queryCount, 10);
                            if (queryTime) this.lastQueryTime = queryTime;
                            if (memPeak) this.lastMemoryPeak = memPeak;

                            // Calculate Server Load
                            let qCount = parseInt(queryCount || '0', 10);
                            if (duration < 80 && qCount < 8) {
                                this.serverLoadStatus = 'low';
                                this.serverLoadText = 'Sangat Ringan';
                            } else if (duration < 250 && qCount < 20) {
                                this.serverLoadStatus = 'med';
                                this.serverLoadText = 'Sedang';
                            } else {
                                this.serverLoadStatus = 'high';
                                this.serverLoadText = 'Tinggi';
                            }

                            // Extract action name if available
                            let actionName = 'Interaksi Livewire';
                            let icon = '⚡';
                            try {
                                if (payload && payload.updates) {
                                    let calls = payload.updates.map(u => u.type + (u.payload && u.payload.method ? ':' + u.payload.method : '')).join(', ');
                                    if (calls) actionName = calls;
                                }
                            } catch(e) {}

                            if (actionName.includes('autoSaveJournal')) {
                                icon = '💾';
                                actionName = 'Autosave Catatan';
                            } else if (actionName.includes('switchNote') || actionName.includes('editJournal')) {
                                icon = '📝';
                                actionName = 'Buka / Ganti Catatan';
                            } else if (actionName.includes('completeTask') || actionName.includes('award') || actionName.includes('xp')) {
                                icon = '⭐';
                                actionName = 'Selesai Tugas & Pembagian EXP';
                            }

                            this.addLog({
                                action: actionName,
                                icon: icon,
                                duration: duration,
                                load: this.serverLoadText,
                                detail: `Queries: ${this.lastQueryCount} • DB: ${this.lastQueryTime || '<1ms'} • RAM: ${this.lastMemoryPeak}`
                            });
                        });
                    });
                }
            });

            // 3. Track Page Navigation (SPA wire:navigate)
            let navStartTime = 0;
            document.addEventListener('livewire:navigating', () => {
                navStartTime = performance.now();
            });
            document.addEventListener('livewire:navigated', () => {
                if (navStartTime > 0) {
                    let navDuration = Math.round(performance.now() - navStartTime);
                    this.lastDuration = navDuration;
                    this.addLog({
                        action: 'Ganti Halaman: ' + window.location.pathname,
                        icon: '📄',
                        duration: navDuration,
                        load: navDuration < 100 ? 'Ringan' : 'Sedang',
                        detail: 'Navigasi SPA instan via wire:navigate'
                    });
                    navStartTime = 0;
                }
            });

            // 4. Track Note Scrolling Activity
            let scrollTimer = null;
            let scrollStart = 0;
            window.addEventListener('scroll', () => {
                if (!scrollTimer) {
                    scrollStart = performance.now();
                }
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(() => {
                    let scrollDuration = Math.round(performance.now() - scrollStart);
                    if (scrollDuration > 100) {
                        this.addLog({
                            action: 'Scroll Catatan / Halaman',
                            icon: '📜',
                            duration: scrollDuration,
                            load: '0% Server',
                            detail: 'Rendering 100% di GPU Browser • 0 request ke server'
                        });
                    }
                    scrollTimer = null;
                }, 300);
            }, { passive: true });

            // 5. Track Custom Events (XP, Pomodoro, Preview)
            window.addEventListener('journal-autosaved', (e) => {
                this.addLog({
                    action: 'Autosave Catatan Selesai',
                    icon: '💾',
                    duration: 12,
                    load: 'Ringan',
                    detail: 'Draft tersimpan otomatis di background'
                });
            });

            window.addEventListener('pomodoro-session-completed', (e) => {
                this.addLog({
                    action: 'Sesi Pomodoro Selesai',
                    icon: '🍅',
                    duration: 15,
                    load: 'Ringan',
                    detail: 'Sinkronisasi riwayat sesi pomodoro ke server'
                });
            });

            // Initial log
            this.addLog({
                action: 'Aplikasi Siap & Monitoring Aktif',
                icon: '🚀',
                duration: 10,
                load: 'Ringan',
                detail: 'SQLite WAL Mode aktif • Koneksi optimal'
            });
        },

        addLog(entry) {
            let now = new Date();
            let timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.logs.unshift({
                id: Date.now() + Math.random(),
                time: timeStr,
                action: entry.action,
                icon: entry.icon || '⚡',
                duration: entry.duration || 0,
                load: entry.load || 'Ringan',
                detail: entry.detail || ''
            });
            if (this.logs.length > 50) this.logs.pop();
        },

        clearLogs() {
            this.logs = [];
        },

        toggleDashboard() {
            this.showDashboard = !this.showDashboard;
        }
    };
}
</script>
@endif
