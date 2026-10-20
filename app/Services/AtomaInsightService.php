<?php

namespace App\Services;

use App\Models\User;
use App\Models\DailySummary;
use App\Models\AiInsight;
use App\Models\FocusSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AtomaInsightService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY') ?? '';
    }

    /**
     * Generate empathetic Daily Summary based on real user data.
     * 100% Deterministic Local Heuristic (0 Token, 0 Latency).
     */
    public function generateDailySummary(User $user): array
    {
        $today = Carbon::today();
        
        // Gather empirical data for today
        $todayTasks = $user->tasks()->where(function($q) use ($today) {
            $q->whereDate('date', $today)->orWhereDate('created_at', $today);
        })->get();

        $totalTasks = $todayTasks->count();
        $doneTasks = $todayTasks->where('is_completed', true)->count();
        $completionRate = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;

        $journal = $user->journals()->whereDate('created_at', $today)->first();
        $mood = $journal->mood ?? 'netral';

        $expense = $user->financeTransactions()->whereDate('date', $today)->where('type', 'expense')->sum('amount');

        if ($totalTasks === 0) {
            $content = "Belum ada aktivitas tugas yang tercatat hari ini. Jangan ragu untuk memulai dari hal kecil sebelum hari berakhir! 😊";
        } elseif ($doneTasks === $totalTasks) {
            $content = "Luar biasa! Kamu menyelesaikan seluruh {$totalTasks} tugas hari ini (100% tuntas). Mood tercatat '{$mood}'. Nikmati waktu istirahatmu malam ini! 🌟";
        } else {
            $content = "Hari ini kamu berhasil menuntaskan {$doneTasks} dari {$totalTasks} tugas ({$completionRate}% selesai). Mood tercatat '{$mood}'. Terus rawat konsistensi ini untuk esok hari! 💪";
        }

        if ($expense > 0) {
            $content .= " Pengeluaran tercatat Rp " . number_format($expense, 0, ',', '.') . ".";
        }

        $summary = DailySummary::updateOrCreate(
            ['user_id' => $user->id, 'summary_date' => $today->toDateString()],
            ['content' => $content]
        );

        return ['summary' => $summary, 'is_fallback' => false];
    }

    /**
     * Generate weekly ATOMA Says insight (Deep Strategic Analysis: Observasi, Tantangan & Solusi, Afirmasi & Rencana).
     */
    public function generateWeeklyInsight(User $user): array
    {
        $periodEnd = Carbon::today();
        $periodStart = Carbon::today()->subDays(6);

        // 1. Tasks analysis (7 days)
        $tasks7Days = $user->tasks()
            ->where(function($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()]);
            })->get();
        $totalCreated = $tasks7Days->count();
        $completedTasks = $tasks7Days->where('is_completed', true);
        $done = $completedTasks->count();
        $rate = $totalCreated > 0 ? (int) round(($done / $totalCreated) * 100) : 0;

        // 2. Deep Work Focus analysis (7 days)
        $focusSessions = FocusSession::where('user_id', $user->id)
            ->whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->get();
        $totalFocusMinutes = (int) $focusSessions->sum('minutes');
        $totalFocusHours = round($totalFocusMinutes / 60, 1);
        $totalSessions = $focusSessions->count();

        // 3. Peak Focus / Golden Hour window
        $morningCount = $completedTasks->filter(function($t) {
            $h = $t->updated_at ? (int)$t->updated_at->format('H') : 12;
            return $h >= 5 && $h < 11;
        })->count();
        $afternoonCount = $completedTasks->filter(function($t) {
            $h = $t->updated_at ? (int)$t->updated_at->format('H') : 12;
            return $h >= 11 && $h < 16;
        })->count();
        $eveningCount = $completedTasks->filter(function($t) {
            $h = $t->updated_at ? (int)$t->updated_at->format('H') : 12;
            return $h >= 16 && $h < 21;
        })->count();
        $nightCount = $completedTasks->filter(function($t) {
            $h = $t->updated_at ? (int)$t->updated_at->format('H') : 12;
            return $h >= 21 || $h < 5;
        })->count();

        $windows = [
            'Pagi Hari (05:00 - 11:00)' => $morningCount,
            'Siang Hari (11:00 - 16:00)' => $afternoonCount,
            'Sore Hari (16:00 - 21:00)' => $eveningCount,
            'Malam Hari (21:00 - 05:00)' => $nightCount,
        ];
        arsort($windows);
        $peakWindow = array_key_first($windows);

        // 4. Overdue & Discipline analysis
        $overdueTasks = $user->tasks()
            ->where('is_completed', false)
            ->whereNotNull('date')
            ->where('date', '<', $periodEnd->toDateString())
            ->get();
        $overdue = $overdueTasks->count();
        $reasonsGrouped = $overdueTasks->whereNotNull('skip_reason')->groupBy('skip_reason');
        $topOverdueReason = $reasonsGrouped->isNotEmpty()
            ? $reasonsGrouped->sortByDesc(fn($g) => $g->count())->keys()->first()
            : null;

        // 5. Category breakdown
        $categories = $tasks7Days->groupBy(fn($t) => $t->category ?: 'Umum');
        $topCat = 'Umum';
        $topCatDone = 0;
        $skipCat = 'Umum';
        if ($categories->isNotEmpty()) {
            $sortedByDone = $categories->sortByDesc(fn($g) => $g->where('is_completed', true)->count());
            $topCat = $sortedByDone->keys()->first() ?? 'Umum';
            $topCatDone = $sortedByDone->first()->where('is_completed', true)->count();

            $sortedByRate = $categories->sortBy(function($group) {
                $c = $group->count();
                $d = $group->where('is_completed', true)->count();
                return $c > 0 ? ($d / $c) : 0;
            });
            $skipCat = $sortedByRate->keys()->first() ?? 'Umum';
        }

        // 6. Best Day Analysis
        $daysMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
        ];
        $tasksByDay = $tasks7Days->groupBy(function($t) {
            $d = $t->date ?? $t->created_at;
            return $d ? $d->format('l') : 'Today';
        });
        $bestDayEn = $tasksByDay->sortByDesc(fn($g) => $g->where('is_completed', true)->count())->keys()->first() ?? 'Monday';
        $bestDay = $daysMap[$bestDayEn] ?? $bestDayEn;
        $bestDayCount = $tasksByDay->get($bestDayEn)?->where('is_completed', true)->count() ?? 0;

        $lowestDayEn = $tasksByDay->sortBy(fn($g) => $g->where('is_completed', true)->count())->keys()->first() ?? 'Sunday';
        $lowestDay = $daysMap[$lowestDayEn] ?? $lowestDayEn;

        // 7. Journal Consistency
        $journalCount = $user->journals()->whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])->count();
        $consistency = (int) round(($journalCount / 7) * 100);

        // Dynamic Archetype
        if ($rate >= 80 && $totalFocusHours >= 5) {
            $archetype = 'The Deep-Sprint Master';
        } elseif ($rate >= 75) {
            $archetype = 'The Strategic Executor';
        } elseif ($totalFocusHours >= 6) {
            $archetype = 'The Relentless Scholar';
        } elseif ($journalCount >= 5) {
            $archetype = 'The Mindful Architect';
        } elseif ($done >= 10) {
            $archetype = 'The Momentum Builder';
        } else {
            $archetype = 'The Resilient Sprinter';
        }

        // Rich Heuristic Fallback Strings
        $fallbackObservasi = "📊 Kecepatan Eksekusi: Minggu ini kamu menuntaskan {$done} dari {$totalCreated} tugas ({$rate}% completion rate), didukung oleh {$totalFocusHours} jam ({$totalFocusMinutes} menit) fokus Pomodoro terstruktur sepanjang {$totalSessions} sesi.\n\n"
            . "⚡ Ritme & Jam Emas: Performa puncamu terkonsentrasi di waktu {$peakWindow}, dengan hari paling produktif jatuh pada hari {$bestDay} ({$bestDayCount} tugas tuntas). Kategori '{$topCat}' menjadi tumpuan aksimu dengan {$topCatDone} penyelesaian.\n\n"
            . "🛡️ Kedisiplinan: " . ($overdue > 0 
                ? "Terdapat {$overdue} tugas tertunda" . ($topOverdueReason ? " dengan kendala utama '{$topOverdueReason}'" : "") . ". Rapikan antrean tugas sebelum memulai siklus minggu depan."
                : "Sempurna! Tidak ada tugas tertunda yang menumpuk. Ritme kerjamu sangat presisi dan terkelola rapi.");

        $fallbackTantangan = "🎯 Titik Friksi: " . ($skipCat !== $topCat 
                ? "Kategori '{$skipCat}' dan ritme pada hari {$lowestDay} memperlihatkan resistensi atau penurunan momentum terbesar." 
                : "Konsistensi energi di paruh kedua minggu memerlukan kalibrasi agar ritme kerja tidak drop di akhir pekan.") . "\n\n"
            . "📋 3 Rencana Aksi Taktis (Action Plan):\n"
            . "1. Rule of 3: Tetapkan maksimal 3 target non-negosiasi setiap pagi sebelum pukul 10:00 untuk mencegah cognitive overload.\n"
            . "2. Time-blocking di {$peakWindow}: Kunci 45-60 menit sesi fokus tanpa distraksi khusus untuk menuntaskan kategori '{$skipCat}'.\n"
            . "3. Micro-Stepping: Pecah tugas rumit menjadi subtask kecil berdurasi < 15 menit menggunakan fitur AI Subtasks.";

        $fallbackAfirmasi = "💎 Mental Model: \"Momentum tidak tercipta dari motivasi instan, melainkan dari kemenangan-kemenangan kecil yang konsisten dieksekusi setiap hari.\"\n\n"
            . "🚀 Komitmen Sprint Berikutnya: Refleksi jurnalmu mencapai {$consistency}% ({$journalCount}/7 hari). Kamu telah membuktikan bahwa sistem kerjamu efektif. Evaluasi hari ini, kunci fokusmu, dan kuasai sprint minggu depan! ✨";

        $userId = $user->id;
        $modelPool = AiLoggerService::getModelPool();

        if (!$this->apiKey) {
            $insight = AiInsight::updateOrCreate(
                ['user_id' => $user->id, 'period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString()],
                [
                    'archetype' => $archetype,
                    'observasi' => $fallbackObservasi,
                    'tantangan' => $fallbackTantangan,
                    'afirmasi' => $fallbackAfirmasi,
                    'generated_at' => now(),
                ]
            );
            return ['insight' => $insight, 'is_fallback' => true];
        }

        $prompt = "Kamu adalah ATOMA Chief Productivity Coach. Analisis performa produktivitas 7 hari pengguna ({$periodStart->format('d M')} - {$periodEnd->format('d M Y')}) secara MENDALAM, RINCI, DAN SPESIFIK:

DATA METRIK 7 HARI:
- Tugas: Dibuat {$totalCreated} | Selesai {$done} ({$rate}%) | Overdue {$overdue}
- Jam Fokus Pomodoro: {$totalFocusHours} jam ({$totalFocusMinutes} menit) dalam {$totalSessions} sesi
- Jam Emas Paling Efektif: {$peakWindow}
- Hari Paling Produktif: {$bestDay} ({$bestDayCount} tugas selesai) | Hari Terendah: {$lowestDay}
- Kategori Unggulan: '{$topCat}' ({$topCatDone} selesai) | Kategori Paling Sering Di-skip/Tertunda: '{$skipCat}'
" . ($topOverdueReason ? "- Hambatan Overdue Tercatat: '{$topOverdueReason}'\n" : "") . "
- Konsistensi Jurnal: {$journalCount}/7 hari ({$consistency}%)

TUGAS:
Hasilkan evaluasi mingguan berbobot tinggi dalam format JSON mentah tanpa markdown:
{
  \"archetype\": \"{$archetype}\",
  \"observasi\": \"3 paragraf padat metrik: (1) Kecepatan eksekusi tugas & jam fokus nyata, (2) Ritme jam emas & hari terbaik serta kategori unggulan, (3) Evaluasi kedisiplinan dan status backlog.\",
  \"tantangan\": \"3 bagian terstruktur: (1) Diagnosis titik friksi spesifik pada kategori '{$skipCat}', (2) 3 rekomendasi taktis bernomor untuk minggu depan yang dapat langsung dieksekusi.\",
  \"afirmasi\": \"2 bagian: (1) Mental model atau prinsip filosofis stoic/kaizen yang relevan, (2) Komitmen dan motivasi berbasis konsistensi jurnal ({$consistency}%).\"
}
Bahasa: Indonesia profesional casual, suportif, tajam, dan gunakan data angka di atas secara presisi.";

        foreach ($modelPool as $model) {
            if (Cache::has("gemini_cooldown_{$model}")) {
                continue;
            }

            $startTime = microtime(true);

            try {
                $response = Http::timeout(12)
                    ->connectTimeout(5)
                    ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
                    [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json']
                    ]
                );

                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $raw = $response->json('candidates.0.content.parts.0.text');
                    if ($raw) {
                        $raw = trim(str_replace(['```json', '```'], '', $raw));
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded) && isset($decoded['observasi'])) {
                            AiLoggerService::logResponse(
                                userId: $userId,
                                feature: 'weekly_insight',
                                model: $model,
                                response: $response,
                                executionTimeMs: $durationMs,
                                promptText: "Weekly Insight: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}",
                                responseText: $raw
                            );

                            $insight = AiInsight::updateOrCreate(
                                ['user_id' => $user->id, 'period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString()],
                                [
                                    'archetype' => $decoded['archetype'] ?? $archetype,
                                    'observasi' => $decoded['observasi'],
                                    'tantangan' => $decoded['tantangan'],
                                    'afirmasi' => $decoded['afirmasi'],
                                    'generated_at' => now(),
                                ]
                            );
                            return ['insight' => $insight, 'is_fallback' => false];
                        }
                    }
                } elseif ($response->status() === 429) {
                    Cache::put("gemini_cooldown_{$model}", true, now()->addMinutes(2));
                    continue;
                }
            } catch (\Exception $e) {
                Log::error("Gemini {$model} Exception in WeeklyInsight: " . $e->getMessage());
                continue;
            }
        }

        // Fallback
        AiLoggerService::log(
            userId: $userId,
            feature: 'weekly_insight',
            promptText: "Weekly Insight: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}",
            model: 'offline_heuristic',
            status: 'fallback'
        );

        $insight = AiInsight::updateOrCreate(
            ['user_id' => $user->id, 'period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString()],
            [
                'archetype' => $archetype,
                'observasi' => $fallbackObservasi,
                'tantangan' => $fallbackTantangan,
                'afirmasi' => $fallbackAfirmasi,
                'generated_at' => now(),
            ]
        );

        return ['insight' => $insight, 'is_fallback' => true];
    }

    /**
     * Generate Monthly Capstone Retrospective (The "Spotify Wrapped" of Productivity).
     * Deep Macro synthesis: Archetype, 4-Week Momentum Curve, Deep Work hours, and 30-day Strategic Blueprint.
     */
    public function generateMonthlyReport(User $user): array
    {
        $periodEnd = Carbon::today();
        $periodStart = Carbon::today()->subDays(29);

        // 1. 30-day task metrics
        $tasks30Days = $user->tasks()
            ->where(function($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()]);
            })->get();
        $totalCreated = $tasks30Days->count();
        $completedTasks = $tasks30Days->where('is_completed', true);
        $done = $completedTasks->count();
        $rate = $totalCreated > 0 ? (int) round(($done / $totalCreated) * 100) : 0;

        // 2. 4-Week Momentum Curve
        $w1Tasks = $tasks30Days->filter(fn($t) => ($t->date ?? $t->created_at)->between($periodStart, $periodStart->copy()->addDays(7)));
        $w2Tasks = $tasks30Days->filter(fn($t) => ($t->date ?? $t->created_at)->between($periodStart->copy()->addDays(8), $periodStart->copy()->addDays(14)));
        $w3Tasks = $tasks30Days->filter(fn($t) => ($t->date ?? $t->created_at)->between($periodStart->copy()->addDays(15), $periodStart->copy()->addDays(21)));
        $w4Tasks = $tasks30Days->filter(fn($t) => ($t->date ?? $t->created_at)->between($periodStart->copy()->addDays(22), $periodEnd));

        $calcRate = fn($collection) => $collection->count() > 0 ? (int) round(($collection->where('is_completed', true)->count() / $collection->count()) * 100) : 0;
        $w1Rate = $calcRate($w1Tasks);
        $w2Rate = $calcRate($w2Tasks);
        $w3Rate = $calcRate($w3Tasks);
        $w4Rate = $calcRate($w4Tasks);

        if ($w4Rate >= $w1Rate && $w4Rate >= 75) {
            $weeklyTrendLabel = 'Menanjak Kuat Menuju Puncak';
        } elseif ($w1Rate >= 80 && $w4Rate >= 75) {
            $weeklyTrendLabel = 'Konsisten Tinggi Sepanjang Bulan';
        } elseif ($w2Rate > $w4Rate && $w3Rate > $w4Rate) {
            $weeklyTrendLabel = 'Puncak di Pertengahan Bulan';
        } else {
            $weeklyTrendLabel = 'Dinamis dengan Potensi Pemulihan';
        }

        // 3. Deep Work Focus analysis (30 days)
        $pomodoroSessions = FocusSession::where('user_id', $user->id)
            ->whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->get();
        $totalFocusMinutes = (int) $pomodoroSessions->sum('minutes');
        $totalFocusHours = round($totalFocusMinutes / 60, 1);
        $totalSessions = $pomodoroSessions->count();
        $avgSessionMins = $totalSessions > 0 ? (int) round($totalFocusMinutes / $totalSessions) : 0;

        // 4. Overdue & Discipline analysis
        $overdueTasks = $user->tasks()
            ->where('is_completed', false)
            ->whereNotNull('date')
            ->where('date', '<', $periodEnd->toDateString())
            ->get();
        $overdue = $overdueTasks->count();
        $reasonsGrouped = $overdueTasks->whereNotNull('skip_reason')->groupBy('skip_reason');
        $topOverdueReason = $reasonsGrouped->isNotEmpty()
            ? $reasonsGrouped->sortByDesc(fn($g) => $g->count())->keys()->first()
            : null;

        // 5. Category Breakdown
        $categories = $tasks30Days->groupBy(fn($t) => $t->category ?: 'Umum');
        $topCat = 'Umum';
        $topCatDone = 0;
        $skipCat = 'Umum';
        if ($categories->isNotEmpty()) {
            $sortedByDone = $categories->sortByDesc(fn($g) => $g->where('is_completed', true)->count());
            $topCat = $sortedByDone->keys()->first() ?? 'Umum';
            $topCatDone = $sortedByDone->first()->where('is_completed', true)->count();

            $sortedByRate = $categories->sortBy(function($group) {
                $c = $group->count();
                $d = $group->where('is_completed', true)->count();
                return $c > 0 ? ($d / $c) : 0;
            });
            $skipCat = $sortedByRate->keys()->first() ?? 'Umum';
        }

        // 6. Habit consistency & Streaks
        $journalCount = $user->journals()->whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])->count();
        $consistency = (int) round(($journalCount / 30) * 100);
        $streak = $user->streak ?? 0;
        $longestStreak = max($streak, $user->longest_streak ?? $streak);

        // 7. Dynamic Monthly Archetype
        if ($rate >= 85 && $totalFocusHours >= 15) {
            $archetype = 'The Deep-Focus Architect';
        } elseif ($rate >= 80 && $consistency >= 70) {
            $archetype = 'The Master Strategist';
        } elseif ($totalFocusHours >= 20) {
            $archetype = 'The Relentless Scholar';
        } elseif ($consistency >= 75) {
            $archetype = 'The Mindful Alchemist';
        } elseif ($done >= 40) {
            $archetype = 'The Systematic Dynamo';
        } else {
            $archetype = 'The Resilient Pioneer';
        }

        // Rich Heuristic Fallback Strings
        $fallbackObservasi = "🔭 Sintesis Makro 30 Hari: Sepanjang sebulan terakhir, kamu telah menuntaskan {$done} dari {$totalCreated} tugas ({$rate}% completion rate), didukung oleh investasi {$totalFocusHours} jam Deep Work ({$totalFocusMinutes} menit) lintas {$totalSessions} sesi Pomodoro terstruktur (rata-rata {$avgSessionMins} menit/sesi).\n\n"
            . "📈 Kurva Momentum Mingguan: Analisis 4 siklus mingguan mencerminkan pola {$weeklyTrendLabel} (Minggu 1: {$w1Rate}% ➔ Minggu 2: {$w2Rate}% ➔ Minggu 3: {$w3Rate}% ➔ Minggu 4: {$w4Rate}%). Kategori '{$topCat}' menjadi pilar terkuat dengan {$topCatDone} tugas terselesaikan.\n\n"
            . "🛡️ Kedisiplinan & Ketahanan: Rekor rantai kebiasaan terpanjangmu mencapai {$longestStreak} hari beruntun. Refleksi jurnal tercatat {$journalCount}/30 hari ({$consistency}%), membuktikan integritas tinggi dalam menjaga komitmen harian.";

        $fallbackTantangan = "⚖️ Audit Titik Hambatan (Bottlenecks): " . ($overdue > 0 
                ? "Tercatat akumulasi {$overdue} tugas tertunda selama 30 hari terakhir. Kategori '{$skipCat}' menjadi area dengan tingkat resistensi tertinggi" . ($topOverdueReason ? ", dengan kendala dominan '{$topOverdueReason}'" : "") . "." 
                : "Manajemen tenggat waktumu sangat presisi tanpa tumpukan tugas overdue yang membebani.") . "\n\n"
            . "🛠️ 3 Langkah Restrukturisasi Sistemik:\n"
            . "1. Kalibrasi Beban Harian: Berikan bantalan waktu 20% lebih longgar untuk jadwal harian agar kejutan agenda tidak merusak fokus utama.\n"
            . "2. Dekomposisi Tugas '{$skipCat}': Uraikan tugas di kategori '{$skipCat}' menjadi modul-modul kecil berdurasi 20 menit untuk memangkas hambatan inisiasi.\n"
            . "3. Mindful Rest Day: Amankan 1 hari pemulihan penuh setiap minggu untuk mencegah akumulasi cognitive fatigue yang memicu burnout.";

        $fallbackAfirmasi = "🗺️ Strategic Blueprint 30 Hari ke Depan:\n"
            . "• Pilar Eksekusi: Kunci standar penyelesaian di atas 80% dengan memprioritaskan tugas berkategori strategis di jam emas produktifmu.\n"
            . "• Pilar Deep Work: Targetkan akumulasi minimal " . max(12, round($totalFocusHours * 1.15)) . " jam sesi fokus tanpa distraksi untuk proyek-proyek berdampak tinggi.\n\n"
            . "👑 Refleksi Filosofis: \"Keberhasilan bukanlah ledakan keajaiban sesaat, melainkan akumulasi dari sistem harian yang kamu rawat tanpa henti.\" Kamu telah membangun fondasi tangguh bulan ini, bawa momentum ini ke level berikutnya! 🚀";

        $userId = $user->id;
        $modelPool = AiLoggerService::getModelPool();

        if (!$this->apiKey) {
            $insight = AiInsight::updateOrCreate(
                ['user_id' => $user->id, 'period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString()],
                [
                    'archetype' => $archetype,
                    'observasi' => $fallbackObservasi,
                    'tantangan' => $fallbackTantangan,
                    'afirmasi' => $fallbackAfirmasi,
                    'generated_at' => now(),
                ]
            );

            return ['insight' => $insight, 'is_fallback' => true];
        }

        $prompt = "Kamu adalah ATOMA Chief Strategy Coach. Analisis performa produktivitas 30 hari pengguna ({$periodStart->format('d M')} - {$periodEnd->format('d M Y')}) secara MAKRO, STRATEGIS, DAN MENDALAM:

DATA MAKRO 30 HARI:
- Tugas: Total Dibuat {$totalCreated} | Selesai {$done} ({$rate}%) | Overdue {$overdue}
- Kurva Mingguan: W1: {$w1Rate}% ➔ W2: {$w2Rate}% ➔ W3: {$w3Rate}% ➔ W4: {$w4Rate}% (Pola: {$weeklyTrendLabel})
- Jam Fokus Pomodoro: {$totalFocusHours} jam ({$totalFocusMinutes} menit) dalam {$totalSessions} sesi (rata-rata {$avgSessionMins} menit)
- Kategori Dominan: '{$topCat}' ({$topCatDone} tugas tuntas) | Kategori Paling Tertunda: '{$skipCat}'
" . ($topOverdueReason ? "- Kendala Overdue Dominan: '{$topOverdueReason}'\n" : "") . "
- Konsistensi Jurnal: {$journalCount}/30 hari ({$consistency}%) | Longest Streak: {$longestStreak} hari
- Rekomendasi Archetype: '{$archetype}'

TUGAS:
Hasilkan evaluasi bulanan Capstone komprehensif dalam format JSON mentah tanpa markdown:
{
  \"archetype\": \"{$archetype}\",
  \"observasi\": \"3 paragraf mendalam: (1) Sintesis komprehensif 30 hari dengan angka tugas dan jam fokus nyata, (2) Analisis kurva momentum mingguan (W1-W4) dan kategori unggulan, (3) Rekam jejak konsistensi streak dan integritas jurnal.\",
  \"tantangan\": \"3 bagian tajam: (1) Audit titik hambatan sistemik dan area resistensi '{$skipCat}', (2) 3 langkah perbaikan sistemik bernomor untuk mencegah friksi dan kelelahan.\",
  \"afirmasi\": \"2 bagian: (1) Strategic Blueprint 30 hari ke depan dengan 2 target konkret (eksekusi & deep work), (2) Refleksi filosofis agung bernada inspiratif yang mengukuhkan pencapaian 30 hari.\"
}
Bahasa: Indonesia profesional casual dengan sentuhan mentoring strategis kelas dunia.";

        foreach ($modelPool as $model) {
            if (Cache::has("gemini_cooldown_{$model}")) {
                continue;
            }

            $startTime = microtime(true);

            try {
                $response = Http::timeout(15)
                    ->connectTimeout(5)
                    ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
                    [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json']
                    ]
                );

                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $raw = $response->json('candidates.0.content.parts.0.text');
                    if ($raw) {
                        $raw = trim(str_replace(['```json', '```'], '', $raw));
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded) && isset($decoded['observasi'])) {
                            AiLoggerService::logResponse(
                                userId: $userId,
                                feature: 'monthly_insight',
                                model: $model,
                                response: $response,
                                executionTimeMs: $durationMs,
                                promptText: "Monthly Capstone: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}",
                                responseText: $raw
                            );

                            $insight = AiInsight::updateOrCreate(
                                ['user_id' => $user->id, 'period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString()],
                                [
                                    'archetype' => $decoded['archetype'] ?? $archetype,
                                    'observasi' => $decoded['observasi'],
                                    'tantangan' => $decoded['tantangan'],
                                    'afirmasi' => $decoded['afirmasi'],
                                    'generated_at' => now(),
                                ]
                            );
                            return ['insight' => $insight, 'is_fallback' => false];
                        }
                    }
                } elseif ($response->status() === 429) {
                    Cache::put("gemini_cooldown_{$model}", true, now()->addMinutes(2));
                    continue;
                }
            } catch (\Exception $e) {
                Log::error("Gemini {$model} Exception in MonthlyReport: " . $e->getMessage());
                continue;
            }
        }

        // Fallback
        AiLoggerService::log(
            userId: $userId,
            feature: 'monthly_insight',
            promptText: "Monthly Capstone: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}",
            model: 'offline_heuristic',
            status: 'fallback'
        );

        $insight = AiInsight::updateOrCreate(
            ['user_id' => $user->id, 'period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString()],
            [
                'archetype' => $archetype,
                'observasi' => $fallbackObservasi,
                'tantangan' => $fallbackTantangan,
                'afirmasi' => $fallbackAfirmasi,
                'generated_at' => now(),
            ]
        );

        return ['insight' => $insight, 'is_fallback' => true];
    }
}
