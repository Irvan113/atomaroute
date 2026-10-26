<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public $unreadCount = 0;
    public $totalCount = 0;
    public $isOpen = false;
    public $filter = 'all'; // 'all' or 'unread'
    public $align = 'right'; // 'right' or 'left'

    public function mount($align = 'right')
    {
        $this->align = $align;
        $this->refreshCounts();
    }

    #[On('notifications-updated')]
    #[On('social-notifications-cleared')]
    public function refreshCounts()
    {
        $user = auth()->user();
        if ($user) {
            $this->unreadCount = $user->unreadNotifications()->count();
            $this->totalCount = $user->notifications()->count();
        }
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    public function toggle()
    {
        $this->isOpen = !$this->isOpen;
        if ($this->isOpen) {
            $this->refreshCounts();
        }
    }

    public function markAllAsRead()
    {
        $user = auth()->user();
        if ($user && $this->unreadCount > 0) {
            $user->unreadNotifications->markAsRead();
            $this->unreadCount = 0;
            $this->dispatch('toast', message: 'Semua notifikasi ditandai sudah dibaca ✨', type: 'success');
        }
    }

    public function markSingleAsRead($notificationId)
    {
        $user = auth()->user();
        if ($user) {
            $notification = $user->notifications()->find($notificationId);
            if ($notification) {
                $notification->markAsRead();
                $this->refreshCounts();
            }
        }
    }

    public function deleteNotification($notificationId)
    {
        $user = auth()->user();
        if ($user) {
            $notification = $user->notifications()->find($notificationId);
            if ($notification) {
                $notification->delete();
                $this->refreshCounts();
            }
        }
    }

    public function clearAll()
    {
        $user = auth()->user();
        if ($user && $this->totalCount > 0) {
            $user->notifications()->delete();
            $this->refreshCounts();
            $this->dispatch('toast', message: 'Semua notifikasi berhasil dibersihkan', type: 'info');
        }
    }
    
    public function close()
    {
        $this->isOpen = false;
    }

    public function with()
    {
        $user = auth()->user();
        $notifications = collect();
        if ($user) {
            $query = $this->filter === 'unread' ? $user->unreadNotifications() : $user->notifications();
            $notifications = $query->limit(20)->get();
        }

        return [
            'notificationsList' => $notifications,
        ];
    }
}; ?>

