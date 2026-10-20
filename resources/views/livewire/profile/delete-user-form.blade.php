<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\rules;
use function Livewire\Volt\state;

state(['password' => '']);

rules(['password' => ['required', 'string', 'current_password']]);

$deleteUser = function (Logout $logout) {
    $this->validate();

    tap(Auth::user(), $logout(...))->delete();

    $this->redirect('/', navigate: true);
};

?>

<section class="space-y-6">
    <header class="mb-8 border-b border-red-100 dark:border-red-900/30 pb-5">
        <h2 class="text-2xl font-black text-red-600 dark:text-red-400 tracking-tight">
            {{ __('Delete Account') }}
        </h2>
        <p class="mt-1 text-sm text-red-500/80 dark:text-red-400/80 font-medium">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted.') }}
        </p>
    </header>

    <x-danger-button wire:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" class="rounded-xl shadow-sm hover:shadow-md transition-all font-bold">
        {{ __('Delete Account') }}
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-8">
            <h2 class="text-xl font-black text-gray-900 dark:text-gray-100">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 font-medium">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6 space-y-1.5">
                <x-input-label for="password" value="{{ __('Confirm Password') }}" class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300" />
                <input wire:model="password" id="password" name="password" type="password" 
                       class="block w-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-3 text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition-all text-sm" 
                       placeholder="{{ __('Password') }}" />
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold py-2.5 px-6 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    {{ __('Cancel') }}
                </button>

                <x-danger-button class="rounded-xl shadow-sm hover:shadow-md transition-all font-bold">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
