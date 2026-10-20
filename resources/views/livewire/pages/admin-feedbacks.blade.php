<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\UserFeedback;
use App\Models\User;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public string $filterType = 'all';
    public string $filterStatus = 'all';
    public string $search = '';

    // Reply state
    public ?int $replyingFeedbackId = null;
    public string $replyMessage = '';

    public function mount()
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Auto-grant admin status to User ID 1 for testing & management ease
        if (!$user->is_admin && $user->id === 1) {
            $user->is_admin = true;
            $user->save();
        }

        if (!$user->is_admin) {
            abort(403, 'Akses Ditolak: Halaman ini khusus untuk Administrator.');
        }
    }

    public function getFeedbacksProperty()
    {
        $query = UserFeedback::with('user')->latest();

        if ($this->filterType !== 'all') {
            $query->where('type', $this->filterType);
        }

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if (!empty($this->search)) {
            $s = trim($this->search);
            $query->where(function($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('message', 'like', "%{$s}%")
                  ->orWhereHas('user', function($uq) use ($s) {
                      $uq->where('name', 'like', "%{$s}%")
                         ->orWhere('email', 'like', "%{$s}%");
                  });
            });
        }

        return $query->get();
    }

    public function getStatsProperty()
    {
        return [
            'total' => UserFeedback::count(),
            'pending' => UserFeedback::where('status', 'pending')->count(),
            'in_progress' => UserFeedback::where('status', 'in_progress')->count(),
            'resolved' => UserFeedback::where('status', 'resolved')->count(),
        ];
    }

    public function updateStatus(int $feedbackId, string $status): void
    {
        $feedback = UserFeedback::find($feedbackId);
        if ($feedback) {
            $feedback->status = $status;
            $feedback->save();
            $this->dispatch('toast', message: 'Status surat diperbarui ke: ' . $status, type: 'success');
        }
    }

    public function openReply(int $feedbackId): void
    {
        $feedback = UserFeedback::find($feedbackId);
        if ($feedback) {
            $this->replyingFeedbackId = $feedbackId;
            $this->replyMessage = $feedback->dev_reply ?? '';
        }
    }

    public function cancelReply(): void
    {
        $this->replyingFeedbackId = null;
        $this->replyMessage = '';
    }

    public function saveReply(): void
    {
        $this->validate([
            'replyMessage' => 'required|string|min:3|max:1500',
        ]);

        $feedback = UserFeedback::find($this->replyingFeedbackId);
        if ($feedback) {
            $feedback->dev_reply = trim($this->replyMessage);
            $feedback->dev_replied_at = now();
            if ($feedback->status === 'pending') {
                $feedback->status = 'reviewed';
            }
            $feedback->save();

            $this->replyingFeedbackId = null;
            $this->replyMessage = '';
            $this->dispatch('toast', message: 'Balasan developer berhasil dikirim ke pengguna!', type: 'success');
            $this->dispatch('play-sound', type: 'success');
        }
    }

    public function deleteFeedback(int $feedbackId): void
    {
        $feedback = UserFeedback::find($feedbackId);
        if ($feedback) {
            $feedback->delete();
            $this->dispatch('toast', message: 'Surat berhasil dihapus.', type: 'info');
        }
    }
}; ?>

