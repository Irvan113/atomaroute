<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\SystemLog;
use App\Services\SystemLogService;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterLevel = 'all';
    public string $filterCategory = 'all';
    public string $timeRange = 'all'; // all, today, 7days, 30days
    public int $perPage = 25;

    // Inspector Modal
    public ?int $inspectLogId = null;

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

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterLevel()
    {
        $this->resetPage();
    }

    public function updatedFilterCategory()
    {
        $this->resetPage();
    }

    public function updatedTimeRange()
    {
        $this->resetPage();
    }

    public function inspectLog(int $id)
    {
        $this->inspectLogId = $id;
    }

    public function closeInspector()
    {
        $this->inspectLogId = null;
    }

    public function pruneLogs()
    {
        $deleted = SystemLogService::prune(30);
        SystemLogService::audit('logs.pruned', "Admin membersihkan {$deleted} log lama (> 30 hari).");
        session()->flash('success', "Berhasil menghapus {$deleted} data log yang lebih dari 30 hari.");
    }

    public function generateTestLogs()
    {
        SystemLogService::debug('Test DEBUG log: Memeriksa state komponen dan alokasi memori internal.', ['cache_key' => 'system_metrics', 'memory' => '12.4 MB']);
        SystemLogService::info('Test INFO log: Siklus background cron dan pengecekan streak berjalan normal.', ['processed_users' => 45]);
        SystemLogService::warn('Test WARN log: Penggunaan kuota API pihak ketiga mendekati 80%.', ['quota_used' => 82, 'provider' => 'YouTube']);
        SystemLogService::error('Test ERROR log: Percobaan sinkronisasi eksternal gagal karena timeout sementara.', ['attempt' => 3, 'timeout_seconds' => 5]);
        SystemLogService::critical('Test CRITICAL log: Peringatan anomali beban transaksi server tinggi.', ['cpu_load' => '94%', 'active_threads' => 120]);
        SystemLogService::security('test.security_alert', 'Test SECURITY: Deteksi aktivitas IP asing mencoba akses port administratif.', ['target_route' => '/admin/config'], 'WARN');
        SystemLogService::performance('test.heavy_computation', 1450, 'Test PERFORMA: Kueri analitik grafik membutuhkan waktu 1450ms.', ['rows_scanned' => 5200]);

        session()->flash('success', 'Berhasil membuat 7 sampel log dengan berbagai tingkatan (DEBUG, INFO, WARN, ERROR, CRITICAL).');
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        SystemLogService::audit('logs.exported', 'Admin mengekspor data system log ke format CSV.');

        $logs = $this->buildQuery()->get();
        $filename = 'atoma_system_logs_' . date('Y-m-d_H-i') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Waktu (WIB)', 'Level', 'Kategori', 'Aksi', 'Pesan', 'User ID', 'User Email', 'IP Address', 'URL', 'Metode', 'Durasi (ms)', 'Konteks JSON']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->level,
                    $log->category,
                    $log->action,
                    $log->message,
                    $log->user_id,
                    $log->user ? $log->user->email : 'Guest',
                    $log->ip_address,
                    $log->url,
                    $log->method,
                    $log->execution_time_ms,
                    $log->context ? json_encode($log->context) : '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function buildQuery()
    {
        $query = SystemLog::with('user')->latest();

        if ($this->filterLevel !== 'all') {
            $query->where('level', $this->filterLevel);
        }

        if ($this->filterCategory !== 'all') {
            $query->where('category', $this->filterCategory);
        }

        if ($this->timeRange === 'today') {
            $query->where('created_at', '>=', Carbon::today());
        } elseif ($this->timeRange === '7days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($this->timeRange === '30days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        if (!empty($this->search)) {
            $search = trim($this->search);
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        return $query;
    }

    public function with(): array
    {
        $stats = [
            'total' => SystemLog::count(),
            'errors' => SystemLog::whereIn('level', ['ERROR', 'CRITICAL'])->count(),
            'security' => SystemLog::where('category', 'security')->count(),
            'performance' => SystemLog::where('category', 'performance')->where('execution_time_ms', '>=', 1000)->count(),
        ];

        $inspectedLog = $this->inspectLogId ? SystemLog::with('user')->find($this->inspectLogId) : null;

        return [
            'logs' => $this->buildQuery()->paginate($this->perPage),
            'stats' => $stats,
            'inspectedLog' => $inspectedLog,
        ];
    }
};
?>

<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">

    <!-- Unified Admin Navigation Bar -->
    <x-admin-navbar active="logs" />

    <!-- Top Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">
                <span>{{ __('Administrator') }}</span>
                <span>/</span>
                <span class="text-indigo-600 dark:text-indigo-400">{{ __('System Logging & Telemetri') }}</span>
            </div>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-base shadow-sm">
                    📋
                </span>
                <span>{{ __('Pusat Pemantauan Log & Audit') }}</span>
            </h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Pantau troubleshooting bug, metrik performa respon, jejak audit keamanan, dan compliance aktivitas web secara real-time.') }}
            </p>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex items-center gap-2">
            <button wire:click="generateTestLogs" 
                    type="button" 
                    class="px-3 py-2 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 rounded-xl text-xs font-bold transition-all border border-indigo-200 dark:border-indigo-800 flex items-center gap-1.5 cursor-pointer shadow-2xs">
                <span>🧪</span>
                <span>{{ __('Buat Sampel Log') }}</span>
            </button>
            <button wire:click="exportCsv" 
                    type="button" 
                    class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>{{ __('Ekspor CSV') }}</span>
            </button>
            <button wire:click="pruneLogs" 
                    wire:confirm="Hapus data log yang berusia lebih dari 30 hari?"
                    type="button" 
                    class="px-3 py-2 bg-gray-100 hover:bg-rose-50 dark:bg-gray-800 dark:hover:bg-rose-950/50 text-gray-700 hover:text-rose-600 dark:text-gray-300 dark:hover:text-rose-400 rounded-xl text-xs font-bold transition-all border border-gray-200 dark:border-gray-700 flex items-center gap-1 cursor-pointer">
                <span>🧹</span>
                <span>{{ __('Bersihkan (>30h)') }}</span>
            </button>
        </div>
    </div>

    <!-- Flash message -->
    @if(session('success'))
        <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2">
            <span>✅</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- 4 Core Metrics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- 1. Total Activity -->
        <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Total Rekaman') }}</span>
                <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs">📊</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</div>
            <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Seluruh riwayat sistem') }}</div>
        </div>

        <!-- 2. Errors & Crashes (Troubleshooting) -->
        <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-500">{{ __('Error & Crash') }}</span>
                <span class="w-7 h-7 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xs">🚨</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400">{{ number_format($stats['errors']) }}</div>
            <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Perlu investigasi debug') }}</div>
        </div>

        <!-- 3. Security & Brute Force (Keamanan) -->
        <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-500">{{ __('Keamanan & Audit') }}</span>
                <span class="w-7 h-7 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs">🛡️</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ number_format($stats['security']) }}</div>
            <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Login, lockout & anomali') }}</div>
        </div>

        <!-- 4. Slow Requests (Performa) -->
        <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-500">{{ __('Kueri/Req Lambat') }}</span>
                <span class="w-7 h-7 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs">⚡</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400">{{ number_format($stats['performance']) }}</div>
            <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Respon lebih dari 1.000ms') }}</div>
        </div>
    </div>

    <!-- Filter Control Strip -->
    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
            <!-- Search Input -->
            <div class="relative">
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="{{ __('Cari pesan, aksi, IP, URL, atau user...') }}"
                       class="w-full pl-9 pr-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Level Filter (DEBUG, INFO, WARN, ERROR, CRITICAL) -->
            <div>
                <select wire:model.live="filterLevel" 
                        class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="all">{{ __('Semua Tingkatan Log (Level)') }}</option>
                    <option value="DEBUG">DEBUG (Detail Teknis)</option>
                    <option value="INFO">INFO (Operasional Normal)</option>
                    <option value="WARN">WARN (Peringatan Sistem)</option>
                    <option value="ERROR">ERROR (Kegagalan Alur)</option>
                    <option value="CRITICAL">CRITICAL (Fatal / Anomali)</option>
                </select>
            </div>

            <!-- Category Filter (4 Core Functions) -->
            <div>
                <select wire:model.live="filterCategory" 
                        class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="all">{{ __('Semua Kategori Fungsi') }}</option>
                    <option value="troubleshooting">🩺 Troubleshooting & Bug</option>
                    <option value="performance">⚡ Monitoring & Performa</option>
                    <option value="security">🛡️ Keamanan & Brute Force</option>
                    <option value="audit">📜 Compliance & Audit Trail</option>
                    <option value="system">⚙️ Sistem Umum</option>
                </select>
            </div>

            <!-- Time Range -->
            <div>
                <select wire:model.live="timeRange" 
                        class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="all">{{ __('Rentang Waktu: Semua') }}</option>
                    <option value="today">{{ __('Hari Ini Saja') }}</option>
                    <option value="7days">{{ __('7 Hari Terakhir') }}</option>
                    <option value="30days">{{ __('30 Hari Terakhir') }}</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Main Log Table -->
    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-750/70 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                        <th class="px-4 py-3">{{ __('Waktu') }}</th>
                        <th class="px-4 py-3">{{ __('Level') }}</th>
                        <th class="px-4 py-3">{{ __('Kategori & Aksi') }}</th>
                        <th class="px-4 py-3">{{ __('Pesan') }}</th>
                        <th class="px-4 py-3">{{ __('User & IP') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Durasi') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 font-medium">
                    @forelse($logs as $item)
                        @php
                            $badge = $item->level_badge;
                            $cat = $item->category_meta;
                        @endphp
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-750/40 transition-colors">
                            <!-- Timestamp -->
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400 text-[11px] font-mono">
                                <div>{{ $item->created_at->format('H:i:s') }}</div>
                                <div class="text-[9px] text-gray-400">{{ $item->created_at->format('d M Y') }}</div>
                            </td>

                            <!-- Log Level Badge -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black border {{ $badge['bg'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>
                                    <span>{{ $item->level }}</span>
                                </span>
                            </td>

                            <!-- Category & Action -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-1 font-bold text-gray-900 dark:text-white">
                                    <span>{{ $cat['icon'] }}</span>
                                    <span class="{{ $cat['color'] }}">{{ $cat['label'] }}</span>
                                </div>
                                <div class="text-[10px] font-mono text-gray-500 dark:text-gray-400">
                                    {{ $item->action }}
                                </div>
                            </td>

                            <!-- Message Content -->
                            <td class="px-4 py-3 max-w-xs sm:max-w-sm md:max-w-md truncate">
                                <div class="font-bold text-gray-800 dark:text-gray-200 truncate" title="{{ $item->message }}">
                                    {{ $item->message }}
                                </div>
                                @if($item->url)
                                    <div class="text-[10px] text-gray-400 font-mono truncate">
                                        {{ $item->method }} {{ $item->url }}
                                    </div>
                                @endif
                            </td>

                            <!-- User & IP Address -->
                            <td class="px-4 py-3 whitespace-nowrap text-[11px]">
                                @if($item->user)
                                    <div class="font-bold text-gray-800 dark:text-gray-200">{{ $item->user->name }}</div>
                                @else
                                    <div class="text-gray-400 italic">Guest / Anonim</div>
                                @endif
                                <div class="text-[10px] font-mono text-gray-400">
                                    {{ $item->ip_address ?: '-' }}
                                </div>
                            </td>

                            <!-- Duration / Execution Time -->
                            <td class="px-4 py-3 whitespace-nowrap text-right text-[11px] font-mono">
                                @if($item->execution_time_ms)
                                    <span class="{{ $item->execution_time_ms >= 1000 ? 'text-amber-600 dark:text-amber-400 font-black' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $item->execution_time_ms }}ms
                                    </span>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">-</span>
                                @endif
                            </td>

                            <!-- Action Inspect -->
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <button type="button" 
                                        wire:click="inspectLog({{ $item->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-indigo-50 dark:bg-gray-700 dark:hover:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold text-[11px] transition-colors cursor-pointer">
                                    {{ __('Inspeksi') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                <div class="text-2xl mb-1">📭</div>
                                <div class="font-bold text-sm">{{ __('Tidak ada data log yang sesuai filter.') }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">{{ __('Coba reset filter atau buat sampel log untuk melihat simulasi.') }}</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    <!-- Inspector Modal -->
    @if($inspectedLog)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" wire:click="closeInspector"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all w-full max-w-3xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                    
                    <!-- Modal Header -->
                    <div class="flex items-start justify-between gap-4 pb-3 border-b border-gray-100 dark:border-gray-700">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black border {{ $inspectedLog->level_badge['bg'] }}">
                                    {{ $inspectedLog->level }}
                                </span>
                                <span class="text-xs font-bold text-gray-500">{{ $inspectedLog->category_meta['icon'] }} {{ $inspectedLog->category_meta['label'] }}</span>
                                <span class="text-xs text-gray-400 font-mono">• {{ $inspectedLog->action }}</span>
                            </div>
                            <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white" id="modal-title">
                                {{ $inspectedLog->message }}
                            </h3>
                            <div class="text-[11px] font-mono text-gray-400 mt-0.5">
                                {{ $inspectedLog->created_at->format('l, d F Y - H:i:s T') }}
                            </div>
                        </div>

                        <button type="button" wire:click="closeInspector" class="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Metadata Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/70 border border-gray-200/70 dark:border-gray-700/60 text-xs">
                        <div>
                            <span class="block text-[10px] text-gray-400 font-bold uppercase">{{ __('User') }}</span>
                            <span class="font-bold text-gray-800 dark:text-gray-200">{{ $inspectedLog->user ? $inspectedLog->user->name : 'Guest' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 font-bold uppercase">{{ __('IP Address') }}</span>
                            <span class="font-mono font-bold text-gray-800 dark:text-gray-200">{{ $inspectedLog->ip_address ?: '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 font-bold uppercase">{{ __('Durasi') }}</span>
                            <span class="font-mono font-bold text-gray-800 dark:text-gray-200">{{ $inspectedLog->execution_time_ms ? $inspectedLog->execution_time_ms . ' ms' : '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 font-bold uppercase">{{ __('Metode / URL') }}</span>
                            <span class="font-mono font-bold text-gray-800 dark:text-gray-200 truncate block">{{ $inspectedLog->method ?: '-' }}</span>
                        </div>
                    </div>

                    <!-- Full URL & User Agent -->
                    @if($inspectedLog->url || $inspectedLog->user_agent)
                        <div class="text-xs space-y-1 p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800 font-mono text-gray-600 dark:text-gray-400">
                            @if($inspectedLog->url)
                                <div><span class="font-bold text-gray-400">URL:</span> {{ $inspectedLog->url }}</div>
                            @endif
                            @if($inspectedLog->user_agent)
                                <div class="truncate"><span class="font-bold text-gray-400">UA:</span> {{ $inspectedLog->user_agent }}</div>
                            @endif
                        </div>
                    @endif

                    <!-- Context Payload / Stack Trace JSON -->
                    <div>
                        <div class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                            <span>{{ __('Payload Konteks / Stack Trace') }}</span>
                            <span class="text-[10px] font-mono text-gray-400">{{ __('JSON Sanitized') }}</span>
                        </div>
                        <div class="p-4 rounded-2xl bg-gray-900 text-emerald-400 font-mono text-xs overflow-x-auto max-h-72 border border-gray-800">
                            @if($inspectedLog->context)
                                <pre>{{ json_encode($inspectedLog->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            @else
                                <span class="text-gray-500 italic">{{ __('Tidak ada payload konteks tambahan untuk log ini.') }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex justify-end pt-2">
                        <button type="button" wire:click="closeInspector" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                            {{ __('Tutup') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
