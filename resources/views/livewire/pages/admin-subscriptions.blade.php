<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\SubscriptionTransaction;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public string $search = '';
    public string $filterTier = 'all'; // 'all', 'pro_active', 'pro_expiring', 'free_or_expired'
    
    // Modal Custom Grant State
    public bool $showCustomModal = false;
    public ?int $selectedUserId = null;
    public string $selectedUserName = '';
    public string $selectedUserEmail = '';
    public string $modalPlanType = 'monthly';
    public string $modalCustomExpiresAt = '';
    public string $modalPaymentMethod = 'manual_transfer';
    public int $modalAmount = 29000;
    public string $modalNotes = '';

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

        $this->modalCustomExpiresAt = now()->addDays(30)->toDateString();
    }

    public function updatedModalPlanType($val)
    {
        $base = now();
        if ($this->selectedUserId) {
            $u = User::find($this->selectedUserId);
            if ($u && $u->subscription_expires_at && $u->subscription_expires_at->isFuture()) {
                $base = $u->subscription_expires_at;
            }
        }

        switch ($val) {
            case 'monthly':
                $this->modalAmount = 29000;
                $this->modalCustomExpiresAt = (clone $base)->addDays(30)->toDateString();
                break;
            case 'quarterly':
                $this->modalAmount = 79000;
                $this->modalCustomExpiresAt = (clone $base)->addDays(90)->toDateString();
                break;
            case 'yearly':
                $this->modalAmount = 199000;
                $this->modalCustomExpiresAt = (clone $base)->addDays(365)->toDateString();
                break;
            case 'lifetime':
                $this->modalAmount = 499000;
                $this->modalCustomExpiresAt = '';
                break;
            case 'custom':
                $this->modalAmount = 0;
                break;
        }
    }

    public function quickGrant(int $userId, string $planType)
    {
        $targetUser = User::find($userId);
        if (!$targetUser) {
            $this->dispatch('toast', message: 'User tidak ditemukan.', type: 'error');
            return;
        }

        $admin = auth()->user();
        $trx = $targetUser->grantPro(
            planType: $planType,
            expiresAt: null,
            notes: 'Aktivasi cepat oleh Admin ' . $admin->name,
            admin: $admin,
            paymentMethod: 'manual_transfer'
        );

        $days = match($planType) {
            'monthly' => 30,
            'quarterly' => 90,
            'yearly' => 365,
            default => 30
        };

        $this->dispatch('toast', message: "✅ Berhasil memberikan PRO ({$days} hari) untuk {$targetUser->name}!", type: 'success');
    }

    public function openCustomModal(int $userId)
    {
        $targetUser = User::find($userId);
        if (!$targetUser) return;

        $this->selectedUserId = $targetUser->id;
        $this->selectedUserName = $targetUser->name;
        $this->selectedUserEmail = $targetUser->email;
        $this->modalPlanType = 'monthly';
        $this->modalAmount = 29000;
        $this->modalPaymentMethod = 'manual_transfer';
        $this->modalNotes = '';

        $base = ($targetUser->subscription_expires_at && $targetUser->subscription_expires_at->isFuture()) 
            ? $targetUser->subscription_expires_at 
            : now();
        $this->modalCustomExpiresAt = (clone $base)->addDays(30)->toDateString();

        $this->showCustomModal = true;
    }

    public function closeCustomModal()
    {
        $this->showCustomModal = false;
        $this->reset(['selectedUserId', 'selectedUserName', 'selectedUserEmail', 'modalNotes']);
    }

    public function saveCustomGrant()
    {
        if (!$this->selectedUserId) return;

        $targetUser = User::find($this->selectedUserId);
        if (!$targetUser) {
            $this->dispatch('toast', message: 'User tidak ditemukan.', type: 'error');
            return;
        }

        $expiresAt = null;
        if ($this->modalPlanType !== 'lifetime' && !empty($this->modalCustomExpiresAt)) {
            $expiresAt = Carbon::parse($this->modalCustomExpiresAt)->endOfDay();
        }

        $admin = auth()->user();
        $targetUser->grantPro(
            planType: $this->modalPlanType,
            expiresAt: $expiresAt,
            notes: $this->modalNotes ?: ('Aktivasi manual oleh Admin ' . $admin->name),
            admin: $admin,
            amount: (float) $this->modalAmount,
            paymentMethod: $this->modalPaymentMethod
        );

        $this->closeCustomModal();
        $this->dispatch('toast', message: "✅ Paket PRO untuk {$targetUser->name} berhasil diperbarui!", type: 'success');
    }

    public function revokeUserPro(int $userId)
    {
        $targetUser = User::find($userId);
        if (!$targetUser) return;

        $targetUser->revokePro('Dicabut oleh Administrator ' . auth()->user()->name, auth()->user());
        $this->dispatch('toast', message: "Status PRO {$targetUser->name} telah dicabut (kembali ke Free).", type: 'info');
    }

    public function getUsersProperty()
    {
        $query = User::query()->latest('created_at');

        if (!empty($this->search)) {
            $s = trim($this->search);
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('id', $s);
            });
        }

        if ($this->filterTier === 'pro_active') {
            $query->where('subscription_tier', 'pro')
                  ->where(function($q) {
                      $q->whereNull('subscription_expires_at')
                        ->orWhere('subscription_expires_at', '>', now());
                  });
        } elseif ($this->filterTier === 'pro_expiring') {
            $query->where('subscription_tier', 'pro')
                  ->where('subscription_expires_at', '>', now())
                  ->where('subscription_expires_at', '<=', now()->addDays(3));
        } elseif ($this->filterTier === 'free_or_expired') {
            $query->where(function($q) {
                $q->where('subscription_tier', '!=', 'pro')
                  ->orWhere('subscription_expires_at', '<=', now());
            });
        }

        return $query->paginate(15);
    }

    public function getRecentTransactionsProperty()
    {
        return SubscriptionTransaction::with(['user', 'admin'])
            ->latest()
            ->take(8)
            ->get();
    }
}; ?>