<div class="max-w-6xl mx-auto space-y-6 pb-20">
    <!-- Unified Admin Navigation Bar -->
    <x-admin-navbar active="feedbacks" />

    <!-- Header Banner -->
    <div class="rounded-3xl bg-gradient-to-r from-[#1b3cb8] via-atoma-blue to-[#284fd6] text-white p-6 sm:p-8 shadow-xl relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-white/20 text-white border border-white/30 backdrop-blur-xs">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>{{ __('Admin Dev Mailbox') }}</span>
                    </span>
                    <a href="{{ route('admin.ai-usage') }}" wire:navigate class="text-xs text-white/80 hover:text-white underline">
                        Ke AI Usage Telemetry &rarr;
                    </a>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight flex items-center gap-2.5">
                    <span>{{ __('Kotak Surat & Masukan Pengguna') }}</span>
                    <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center border border-white/30 shrink-0">
                        <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                </h1>
                <p class="text-xs sm:text-sm text-white/80 mt-1 max-w-xl leading-relaxed">
                    Baca ide baru, laporan bug, kritik, dan apresiasi langsung dari para pengguna ATOMA. Kamu bisa membalas surat mereka langsung dari dashboard ini.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('dashboard') }}" wire:navigate class="px-4 py-2 rounded-2xl bg-white/15 hover:bg-white/25 text-white text-xs font-bold transition-all border border-white/20">
                    &larr; Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 font-bold">
                <span>{{ __('Total Surat') }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="text-2xl font-black text-gray-900 dark:text-white mt-1">{{ $this->stats['total'] }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs">
            <div class="flex items-center justify-between text-xs text-yellow-600 dark:text-yellow-400 font-bold">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Menunggu Ditinjau') }}
                </span>
            </div>
            <div class="text-2xl font-black text-yellow-600 dark:text-yellow-400 mt-1">{{ $this->stats['pending'] }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs">
            <div class="flex items-center justify-between text-xs text-purple-600 dark:text-purple-400 font-bold">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ __('Sedang Dikerjakan') }}
                </span>
            </div>
            <div class="text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">{{ $this->stats['in_progress'] }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs">
            <div class="flex items-center justify-between text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Selesai Diterapkan') }}
                </span>
            </div>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $this->stats['resolved'] }}</div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <!-- Search Bar -->
            <div class="relative flex-1">
                <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari berdasarkan subjek, pesan, atau nama user..." 
                       class="w-full text-xs sm:text-sm pl-10 pr-4 py-2.5 rounded-2xl border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/60 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-atoma-blue focus:ring-atoma-blue">
            </div>

            <!-- Filter Type -->
            <div class="flex items-center gap-2">
                <select wire:model.live="filterType" class="text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 py-2 pl-3 pr-8 focus:ring-atoma-blue">
                    <option value="all">Semua Jenis Pesan</option>
                    <option value="feature">Ide Fitur</option>
                    <option value="bug">Lapor Bug</option>
                    <option value="critique">Kritik & Masukan</option>
                    <option value="appreciation">Apresiasi</option>
                    <option value="donation">Konfirmasi Donasi ☕</option>
                </select>

                <!-- Filter Status -->
                <select wire:model.live="filterStatus" class="text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 py-2 pl-3 pr-8 focus:ring-atoma-blue">
                    <option value="all">Semua Status</option>
                    <option value="pending">Menunggu Ditinjau</option>
                    <option value="reviewed">Sudah Dibaca Dev</option>
                    <option value="in_progress">Sedang Dikerjakan</option>
                    <option value="resolved">Selesai / Diterapkan</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Letter Feedbacks List -->
    <div class="space-y-4">
        @forelse($this->feedbacks as $item)
            @php
                $typeMeta = $item->type_meta;
                $statusMeta = $item->status_meta;
            @endphp
            <div class="rounded-3xl bg-white dark:bg-gray-800 p-5 sm:p-6 border border-gray-200/80 dark:border-gray-700 shadow-xs space-y-4 transition-all">
                <!-- User & Meta Info Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-700/80 pb-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ $item->user->avatar_url }}" alt="{{ $item->user->name }}" class="w-10 h-10 rounded-full border border-atoma-aqua/40 bg-white object-cover shrink-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-black text-sm text-gray-900 dark:text-white">{{ $item->user->name }}</span>
                                <span class="text-[11px] text-gray-400">({{ $item->user->email }})</span>
                                @if($item->mood)
                                    @if($item->mood === 'happy')
                                        <span class="inline-flex text-emerald-500" title="Mood: Happy">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </span>
                                    @elseif($item->mood === 'love')
                                        <span class="inline-flex text-pink-500" title="Mood: Love / Excited">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
                                        </span>
                                    @elseif($item->mood === 'frustrated')
                                        <span class="inline-flex text-orange-500" title="Mood: Frustrated">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </span>
                                    @else
                                        <span class="inline-flex text-gray-400" title="Mood: Neutral">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14h6M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </span>
                                    @endif
                                @endif
                            </div>
                            <div class="text-[11px] text-gray-400 font-medium">
                                Dikirim pada {{ $item->created_at->format('d M Y, H:i') }} WIB ({{ $item->created_at->diffForHumans() }})
                            </div>
                        </div>
                    </div>

                    <!-- Type & Status Badges -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold {{ $typeMeta['badge_class'] }}">
                            @if($item->type === 'feature')
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            @elseif($item->type === 'bug')
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a4 4 0 00-4 4v2h8v-2a4 4 0 00-4-4zm-6 8h12m-12 0a6 6 0 006 6m6-6a6 6 0 01-6 6m0 0v2m-6-6H3m18 0h-3M4.929 7.929l2.121 2.121M19.071 7.929l-2.121 2.121M4.929 16.071l2.121-2.121M19.071 16.071l-2.121-2.121"/></svg>
                            @elseif($item->type === 'critique')
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            @else
                                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                            @endif
                            <span>{{ $typeMeta['label'] }}</span>
                        </span>
                        
                        <!-- Status Dropdown Quick Change -->
                        <div class="relative" x-data="{ openStatus: false }">
                            <button @click="openStatus = !openStatus" class="px-3 py-1 rounded-xl text-xs font-black {{ $statusMeta['class'] }} flex items-center gap-1.5 cursor-pointer">
                                @if($item->status === 'pending')
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($item->status === 'reviewed')
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                @elseif($item->status === 'in_progress')
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                @else
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                                <span>{{ $statusMeta['label'] }}</span>
                                <svg class="w-3 h-3 ml-0.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>

                            <div x-show="openStatus" @click.away="openStatus = false" x-cloak
                                 class="absolute right-0 mt-1 w-48 rounded-2xl bg-white dark:bg-gray-900 shadow-xl border border-gray-100 dark:border-gray-700 py-1.5 z-20 text-xs">
                                <button wire:click="updateStatus({{ $item->id }}, 'pending'); openStatus = false" class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2 text-yellow-600 font-bold transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Menunggu Ditinjau</span>
                                </button>
                                <button wire:click="updateStatus({{ $item->id }}, 'reviewed'); openStatus = false" class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2 text-blue-600 font-bold transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Sudah Dibaca Dev</span>
                                </button>
                                <button wire:click="updateStatus({{ $item->id }}, 'in_progress'); openStatus = false" class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2 text-purple-600 font-bold transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>Sedang Dikerjakan</span>
                                </button>
                                <button wire:click="updateStatus({{ $item->id }}, 'resolved'); openStatus = false" class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2 text-emerald-600 font-bold transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Selesai / Diterapkan</span>
                                </button>
                            </div>
                        </div>

                        <!-- Delete Button -->
                        <button wire:click="deleteFeedback({{ $item->id }})" wire:confirm="Hapus surat ini dari database?" class="p-1.5 rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Hapus Surat">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Letter Content -->
                <div class="space-y-2">
                    <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white">
                        {{ $item->subject }}
                    </h4>
                    <p class="text-xs sm:text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-normal whitespace-pre-line bg-gray-50 dark:bg-gray-900/60 p-4 rounded-2xl border border-gray-100 dark:border-gray-700/60">
                        {{ $item->message }}
                    </p>
                </div>

                <!-- Technical Device Info (If Provided) -->
                @if(!empty($item->device_info))
                    <div class="flex items-center gap-2 flex-wrap text-[10px] text-gray-500 dark:text-gray-400 pt-1">
                        <span class="font-bold text-gray-400">Context:</span>
                        @if(!empty($item->device_info['platform']))
                            <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700">OS: {{ $item->device_info['platform'] }}</span>
                        @endif
                        @if(!empty($item->device_info['url']))
                            <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700">Page: {{ $item->device_info['url'] }}</span>
                        @endif
                        @if(!empty($item->device_info['screenWidth']))
                            <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700">Screen: {{ $item->device_info['screenWidth'] }}x{{ $item->device_info['screenHeight'] ?? 0 }}</span>
                        @endif
                    </div>
                @endif

                <!-- Developer Reply Section -->
                <div class="pt-2">
                    @if($replyingFeedbackId === $item->id)
                        <!-- Inline Reply Form -->
                        <div class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-4 border border-indigo-200 dark:border-indigo-800 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black text-atoma-blue dark:text-atoma-cyan flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    <span>Tulis Balasan Developer ke {{ $item->user->name }}</span>
                                </span>
                                <button wire:click="cancelReply" class="text-xs text-gray-500 hover:text-gray-700">Batal</button>
                            </div>

                            <textarea wire:model="replyMessage" rows="3" placeholder="Tuliskan apresiasi, update perbaikan, atau jawaban developer..."
                                      class="w-full text-xs sm:text-sm rounded-xl border-indigo-200 dark:border-indigo-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 p-3 placeholder-gray-400 focus:ring-atoma-blue"></textarea>
                            @error('replyMessage') <span class="text-xs text-rose-500 block">{{ $message }}</span> @enderror

                            <div class="flex justify-end gap-2">
                                <button wire:click="cancelReply" class="px-3 py-1.5 text-xs font-bold text-gray-600 dark:text-gray-300">Batal</button>
                                <button wire:click="saveReply" class="px-4 py-2 rounded-xl text-xs font-black bg-atoma-blue text-white hover:bg-indigo-700 transition-all shadow-sm flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span>Kirim Balasan Resmi</span>
                                </button>
                            </div>
                        </div>
                    @else
                        @if($item->dev_reply)
                            <!-- Already Replied Card -->
                            <div class="rounded-2xl bg-atoma-ice/60 dark:bg-atoma-blue/15 p-3.5 border border-atoma-aqua/40 dark:border-atoma-blue/30 flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-black text-atoma-blue dark:text-atoma-cyan flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                            <span>Balasan Developer:</span>
                                        </span>
                                        @if($item->dev_replied_at)
                                            <span class="text-[10px] text-gray-400 font-medium">({{ $item->dev_replied_at->format('d M Y, H:i') }})</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-800 dark:text-gray-200 leading-relaxed font-medium">
                                        "{{ $item->dev_reply }}"
                                    </p>
                                </div>
                                <button wire:click="openReply({{ $item->id }})" class="text-xs text-atoma-blue dark:text-atoma-cyan font-bold hover:underline shrink-0">
                                    Edit Balasan
                                </button>
                            </div>
                        @else
                            <button wire:click="openReply({{ $item->id }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition-colors">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                <span>Balas Surat Ini</span>
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-3xl bg-white dark:bg-gray-800 p-12 text-center border border-gray-100 dark:border-gray-700 shadow-xs">
                <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700/60 rounded-3xl flex items-center justify-center mx-auto mb-3 text-gray-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h4 class="text-lg font-black text-gray-900 dark:text-white">Tidak Ada Surat Masuk</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                    Belum ada surat yang cocok dengan filter pencarian ini.
                </p>
            </div>
        @endforelse
    </div>
</div>
