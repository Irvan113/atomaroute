<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\rules;
use function Livewire\Volt\state;

state([
    'current_password' => '',
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'current_password' => ['required', 'string', 'current_password'],
    'password' => ['required', 'string', Password::defaults(), 'confirmed'],
]);

$updatePassword = function () {
    try {
        $validated = $this->validate();
    } catch (ValidationException $e) {
        $this->reset('current_password', 'password', 'password_confirmation');

        throw $e;
    }

    Auth::user()->update([
        'password' => Hash::make($validated['password']),
    ]);

    $this->reset('current_password', 'password', 'password_confirmation');

    $this->dispatch('password-updated');
};

?>

<section>
    <header class="mb-8 border-b border-gray-100 dark:border-gray-700/60 pb-5">
        <h2 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
            {{ __('Update Password') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 font-medium">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="space-y-6">
        <div class="space-y-5">
            <div class="space-y-1.5">
                <x-input-label for="update_password_current_password" :value="__('Current Password')" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                <input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" 
                       class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" 
                       autocomplete="current-password" />
                <x-input-error :messages="$errors->get('current_password')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                    <x-input-label for="update_password_password" :value="__('New Password')" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                    <input wire:model="password" id="update_password_password" name="password" type="password" 
                           class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" 
                           autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>

                <div class="space-y-1.5">
                    <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                    <input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" 
                           class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm" 
                           autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-6 border-t border-gray-100 dark:border-gray-700/60">
            <button type="submit" class="bg-gray-900 hover:bg-black dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-900 font-bold py-2.5 px-6 rounded-xl shadow-xs transition-transform hover:-translate-y-0.5 focus:ring-4 focus:ring-gray-500/20 cursor-pointer text-sm">
                {{ __('Update Password') }}
            </button>

            <x-action-message class="me-3 font-semibold text-xs text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 px-3 py-1.5 rounded-xl" on="password-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