<div class="relative" x-data="{ open: @entangle('isOpen') }" @click.away="$wire.close()">
    <!-- Mobile Backdrop Overlay -->
    <div x-show="open" 
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/35 backdrop-blur-xs z-40 sm:hidden" 
         @click="$wire.close()"
         style="display: none;">
    </div>

    <!-- Bell Trigger Button -->
    <button type="button" 
            @click="$wire.toggle()" 
            class="w-8 h-8 rounded-xl bg-gray-100/90 hover:bg-indigo-50/80 dark:bg-gray-700/60 dark:hover:bg-indigo-950/50 border border-gray-200/80 dark:border-gray-600/60 flex items-center justify-center text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition-all shadow-sm cursor-pointer select-none active:scale-95 relative group shrink-0"
            title="Pemberitahuan & Notifikasi">
        <svg class="w-4.5 h-4.5 transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        
        <!-- Animated Unread Badge -->
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex items-center justify-center min-w-4 h-4 px-1 text-[9px] font-black text-white bg-gradient-to-r from-red-500 to-rose-600 border-2 border-white dark:border-gray-900 rounded-full shadow-sm animate-pulse">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown Panel (Responsive: Native Card on Mobile, Smart Adaptive Popover on Desktop) -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2 sm:translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-2 sm:translate-y-2 scale-95"
         class="fixed inset-x-3 top-14 z-50 sm:z-50 sm:absolute sm:inset-auto sm:top-auto sm:mt-2.5 sm:w-96 {{ $align === 'left' ? 'sm:left-0 sm:right-auto' : 'sm:right-0 sm:left-auto' }} bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col max-h-[78vh] sm:max-h-[82vh]"
         style="display: none;">
        
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-900/50 shadow-xs shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-black text-gray-900 dark:text-white leading-none">Notifikasi</h3>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mt-0.5">
                        @if($unreadCount > 0)
                            <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ $unreadCount }} belum dibaca</span>
                        @else
                            Semua sudah terbaca ✨
                        @endif
                    </p>
                </div>
            </div>

            <!-- Global Actions & Close Button -->
            <div class="flex items-center gap-1">
                @if($unreadCount > 0)
                    <button type="button" 
                            wire:click="markAllAsRead" 
                            class="px-2.5 py-1 rounded-xl text-[11px] font-black text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors cursor-pointer flex items-center gap-1"
                            title="Tandai semua sudah dibaca">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>Baca Semua</span>
                    </button>
                @endif
                
                @if($totalCount > 0)
                    <button type="button" 
                            wire:click="clearAll" 
                            class="w-7 h-7 rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 flex items-center justify-center transition-colors cursor-pointer"
                            title="Hapus semua notifikasi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                @endif

                <button type="button" 
                        @click="$wire.close()" 
                        class="w-7 h-7 rounded-xl text-gray-400 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-center transition-colors cursor-pointer"
                        title="Tutup Notifikasi">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Segmented Filter Control (Capsule Bar) -->
        <div class="p-1 mx-4 my-2.5 bg-gray-100 dark:bg-gray-700/60 rounded-2xl flex items-center gap-1 border border-gray-200/80 dark:border-gray-600/60 shrink-0">
            <button type="button" 
                    wire:click="setFilter('all')" 
                    class="flex-1 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5 {{ $filter === 'all' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm font-black' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200' }}">
                <span>Semua</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $filter === 'all' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-300 font-black' : 'bg-gray-200/80 dark:bg-gray-600 text-gray-600 dark:text-gray-300' }}">
                    {{ $totalCount }}
                </span>
            </button>
            <button type="button" 
                    wire:click="setFilter('unread')" 
                    class="flex-1 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5 {{ $filter === 'unread' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm font-black' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200' }}">
                <span>Belum Dibaca</span>
                @if($unreadCount > 0)
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-red-500 text-white font-black animate-pulse">
                        {{ $unreadCount }}
                    </span>
                @else
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-gray-200/80 dark:bg-gray-600 text-gray-600 dark:text-gray-300">
                        0
                    </span>
                @endif
            </button>
        </div>
        
        <!-- Notifications List -->
        <div class="overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/60 flex-1 hide-scrollbar bg-white dark:bg-gray-800">
            @forelse($notificationsList as $notification)
                @php
                    $isUnread = is_null($notification->read_at);
                    $icon = $notification->data['icon'] ?? '🔔';
                    $url = $notification->data['url'] ?? null;
                    $type = $notification->data['type'] ?? 'general';
                @endphp
                <div class="p-3.5 hover:bg-gray-50/90 dark:hover:bg-gray-750 transition-all relative group {{ $isUnread ? 'bg-indigo-50/50 dark:bg-indigo-950/30 border-l-4 border-indigo-500' : 'border-l-4 border-transparent' }}">
                    <div class="flex items-start space-x-3">
                        <!-- Notification Icon Bubble -->
                        <div class="w-9 h-9 rounded-2xl flex items-center justify-center text-lg shrink-0 mt-0.5 shadow-sm 
                            {{ $isUnread ? 'bg-white dark:bg-gray-700 border border-indigo-100 dark:border-indigo-800/60 ring-2 ring-indigo-500/10' : 'bg-gray-100 dark:bg-gray-700 text-gray-500' }}">
                            {{ $icon }}
                        </div>

                        <!-- Notification Content & Actions -->
                        <div class="flex-1 min-w-0 pr-1">
                            <div class="text-xs text-gray-800 dark:text-gray-200 leading-relaxed break-words font-medium">
                                {!! $notification->data['message'] ?? 'Pemberitahuan baru' !!}
                            </div>
                            
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="text-[10px] font-semibold text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $notification->created_at->diffForHumans() }}
                                </span>

                                @if($url)
                                    <a href="{{ $url }}" wire:navigate @click="$wire.markSingleAsRead('{{ $notification->id }}'); $wire.close();" class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-0.5">
                                        <span>Buka</span>
                                        <span>&rarr;</span>
                                    </a>
                                @endif
                            </div>
                        </div>

                        <!-- Touch-friendly Status & Action Buttons -->
                        <div class="shrink-0 flex items-center gap-1 self-center">
                            @if($isUnread)
                                <button type="button" 
                                        wire:click="markSingleAsRead('{{ $notification->id }}')" 
                                        class="w-7 h-7 rounded-xl text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100/70 dark:hover:bg-indigo-900/50 flex items-center justify-center transition-colors cursor-pointer"
                                        title="Tandai dibaca">
                                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 dark:bg-indigo-400 ring-4 ring-indigo-100 dark:ring-indigo-900/60"></span>
                                </button>
                            @endif

                            <button type="button" 
                                    wire:click="deleteNotification('{{ $notification->id }}')" 
                                    wire:confirm="{{ __('Hapus notifikasi ini?') }}"
                                    class="w-7 h-7 rounded-xl text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 flex items-center justify-center transition-colors cursor-pointer"
                                    title="Hapus pemberitahuan ini">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <!-- Empty State -->
                <div class="py-7 px-5 text-center flex flex-col items-center justify-center">
                    @if($filter === 'unread')
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-2.5 border border-emerald-100 dark:border-emerald-800/50 shadow-xs">
                            <svg class="w-6 h-6 text-emerald-500 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h4 class="text-sm font-black text-gray-900 dark:text-white">Semua Sudah Dibaca!</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-[210px] leading-relaxed">
                            Tidak ada notifikasi baru yang terlewat. Kamu selalu up to date! 🎉
                        </p>
                    @else
                        <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-700/60 text-gray-400 dark:text-gray-500 flex items-center justify-center mb-2.5 border border-gray-200/80 dark:border-gray-600 shadow-xs">
                            <svg class="w-6 h-6 text-gray-400 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        </div>
                        <h4 class="text-sm font-black text-gray-900 dark:text-white">Kotak Notifikasi Kosong</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-[220px] leading-relaxed">
                            Pemberitahuan duel dari teman, tugas harian, dan pencapaianmu akan muncul di sini.
                        </p>
                    @endif
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        @if($totalCount > 0)
            <div class="p-2.5 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-850 text-center shrink-0">
                <a href="{{ route('social') }}" wire:navigate @click="$wire.close()" class="text-[11px] font-black text-indigo-600 dark:text-indigo-400 hover:underline">
                    Lihat Aktivitas Teman di Sosial &rarr;
                </a>
            </div>
        @endif
    </div>
</div>
