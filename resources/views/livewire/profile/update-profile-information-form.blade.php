<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $bio = '';
    public ?string $timezone = null;
    public string $locale = 'id';
    public $avatar;
    public $defaultAvatar = '';

    public $dicebearAvatars = [
        'adventurer',
        'avataaars',
        'bottts',
        'fun-emoji',
        'lorelei',
        'micah',
        'miniavs',
        'notionists'
    ];

    public function mount()
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->bio = Auth::user()->bio ?? '';
        $this->timezone = Auth::user()->timezone;
        $this->locale = Auth::user()->locale ?? 'id';
    }

    public function selectDefaultAvatar($style)
    {
        $this->defaultAvatar = 'https://api.dicebear.com/9.x/' . $style . '/svg?seed=' . urlencode($this->name);
        $this->avatar = null; // Clear file upload
    }

    public function updateProfileInformation()
    {
        $user = Auth::user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'in:id,en'],
            'avatar' => ['nullable', 'image', 'max:15360'], // 15MB client allowance (auto-compressed on client)
        ]);

        $user->name = $this->name;
        $user->email = $this->email;
        $user->bio = $this->bio;
        // If they select empty value, we set it to null so it falls back to auto-detect
        $user->timezone = empty($this->timezone) ? null : $this->timezone;
        $user->locale = $this->locale ?: 'id';
        session(['locale' => $user->locale]);

        if ($this->avatar) {
            $path = \App\Services\AvatarSecurityService::processAndStore($this->avatar, $user);
            $user->avatar_path = $path;
            $this->avatar = null;
        } elseif ($this->defaultAvatar) {
            \App\Services\AvatarSecurityService::deleteOldAvatar($user);
            $user->avatar_path = $this->defaultAvatar;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
        $this->dispatch('toast', message: '✨ Profil dan foto avatar berhasil diperbarui!', type: 'success');
    }

    public function sendVerification()
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));
            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header class="mb-8 border-b border-gray-100 dark:border-gray-700/60 pb-5">
        <h2 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
            {{ __('Profile & Avatar') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 font-medium">
            {{ __("Personalize your identity across ATOMA.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="space-y-8">
        
        <!-- Avatar Selection Grid -->
        <div class="bg-gray-50/50 dark:bg-gray-900/30 rounded-2xl p-5 sm:p-6 border border-gray-100 dark:border-gray-700/50">
            <x-input-label :value="__('Your Avatar')" class="mb-4 text-sm font-black uppercase tracking-wider text-gray-500" />
            
            <div class="flex flex-col md:flex-row gap-6 items-center md:items-start">
                <!-- Preview Avatar -->
                <div class="shrink-0 relative group">
                    <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full p-1 bg-gradient-to-tr from-indigo-500 to-purple-500 shadow-md">
                        @if ($avatar)
                            <img src="{{ $avatar->temporaryUrl() }}" alt="Preview" class="w-full h-full rounded-full object-cover border-4 border-white dark:border-gray-800 bg-white">
                        @elseif ($defaultAvatar)
                            <img src="{{ $defaultAvatar }}" alt="Preview" class="w-full h-full rounded-full object-cover border-4 border-white dark:border-gray-800 bg-white">
                        @else
                            <img src="{{ auth()->user()->avatar_url }}" alt="Current" class="w-full h-full rounded-full object-cover border-4 border-white dark:border-gray-800 bg-white">
                        @endif
                    </div>
                </div>

                <div class="flex-1 w-full space-y-4">
                    <!-- Default Avatars (Horizontal Scroll) -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">{{ __('Pilihan Karakter (DiceBear)') }}</label>
                        </div>
                        <div class="flex gap-2 overflow-x-auto pb-2 hide-scrollbar snap-x snap-mandatory">
                            @foreach($dicebearAvatars as $style)
                                @php $styleUrl = 'https://api.dicebear.com/9.x/' . $style . '/svg?seed=' . urlencode($name ?: 'user'); @endphp
                                <button type="button" wire:click="selectDefaultAvatar('{{ $style }}')" class="shrink-0 snap-center p-0.5 w-12 h-12 rounded-xl border-2 transition-all hover:scale-105 active:scale-95 {{ $defaultAvatar === $styleUrl ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/40 shadow-sm' : 'border-transparent hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                    <img src="{{ $styleUrl }}" class="w-full h-full rounded-lg bg-white shadow-sm" alt="{{ $style }}">
                                </button>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- File Upload with WhatsApp/Discord Client-Side Compression & Security -->
                    <div x-data="avatarClientCompressor()" class="pt-3 border-t border-gray-100 dark:border-gray-700/50 space-y-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">
                                {{ __('Unggah Foto Sendiri') }}
                            </label>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 font-medium">
                                Kompresi Otomatis Ala WhatsApp • Max 15MB ➔ ~40KB WebP
                            </span>
                        </div>

                        <!-- Action Bar: Select Button & Manual Quality Setting Toggle -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <label class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-gray-100 dark:hover:bg-white text-white dark:text-gray-900 text-xs font-bold transition-all shadow-xs cursor-pointer active:scale-95 select-none">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span>{{ __('Pilih Berkas Foto') }}</span>
                                <input type="file" 
                                       accept="image/jpeg,image/png,image/webp,image/gif" 
                                       @change="handleSelectFile($event)" 
                                       class="hidden" />
                            </label>

                            <!-- Manual Quality Adjust Toggle -->
                            <button type="button" 
                                    @click="showQualityControl = !showQualityControl" 
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold transition-colors cursor-pointer select-none">
                                <span>⚙️</span>
                                <span>{{ __('Kualitas:') }}</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400" x-text="Math.round(quality * 100) + '%'"></span>
                            </button>
                        </div>

                        <!-- Manual Quality Slider (Collapsible) -->
                        <div x-show="showQualityControl" x-cloak class="p-3 rounded-xl bg-gray-100/70 dark:bg-gray-800/70 border border-gray-200/80 dark:border-gray-700/80 space-y-1.5 animate-in fade-in">
                            <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                <span>Atur Kualitas Kompresi Manual</span>
                                <span class="text-indigo-600 dark:text-indigo-400" x-text="Math.round(quality * 100) + '%'"></span>
                            </div>
                            <input type="range" 
                                   min="0.5" 
                                   max="0.95" 
                                   step="0.05" 
                                   x-model="quality" 
                                   @change="recompressWithNewQuality()" 
                                   class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-indigo-600">
                            <p class="text-[10px] text-gray-400">Semakin rendah kualitas, ukuran file semakin kecil & storage semakin awet.</p>
                        </div>

                        <!-- Visual Client Compression Metrics -->
                        <template x-if="compressStats">
                            <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs flex items-center justify-between animate-in fade-in">
                                <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-200 min-w-0">
                                    <span class="text-sm shrink-0">⚡</span>
                                    <span class="font-medium truncate">
                                        Asli: <strong x-text="compressStats.original"></strong> ➔ WebP: <strong x-text="compressStats.compressed"></strong> 
                                        (<span class="text-emerald-600 dark:text-emerald-400 font-bold" x-text="compressStats.ratio"></span> lebih hemat)
                                    </span>
                                </div>
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-200/60 dark:bg-emerald-900 text-emerald-900 dark:text-emerald-100 font-black shrink-0">
                                    0% Server Load
                                </span>
                            </div>
                        </template>

                        <!-- Progress Bar during upload -->
                        <div x-show="isUploading" x-cloak class="space-y-1">
                            <div class="flex items-center justify-between text-[11px] text-indigo-600 dark:text-indigo-400 font-bold">
                                <span class="flex items-center gap-1.5">
                                    <span class="animate-spin inline-block">⏳</span> Mengunggah foto terkompresi...
                                </span>
                                <span x-text="uploadProgress + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-indigo-600 h-full transition-all duration-200" :style="'width: ' + uploadProgress + '%'"></div>
                            </div>
                        </div>

                        <!-- Error Message & Friendly Community Guidance Banner -->
                        @if($errors->has('avatar'))
                            <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-200 space-y-1.5 animate-in fade-in">
                                <div class="flex items-center gap-2 font-bold text-rose-700 dark:text-rose-300">
                                    <span class="text-base">⛔</span>
                                    <span>Peringatan Kebijakan Foto Profil</span>
                                </div>
                                <p class="text-xs font-medium leading-relaxed pl-6">
                                    {{ $errors->first('avatar') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <script>
            function avatarClientCompressor() {
                return {
                    isUploading: false,
                    uploadProgress: 0,
                    compressStats: null,
                    quality: 0.82,
                    showQualityControl: false,
                    rawFile: null,

                    async handleSelectFile(event) {
                        const file = event.target.files[0];
                        if (!file) return;

                        this.rawFile = file;
                        await this.compressAndUpload(file);
                    },

                    async recompressWithNewQuality() {
                        if (this.rawFile) {
                            await this.compressAndUpload(this.rawFile);
                        }
                    },

                    async compressAndUpload(file) {
                        const originalBytes = file.size;
                        const originalFormatted = originalBytes > 1024 * 1024 
                            ? (originalBytes / (1024 * 1024)).toFixed(2) + ' MB'
                            : (originalBytes / 1024).toFixed(0) + ' KB';

                        try {
                            this.isUploading = true;
                            this.uploadProgress = 15;

                            // 1. Create Image element
                            const img = new Image();
                            const objectUrl = URL.createObjectURL(file);

                            await new Promise((resolve, reject) => {
                                img.onload = resolve;
                                img.onerror = reject;
                                img.src = objectUrl;
                            });

                            // 2. Setup Canvas for center square crop (WhatsApp/Discord client engine)
                            const maxDim = 512;
                            const square = Math.min(img.width, img.height);
                            const sx = (img.width - square) / 2;
                            const sy = (img.height - square) / 2;

                            const canvas = document.createElement('canvas');
                            canvas.width = maxDim;
                            canvas.height = maxDim;
                            const ctx = canvas.getContext('2d');

                            ctx.imageSmoothingEnabled = true;
                            ctx.imageSmoothingQuality = 'high';
                            ctx.drawImage(img, sx, sy, square, square, 0, 0, maxDim, maxDim);

                            URL.revokeObjectURL(objectUrl);
                            this.uploadProgress = 40;

                            // 3. Compress to WebP Blob
                            let blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', this.quality));
                            if (!blob) {
                                blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', this.quality));
                            }

                            const compressedBytes = blob.size;
                            const compressedFormatted = (compressedBytes / 1024).toFixed(0) + ' KB';
                            const savedPercent = Math.max(0, Math.round(((originalBytes - compressedBytes) / originalBytes) * 100));

                            this.compressStats = {
                                original: originalFormatted,
                                compressed: compressedFormatted,
                                ratio: savedPercent + '%'
                            };

                            this.uploadProgress = 60;

                            // 4. Create new lightweight File
                            const cleanName = 'avatar_' + Date.now() + '.webp';
                            const compressedFile = new File([blob], cleanName, { type: blob.type || 'image/webp' });

                            // 5. Stream to Livewire component
                            @this.upload('avatar', compressedFile, 
                                () => {
                                    this.isUploading = false;
                                    this.uploadProgress = 100;
                                }, 
                                (err) => {
                                    this.isUploading = false;
                                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Gagal mengunggah foto: ' + (err || 'Terjadi kesalahan'), type: 'error' } }));
                                }, 
                                (e) => {
                                    this.uploadProgress = 60 + Math.round(e.detail.progress * 0.4);
                                }
                            );
                        } catch (err) {
                            this.isUploading = false;
                            console.error('Client compression error:', err);
                            // Safe fallback to direct upload
                            @this.upload('avatar', file, () => { this.isUploading = false; }, () => { this.isUploading = false; });
                        }
                    }
                };
            }
        </script>

        <div class="space-y-5">
            <!-- Row 1: Display Name & Email Address (2-Column on sm+) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                    <x-input-label for="name" :value="__('Display Name')" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                    <input wire:model="name" id="name" name="name" type="text" 
                           class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" 
                           required autocomplete="name" placeholder="{{ __('Contoh: Alex') }}" />
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>

                <div class="space-y-1.5">
                    <x-input-label for="email" :value="__('Email Address')" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                    <input wire:model="email" id="email" name="email" type="email" 
                           class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" 
                           required autocomplete="username" />
                    <x-input-error class="mt-1" :messages="$errors->get('email')" />
                </div>
            </div>

            @if (auth()->user() instanceof MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl">
                    <p class="text-sm text-amber-900 dark:text-amber-200 font-medium">
                        {{ __('Your email address is unverified.') }}
                        <button wire:click.prevent="sendVerification" class="underline hover:text-amber-950 dark:hover:text-amber-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 ml-1 font-bold">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 p-2 rounded-xl">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif

            <!-- Row 2: Bio / Status -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <x-input-label for="bio" :value="__('Bio / Status')" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                    <span class="text-[10px] text-gray-400 font-bold uppercase">Max 500</span>
                </div>
                <textarea wire:model="bio" id="bio" name="bio" rows="2" 
                          class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm resize-none" 
                          placeholder="{{ __('Tuliskan bio singkat tentang dirimu...') }}"></textarea>
                <x-input-error class="mt-1" :messages="$errors->get('bio')" />
            </div>

            <!-- Row 3: Timezone & Language (2-Column on sm+) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                    <x-input-label for="timezone" :value="__('Timezone')" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                    <select wire:model="timezone" id="timezone" name="timezone" 
                            class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm">
                        <option value="">{{ __('Deteksi Otomatis (Direkomendasikan)') }}</option>
                        @php
                            $timezones = [
                                'Pacific/Midway' => '(GMT-11:00) Midway Island, Samoa',
                                'Pacific/Honolulu' => '(GMT-10:00) Hawaii',
                                'America/Anchorage' => '(GMT-09:00) Alaska',
                                'America/Los_Angeles' => '(GMT-08:00) Pacific Time (US & Canada)',
                                'America/Denver' => '(GMT-07:00) Mountain Time (US & Canada)',
                                'America/Chicago' => '(GMT-06:00) Central Time (US & Canada)',
                                'America/New_York' => '(GMT-05:00) Eastern Time (US & Canada)',
                                'America/Caracas' => '(GMT-04:00) Caracas, La Paz',
                                'America/Buenos_Aires' => '(GMT-03:00) Buenos Aires, Georgetown',
                                'Atlantic/South_Georgia' => '(GMT-02:00) Mid-Atlantic',
                                'Atlantic/Azores' => '(GMT-01:00) Azores, Cape Verde Is.',
                                'Europe/London' => '(GMT+00:00) London, Edinburgh, Dublin, Lisbon',
                                'Europe/Paris' => '(GMT+01:00) Amsterdam, Berlin, Rome, Paris',
                                'Europe/Athens' => '(GMT+02:00) Athens, Bucharest, Istanbul',
                                'Europe/Moscow' => '(GMT+03:00) Moscow, St. Petersburg, Volgograd',
                                'Asia/Dubai' => '(GMT+04:00) Abu Dhabi, Muscat',
                                'Asia/Karachi' => '(GMT+05:00) Islamabad, Karachi, Tashkent',
                                'Asia/Dhaka' => '(GMT+06:00) Almaty, Dhaka, Colombo',
                                'Asia/Jakarta' => '(GMT+07:00) Bangkok, Hanoi, Jakarta',
                                'Asia/Singapore' => '(GMT+08:00) Beijing, Perth, Singapore, Hong Kong',
                                'Asia/Tokyo' => '(GMT+09:00) Tokyo, Seoul, Osaka, Sapporo',
                                'Australia/Sydney' => '(GMT+10:00) Brisbane, Canberra, Melbourne, Sydney',
                                'Pacific/Noumea' => '(GMT+11:00) Magadan, Solomon Is., New Caledonia',
                                'Pacific/Auckland' => '(GMT+12:00) Auckland, Wellington, Fiji, Kamchatka'
                            ];
                        @endphp
                        @foreach($timezones as $tzValue => $tzLabel)
                            <option value="{{ $tzValue }}">{{ $tzLabel }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('timezone')" />
                </div>

                <div class="space-y-1.5">
                    <x-input-label for="locale" :value="__('Bahasa (Language)')" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                    <select wire:model="locale" id="locale" name="locale" 
                            class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm">
                        <option value="id">🇮🇩 Bahasa Indonesia</option>
                        <option value="en">🇺🇸 English</option>
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('locale')" />
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-6 border-t border-gray-100 dark:border-gray-700/60">
            <button type="submit" class="bg-gray-900 hover:bg-black dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-900 font-bold py-2.5 px-6 rounded-xl shadow-xs transition-transform hover:-translate-y-0.5 focus:ring-4 focus:ring-gray-500/20 cursor-pointer text-sm">
                {{ __('Save Changes') }}
            </button>

            <x-action-message class="me-3 font-semibold text-xs text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 px-3 py-1.5 rounded-xl" on="profile-updated">
                {{ __('Successfully saved!') }}
            </x-action-message>
        </div>
    </form>
</section>
