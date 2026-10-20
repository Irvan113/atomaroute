<div x-data="commandPalette()"
     @open-command-palette.window="open()"
     @keydown.window.prevent.cmd.k="open()"
     @keydown.window.prevent.ctrl.k="open()"
     @keydown.escape.window="close()"
     x-cloak>

    <!-- Backdrop Overlay -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="close()"
         class="fixed inset-0 z-[99998] bg-gray-900/60 backdrop-blur-md"></div>

    <!-- Command Palette Modal -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-4"
         class="fixed top-12 sm:top-20 inset-x-0 mx-auto z-[99999] w-full max-w-2xl px-4 pointer-events-none">
        
        <div class="pointer-events-auto bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh] border-atoma-blue/20">
            
            <!-- Search Bar Header -->
            <div class="relative flex items-center px-4 py-3.5 border-b border-gray-200/80 dark:border-gray-800/80">
                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 shrink-0 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>

                <input type="text" 
                       x-ref="searchInput"
                       x-model="query"
                       @input.debounce.150ms="performSearch()"
                       @keydown.down.prevent="selectNext()"
                       @keydown.up.prevent="selectPrev()"
                       @keydown.enter.prevent="executeSelected()"
                       placeholder="Cari tugas, deck flashcard, catatan, atau ketik aksi cepat..."
                       class="w-full bg-transparent border-0 text-sm sm:text-base font-semibold text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-0 focus:outline-none p-0">

                <div class="flex items-center gap-1.5 shrink-0 ml-2">
                    <span class="hidden sm:inline text-[10px] font-mono px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-500 border border-gray-200 dark:border-gray-700">
                        ESC
                    </span>
                    <button type="button" @click="close()" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Results & Actions List -->
            <div class="flex-1 overflow-y-auto p-2 space-y-1 text-xs sm:text-sm hide-scrollbar max-h-[60vh]">
                
                <!-- Loading State -->
                <div x-show="isLoading" class="py-6 text-center text-xs text-gray-400">
                    <span class="inline-block animate-spin mr-1.5">⏳</span> Mencari di ATOMA...
                </div>

                <!-- Empty State -->
                <div x-show="!isLoading && flattenedItems.length === 0" class="py-8 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tidak ada hasil yang cocok dengan "<span x-text="query" class="font-bold"></span>".</p>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Coba ketik kata kunci seperti "pomodoro", "tugas", atau "tema".</p>
                </div>

                <!-- List Items -->
                <template x-for="(item, index) in flattenedItems" :key="item.id || item.title + index">
                    <div @click="executeItem(item)"
                         @mouseenter="selectedIndex = index"
                         :class="selectedIndex === index ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-900 dark:text-indigo-100 border-indigo-200 dark:border-indigo-800' : 'text-gray-700 dark:text-gray-300 border-transparent hover:bg-gray-50 dark:hover:bg-gray-800/50'"
                         class="group flex items-center justify-between p-2.5 sm:p-3 rounded-2xl border transition-all cursor-pointer select-none">
                        
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <!-- Icon / Category Emoji -->
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm shrink-0 bg-gray-100 dark:bg-gray-800 group-hover:scale-105 transition-transform"
                                 :class="selectedIndex === index ? 'bg-indigo-500 text-white shadow-xs' : ''">
                                <span x-text="item.icon || '⚡'"></span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="text-xs sm:text-sm font-bold truncate" x-text="item.title"></p>
                                    <template x-if="item.badge">
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-black uppercase tracking-wider bg-gray-200/70 dark:bg-gray-700 text-gray-600 dark:text-gray-300 shrink-0"
                                              x-text="item.badge"></span>
                                    </template>
                                </div>
                                <template x-if="item.subtitle">
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 truncate mt-0.5" x-text="item.subtitle"></p>
                                </template>
                            </div>
                        </div>

                        <!-- Right Hint / Action -->
                        <div class="flex items-center gap-1 shrink-0 ml-2">
                            <span class="text-[10px] text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity"
                                  :class="selectedIndex === index ? 'opacity-100 text-indigo-500 font-bold' : ''">
                                Tekan ↵
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer Shortcuts Hint -->
            <div class="px-4 py-2.5 bg-gray-50/80 dark:bg-gray-850/80 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[11px] text-gray-400 dark:text-gray-500">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-mono">↑↓</kbd> Navigasi
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-mono">↵</kbd> Buka
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-mono">ESC</kbd> Tutup
                    </span>
                </div>
                <div class="hidden sm:block font-semibold text-indigo-500">
                    ATOMA Command Palette
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function commandPalette() {
        return {
            isOpen: false,
            query: '',
            isLoading: false,
            selectedIndex: 0,
            remoteResults: { tasks: [], flashcards: [], journals: [] },
            
            // Static Quick System Actions
            staticActions: [
                { id: 'act-new-task', title: 'Tambah Tugas Baru', subtitle: 'Buka modal input tugas harian', icon: '➕', badge: 'Tugas', action: () => { window.dispatchEvent(new CustomEvent('open-new-task-modal')); } },
                { id: 'act-pomodoro', title: 'Nyalakan / Buka Pomodoro', subtitle: 'Buka floating timer fokus', icon: '🍅', badge: 'Fokus', action: () => { window.dispatchEvent(new CustomEvent('toggle-pomodoro-widget')); } },
                { id: 'act-pip', title: 'Mini Floating Window (Always on Top)', subtitle: 'Buka jendela mini yang melayang di atas aplikasi lain', icon: '📌', badge: 'Desktop PiP', action: () => { window.dispatchEvent(new CustomEvent('toggle-pip-window')); } },
                { id: 'act-zen', title: 'Beralih Mode Zen / Sistematis', subtitle: 'Ganti tampilan fokus tugas', icon: '🧘', badge: 'Tampilan', action: () => { window.dispatchEvent(new CustomEvent('toggle-zen-mode')); } },
                { id: 'act-srs', title: 'Review Flashcards (SRS)', subtitle: 'Lompat ke halaman flashcard spaced repetition', icon: '🧠', badge: 'Belajar', url: '/flashcards' },
                { id: 'act-journal', title: 'Buka Catatan & Jurnal', subtitle: 'Tulis ide atau catatan harian baru', icon: '📖', badge: 'Jurnal', url: '/journal' },
                { id: 'act-social', title: 'Ruang Belajar Hening & Teman', subtitle: 'Lihat teman yang sedang fokus', icon: '🟢', badge: 'Komunitas', url: '/social' },
                { id: 'act-prestige', title: 'Status Prestige & Level', subtitle: 'Cek level, gelar, dan lencana', icon: '👑', badge: 'Gamifikasi', url: '/prestige' },
                { id: 'act-store', title: 'Toko Hadiah & Kostumisasi', subtitle: 'Beli tema, bingkai avatar, dan sound pack', icon: '🛍️', badge: 'Toko', url: '/store' },
                { id: 'act-feedback', title: 'Surat Dev & Donasi', subtitle: 'Kirim masukan atau traktir kopi developer', icon: '☕', badge: 'Surat Dev', action: () => { window.dispatchEvent(new CustomEvent('open-feedback-modal')); } },
                { id: 'act-theme-dark', title: 'Tema: Dark Mode', subtitle: 'Ubah tema menjadi mode gelap', icon: '🌙', badge: 'Tema', action: () => { localStorage.setItem('darkMode', 'true'); document.documentElement.classList.add('dark'); window.dispatchEvent(new CustomEvent('toast', { detail: { message: '🌙 Mode Gelap diaktifkan', type: 'info' } })); } },
                { id: 'act-theme-light', title: 'Tema: Light Mode', subtitle: 'Ubah tema menjadi mode terang', icon: '☀️', badge: 'Tema', action: () => { localStorage.setItem('darkMode', 'false'); document.documentElement.classList.remove('dark'); window.dispatchEvent(new CustomEvent('toast', { detail: { message: '☀️ Mode Terang diaktifkan', type: 'info' } })); } },
                { id: 'act-theme-midnight', title: 'Tema Warna: Midnight OLED', subtitle: 'Tema hitam pekat aksen ungu neon', icon: '🌌', badge: 'Tema', action: () => { document.documentElement.setAttribute('data-theme', 'midnight'); localStorage.setItem('atoma_theme', 'midnight'); } },
                { id: 'act-theme-cyberpunk', title: 'Tema Warna: Cyberpunk Cyan', subtitle: 'Tema neon cyan & elektrik', icon: '⚡', badge: 'Tema', action: () => { document.documentElement.setAttribute('data-theme', 'cyberpunk'); localStorage.setItem('atoma_theme', 'cyberpunk'); } },
                { id: 'act-theme-lofi', title: 'Tema Warna: Lofi Warm Amber', subtitle: 'Tema hangat kayu dan kopi', icon: '☕', badge: 'Tema', action: () => { document.documentElement.setAttribute('data-theme', 'lofi'); localStorage.setItem('atoma_theme', 'lofi'); } },
                { id: 'act-theme-emerald', title: 'Tema Warna: Emerald Forest', subtitle: 'Tema tenang hijau zamrud', icon: '🌲', badge: 'Tema', action: () => { document.documentElement.setAttribute('data-theme', 'emerald'); localStorage.setItem('atoma_theme', 'emerald'); } },
                @if(auth()->check() && (auth()->user()->is_admin || auth()->user()->id === 1))
                { id: 'act-admin-dash', title: 'Admin Command Center', subtitle: 'Pusat dashboard kendali dan pemeliharaan admin', icon: '👑', badge: 'Admin', url: '/admin' },
                { id: 'act-admin-logs', title: 'Admin System Logs & Audit', subtitle: 'Pantau log sistem, bug crash, dan jejak keamanan', icon: '📋', badge: 'Admin', url: '/admin/system-logs' },
                { id: 'act-admin-ai', title: 'Admin AI Telemetry & Tokens', subtitle: 'Observabilitas model Gemini AI dan biaya token', icon: '🛡️', badge: 'Admin', url: '/admin/ai-usage' },
                { id: 'act-admin-feedbacks', title: 'Admin Kotak Surat Pengguna', subtitle: 'Lihat saran dan feedback dari pengguna ATOMA', icon: '📬', badge: 'Admin', url: '/admin/feedbacks' },
                @endif
            ],

            open() {
                this.isOpen = true;
                this.query = '';
                this.selectedIndex = 0;
                this.$nextTick(() => {
                    this.$refs.searchInput?.focus();
                });
                this.performSearch();
            },

            close() {
                this.isOpen = false;
            },

            get flattenedItems() {
                const q = this.query.trim().toLowerCase();
                
                // Filter static actions
                const filteredActions = q 
                    ? this.staticActions.filter(a => a.title.toLowerCase().includes(q) || a.subtitle.toLowerCase().includes(q) || (a.badge && a.badge.toLowerCase().includes(q)))
                    : this.staticActions;

                const taskItems = (this.remoteResults.tasks || []).map(t => ({
                    id: 'task-' + t.id,
                    title: t.title,
                    subtitle: t.subtitle,
                    badge: t.badge || 'Tugas',
                    icon: t.is_completed ? '✅' : '🎯',
                    action: () => {
                        if (window.location.pathname === '/tasks') {
                            window.dispatchEvent(new CustomEvent('toast', { detail: { message: `🎯 Tugas: ${t.title}`, type: 'info' } }));
                        } else {
                            if (window.Livewire) window.Livewire.navigate('/tasks');
                            else window.location.href = '/tasks';
                        }
                    }
                }));

                const flashcardItems = (this.remoteResults.flashcards || []).map(f => ({
                    id: 'card-' + f.id,
                    title: f.title,
                    subtitle: f.subtitle,
                    badge: f.badge,
                    icon: '🗂️',
                    url: '/flashcards'
                }));

                const journalItems = (this.remoteResults.journals || []).map(j => ({
                    id: 'journal-' + j.id,
                    title: j.title,
                    subtitle: j.subtitle,
                    badge: j.badge,
                    icon: '📖',
                    url: '/journal'
                }));

                return [...taskItems, ...flashcardItems, ...journalItems, ...filteredActions];
            },

            async performSearch() {
                const q = this.query.trim();
                if (q.length < 1) {
                    this.remoteResults = { tasks: [], flashcards: [], journals: [] };
                    return;
                }

                this.isLoading = true;
                try {
                    const res = await fetch(`/api/command-palette/search?q=${encodeURIComponent(q)}`);
                    if (res.ok) {
                        const data = await res.json();
                        this.remoteResults = data;
                    }
                } catch (e) {
                    // Fail gracefully
                } finally {
                    this.isLoading = false;
                    this.selectedIndex = 0;
                }
            },

            selectNext() {
                const items = this.flattenedItems;
                if (items.length === 0) return;
                this.selectedIndex = (this.selectedIndex + 1) % items.length;
            },

            selectPrev() {
                const items = this.flattenedItems;
                if (items.length === 0) return;
                this.selectedIndex = (this.selectedIndex - 1 + items.length) % items.length;
            },

            executeSelected() {
                const items = this.flattenedItems;
                if (items.length === 0 || !items[this.selectedIndex]) return;
                this.executeItem(items[this.selectedIndex]);
            },

            executeItem(item) {
                this.close();
                if (typeof item.action === 'function') {
                    item.action();
                } else if (item.url) {
                    if (window.Livewire) {
                        window.Livewire.navigate(item.url);
                    } else {
                        window.location.href = item.url;
                    }
                }
            }
        };
    }
</script>
