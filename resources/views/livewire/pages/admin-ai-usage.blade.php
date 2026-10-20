<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $searchUser = '';
    public string $filterFeature = 'all';
    public string $filterStatus = 'all';
    public string $timeRange = 'all'; // all, today, 7days, 30days
    public int $perPage = 20;

    // Inspector Modal
    public ?int $inspectLogId = null;
    public string $inspectTab = 'output'; // output, prompt, meta
    public string $inspectViewMode = 'formatted'; // formatted, raw

    // Inline row expansion toggle
    public array $expandedLogIds = [];

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

    public function updatedSearchUser()
    {
        $this->resetPage();
    }

    public function updatedFilterFeature()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    public function updatedTimeRange()
    {
        $this->resetPage();
    }

    public function toggleExpand(int $logId): void
    {
        if (in_array($logId, $this->expandedLogIds)) {
            $this->expandedLogIds = array_values(array_diff($this->expandedLogIds, [$logId]));
        } else {
            $this->expandedLogIds[] = $logId;
        }
    }

    public function openInspect(int $logId, string $tab = 'output'): void
    {
        $this->inspectLogId = $logId;
        $this->inspectTab = $tab;
    }

    public function closeInspect(): void
    {
        $this->inspectLogId = null;
    }

    public function setInspectTab(string $tab): void
    {
        $this->inspectTab = $tab;
    }

    public function setInspectViewMode(string $mode): void
    {
        $this->inspectViewMode = $mode;
    }

    public function getInspectedLogProperty(): ?AiUsageLog
    {
        return $this->inspectLogId ? AiUsageLog::with('user')->find($this->inspectLogId) : null;
    }

    public function parseResponseData(?string $raw): ?array
    {
        if (empty($raw)) return null;
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw));
        $data = json_decode($clean, true);
        return is_array($data) ? $data : null;
    }

    public function pruneOldLogs(int $days = 30): void
    {
        $count = AiUsageLog::where('created_at', '<', now()->subDays($days))->delete();
        $this->dispatch('toast', message: "Berhasil membersihkan {$count} log AI (> {$days} hari).", type: 'info');
    }

    public function exportCsv(): StreamedResponse
    {
        $fileName = 'atoma_ai_telemetry_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'ID', 'Waktu (WIB)', 'User', 'Email', 'Fitur', 'Model',
                'Prompt Tokens', 'Completion Tokens', 'Total Tokens', 'Latency (ms)',
                'Status', 'Prompt', 'Respon AI', 'Error Message'
            ]);

            $query = AiUsageLog::with('user')->orderByDesc('id');
            if ($this->filterFeature !== 'all') {
                $query->where('feature', $this->filterFeature);
            }
            if ($this->filterStatus !== 'all') {
                $query->where('status', $this->filterStatus);
            }
            if ($this->timeRange === 'today') {
                $query->whereDate('created_at', now()->toDateString());
            } elseif ($this->timeRange === '7days') {
                $query->where('created_at', '>=', now()->subDays(7));
            } elseif ($this->timeRange === '30days') {
                $query->where('created_at', '>=', now()->subDays(30));
            }

            if (!empty($this->searchUser)) {
                $s = trim($this->searchUser);
                $query->where(function ($q) use ($s) {
                    $q->where('prompt_text', 'like', "%{$s}%")
                      ->orWhere('response_text', 'like', "%{$s}%")
                      ->orWhereHas('user', function ($uq) use ($s) {
                          $uq->where('name', 'like', "%{$s}%")
                             ->orWhere('email', 'like', "%{$s}%");
                      });
                });
            }

            $query->chunk(150, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->setTimezone('Asia/Jakarta')->toDateTimeString(),
                        $log->user->name ?? 'System',
                        $log->user->email ?? '-',
                        $log->feature,
                        $log->model,
                        $log->prompt_tokens,
                        $log->completion_tokens,
                        $log->total_tokens,
                        $log->execution_time_ms,
                        $log->status,
                        $log->prompt_text,
                        $log->response_text,
                        $log->error_message,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    public function getStatsProperty(): array
    {
        $sub60 = now()->subSeconds(60);

        $totalRequests = AiUsageLog::count();
        $totalTokens = (int) AiUsageLog::sum('total_tokens');
        $promptTokens = (int) AiUsageLog::sum('prompt_tokens');
        $completionTokens = (int) AiUsageLog::sum('completion_tokens');

        $rpm = AiUsageLog::where('created_at', '>=', $sub60)->count();
        $tpm = (int) AiUsageLog::where('created_at', '>=', $sub60)->sum('total_tokens');

        $successCount = AiUsageLog::where('status', 'success')->count();
        $rateLimitedCount = AiUsageLog::where('status', 'rate_limited')->count();
        $fallbackCount = AiUsageLog::where('status', 'fallback')->count();
        $errorCount = AiUsageLog::where('status', 'error')->count();

        $successRate = $totalRequests > 0 ? round(($successCount / $totalRequests) * 100, 1) : 100;
        $avgLatency = (int) round(AiUsageLog::avg('execution_time_ms') ?: 0);

        // Cost estimation based on Gemini 1.5/2.5/3 Flash Lite: $0.075 / 1M prompt, $0.30 / 1M completion
        $costUsd = ($promptTokens / 1000000 * 0.075) + ($completionTokens / 1000000 * 0.30);
        $costIdr = (int) round($costUsd * 16200);

        // Feature Distribution
        $featureCounts = AiUsageLog::select('feature', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_tokens) as tokens'))
            ->groupBy('feature')
            ->orderByDesc('count')
            ->get();

        $featureLabels = [
            'task_decomposer' => ['name' => 'Breakdown Tugas AI', 'icon' => '⚡', 'color' => 'indigo'],
            'weekly_insight' => ['name' => 'Weekly Insight Coach', 'icon' => '📊', 'color' => 'emerald'],
            'daily_summary' => ['name' => 'Ringkasan Harian', 'icon' => '📝', 'color' => 'blue'],
            'skill_planner' => ['name' => 'Skill Roadmap Planner', 'icon' => '🎯', 'color' => 'purple'],
            'ai_companion' => ['name' => 'AI Companion Chat', 'icon' => '💬', 'color' => 'pink'],
        ];

        $distribution = [];
        foreach ($featureCounts as $fc) {
            $meta = $featureLabels[$fc->feature] ?? ['name' => ucfirst($fc->feature), 'icon' => '🤖', 'color' => 'gray'];
            $pct = $totalRequests > 0 ? round(($fc->count / $totalRequests) * 100, 1) : 0;
            $distribution[] = [
                'feature' => $fc->feature,
                'name' => $meta['name'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
                'count' => (int) $fc->count,
                'tokens' => (int) $fc->tokens,
                'percentage' => $pct,
            ];
        }

        return [
            'total_requests' => $totalRequests,
            'total_tokens' => $totalTokens,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'rpm' => $rpm,
            'tpm' => $tpm,
            'avg_latency' => $avgLatency,
            'success_rate' => $successRate,
            'rate_limited_count' => $rateLimitedCount,
            'fallback_count' => $fallbackCount,
            'error_count' => $errorCount,
            'cost_usd' => $costUsd,
            'cost_idr' => $costIdr,
            'distribution' => $distribution,
        ];
    }

    public function getUserSummariesProperty()
    {
        return AiUsageLog::select(
                'user_id',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('MAX(created_at) as last_activity')
            )
            ->with('user')
            ->groupBy('user_id')
            ->orderByDesc('request_count')
            ->limit(8)
            ->get();
    }

    public function getLogsProperty()
    {
        $query = AiUsageLog::with('user')->orderByDesc('id');

        if ($this->filterFeature !== 'all') {
            $query->where('feature', $this->filterFeature);
        }

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->timeRange === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($this->timeRange === '7days') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($this->timeRange === '30days') {
            $query->where('created_at', '>=', now()->subDays(30));
        }

        if (!empty($this->searchUser)) {
            $s = trim($this->searchUser);
            $query->where(function ($q) use ($s) {
                $q->where('prompt_text', 'like', "%{$s}%")
                  ->orWhere('response_text', 'like', "%{$s}%")
                  ->orWhere('model', 'like', "%{$s}%")
                  ->orWhereHas('user', function ($uq) use ($s) {
                      $uq->where('name', 'like', "%{$s}%")
                         ->orWhere('email', 'like', "%{$s}%");
                  });
            });
        }

        return $query->paginate($this->perPage);
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-24 pt-4 sm:pt-6" x-data="{ copyText(text) { navigator.clipboard.writeText(text); $dispatch('toast', { message: 'Berhasil disalin ke clipboard!', type: 'success' }); } }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-8">
        
        <!-- Unified Admin Navigation Bar -->
        <x-admin-navbar active="ai" />

        <!-- Header & Action Bar -->
        <div class="rounded-3xl bg-gradient-to-r from-[#1b3cb8] via-indigo-600 to-purple-700 text-white p-6 sm:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute -right-8 -bottom-8 w-48 h-48 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-1/3 -top-10 w-40 h-40 bg-purple-500/20 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-white/20 text-white border border-white/30 backdrop-blur-xs">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>{{ __('AI Telemetry & Intelligence Observability') }}</span>
                        </span>
                        <a href="{{ route('admin.feedbacks') }}" wire:navigate class="text-xs text-white/80 hover:text-white underline font-bold flex items-center gap-1">
                            <span>Kotak Surat Pengguna</span> &rarr;
                        </a>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight flex items-center gap-3">
                        <span>{{ __('Dashboard Telemetri & Analisis AI') }}</span>
                        <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center border border-white/30 shrink-0">
                            🤖
                        </div>
                    </h1>
                    <p class="text-xs sm:text-sm text-white/80 mt-1 max-w-2xl leading-relaxed">
                        Pantau seluruh aktivitas model Gemini AI secara realtime. Baca prompt input pengguna, periksa respon output AI, analisis latensi kecepatan, estimasi biaya token, serta pantau kegagalan sistem & rate limit.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <button wire:click="exportCsv" 
                            class="px-4 py-2.5 rounded-2xl bg-white text-indigo-700 hover:bg-gray-100 text-xs font-extrabold transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>{{ __('Ekspor CSV') }}</span>
                    </button>

                    <button wire:click="pruneOldLogs(30)" 
                            wire:confirm="Yakin ingin membersihkan log AI yang lebih lama dari 30 hari?"
                            class="px-3.5 py-2.5 rounded-2xl bg-white/15 hover:bg-white/25 text-white text-xs font-bold transition-all border border-white/20 flex items-center gap-1.5 cursor-pointer"
                            title="Bersihkan Log Lama">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span>{{ __('Prune > 30 Hari') }}</span>
                    </button>

                    <a href="{{ route('dashboard') }}" wire:navigate class="px-3.5 py-2.5 rounded-2xl bg-black/20 hover:bg-black/30 text-white text-xs font-bold transition-all border border-white/15">
                        &larr; {{ __('Dashboard') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- 6 Key Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <!-- 1. Realtime RPM -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-xs border border-gray-100 dark:border-gray-700/80 transition-all hover:shadow-md">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-wider">{{ __('Live RPM') }}</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                </div>
                <div class="text-2xl font-black text-gray-900 dark:text-white tracking-tight font-mono">
                    {{ $this->stats['rpm'] }} <span class="text-xs font-bold text-gray-400 font-sans">req/m</span>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ __('Permintaan 60 detik') }}</p>
            </div>

            <!-- 2. Realtime TPM -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-xs border border-gray-100 dark:border-gray-700/80 transition-all hover:shadow-md">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-wider">{{ __('Live TPM') }}</span>
                    <span class="text-xs font-mono text-indigo-500 font-bold">⚡</span>
                </div>
                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 tracking-tight font-mono">
                    {{ number_format($this->stats['tpm']) }}
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ __('Token/menit saat ini') }}</p>
            </div>

            <!-- 3. Total Tokens Consumed -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-xs border border-gray-100 dark:border-gray-700/80 transition-all hover:shadow-md">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-wider">{{ __('Total Token') }}</span>
                    <span class="text-xs text-amber-500">🪙</span>
                </div>
                <div class="text-2xl font-black text-amber-500 tracking-tight font-mono">
                    {{ number_format($this->stats['total_tokens']) }}
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ $this->stats['total_requests'] }} {{ __('total panggilan') }}</p>
            </div>

            <!-- 4. Rata-Rata Latensi -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-xs border border-gray-100 dark:border-gray-700/80 transition-all hover:shadow-md">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-wider">{{ __('Rata-rata Latensi') }}</span>
                    <span class="text-xs text-cyan-500">⏱️</span>
                </div>
                <div class="text-2xl font-black text-cyan-600 dark:text-cyan-400 tracking-tight font-mono">
                    {{ $this->stats['avg_latency'] }} <span class="text-xs font-bold text-gray-400 font-sans">ms</span>
                </div>
                <p class="text-[11px] font-bold mt-1 {{ $this->stats['avg_latency'] < 1500 ? 'text-emerald-500' : ($this->stats['avg_latency'] < 3000 ? 'text-amber-500' : 'text-rose-500') }}">
                    {{ $this->stats['avg_latency'] < 1500 ? '⚡ Respons Cepat' : ($this->stats['avg_latency'] < 3000 ? '⏳ Cukup Cepat' : '⚠️ Agak Lambat') }}
                </p>
            </div>

            <!-- 5. Tingkat Sukses & Health -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-xs border border-gray-100 dark:border-gray-700/80 transition-all hover:shadow-md">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-wider">{{ __('Success Rate') }}</span>
                    <span class="text-xs text-emerald-500">✅</span>
                </div>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight font-mono">
                    {{ $this->stats['success_rate'] }}%
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                    {{ $this->stats['rate_limited_count'] }} {{ __('limit') }} / {{ $this->stats['error_count'] }} {{ __('err') }}
                </p>
            </div>

            <!-- 6. Estimasi Biaya API -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-xs border border-gray-100 dark:border-gray-700/80 transition-all hover:shadow-md">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-wider">{{ __('Estimasi Biaya') }}</span>
                    <span class="text-xs text-purple-500">💵</span>
                </div>
                <div class="text-2xl font-black text-purple-600 dark:text-purple-400 tracking-tight font-mono">
                    ${{ number_format($this->stats['cost_usd'], 4) }}
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 font-mono">
                    ≈ Rp {{ number_format($this->stats['cost_idr']) }}
                </p>
            </div>
        </div>

        <!-- Section: Feature Distribution & Top Users (2-Column Grid) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Feature Distribution Analysis -->
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-xs border border-gray-100 dark:border-gray-700/80 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700/80 pb-3">
                    <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="text-base">📊</span>
                        <span>{{ __('Distribusi Fitur AI') }}</span>
                    </h3>
                    <span class="text-xs text-gray-400 font-bold">{{ count($this->stats['distribution']) }} Fitur</span>
                </div>

                <div class="space-y-3.5">
                    @forelse($this->stats['distribution'] as $item)
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-extrabold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                                    <span>{{ $item['icon'] }}</span>
                                    <span>{{ $item['name'] }}</span>
                                </span>
                                <span class="font-mono text-gray-500 dark:text-gray-400 font-bold">
                                    {{ $item['count'] }} call ({{ $item['percentage'] }}%)
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                                <div class="bg-indigo-600 dark:bg-indigo-500 h-full rounded-full transition-all duration-500" style="width: {{ $item['percentage'] }}%"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 font-mono">
                                <span>{{ $item['feature'] }}</span>
                                <span>{{ number_format($item['tokens']) }} tokens</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-xs text-gray-400">{{ __('Belum ada aktivitas fitur AI.') }}</div>
                    @endforelse
                </div>
            </div>

            <!-- Top AI Users Breakdown -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-xs border border-gray-100 dark:border-gray-700/80 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700/80 pb-3">
                    <h3 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="text-base">👥</span>
                        <span>{{ __('Pengguna AI Teraktif') }}</span>
                    </h3>
                    <span class="text-xs text-gray-400 font-bold">Top Pengguna Berdasarkan Permintaan</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700/60 text-gray-400 uppercase font-black tracking-wider">
                                <th class="py-2.5 px-3">Pengguna</th>
                                <th class="py-2.5 px-3 text-center">Panggilan AI</th>
                                <th class="py-2.5 px-3 text-center">Konsumsi Token</th>
                                <th class="py-2.5 px-3 text-right">Aktivitas Terakhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-750 font-medium">
                            @forelse($this->userSummaries as $row)
                                <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-750/60 transition-colors">
                                    <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                                        <img src="{{ $row->user->avatar_url ?? asset('images/default-avatar.svg') }}" class="w-7 h-7 rounded-full bg-gray-200 object-cover border border-gray-200 dark:border-gray-700" alt="Avatar">
                                        <div class="truncate max-w-[200px]">
                                            <div class="truncate font-black">{{ $row->user->name ?? 'System / Anonymous' }}</div>
                                            <div class="text-[10px] text-gray-400 font-normal truncate">{{ $row->user->email ?? '-' }}</div>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">
                                        {{ number_format($row->request_count) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-black text-amber-500 font-mono">
                                        {{ number_format($row->total_tokens) }} <span class="text-[9px] text-gray-400 uppercase font-bold">tok</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-400 font-mono text-[11px]">
                                        {{ \Carbon\Carbon::parse($row->last_activity)->diffForHumans() }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-gray-400">Belum ada aktivitas pengguna.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Section: Realtime Request Logs & Output Explorer -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-xs border border-gray-100 dark:border-gray-700/80 space-y-6">
            
            <!-- Controls & Filters Bar -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700/80 pb-5">
                <div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <span>🔍</span>
                        <span>{{ __('Audit Log & Eksplorasi Output AI') }}</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Klik pada baris log untuk memeriksa respon lengkap, JSON terstruktur, dan prompt pengguna.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-60">
                        <input type="text" 
                               wire:model.live.debounce.300ms="searchUser" 
                               placeholder="Cari prompt, output, user..." 
                               class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 pl-8 pr-7 py-2 focus:ring-2 focus:ring-indigo-500">
                        <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        @if($searchUser)
                            <button wire:click="$set('searchUser', '')" class="absolute right-2.5 top-2 text-gray-400 hover:text-gray-600 text-xs cursor-pointer">✕</button>
                        @endif
                    </div>

                    <!-- Filter Rentang Waktu -->
                    <select wire:model.live="timeRange" class="text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 py-2 pl-3 pr-8 focus:ring-indigo-500 cursor-pointer">
                        <option value="all">Semua Waktu</option>
                        <option value="today">Hari Ini</option>
                        <option value="7days">7 Hari Terakhir</option>
                        <option value="30days">30 Hari Terakhir</option>
                    </select>

                    <!-- Filter Fitur -->
                    <select wire:model.live="filterFeature" class="text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 py-2 pl-3 pr-8 focus:ring-indigo-500 cursor-pointer">
                        <option value="all">Semua Fitur</option>
                        <option value="task_decomposer">Breakdown Tugas (task_decomposer)</option>
                        <option value="weekly_insight">Weekly Insight (weekly_insight)</option>
                        <option value="skill_planner">Skill Planner (skill_planner)</option>
                        <option value="daily_summary">Ringkasan Harian (daily_summary)</option>
                        <option value="ai_companion">AI Companion (ai_companion)</option>
                    </select>

                    <!-- Filter Status -->
                    <select wire:model.live="filterStatus" class="text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 py-2 pl-3 pr-8 focus:ring-indigo-500 cursor-pointer">
                        <option value="all">Semua Status</option>
                        <option value="success">Success</option>
                        <option value="fallback">Fallback Engine</option>
                        <option value="rate_limited">Rate Limited (429)</option>
                        <option value="error">Error</option>
                    </select>
                </div>
            </div>

            <!-- Table of AI Telemetry Logs -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-400 uppercase font-black tracking-wider">
                            <th class="py-3 px-3">Waktu</th>
                            <th class="py-3 px-3">Pengguna</th>
                            <th class="py-3 px-3">Fitur & Model</th>
                            <th class="py-3 px-3">Prompt Input & Output AI</th>
                            <th class="py-3 px-3 text-center">Tokens</th>
                            <th class="py-3 px-3 text-center">Latensi</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-750 font-medium">
                        @forelse($this->logs as $log)
                            @php
                                $isExpanded = in_array($log->id, $expandedLogIds);
                                $parsedJson = $this->parseResponseData($log->response_text);
                            @endphp
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-750/70 transition-colors group">
                                <!-- Waktu -->
                                <td class="py-3.5 px-3 text-gray-500 dark:text-gray-400 whitespace-nowrap font-mono">
                                    <div class="font-bold text-gray-800 dark:text-gray-200">{{ $log->created_at->setTimezone('Asia/Jakarta')->format('H:i:s') }}</div>
                                    <div class="text-[10px] text-gray-400">{{ $log->created_at->format('d M') }} ({{ $log->created_at->diffForHumans() }})</div>
                                </td>

                                <!-- User -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <img src="{{ $log->user->avatar_url ?? asset('images/default-avatar.svg') }}" class="w-7 h-7 rounded-full bg-gray-200 object-cover border border-gray-200 dark:border-gray-700" alt="Avatar">
                                        <div class="truncate max-w-[140px]">
                                            <span class="font-bold text-gray-900 dark:text-white truncate block">{{ $log->user->name ?? 'System' }}</span>
                                            <span class="text-[10px] text-gray-400 font-normal truncate block">{{ $log->user->email ?? '-' }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Fitur & Model -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded-lg text-[11px] font-black font-mono bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/40">
                                        {{ $log->feature }}
                                    </span>
                                    <span class="block text-[10px] text-gray-400 font-mono mt-0.5">{{ $log->model }}</span>
                                </td>

                                <!-- Prompt & Response Preview -->
                                <td class="py-3.5 px-3 max-w-md">
                                    <div class="space-y-1.5">
                                        <!-- Prompt -->
                                        <div class="flex items-start gap-1.5">
                                            <span class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 text-[10px] font-black shrink-0">PROMPT</span>
                                            <span class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate italic cursor-pointer hover:underline" wire:click="openInspect({{ $log->id }}, 'prompt')">
                                                "{{ $log->prompt_text ?? '-' }}"
                                            </span>
                                        </div>

                                        <!-- Response Preview -->
                                        @if($log->response_text)
                                            <div class="flex items-start gap-1.5">
                                                <span class="px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 text-[10px] font-black shrink-0">OUTPUT</span>
                                                <span class="text-[11px] text-gray-600 dark:text-gray-400 truncate cursor-pointer hover:underline flex-1" wire:click="openInspect({{ $log->id }}, 'output')">
                                                    @if($parsedJson && isset($parsedJson['title']))
                                                        <span class="font-bold text-purple-600 dark:text-purple-400">{{ $parsedJson['title'] }}</span> ({{ count($parsedJson['subtasks'] ?? []) }} subtasks)
                                                    @else
                                                        {{ \Illuminate\Support\Str::limit($log->response_text, 75) }}
                                                    @endif
                                                </span>
                                            </div>
                                        @elseif($log->error_message)
                                            <div class="text-[11px] text-rose-600 dark:text-rose-400 truncate font-mono">
                                                ⚠️ {{ $log->error_message }}
                                            </div>
                                        @endif

                                        <!-- Inline Quick Toggle -->
                                        @if($isExpanded)
                                            <div class="mt-2 p-3 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 space-y-2 text-xs">
                                                <div class="font-bold text-gray-700 dark:text-gray-300">Prompt Lengkap:</div>
                                                <div class="text-gray-600 dark:text-gray-400 font-mono bg-white dark:bg-gray-800 p-2 rounded-xl whitespace-pre-wrap select-all">{{ $log->prompt_text }}</div>
                                                
                                                <div class="font-bold text-gray-700 dark:text-gray-300 pt-1">Respon AI Lengkap:</div>
                                                <div class="text-gray-600 dark:text-gray-400 font-mono bg-white dark:bg-gray-800 p-2 rounded-xl whitespace-pre-wrap select-all max-h-48 overflow-y-auto">{{ $log->response_text }}</div>
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <!-- Tokens -->
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    <span class="font-black text-amber-500 font-mono text-sm">{{ number_format($log->total_tokens) }}</span>
                                    <span class="text-[10px] text-gray-400 block font-mono">({{ $log->prompt_tokens }}/{{ $log->completion_tokens }})</span>
                                </td>

                                <!-- Latensi -->
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    <span class="font-mono text-xs font-bold {{ $log->execution_time_ms < 1000 ? 'text-emerald-500' : ($log->execution_time_ms < 2500 ? 'text-amber-500' : 'text-rose-500') }}">
                                        {{ $log->execution_time_ms }} ms
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    @if($log->status === 'success')
                                        <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700/70 px-2.5 py-0.5 rounded-full text-[11px] font-black">
                                            ✓ SUCCESS
                                        </span>
                                    @elseif($log->status === 'fallback')
                                        <span class="inline-flex items-center gap-1 bg-indigo-100 text-indigo-800 dark:bg-indigo-950/80 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700/70 px-2.5 py-0.5 rounded-full text-[11px] font-black">
                                            ⚡ FALLBACK
                                        </span>
                                    @elseif($log->status === 'rate_limited')
                                        <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-300 dark:border-amber-700/70 px-2.5 py-0.5 rounded-full text-[11px] font-black" title="{{ $log->error_message }}">
                                            ⏳ RATE LIMIT
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300 dark:border-rose-700/70 px-2.5 py-0.5 rounded-full text-[11px] font-black" title="{{ $log->error_message }}">
                                            ✕ ERROR
                                        </span>
                                    @endif
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-3.5 px-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button wire:click="openInspect({{ $log->id }}, 'output')" 
                                                class="px-2.5 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/50 hover:bg-purple-100 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-300 font-black text-xs transition-all border border-purple-200 dark:border-purple-800/60 flex items-center gap-1 cursor-pointer"
                                                title="Buka Output & Detail AI">
                                            <span>👁️</span>
                                            <span>Detail</span>
                                        </button>

                                        <button wire:click="toggleExpand({{ $log->id }})" 
                                                class="p-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-750 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-300 text-xs transition-all cursor-pointer"
                                                title="{{ $isExpanded ? 'Tutup Inline' : 'Buka Inline' }}">
                                            {{ $isExpanded ? '▲' : '▼' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-gray-400">
                                    <div class="text-3xl mb-2">🤖</div>
                                    <div class="font-bold text-sm text-gray-500 dark:text-gray-400">{{ __('Tidak ada data telemetri AI yang cocok.') }}</div>
                                    <div class="text-xs text-gray-400 mt-1">{{ __('Coba ganti kata kunci pencarian atau filter status/fitur.') }}</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700/80">
                {{ $this->logs->links() }}
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- AI OUTPUT & PROMPT INSPECTOR MODAL -->
    <!-- ========================================================================= -->
    @if($this->inspectedLog)
        @php
            $logItem = $this->inspectedLog;
            $parsedData = $this->parseResponseData($logItem->response_text);
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/70 backdrop-blur-xs flex items-center justify-center p-4" x-transition>
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-700 w-full max-w-3xl overflow-hidden relative"
                 @click.away="$wire.closeInspect()">
                
                <!-- Modal Header -->
                <div class="p-5 sm:p-6 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-4 bg-gray-50/50 dark:bg-gray-800/50">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-lg font-black shrink-0 shadow-md">
                            🤖
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base font-black text-gray-900 dark:text-white truncate">
                                    {{ __('Inspeksi Output & Log AI') }} #{{ $logItem->id }}
                                </h3>
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-mono font-black bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                    {{ $logItem->feature }}
                                </span>
                                @if($logItem->status === 'success')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700/60">
                                        ✓ SUCCESS
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300 dark:border-rose-700/60">
                                        {{ strtoupper($logItem->status) }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-0.5">
                                {{ $logItem->created_at->format('d M Y, H:i:s') }} WIB • User: {{ $logItem->user->name ?? 'System' }} ({{ $logItem->user->email ?? '-' }})
                            </p>
                        </div>
                    </div>

                    <button wire:click="closeInspect" 
                            class="w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-400 hover:text-gray-700 dark:hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer transition-colors shrink-0">
                        ✕
                    </button>
                </div>

                <!-- Navigation Tabs Inside Modal -->
                <div class="flex items-center gap-2 px-6 pt-4 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <button wire:click="setInspectTab('output')" 
                            class="pb-3 px-3 text-xs font-black transition-all border-b-2 cursor-pointer flex items-center gap-1.5 {{ $inspectTab === 'output' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        <span>🤖 Output AI (Respon)</span>
                    </button>
                    <button wire:click="setInspectTab('prompt')" 
                            class="pb-3 px-3 text-xs font-black transition-all border-b-2 cursor-pointer flex items-center gap-1.5 {{ $inspectTab === 'prompt' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        <span>💬 Prompt Pengguna (Input)</span>
                    </button>
                    <button wire:click="setInspectTab('meta')" 
                            class="pb-3 px-3 text-xs font-black transition-all border-b-2 cursor-pointer flex items-center gap-1.5 {{ $inspectTab === 'meta' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        <span>⚙️ Metrik & Diagnostik</span>
                    </button>
                </div>

                <!-- Modal Body Content -->
                <div class="p-6 max-h-[68vh] overflow-y-auto space-y-4">
                    
                    <!-- TAB 1: OUTPUT AI -->
                    @if($inspectTab === 'output')
                        <div class="space-y-4">
                            <!-- Switcher Formatted vs Raw -->
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 p-1 rounded-xl text-xs font-bold">
                                    <button wire:click="setInspectViewMode('formatted')" 
                                            class="px-3 py-1 rounded-lg transition-all cursor-pointer {{ $inspectViewMode === 'formatted' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-300 shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                                        🎨 Format Visual
                                    </button>
                                    <button wire:click="setInspectViewMode('raw')" 
                                            class="px-3 py-1 rounded-lg transition-all cursor-pointer {{ $inspectViewMode === 'raw' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-300 shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                                        📄 Raw JSON / Text
                                    </button>
                                </div>

                                <button @click="copyText(`{{ addslashes($logItem->response_text ?? '') }}`)" 
                                        class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-300 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                                    <span>📋</span>
                                    <span>Salin Output AI</span>
                                </button>
                            </div>

                            @if($inspectViewMode === 'formatted' && $parsedData)
                                <!-- Formatted Structured View (Task Decomposer) -->
                                @if($logItem->feature === 'task_decomposer')
                                    <div class="rounded-2xl p-5 bg-purple-50/50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/40 space-y-4">
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-700">
                                                    {{ $parsedData['category'] ?? 'Task' }}
                                                </span>
                                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300">
                                                    Priority: {{ $parsedData['priority'] ?? 'Normal' }}
                                                </span>
                                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-cyan-100 dark:bg-cyan-900/60 text-cyan-700 dark:text-cyan-300 font-mono">
                                                    ⏱️ {{ $parsedData['estimated_minutes'] ?? 45 }} Menit
                                                </span>
                                            </div>
                                            <h4 class="text-lg font-black text-gray-900 dark:text-white mt-2">
                                                {{ $parsedData['title'] ?? 'Tanpa Judul' }}
                                            </h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">
                                                {{ $parsedData['description'] ?? '-' }}
                                            </p>
                                        </div>

                                        <!-- Subtasks List -->
                                        @if(!empty($parsedData['subtasks']))
                                            <div class="space-y-2 pt-2 border-t border-purple-100 dark:border-purple-900/40">
                                                <div class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                                    Rincian Subtugas ({{ count($parsedData['subtasks']) }}):
                                                </div>
                                                <div class="space-y-2">
                                                    @foreach($parsedData['subtasks'] as $idx => $st)
                                                        <div class="flex items-start gap-2.5 p-2.5 rounded-xl bg-white dark:bg-gray-800 border border-purple-100 dark:border-purple-900/30 text-xs font-medium text-gray-800 dark:text-gray-200">
                                                            <span class="w-5 h-5 rounded-full bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">
                                                                {{ $idx + 1 }}
                                                            </span>
                                                            <span class="leading-relaxed">{{ $st }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @elseif($logItem->feature === 'weekly_insight')
                                    <!-- Formatted Weekly Insight View -->
                                    <div class="space-y-3">
                                        @if(!empty($parsedData['observasi']))
                                            <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/40">
                                                <div class="text-xs font-black text-blue-700 dark:text-blue-300 uppercase tracking-wider mb-1">🔍 Observasi Produktivitas:</div>
                                                <div class="text-xs text-gray-800 dark:text-gray-200 leading-relaxed">{{ $parsedData['observasi'] }}</div>
                                            </div>
                                        @endif
                                        @if(!empty($parsedData['tantangan']))
                                            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40">
                                                <div class="text-xs font-black text-amber-700 dark:text-amber-300 uppercase tracking-wider mb-1">⚠️ Tantangan & Hambatan:</div>
                                                <div class="text-xs text-gray-800 dark:text-gray-200 leading-relaxed">{{ $parsedData['tantangan'] }}</div>
                                            </div>
                                        @endif
                                        @if(!empty($parsedData['afirmasi']))
                                            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/40">
                                                <div class="text-xs font-black text-emerald-700 dark:text-emerald-300 uppercase tracking-wider mb-1">✨ Rekomendasi & Afirmasi:</div>
                                                <div class="text-xs text-gray-800 dark:text-gray-200 leading-relaxed">{{ $parsedData['afirmasi'] }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <!-- Generic Structured JSON Card -->
                                    <div class="bg-gray-50 dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-700 font-mono text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap overflow-x-auto">
                                        {{ json_encode($parsedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}
                                    </div>
                                @endif
                            @else
                                <!-- Raw Output Code Container -->
                                <div class="relative">
                                    <pre class="bg-gray-900 text-gray-100 rounded-2xl p-4 text-xs font-mono whitespace-pre-wrap leading-relaxed max-h-96 overflow-y-auto border border-gray-700 select-all">{{ $logItem->response_text ?? 'Tidak ada teks output yang tercatat.' }}</pre>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- TAB 2: PROMPT INPUT -->
                    @if($inspectTab === 'prompt')
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-black text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                    Pesan / Teks Prompt yang Dikirim ke Gemini:
                                </div>
                                <button @click="copyText(`{{ addslashes($logItem->prompt_text ?? '') }}`)" 
                                        class="px-3 py-1 rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-300 text-xs font-bold transition-all flex items-center gap-1 cursor-pointer">
                                    <span>📋</span>
                                    <span>Salin Prompt</span>
                                </button>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-700 font-mono text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed select-all">
                                {{ $logItem->prompt_text ?? 'Tidak ada prompt tercatat.' }}
                            </div>
                        </div>
                    @endif

                    <!-- TAB 3: METRIKS & DIAGNOSTIK -->
                    @if($inspectTab === 'meta')
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[10px] text-gray-400 font-bold uppercase">Model</div>
                                    <div class="text-xs font-black text-gray-900 dark:text-white font-mono mt-0.5">{{ $logItem->model }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[10px] text-gray-400 font-bold uppercase">Latensi Eksekusi</div>
                                    <div class="text-xs font-black text-cyan-500 font-mono mt-0.5">{{ $logItem->execution_time_ms }} ms ({{ round($logItem->execution_time_ms / 1000, 2) }}s)</div>
                                </div>
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[10px] text-gray-400 font-bold uppercase">Total Token</div>
                                    <div class="text-xs font-black text-amber-500 font-mono mt-0.5">{{ number_format($logItem->total_tokens) }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[10px] text-gray-400 font-bold uppercase">Prompt Tokens</div>
                                    <div class="text-xs font-black text-gray-700 dark:text-gray-300 font-mono mt-0.5">{{ number_format($logItem->prompt_tokens) }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[10px] text-gray-400 font-bold uppercase">Completion Tokens</div>
                                    <div class="text-xs font-black text-gray-700 dark:text-gray-300 font-mono mt-0.5">{{ number_format($logItem->completion_tokens) }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                                    <div class="text-[10px] text-gray-400 font-bold uppercase">Estimasi Biaya</div>
                                    @php
                                        $cost = ($logItem->prompt_tokens / 1000000 * 0.075) + ($logItem->completion_tokens / 1000000 * 0.30);
                                    @endphp
                                    <div class="text-xs font-black text-purple-600 dark:text-purple-400 font-mono mt-0.5">${{ number_format($cost, 6) }}</div>
                                </div>
                            </div>

                            @if($logItem->error_message)
                                <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 space-y-1">
                                    <div class="text-xs font-black text-rose-700 dark:text-rose-300 uppercase tracking-wider">Pesan Error / Diagnostik:</div>
                                    <div class="text-xs text-rose-600 dark:text-rose-400 font-mono whitespace-pre-wrap">{{ $logItem->error_message }}</div>
                                </div>
                            @endif

                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 space-y-2">
                                <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Detail Akun Pemanggil:</div>
                                <div class="flex items-center gap-3">
                                    <img src="{{ $logItem->user->avatar_url ?? asset('images/default-avatar.svg') }}" class="w-10 h-10 rounded-full object-cover border border-gray-300 dark:border-gray-600" alt="Avatar">
                                    <div>
                                        <div class="text-xs font-black text-gray-900 dark:text-white">{{ $logItem->user->name ?? 'System' }}</div>
                                        <div class="text-[11px] text-gray-400">{{ $logItem->user->email ?? 'No email' }} • Level {{ $logItem->user->level ?? 1 }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 flex items-center justify-between">
                    <span class="text-[11px] text-gray-400 font-mono">ID: {{ $logItem->id }} • {{ $logItem->created_at->toIso8601String() }}</span>
                    <button wire:click="closeInspect" class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-xs font-bold text-gray-800 dark:text-gray-200 cursor-pointer">
                        {{ __('Tutup') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
