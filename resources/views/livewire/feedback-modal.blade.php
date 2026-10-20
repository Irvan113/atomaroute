<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Models\UserFeedback;
use Carbon\Carbon;

new class extends Component {
    public bool $showModal = false;
    public string $activeTab = 'compose'; // 'compose', 'history', 'donate'
    public string $type = 'critique'; // feature, bug, critique, appreciation
    public string $subject = '';
    public string $message = '';
    public string $mood = 'happy'; // love, happy, neutral, frustrated
    public bool $includeDeviceInfo = true;
    public array $clientDeviceInfo = [];
    public bool $sentSuccess = false;

    #[On('open-feedback-modal')]
    public function openModal($tab = 'compose'): void
    {
        $target = is_array($tab) ? ($tab['tab'] ?? 'compose') : (string)$tab;
        $this->activeTab = in_array($target, ['compose', 'history', 'donate']) ? $target : 'compose';
        $this->showModal = true;
        $this->sentSuccess = false;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->sentSuccess = false;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setMood(string $mood): void
    {
        $this->mood = $mood;
    }

    public function prepareDonationLetter(): void
    {
        $this->activeTab = 'compose';
        $this->type = 'donation';
        $this->mood = 'love';
        $user = auth()->user();
        $userName = $user->name ?? 'Teman';
        $this->subject = __('Konfirmasi Donasi dari :name ☕', ['name' => $userName]);
        $this->message = "Halo Developer ATOMA (Irvan & Gemini),\n\nSaya baru saja mengirimkan donasi untuk mendukung pengembangan ATOMA:\n- Jalur Donasi: [Saweria / Trakteer / DANA / GoPay / ShopeePay]\n- Nama Pengirim / Akun: " . $userName . "\n- Nominal / Catatan: [Tulis nominal atau pesan hangatmu di sini]\n\nTerima kasih telah membangun dan merawat aplikasi yang luar biasa ini!";
    }

    #[Computed]
    public function myFeedbacks()
    {
        $user = auth()->user();
        if (!$user) return collect();
        return $user->feedbacks()->latest()->get();
    }

    public function sendFeedback(): void
    {
        $this->validate([
            'subject' => 'required|string|min:3|max:150',
            'message' => 'required|string|min:10|max:3000',
            'type' => 'required|in:feature,bug,critique,appreciation,donation',
            'mood' => 'nullable|in:love,happy,neutral,frustrated',
        ], [
            'subject.required' => __('Mohon isi subjek atau judul pesan singkat.'),
            'subject.min' => __('Subjek minimal 3 karakter.'),
            'message.required' => __('Mohon tuliskan isi surat / pesan untuk developer.'),
            'message.min' => __('Pesan minimal 10 karakter agar developer memahami konteks.'),
        ]);

        $user = auth()->user();
        if (!$user) return;

        $deviceInfo = $this->includeDeviceInfo ? $this->clientDeviceInfo : [];

        UserFeedback::create([
            'user_id' => $user->id,
            'type' => $this->type,
            'subject' => trim($this->subject),
            'message' => trim($this->message),
            'mood' => $this->mood,
            'device_info' => $deviceInfo,
            'status' => 'pending',
        ]);

        $this->subject = '';
        $this->message = '';
        $this->sentSuccess = true;

        $this->dispatch('toast', message: __('Suratmu sudah mendarat di meja developer! Terima kasih telah membantu ATOMA tumbuh lebih baik ✨'), type: 'success');
        $this->dispatch('play-sound', type: 'success');
    }
}; ?>

<div>
    <!-- The Modal Dialog -->
    <div x-data="{
            show: @entangle('showModal'),
            captureDeviceInfo() {
                try {
                    $wire.clientDeviceInfo = {
                        userAgent: navigator.userAgent,
                        platform: navigator.platform || 'Unknown',
                        language: navigator.language || 'id',
                        screenWidth: window.innerWidth,
                        screenHeight: window.innerHeight,
                        url: window.location.pathname,
                    };
                } catch (e) {}
            }
         }"
         x-init="captureDeviceInfo()"
         x-show="show"
         x-cloak
         @keydown.escape.window="show = false"
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="feedback-modal-title" role="dialog" aria-modal="true">

        <!-- Dark Backdrop -->
        <div x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm transition-opacity"
             @click="show = false"></div>

        <!-- Modal Container -->
        <div class="flex min-h-full items-center justify-center p-3 sm:p-4 text-center">
            <div x-show="show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all w-full max-w-xl border border-gray-100 dark:border-gray-700 flex flex-col max-h-[90vh]">

                <!-- Header: Postal & Stationery Theme with Signature ATOMA Gradient -->
                <div class="relative bg-gradient-to-r from-[#1b3cb8] via-atoma-blue to-[#284fd6] text-white p-5 sm:p-6 overflow-hidden shrink-0">
                    <!-- Subtle Postmark stamp background decoration -->
                    <div class="absolute -right-6 -bottom-6 w-32 h-32 border-4 border-white/10 rounded-full flex items-center justify-center pointer-events-none rotate-12">
                        <div class="text-[10px] uppercase tracking-widest text-white/20 font-black text-center">ATOMA • DEV MAIL • POST</div>
                    </div>

                    <div class="flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center shadow-inner border border-white/20 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-xl font-black tracking-tight" id="feedback-modal-title">
                                    {{ __('Surat & Masukan ke Dev') }}
                                </h3>
                                <p class="text-xs text-white/80 font-medium mt-0.5">
                                    @if($activeTab === 'compose')
                                        {{ __('Kirim kritik, saran, ide, atau lapor kendala langsung ke developer') }}
                                    @elseif($activeTab === 'history')
                                        {{ __('Pantau status dan balasan langsung dari developer') }}
                                    @elseif($activeTab === 'donate')
                                        {{ __('Dukung keberlangsungan server & pengembangan ATOMA') }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <button @click="show = false" type="button" 
                                class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white/80 hover:text-white transition-colors cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <!-- Navigation Sub-tabs -->
                    <div class="flex items-center gap-1.5 sm:gap-2 mt-4 pt-3 border-t border-white/15 text-xs font-bold relative z-10 overflow-x-auto no-scrollbar pb-0.5">
                        <button wire:click="$set('activeTab', 'compose')" 
                                class="px-3 py-1.5 rounded-xl transition-all cursor-pointer shrink-0 flex items-center gap-1.5 {{ $activeTab === 'compose' ? 'bg-white text-atoma-blue shadow-sm' : 'text-white/80 hover:text-white hover:bg-white/10' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            <span>{{ __('Tulis Surat') }}</span>
                        </button>
                        <button wire:click="$set('activeTab', 'history')" 
                                class="px-3 py-1.5 rounded-xl transition-all cursor-pointer shrink-0 flex items-center gap-1.5 {{ $activeTab === 'history' ? 'bg-white text-atoma-blue shadow-sm' : 'text-white/80 hover:text-white hover:bg-white/10' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                            <span>{{ __('Riwayat') }}</span>
                            @php
                                $feedbacksCount = count($this->myFeedbacks);
                            @endphp
                            @if($feedbacksCount > 0)
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'history' ? 'bg-atoma-blue text-white' : 'bg-white/20 text-white' }}">
                                    {{ $feedbacksCount }}
                                </span>
                            @endif
                        </button>
                        <button wire:click="$set('activeTab', 'donate')" 
                                class="px-3 py-1.5 rounded-xl transition-all cursor-pointer shrink-0 flex items-center gap-1.5 {{ $activeTab === 'donate' ? 'bg-amber-400 text-gray-900 shadow-sm font-black' : 'bg-amber-400/20 text-amber-200 hover:bg-amber-400/30' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>{{ __('Donasi ☕') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Body Area (Scrollable) -->
                <div class="p-5 sm:p-6 overflow-y-auto flex-1 space-y-5">

                    <!-- TAB 1: COMPOSE (Tulis Surat) -->
                    @if($activeTab === 'compose')
                        @if($sentSuccess)
                            <!-- Success State -->
                            <div class="text-center py-8 px-4 animate-fade-in">
                                <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-3 border border-emerald-200 dark:border-emerald-700/50 shadow-sm animate-bounce">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <h4 class="text-xl font-black text-gray-900 dark:text-white mb-1">{{ __('Surat Terkirim!') }}</h4>
                                <p class="text-xs text-gray-600 dark:text-gray-300 max-w-sm mx-auto leading-relaxed">
                                    {{ __('Pesanmu telah kami simpan. Developer membaca setiap kritik & saran untuk terus menyempurnakan ATOMA.') }}
                                </p>

                                <div class="flex justify-center gap-3 mt-6">
                                    <button wire:click="$set('sentSuccess', false)" class="px-4 py-2 text-xs font-bold rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 transition-colors cursor-pointer">
                                        {{ __('Tulis Surat Lain') }}
                                    </button>
                                    <button wire:click="$set('activeTab', 'history')" class="px-4 py-2 text-xs font-bold rounded-xl bg-atoma-blue text-white hover:bg-indigo-700 shadow-md transition-all cursor-pointer flex items-center gap-1.5">
                                        {{ __('Lihat Riwayat Surat') }}
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- Type Selector Pills -->
                            <div>
                                <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                    {{ __('Pilih Jenis Pesan') }}
                                </label>
                                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                                    @foreach(\App\Models\UserFeedback::TYPES as $k => $info)
                                        <button type="button" 
                                                wire:click="setType('{{ $k }}')"
                                                class="p-2.5 rounded-2xl border text-left transition-all cursor-pointer flex flex-col justify-between {{ $type === $k ? 'border-atoma-blue bg-atoma-ice/60 dark:bg-atoma-blue/20 ring-2 ring-atoma-blue/30 shadow-xs' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/40' }}">
                                            <div class="w-8 h-8 rounded-xl flex items-center justify-center mb-2 {{ $type === $k ? 'bg-atoma-blue text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                                                @if($k === 'feature')
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                                @elseif($k === 'bug')
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a4 4 0 00-4 4v2h8v-2a4 4 0 00-4-4zm-6 8h12m-12 0a6 6 0 006 6m6-6a6 6 0 01-6 6m0 0v2m-6-6H3m18 0h-3M4.929 7.929l2.121 2.121M19.071 7.929l-2.121 2.121M4.929 16.071l2.121-2.121M19.071 16.071l-2.121-2.121"/></svg>
                                                @elseif($k === 'critique')
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                                @elseif($k === 'donation')
                                                    <span class="text-sm">☕</span>
                                                @else
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                                @endif
                                            </div>
                                            <span class="text-[11px] sm:text-xs font-black truncate {{ $type === $k ? 'text-atoma-blue dark:text-atoma-cyan' : 'text-gray-700 dark:text-gray-300' }}">
                                                {{ __($info['label']) }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Subject Input -->
                            <div>
                                <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                    {{ __('Subjek / Topik Surat') }}
                                </label>
                                <input type="text" 
                                       wire:model="subject" 
                                       placeholder="{{ __('Contoh: Ide fitur mode timer Spotify, saran font di dark mode...') }}" 
                                       class="w-full text-sm rounded-2xl border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/60 p-3 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-atoma-blue focus:ring-atoma-blue transition-colors">
                                @error('subject') <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Stationery Message Area (Kertas Surat Hangat) -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                        {{ __('Isi Surat & Ceritamu') }}
                                    </label>
                                    <span class="text-[10px] text-gray-400 font-medium">
                                        {{ strlen($message) }}/3000 {{ __('karakter') }}
                                    </span>
                                </div>

                                <div class="relative rounded-2xl border border-gray-200 dark:border-gray-700 bg-amber-50/20 dark:bg-gray-900/80 p-3 shadow-inner">
                                    <textarea wire:model="message" 
                                              rows="5"
                                              placeholder="{{ __('Hai Developer ATOMA, saya ingin menyampaikan pendapat tentang...') }}" 
                                              class="w-full text-sm bg-transparent border-none text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-0 resize-none leading-relaxed font-normal"></textarea>
                                </div>
                                @error('message') <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Mood Rating Picker -->
                            <div>
                                <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                    {{ __('Bagaimana Perasaanmu dengan ATOMA Saat Ini?') }}
                                </label>
                                <div class="flex items-center justify-between gap-2 p-2.5 rounded-2xl bg-gray-50 dark:bg-gray-700/40 border border-gray-100 dark:border-gray-700">
                                    <button type="button" wire:click="setMood('love')"
                                            class="flex-1 py-2 rounded-xl text-center transition-all cursor-pointer flex flex-col items-center gap-1 {{ $mood === 'love' ? 'bg-white dark:bg-gray-800 shadow-sm scale-105 border border-pink-200 dark:border-pink-800 text-pink-600 dark:text-pink-400' : 'opacity-60 hover:opacity-100 text-gray-600 dark:text-gray-400' }}">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke-width="1.8"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 13.5c1 1.8 2.5 2.5 4 2.5s3-.7 4-2.5M7.5 9.5l1.5-1 1.5 1M13.5 9.5l1.5-1 1.5 1"/>
                                        </svg>
                                        <span class="text-[10px] font-bold">{{ __('Antusias') }}</span>
                                    </button>
                                    <button type="button" wire:click="setMood('happy')"
                                            class="flex-1 py-2 rounded-xl text-center transition-all cursor-pointer flex flex-col items-center gap-1 {{ $mood === 'happy' ? 'bg-white dark:bg-gray-800 shadow-sm scale-105 border border-emerald-200 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400' : 'opacity-60 hover:opacity-100 text-gray-600 dark:text-gray-400' }}">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke-width="1.8"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14s1.5 2 4 2 4-2 4-2M9 9.5h.01M15 9.5h.01"/>
                                        </svg>
                                        <span class="text-[10px] font-bold">{{ __('Puas') }}</span>
                                    </button>
                                    <button type="button" wire:click="setMood('neutral')"
                                            class="flex-1 py-2 rounded-xl text-center transition-all cursor-pointer flex flex-col items-center gap-1 {{ $mood === 'neutral' ? 'bg-white dark:bg-gray-800 shadow-sm scale-105 border border-amber-200 dark:border-amber-800 text-amber-600 dark:text-amber-400' : 'opacity-60 hover:opacity-100 text-gray-600 dark:text-gray-400' }}">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke-width="1.8"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 15h6M9 9.5h.01M15 9.5h.01"/>
                                        </svg>
                                        <span class="text-[10px] font-bold">{{ __('Biasa Aja') }}</span>
                                    </button>
                                    <button type="button" wire:click="setMood('frustrated')"
                                            class="flex-1 py-2 rounded-xl text-center transition-all cursor-pointer flex flex-col items-center gap-1 {{ $mood === 'frustrated' ? 'bg-white dark:bg-gray-800 shadow-sm scale-105 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400' : 'opacity-60 hover:opacity-100 text-gray-600 dark:text-gray-400' }}">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke-width="1.8"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 16s1.5-2 4-2 4 2 4 2M8.5 9l2 1M15.5 9l-2 1"/>
                                        </svg>
                                        <span class="text-[10px] font-bold">{{ __('Ada Kendala') }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Tech Context Auto-inclusion -->
                            <div class="flex items-center gap-2 pt-1 text-xs text-gray-500 dark:text-gray-400">
                                <input type="checkbox" id="include-device-info" wire:model="includeDeviceInfo"
                                       class="rounded-md border-gray-300 text-atoma-blue focus:ring-atoma-blue h-4 w-4">
                                <label for="include-device-info" class="cursor-pointer font-medium">
                                    {{ __('Sertakan informasi browser & perangkat untuk membantu dev menelusuri kendala') }}
                                </label>
                            </div>

                            <!-- Quick Callout to Support Developer -->
                            <div class="p-3 rounded-2xl bg-gradient-to-r from-amber-50 to-indigo-50/60 dark:from-gray-750 dark:to-indigo-950/30 border border-amber-200/60 dark:border-gray-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-amber-500/15 dark:bg-amber-500/25 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm shrink-0">
                                        ☕
                                    </div>
                                    <div>
                                        <div class="text-xs font-black text-gray-900 dark:text-white">{{ __('Dukung Developer ATOMA') }}</div>
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('Traktir kopi untuk biaya operasional server & pengembangan ATOMA.') }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 w-full sm:w-auto shrink-0">
                                    <button type="button" wire:click="$set('activeTab', 'donate')" class="w-full sm:w-auto px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white text-xs font-black shadow-xs transition-all cursor-pointer flex items-center justify-center gap-1">
                                        <span>{{ __('Donasi') }}</span> <span>☕</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-1">
                                <button type="button" 
                                        wire:click="sendFeedback" 
                                        wire:loading.attr="disabled"
                                        class="w-full bg-gradient-to-r from-atoma-blue to-[#284fd6] hover:from-[#284fd6] hover:to-atoma-blue text-white font-black py-3.5 px-4 rounded-2xl shadow-lg shadow-atoma-blue/30 transition-all transform active:scale-95 cursor-pointer flex items-center justify-center gap-2">
                                    <span wire:loading.remove class="flex items-center gap-2">
                                        {{ __('Kirim Surat ke Developer') }}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    </span>
                                    <span wire:loading class="flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        {{ __('Mengirim surat...') }}
                                    </span>
                                </button>
                            </div>
                        @endif

                    <!-- TAB 2: HISTORY (Riwayat Surat) -->
                    @elseif($activeTab === 'history')
                        <div class="space-y-4">
                            @forelse($this->myFeedbacks as $item)
                                @php
                                    $typeMeta = $item->type_meta;
                                    $statusMeta = $item->status_meta;
                                @endphp
                                <div class="rounded-2xl p-4 border transition-all hover:shadow-sm {{ $item->type === 'donation' ? 'bg-gradient-to-br from-amber-50/40 via-emerald-50/30 to-indigo-50/30 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-700/60 ring-1 ring-emerald-400/30' : 'bg-gray-50 dark:bg-gray-700/40 border-gray-100 dark:border-gray-700' }}">
                                    <!-- Card Header -->
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold flex items-center gap-1.5 {{ $typeMeta['badge_class'] }}">
                                                @if($item->type === 'feature')
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                                @elseif($item->type === 'bug')
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a4 4 0 00-4 4v2h8v-2a4 4 0 00-4-4zm-6 8h12m-12 0a6 6 0 006 6m6-6a6 6 0 01-6 6m0 0v2m-6-6H3m18 0h-3M4.929 7.929l2.121 2.121M19.071 7.929l-2.121 2.121M4.929 16.071l2.121-2.121M19.071 16.071l-2.121-2.121"/></svg>
                                                @elseif($item->type === 'critique')
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                                @elseif($item->type === 'donation')
                                                    <span class="text-xs">☕</span>
                                                @else
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                                @endif
                                                <span>{{ __($typeMeta['label']) }}</span>
                                            </span>
                                            <span class="text-[11px] text-gray-400 font-medium">
                                                {{ $item->created_at->format('d M Y, H:i') }}
                                            </span>
                                        </div>

                                        <!-- Status Badge -->
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-black {{ $statusMeta['class'] }}">
                                            @if($item->status === 'pending')
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @elseif($item->status === 'reviewed')
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            @elseif($item->status === 'in_progress')
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            @else
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @endif
                                            <span>{{ __($statusMeta['label']) }}</span>
                                        </span>
                                    </div>

                                    <!-- Subject & Message -->
                                    <h5 class="text-sm font-black text-gray-900 dark:text-white mb-1">
                                        {{ $item->subject }}
                                    </h5>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed font-normal whitespace-pre-line">
                                        {{ $item->message }}
                                    </p>

                                    <!-- Developer Reply Box (If Replied) -->
                                    @if($item->dev_reply)
                                        <div class="mt-3 pt-3 border-t {{ $item->type === 'donation' ? 'border-emerald-200/70 dark:border-emerald-800/50' : 'border-gray-200/70 dark:border-gray-600/60' }}">
                                            <div class="rounded-xl {{ $item->type === 'donation' ? 'bg-gradient-to-r from-emerald-500/15 via-amber-500/10 to-indigo-500/10 dark:from-emerald-950/40 dark:via-amber-950/30 dark:to-indigo-950/30 border border-emerald-300/70 dark:border-emerald-700/60 shadow-xs' : 'bg-atoma-ice/70 dark:bg-atoma-blue/15 border border-atoma-aqua/40 dark:border-atoma-blue/30' }} p-3.5">
                                                <div class="flex items-center justify-between mb-1.5">
                                                    <span class="text-[11px] font-black {{ $item->type === 'donation' ? 'text-emerald-700 dark:text-emerald-300' : 'text-atoma-blue dark:text-atoma-cyan' }} flex items-center gap-1.5">
                                                        @if($item->type === 'donation')
                                                            <span>💌</span>
                                                            <span>{{ __('Ucapan Terima Kasih dari Developer') }}</span>
                                                        @else
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                            <span>{{ __('Balasan Developer') }}</span>
                                                        @endif
                                                    </span>
                                                    @if($item->dev_replied_at)
                                                        <span class="text-[10px] text-gray-400 font-medium">
                                                            {{ $item->dev_replied_at->diffForHumans() }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-800 dark:text-gray-100 font-medium leading-relaxed">
                                                    "{{ $item->dev_reply }}"
                                                </p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-center py-10">
                                    <div class="w-14 h-14 bg-gray-100 dark:bg-gray-700/60 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400">
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    </div>
                                    <h5 class="text-sm font-black text-gray-900 dark:text-white">{{ __('Kotak Surat Masih Kosong') }}</h5>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-xs mx-auto">
                                        {{ __('Belum ada surat yang dikirim. Tulis surat pertamamu untuk menyampaikan ide atau masukan!') }}
                                    </p>
                                    <button wire:click="$set('activeTab', 'compose')" class="mt-4 px-4 py-2 rounded-xl text-xs font-bold bg-atoma-blue text-white hover:bg-indigo-700 transition-all shadow-sm cursor-pointer flex items-center gap-1.5 mx-auto">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        {{ __('Tulis Surat Sekarang') }}
                                    </button>
                                </div>
                            @endforelse
                        </div>

                    <!-- TAB 3: TEMPAT DONASI (Support Developer) -->
                    @elseif($activeTab === 'donate')
                        <div class="space-y-4 animate-fade-in">
                            <!-- Hero Donation Banner -->
                            <div class="relative rounded-3xl bg-gradient-to-br from-amber-500/15 via-orange-500/10 to-indigo-500/15 dark:from-amber-950/40 dark:via-orange-950/30 dark:to-indigo-950/40 p-5 sm:p-6 border border-amber-300/60 dark:border-amber-700/50 shadow-2xs overflow-hidden">
                                <div class="absolute -right-6 -bottom-6 w-32 h-32 rounded-full bg-amber-400/20 blur-xl pointer-events-none"></div>

                                <div class="relative z-10 flex flex-col sm:flex-row items-center sm:items-start gap-4 text-center sm:text-left">
                                    <div class="w-16 h-16 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-3xl shadow-lg shadow-amber-500/30 shrink-0">
                                        ☕
                                    </div>
                                    <div>
                                        <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white">
                                            {{ __('Dukung Pengembangan ATOMA') }}
                                        </h4>
                                        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed mt-1">
                                            {{ __('ATOMA dibangun secara independen dan 100% bebas biaya untuk semua orang. Donasimu sangat berharga untuk membiayai sewa server cloud, database, kuota API AI, serta secangkir kopi agar developer tetap bersemangat berinovasi setiap hari.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Pilihan Jalur Donasi: Saweria -->
                            <div>
                                <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2.5">
                                    {{ __('Jalur Donasi Resmi') }}
                                </label>

                                <!-- Saweria Card (QRIS & Semua E-Wallet) -->
                                <a href="https://saweria.co/HazamaLugh" target="_blank" rel="noopener noreferrer"
                                   class="p-5 rounded-3xl bg-gradient-to-br from-orange-500/10 via-amber-500/10 to-yellow-500/5 dark:bg-gray-750/70 border-2 border-orange-300 dark:border-orange-500/50 hover:border-orange-500 dark:hover:border-orange-400 transition-all group flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm hover:shadow-md hover:scale-[1.01] active:scale-99">
                                    <div class="flex items-start sm:items-center gap-3.5">
                                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center text-2xl shadow-md shadow-orange-500/25 shrink-0">
                                            ⚡
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                                <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-black bg-orange-100 dark:bg-orange-950/70 text-orange-700 dark:text-orange-300 border border-orange-300/70">
                                                    {{ __('QRIS & Semua E-Wallet') }}
                                                </span>
                                                <span class="text-xs font-black text-orange-600 dark:text-orange-400">
                                                    Saweria (HazamaLugh)
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed font-medium">
                                                {{ __('Scan QRIS instan via GoPay, OVO, DANA, ShopeePay, LinkAja, atau m-Banking (BCA, Mandiri, BRI, dll) mulai Rp 5.000.') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="w-full sm:w-auto shrink-0 flex items-center justify-center sm:justify-end">
                                        <div class="w-full sm:w-auto px-4 py-2.5 rounded-2xl bg-gradient-to-r from-orange-500 to-amber-500 group-hover:from-orange-600 group-hover:to-amber-600 text-white font-black text-xs shadow-md shadow-orange-500/30 flex items-center justify-center gap-2 transition-all">
                                            <span>{{ __('Buka Saweria') }}</span>
                                            <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Interactive Confirmation Banner -->
                            <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-amber-500/10 to-indigo-500/10 dark:from-emerald-950/30 dark:via-amber-950/30 dark:to-indigo-950/30 border border-emerald-200/80 dark:border-emerald-800/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div class="flex items-start gap-2.5">
                                    <span class="text-xl shrink-0">💌</span>
                                    <div>
                                        <div class="text-xs font-black text-gray-900 dark:text-white">
                                            {{ __('Sudah Mengirimkan Donasi di Saweria?') }}
                                        </div>
                                        <div class="text-[11px] text-gray-600 dark:text-gray-300 leading-normal mt-0.5">
                                            {{ __('Kirim surat konfirmasi donasi agar developer bisa mengecek dan membalas ucapan terima kasih langsung ke akunmu!') }}
                                        </div>
                                    </div>
                                </div>
                                <button type="button" 
                                        wire:click="prepareDonationLetter"
                                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-sm transition-all cursor-pointer shrink-0 flex items-center justify-center gap-1.5 active:scale-95">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span>{{ __('Konfirmasi Donasi') }}</span>
                                </button>
                            </div>
                        </div>
                    @endif

                </div>

            </div>
        </div>
    </div>
</div>