<div class="space-y-6">
    {{-- Admin Unified Top Navigation --}}
    <x-admin-navbar active="subscriptions" />

    {{-- Header Banner & Stats --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-500 via-amber-600 to-yellow-600 text-white p-6 sm:p-8 shadow-xl shadow-amber-500/10">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-xs font-black uppercase tracking-wider mb-2">
                    <span>👑 ATOMA PRO Command</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                    <span>Aktivasi Manual & Kelola Member</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight">
                    Manajemen Langganan & Member PRO
                </h1>
                <p class="text-white/80 text-sm max-w-2xl mt-1 leading-relaxed">
                    Kelola aktivasi langganan berbayar secara manual, input bukti pembayaran transfer/QRIS, perpanjang masa aktif user, dan monitor transaksi yang berjalan.
                </p>
            </div>

            {{-- Quick Summary Badges --}}
            @php
                $totalProActive = User::where('subscription_tier', 'pro')
                    ->where(function($q) {
                        $q->whereNull('subscription_expires_at')->orWhere('subscription_expires_at', '>', now());
                    })->count();

                $expiringSoon = User::where('subscription_tier', 'pro')
                    ->where('subscription_expires_at', '>', now())
                    ->where('subscription_expires_at', '<=', now()->addDays(3))
                    ->count();

                $totalRevenue = SubscriptionTransaction::sum('amount');
                $totalTrxCount = SubscriptionTransaction::count();
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3 border border-white/10 text-center">
                    <div class="text-[10px] uppercase font-bold text-white/70">PRO Aktif</div>
                    <div class="text-xl sm:text-2xl font-black mt-0.5">{{ number_format($totalProActive) }}</div>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3 border border-white/10 text-center">
                    <div class="text-[10px] uppercase font-bold text-white/70">H-3 Expire</div>
                    <div class="text-xl sm:text-2xl font-black mt-0.5 text-yellow-200">{{ number_format($expiringSoon) }}</div>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3 border border-white/10 text-center">
                    <div class="text-[10px] uppercase font-bold text-white/70">Aktivasi</div>
                    <div class="text-xl sm:text-2xl font-black mt-0.5">{{ number_format($totalTrxCount) }}</div>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3 border border-white/10 text-center">
                    <div class="text-[10px] uppercase font-bold text-white/70">Total Omzet</div>
                    <div class="text-sm sm:text-base font-black mt-1 text-emerald-200">
                        Rp {{ number_format($totalRevenue / 1000, 0) }}k
                    </div>
                </div>
            </div>
        </div>

        {{-- Background Glow --}}
        <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-yellow-400/20 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    {{-- Main Control Bar: Search & Filters --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-2xs">
        <div class="relative flex-1">
            <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" 
                   wire:model.live.debounce.300ms="search"
                   placeholder="Cari user berdasarkan nama, email, atau ID user..." 
                   class="w-full pl-9 pr-4 py-2 rounded-xl text-xs sm:text-sm bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all outline-hidden">
        </div>

        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
            <button wire:click="$set('filterTier', 'all')" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 {{ $filterTier === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 shadow-xs' : 'bg-gray-100 dark:bg-gray-700/70 text-gray-600 dark:text-gray-300 hover:bg-gray-200' }}">
                Semua User
            </button>
            <button wire:click="$set('filterTier', 'pro_active')" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 {{ $filterTier === 'pro_active' ? 'bg-amber-500 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700/70 text-gray-600 dark:text-gray-300 hover:bg-gray-200' }}">
                👑 PRO Aktif
            </button>
            <button wire:click="$set('filterTier', 'pro_expiring')" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 {{ $filterTier === 'pro_expiring' ? 'bg-rose-500 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700/70 text-gray-600 dark:text-gray-300 hover:bg-gray-200' }}">
                ⚠️ Segera Expired (&le;3 Hari)
            </button>
            <button wire:click="$set('filterTier', 'free_or_expired')" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 {{ $filterTier === 'free_or_expired' ? 'bg-gray-700 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700/70 text-gray-600 dark:text-gray-300 hover:bg-gray-200' }}">
                Free Starter / Expired
            </button>
        </div>
    </div>

    {{-- Users List Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200/80 dark:border-gray-700/80 shadow-2xs overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
            <div>
                <h2 class="text-base font-black text-gray-900 dark:text-white">
                    Daftar Pengguna & Status Langganan
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Klik tombol aksi cepat untuk langsung menambahkan masa aktif paket user.
                </p>
            </div>
            <span class="text-xs font-bold text-gray-400">
                Total: {{ $this->users->total() }} user
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-gray-50/75 dark:bg-gray-700/30 text-gray-500 dark:text-gray-400 font-bold border-b border-gray-100 dark:border-gray-700/60 uppercase text-[11px] tracking-wider">
                        <th class="py-3.5 px-5">User / Akun</th>
                        <th class="py-3.5 px-4 text-center">Status Tier</th>
                        <th class="py-3.5 px-4">Masa Berlaku</th>
                        <th class="py-3.5 px-4">Milestone Aktif</th>
                        <th class="py-3.5 px-5 text-right">Aksi Admin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse($this->users as $u)
                        @php
                            $isUserPro = $u->isPro();
                            $expiresAt = $u->subscription_expires_at;
                            $isExpiringSoon = $isUserPro && $expiresAt && $expiresAt->diffInDays(now()) <= 3 && $expiresAt->isFuture();
                            $milestoneCount = $u->skills()->where('status', '!=', 'completed')->count();
                        @endphp
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-700/20 transition-colors {{ $isExpiringSoon ? 'bg-amber-50/30 dark:bg-amber-950/10' : '' }}">
                            {{-- User Info --}}
                            <td class="py-3.5 px-5">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}" class="w-9 h-9 rounded-xl object-cover bg-gray-100 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-700 shrink-0">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-gray-900 dark:text-white truncate max-w-[160px] sm:max-w-xs">{{ $u->name }}</span>
                                            @if($u->is_admin)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">ADMIN</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                            {{ $u->email }} &bull; ID #{{ $u->id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Status Tier --}}
                            <td class="py-3.5 px-4 text-center">
                                @if($isUserPro)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-black bg-gradient-to-r from-amber-500/20 to-yellow-500/20 text-amber-700 dark:text-amber-300 border border-amber-400/40">
                                        👑 ATOMA PRO
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-700/60 dark:text-gray-300">
                                        Free Starter
                                    </span>
                                @endif
                            </td>

                            {{-- Masa Berlaku --}}
                            <td class="py-3.5 px-4">
                                @if($u->is_admin)
                                    <div class="font-bold text-indigo-600 dark:text-indigo-400">Akses Admin (Permanen)</div>
                                @elseif($isUserPro)
                                    @if($expiresAt)
                                        <div class="font-bold text-gray-900 dark:text-white">
                                            {{ $expiresAt->format('d M Y') }}
                                        </div>
                                        <div class="text-[11px] font-medium {{ $isExpiringSoon ? 'text-amber-600 dark:text-amber-400 font-bold animate-pulse' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $expiresAt->diffForHumans() }}
                                            @if($isExpiringSoon)
                                                &bull; Segera Berakhir!
                                            @endif
                                        </div>
                                    @else
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">Seumur Hidup (Lifetime)</div>
                                    @endif
                                @else
                                    @if($expiresAt && $expiresAt->isPast())
                                        <div class="font-medium text-rose-500 text-xs">
                                            Expired: {{ $expiresAt->format('d M Y') }} ({{ $expiresAt->diffForHumans() }})
                                        </div>
                                    @else
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endif
                                @endif
                            </td>

                            {{-- Active Milestones --}}
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-gray-700 dark:text-gray-300">
                                    {{ $milestoneCount }} aktif
                                </span>
                                @if(!$isUserPro && $milestoneCount >= 1)
                                    <span class="text-[10px] text-amber-600 dark:text-amber-400 block font-bold">
                                        🔒 Mencapai limit free (1)
                                    </span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="py-3.5 px-5 text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    {{-- Quick +1 Bulan --}}
                                    <button wire:click="quickGrant({{ $u->id }}, 'monthly')" 
                                            class="px-2.5 py-1.5 rounded-xl bg-amber-500/10 hover:bg-amber-500 text-amber-700 hover:text-white dark:text-amber-300 dark:hover:text-white text-xs font-bold transition-all cursor-pointer border border-amber-400/30"
                                            title="Tambah 30 Hari PRO">
                                        +1 Bulan
                                    </button>

                                    {{-- Quick +1 Tahun --}}
                                    <button wire:click="quickGrant({{ $u->id }}, 'yearly')" 
                                            class="px-2.5 py-1.5 rounded-xl bg-indigo-500/10 hover:bg-indigo-600 text-indigo-700 hover:text-white dark:text-indigo-300 dark:hover:text-white text-xs font-bold transition-all cursor-pointer border border-indigo-400/30 hidden sm:inline-block"
                                            title="Tambah 365 Hari PRO">
                                        +1 Tahun
                                    </button>

                                    {{-- Custom Modal Button --}}
                                    <button wire:click="openCustomModal({{ $u->id }})" 
                                            class="px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-700/60 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold transition-all cursor-pointer"
                                            title="Kustomisasi Paket & Catatan">
                                        ⚙️ Kustom
                                    </button>

                                    @if($isUserPro && !$u->is_admin)
                                        {{-- Revoke Button --}}
                                        <button wire:click="revokeUserPro({{ $u->id }})" 
                                                wire:confirm="Yakin ingin mencabut status PRO user ini dan mengembalikannya ke Free?"
                                                class="px-2 py-1.5 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold transition-all cursor-pointer"
                                                title="Cabut Status PRO">
                                            ✕ Cabut
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-500">
                                Tidak ada data pengguna yang cocok dengan pencarian atau filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($this->users->hasPages())
            <div class="p-4 border-t border-gray-100 dark:border-gray-700/60">
                {{ $this->users->links() }}
            </div>
        @endif
    </div>

    {{-- Recent Transactions & Audit Trail --}}
    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200/80 dark:border-gray-700/80 shadow-2xs p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <span>🧾 Riwayat Aktivasi & Transaksi Manual Terakhir</span>
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Catatan audit log setiap kali admin memberikan paket PRO atau memperpanjang langganan user.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/75 dark:bg-gray-700/30 text-gray-500 dark:text-gray-400 font-bold border-b border-gray-100 dark:border-gray-700/60 uppercase tracking-wider text-[10px]">
                        <th class="py-2.5 px-3">No. Referensi</th>
                        <th class="py-2.5 px-3">User</th>
                        <th class="py-2.5 px-3">Paket</th>
                        <th class="py-2.5 px-3">Nominal</th>
                        <th class="py-2.5 px-3">Metode Bayar</th>
                        <th class="py-2.5 px-3">Masa Aktif</th>
                        <th class="py-2.5 px-3">Diproses Oleh</th>
                        <th class="py-2.5 px-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse($this->recentTransactions as $tx)
                        <tr class="hover:bg-gray-50/40 dark:hover:bg-gray-700/20">
                            <td class="py-2.5 px-3 font-mono font-bold text-gray-900 dark:text-white">
                                {{ $tx->reference_no }}
                            </td>
                            <td class="py-2.5 px-3 font-bold text-gray-800 dark:text-gray-200">
                                {{ $tx->user?->name ?? 'User Terhapus' }}
                            </td>
                            <td class="py-2.5 px-3 font-medium text-gray-700 dark:text-gray-300">
                                {{ $tx->plan_name }}
                            </td>
                            <td class="py-2.5 px-3 font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $tx->formatted_amount }}
                            </td>
                            <td class="py-2.5 px-3 text-gray-500 capitalize">
                                {{ str_replace('_', ' ', $tx->payment_method) }}
                            </td>
                            <td class="py-2.5 px-3 text-gray-600 dark:text-gray-300">
                                {{ $tx->starts_at?->format('d/m/y') }} &rarr; {{ $tx->expires_at ? $tx->expires_at->format('d/m/y') : 'Lifetime' }}
                            </td>
                            <td class="py-2.5 px-3 text-gray-500">
                                {{ $tx->admin?->name ?? 'Sistem' }}
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tx->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-gray-100 text-gray-600' }}">
                                    {{ strtoupper($tx->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-gray-400">
                                Belum ada riwayat transaksi aktivasi. Klik tombol "+1 Bulan" di atas untuk mencoba aktivasi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Custom Grant / Manual Payment Entry --}}
    @if($showCustomModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white">
                            Aktivasi Paket ATOMA PRO Kustom
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Untuk: <span class="font-bold text-amber-600">{{ $selectedUserName }}</span> ({{ $selectedUserEmail }})
                        </p>
                    </div>
                    <button wire:click="closeCustomModal" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer">
                        ✕
                    </button>
                </div>

                <div class="space-y-3.5 text-xs sm:text-sm">
                    {{-- Plan Type Selection --}}
                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Pilih Durasi Paket</label>
                        <select wire:model.live="modalPlanType" class="w-full py-2 px-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white font-medium outline-hidden focus:ring-2 focus:ring-amber-500/20">
                            <option value="monthly">Bulanan (30 Hari) - Rp 29.000</option>
                            <option value="quarterly">3 Bulan (90 Hari) - Rp 79.000</option>
                            <option value="yearly">Tahunan (365 Hari) - Rp 199.000 (Hemat 40%)</option>
                            <option value="lifetime">Akses Seumur Hidup (Lifetime) - Rp 499.000</option>
                            <option value="custom">Kustom Tanggal / Free Trial</option>
                        </select>
                    </div>

                    {{-- Expires At (if not lifetime) --}}
                    @if($modalPlanType !== 'lifetime')
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Masa Berlaku Hingga</label>
                            <input type="date" 
                                   wire:model="modalCustomExpiresAt" 
                                   class="w-full py-2 px-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white font-medium outline-hidden focus:ring-2 focus:ring-amber-500/20">
                        </div>
                    @endif

                    {{-- Payment Method --}}
                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Metode Pembayaran</label>
                        <select wire:model="modalPaymentMethod" class="w-full py-2 px-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white font-medium outline-hidden focus:ring-2 focus:ring-amber-500/20">
                            <option value="manual_transfer">Transfer Bank (BCA / Mandiri / BRI / BNI)</option>
                            <option value="qris_manual">QRIS Manual (GoPay / OVO / Dana / ShopeePay)</option>
                            <option value="admin_grant">Pemberian Khusus Admin (Free / Tester)</option>
                            <option value="promo">Kupon Promo / Hadiah Komunitas</option>
                        </select>
                    </div>

                    {{-- Amount Input --}}
                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Nominal Pembayaran Diterima (Rp)</label>
                        <input type="number" 
                               wire:model="modalAmount" 
                               placeholder="29000"
                               class="w-full py-2 px-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white font-medium outline-hidden focus:ring-2 focus:ring-amber-500/20">
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Catatan / Bukti Transfer (Opsional)</label>
                        <textarea wire:model="modalNotes" 
                                  rows="2" 
                                  placeholder="Contoh: Transfer BCA a.n Budi Sudarsono, No Ref #TRX987654"
                                  class="w-full py-2 px-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white font-medium outline-hidden focus:ring-2 focus:ring-amber-500/20"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <button wire:click="closeCustomModal" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="saveCustomGrant" class="px-4 py-2 rounded-xl text-xs font-black bg-amber-500 hover:bg-amber-600 text-white shadow-md shadow-amber-500/20 transition-all cursor-pointer">
                        ✓ Simpan & Aktifkan Paket
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
