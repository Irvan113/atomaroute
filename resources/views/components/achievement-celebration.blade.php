<div x-data="achievementCelebration()"
     @achievement-unlocked.window="celebrate($event.detail)"
     style="display: none;"
     x-show="show"
     x-transition.opacity.duration.300ms
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/85 backdrop-blur-md">

    <div @click.away="show = false"
         class="bg-gradient-to-b from-gray-900 to-[#0b1329] border border-amber-400/50 rounded-3xl p-6 sm:p-8 max-w-sm w-full text-center text-white shadow-[0_0_50px_rgba(251,191,36,0.3)] relative transform transition-all animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Close Button -->
        <button type="button" @click="show = false" class="absolute top-4 right-4 text-gray-400 hover:text-white p-1 rounded-xl cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <!-- Top Celebration Header -->
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 text-[10px] font-black uppercase tracking-widest border border-amber-400/40 mb-3 select-none">
            <span>🎉</span> {{ __('Pencapaian Baru Terbuka!') }}
        </div>

        <!-- Big Badge SVG Pop Container -->
        <div class="w-28 h-28 mx-auto my-3 relative flex items-center justify-center">
            <div class="absolute inset-0 bg-gradient-to-tr from-amber-500/20 via-cyan-400/30 to-purple-500/30 rounded-full blur-xl animate-pulse"></div>
            
            <template x-if="badge && badge.svg_path && !imgFailed">
                <img :src="getBadgeSrc()" 
                     :alt="badge ? badge.title : 'Achievement Badge'" 
                     x-on:error="imgFailed = true"
                     class="w-full h-full object-contain relative z-10 drop-shadow-[0_8px_20px_rgba(0,0,0,0.5)] transform hover:scale-110 transition-transform select-none">
            </template>
            <template x-if="!badge || !badge.svg_path || imgFailed">
                <span class="text-6xl relative z-10 select-none animate-bounce" x-text="badge && badge.icon ? badge.icon : '🏆'"></span>
            </template>
        </div>

        <!-- Badge Title & Description -->
        <h3 class="text-lg font-black text-white" x-text="badge ? badge.title : '{{ __('Pencapaian Terbuka') }}'"></h3>
        <p class="text-xs text-gray-300 mt-1.5 leading-relaxed px-2" x-text="badge ? badge.description : ''"></p>

        <!-- Reward Chips -->
        <div class="flex items-center justify-center gap-2 mt-3.5 mb-5" x-show="badge && (badge.bonus_xp || badge.bonus_coins)">
            <template x-if="badge && badge.bonus_xp">
                <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-cyan-300 font-extrabold text-xs border border-cyan-400/30 shadow-xs">
                    +<span x-text="badge.bonus_xp"></span> XP
                </span>
            </template>
            <template x-if="badge && badge.bonus_coins">
                <span class="px-2.5 py-1 rounded-lg bg-amber-500/20 text-amber-300 font-extrabold text-xs border border-amber-400/30 shadow-xs">
                    +<span x-text="badge.bonus_coins"></span> 🪙 {{ __('Koin') }}
                </span>
            </template>
        </div>

        <!-- Actions: Equip or Close -->
        <div class="space-y-2">
            <button type="button"
                    @click="equipNow()" 
                    :disabled="equipping"
                    class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/30 transition-all cursor-pointer disabled:opacity-50">
                <span x-show="!equipping">⭐ {{ __('Pasang di Profil Sekarang') }}</span>
                <span x-show="equipping">{{ __('Menyimpan...') }}</span>
            </button>
            <button type="button"
                    @click="show = false" 
                    class="w-full py-2 px-4 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white font-bold text-xs transition-colors cursor-pointer">
                {{ __('Lain Kali') }}
            </button>
        </div>

    </div>
</div>

<script data-navigate-once>
    const registerAchievementCelebration = () => {
        if (typeof Alpine !== 'undefined' && !Alpine.data('achievementCelebration')) {
            Alpine.data('achievementCelebration', () => ({
                show: false,
                badge: null,
                equipping: false,
                imgFailed: false,

                celebrate(detail) {
                    if (!detail) return;
                    let badge = detail;
                    // Livewire 3 passes positional event parameters as an array [arg1, arg2]
                    if (Array.isArray(detail)) {
                        badge = detail[0] || null;
                    }
                    // Support named parameter dispatch e.g. dispatch('achievement-unlocked', badge: $badge)
                    if (badge && badge.badge) {
                        badge = badge.badge;
                    }
                    if (!badge || typeof badge !== 'object') return;
                    // Only open celebration if badge has at least a title or code
                    if (!badge.title && !badge.code) return;

                    this.badge = badge;
                    this.imgFailed = false;
                    this.show = true;

                    // Play celebration audio
                    if (window.AtomaAudio) {
                        window.AtomaAudio.play('success');
                    }

                    // Trigger confetti explosion if canvas-confetti is loaded
                    if (typeof window.confetti === 'function') {
                        window.confetti({
                            particleCount: 80,
                            spread: 70,
                            origin: { y: 0.6 }
                        });
                    }
                },

                getBadgeSrc() {
                    if (!this.badge || !this.badge.svg_path) return '';
                    return this.badge.svg_path.startsWith('/') ? this.badge.svg_path : '/' + this.badge.svg_path;
                },

                async equipNow() {
                    if (!this.badge || this.equipping) return;
                    this.equipping = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                        const res = await fetch('/api/user-achievements/equip', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({ 
                                code: this.badge.code,
                                action: 'equip',
                                replace: true
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: '⭐ ' + data.message, type: 'success' }
                            }));
                            window.dispatchEvent(new CustomEvent('badge-equipped', {
                                detail: { 
                                    code: data.equipped_badge, 
                                    equipped_badges: data.equipped_badges,
                                    details: data.details,
                                    equipped_badges_details: data.equipped_badges_details
                                }
                            }));
                            window.dispatchEvent(new CustomEvent('profile-updated'));
                            this.show = false;
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message || 'Gagal memasang lencana.', type: 'error' }
                            }));
                        }
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.equipping = false;
                    }
                }
            }));
        }
    };

    if (window.Alpine) {
        registerAchievementCelebration();
    } else {
        document.addEventListener('alpine:init', registerAchievementCelebration);
    }
</script>
