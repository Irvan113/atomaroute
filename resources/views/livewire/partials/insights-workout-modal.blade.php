{{-- Modal: Catat Olahraga Cepat --}}
@if($showWorkoutModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-7 max-w-lg w-full shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4 animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
            <!-- Header -->
            <div class="flex items-center justify-between pb-2 border-b border-gray-150 dark:border-gray-700/60">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-base font-bold shrink-0 border border-emerald-500/20">
                        🏃‍♂️
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white">{{ __('Catat Aktivitas Olahraga') }}</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('Simpan data latihanmu untuk evaluasi progress mingguan & bulanan') }}</p>
                    </div>
                </div>
                <button type="button" wire:click="closeWorkoutModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold cursor-pointer">✕</button>
            </div>

            <!-- Quick Preset Chips -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">{{ __('Preset Cepat:') }}</label>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button type="button" wire:click="setQuickExercise('Lari', 'cardio', 5.0)" class="px-2.5 py-1 rounded-xl text-xs font-bold border transition-all cursor-pointer {{ ($workoutTitle === 'Lari' && $workoutType === 'cardio') ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-gray-100 dark:bg-gray-750 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-emerald-500' }}">
                        🏃 Lari 5km
                    </button>
                    <button type="button" wire:click="setQuickExercise('Push Up', 'bodyweight', 25)" class="px-2.5 py-1 rounded-xl text-xs font-bold border transition-all cursor-pointer {{ ($workoutTitle === 'Push Up' && $workoutType === 'bodyweight') ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-gray-100 dark:bg-gray-750 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-emerald-500' }}">
                        💪 Push Up
                    </button>
                    <button type="button" wire:click="setQuickExercise('Pull Up', 'bodyweight', 10)" class="px-2.5 py-1 rounded-xl text-xs font-bold border transition-all cursor-pointer {{ ($workoutTitle === 'Pull Up' && $workoutType === 'bodyweight') ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-gray-100 dark:bg-gray-750 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-emerald-500' }}">
                        🧗 Pull Up
                    </button>
                    <button type="button" wire:click="setQuickExercise('Sepeda', 'cardio', 15.0)" class="px-2.5 py-1 rounded-xl text-xs font-bold border transition-all cursor-pointer {{ ($workoutTitle === 'Sepeda' && $workoutType === 'cardio') ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-gray-100 dark:bg-gray-750 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-emerald-500' }}">
                        🚴 Sepeda
                    </button>
                    <button type="button" wire:click="setQuickExercise('Bench Press', 'gym', null)" class="px-2.5 py-1 rounded-xl text-xs font-bold border transition-all cursor-pointer {{ ($workoutTitle === 'Bench Press' && $workoutType === 'gym') ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-gray-100 dark:bg-gray-750 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-emerald-500' }}">
                        🏋️ Gym / Beban
                    </button>
                </div>
            </div>

            <!-- Form Fields -->
            <div class="space-y-3 pt-1">
                <!-- Date & Exercise Title -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Tanggal Latihan') }}</label>
                        <input type="date"
                               wire:model="workoutDate"
                               class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 font-medium">
                        @error('workoutDate') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Nama Olahraga / Latihan') }}</label>
                        <input type="text"
                               wire:model="workoutTitle"
                               placeholder="misal: Lari Pagi, Push Up, Gym Dada"
                               class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 font-medium">
                        @error('workoutTitle') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Mode Switcher: Cardio vs Bodyweight vs Gym -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipe Olahraga') }}</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button"
                                wire:click="$set('workoutType', 'cardio')"
                                class="py-2 px-2 text-center rounded-xl text-xs font-bold border transition-all cursor-pointer {{ $workoutType === 'cardio' ? 'bg-emerald-500/15 border-emerald-500 text-emerald-700 dark:text-emerald-300 font-black' : 'bg-gray-50 dark:bg-gray-700/50 border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">
                            🏃 Kardio / Jarak
                        </button>
                        <button type="button"
                                wire:click="$set('workoutType', 'bodyweight')"
                                class="py-2 px-2 text-center rounded-xl text-xs font-bold border transition-all cursor-pointer {{ $workoutType === 'bodyweight' ? 'bg-emerald-500/15 border-emerald-500 text-emerald-700 dark:text-emerald-300 font-black' : 'bg-gray-50 dark:bg-gray-700/50 border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">
                            💪 Kalistenik / Reps
                        </button>
                        <button type="button"
                                wire:click="$set('workoutType', 'gym')"
                                class="py-2 px-2 text-center rounded-xl text-xs font-bold border transition-all cursor-pointer {{ $workoutType === 'gym' ? 'bg-emerald-500/15 border-emerald-500 text-emerald-700 dark:text-emerald-300 font-black' : 'bg-gray-50 dark:bg-gray-700/50 border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">
                            🏋️ Gym / Beban
                        </button>
                    </div>
                </div>

                <!-- Dynamic Metrics Inputs -->
                @if($workoutType === 'cardio')
                    <div class="grid grid-cols-2 gap-3 p-3.5 bg-cyan-50/50 dark:bg-cyan-950/20 rounded-2xl border border-cyan-200/60 dark:border-cyan-800/40">
                        <div>
                            <label class="block text-xs font-bold text-cyan-900 dark:text-cyan-200 mb-1">
                                {{ __('Jarak Tempuh (km)') }}
                            </label>
                            <input type="number"
                                   step="0.1"
                                   wire:model="workoutDistanceKm"
                                   placeholder="5.0"
                                   class="w-full px-3.5 py-2 rounded-xl text-xs bg-white dark:bg-gray-800 border border-cyan-300 dark:border-cyan-700 text-gray-900 dark:text-white font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-cyan-900 dark:text-cyan-200 mb-1">
                                {{ __('Durasi Lari (menit)') }}
                            </label>
                            <input type="number"
                                   wire:model="workoutDurationMinutes"
                                   placeholder="30"
                                   class="w-full px-3.5 py-2 rounded-xl text-xs bg-white dark:bg-gray-800 border border-cyan-300 dark:border-cyan-700 text-gray-900 dark:text-white font-black">
                        </div>
                    </div>
                @elseif($workoutType === 'bodyweight')
                    <div class="grid grid-cols-2 gap-3 p-3.5 bg-amber-50/50 dark:bg-amber-950/20 rounded-2xl border border-amber-200/60 dark:border-amber-800/40">
                        <div>
                            <label class="block text-xs font-bold text-amber-900 dark:text-amber-200 mb-1">
                                {{ __('Jumlah Set') }}
                            </label>
                            <input type="number"
                                   wire:model="workoutSets"
                                   placeholder="4"
                                   class="w-full px-3.5 py-2 rounded-xl text-xs bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-700 text-gray-900 dark:text-white font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-amber-900 dark:text-amber-200 mb-1">
                                {{ __('Repetisi per Set') }}
                            </label>
                            <input type="number"
                                   wire:model="workoutReps"
                                   placeholder="25"
                                   class="w-full px-3.5 py-2 rounded-xl text-xs bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-700 text-gray-900 dark:text-white font-black">
                        </div>
                        <div class="col-span-2 text-[11px] text-amber-800 dark:text-amber-300 font-bold flex items-center justify-between pt-1">
                            <span>Total Akumulasi Reps:</span>
                            <span class="text-sm font-black">{{ (int)($workoutSets ?: 1) * (int)($workoutReps ?: 0) }} Reps</span>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-3 gap-2 p-3.5 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-2xl border border-indigo-200/60 dark:border-indigo-800/40">
                        <div>
                            <label class="block text-xs font-bold text-indigo-900 dark:text-indigo-200 mb-1">
                                {{ __('Beban (kg)') }}
                            </label>
                            <input type="number"
                                   step="0.5"
                                   wire:model="workoutWeight"
                                   placeholder="50"
                                   class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-700 text-gray-900 dark:text-white font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-indigo-900 dark:text-indigo-200 mb-1">
                                {{ __('Set') }}
                            </label>
                            <input type="number"
                                   wire:model="workoutSets"
                                   placeholder="3"
                                   class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-700 text-gray-900 dark:text-white font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-indigo-900 dark:text-indigo-200 mb-1">
                                {{ __('Reps/Set') }}
                            </label>
                            <input type="number"
                                   wire:model="workoutReps"
                                   placeholder="10"
                                   class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-700 text-gray-900 dark:text-white font-black">
                        </div>
                        <div class="col-span-3 text-[11px] text-indigo-800 dark:text-indigo-300 font-bold flex items-center justify-between pt-1">
                            <span>Total Volume Angkatan:</span>
                            <span class="text-sm font-black">{{ number_format(((float)($workoutWeight ?: 0)) * ((int)($workoutSets ?: 1)) * ((int)($workoutReps ?: 0)), 0, ',', '.') }} kg</span>
                        </div>
                    </div>
                @endif

                <!-- Notes / Feeling -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Catatan / Evaluasi Sesi (Opsional)') }}</label>
                    <textarea wire:model="workoutNotes"
                              rows="2"
                              placeholder="misal: Tempo run 5:30 min/km, kaki terasa ringan dan segar"
                              class="w-full px-3.5 py-2 rounded-xl text-xs bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 font-medium"></textarea>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center gap-2 pt-3 border-t border-gray-150 dark:border-gray-700/60">
                <button type="button"
                        wire:click="closeWorkoutModal"
                        class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl cursor-pointer">
                    {{ __('Batal') }}
                </button>
                <button type="button"
                        wire:click="saveWorkoutLog"
                        class="flex-1 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-95 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-500/25 cursor-pointer">
                    {{ __('💾 Simpan & Catat Latihan') }}
                </button>
            </div>
        </div>
    </div>
@endif
