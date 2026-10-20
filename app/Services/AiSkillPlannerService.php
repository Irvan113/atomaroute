<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\SkillTarget;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiSkillPlannerService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Interactive AI Setup Chat
     * 
     * @param string $title Skill title
     * @param int $days Number of days
     * @param array $history Previous chat history [{role: 'user'|'model', text: string}]
     * @param string $userMessage Latest user message
     * @return array [ 'reply' => string, 'is_ready' => bool, 'final_prompt' => string|null ]
     */
    public function chatSetup(string $title, int $days, array $history, string $userMessage, ?string $referenceUrl = null, ?string $aiChatUrl = null, ?string $aiSyllabusText = null): array
    {
        if (!$this->apiKey) {
            return [
                'reply' => "Sip! Ayo kita mulai $title selama $days hari. (Fallback Mode)",
                'is_ready' => true,
                'plan_parameters' => [
                    'title' => $title,
                    'target_days' => $days,
                    'start_date' => now()->toDateString(),
                    'training_days' => ['1', '2', '3', '4', '5', '6', '7'],
                    'mode' => 'systematic',
                ],
                'final_prompt' => $userMessage,
                'summary' => self::normalizeSummary([
                    'youtube_insight' => $referenceUrl ? 'Menggunakan referensi YouTube yang diberikan.' : 'Tidak ada referensi khusus.',
                    'ai_chat_insight' => $aiSyllabusText ? 'Menyerap dan memadukan silabus teks yang diimpor dari AI eksternal.' : ($aiChatUrl ? 'Mengintegrasikan topik dari sesi chat AI yang diberikan.' : 'Tidak ada referensi chat AI khusus.'),
                    'training_schedule' => 'Setiap hari latihan (Fallback Mode)',
                    'overview' => 'Goal: ' . $title . ' dalam ' . $days . ' hari. (Fallback Mode)'
                ], $days, $title)
            ];
        }

        $userTz = config('app.timezone', 'Asia/Jakarta');
        $today = now($userTz);
        $todayDateStr = $today->format('Y-m-d');
        $todayDayName = $today->translatedFormat('l');
        $currentYear = $today->year;

        $systemInstruction = "You are an expert, motivating personal coach in ATOMA. A user wants to achieve '$title' in $days days.
        HARI INI ADALAH: {$todayDayName}, {$today->translatedFormat('d F Y')} (Format YYYY-MM-DD: {$todayDateStr}).
        Tahun kalender saat ini adalah {$currentYear}. DILARANG KERAS menggunakan tahun lampau seperti 2023 atau 2024! Jika user tidak menyebutkan tanggal spesifik, gunakan tanggal mulai default: '{$todayDateStr}'.

        Your goal is to briefly interview them to understand their current level, constraints, available days, and desired start date.
        Ask only 1 question at a time. Keep it highly conversational, brief, and using Indonesian.

        NOTE: You have full authority to dynamically adapt the milestone parameters based on your conversation with the user.
        If the user agrees to change the duration (e.g., from 30 to 90 days), change the start date (e.g. start tomorrow or next week), or specify exact workout days and rest days (e.g., rest on Monday, workout Tuesday-Sunday), you MUST reflect these updated parameters in 'plan_parameters'!";
        
        if ($referenceUrl) {
            $systemInstruction .= "\nThe user provided this YouTube video/content as their reference: $referenceUrl. Please use relevant insights from this reference to structure their training.";
        }

        if (!empty($aiSyllabusText)) {
            $systemInstruction .= "\n\n======================================================
USER TELAH MENGIMPOR TEKS SILABUS / RENCANA DARI AI LAIN (CHATGPT / GEMINI / CLAUDE):
\"{$aiSyllabusText}\"
======================================================
PANDUAN COACH TENTANG SILABUS YANG DIIMPOR INI:
- Pada balasan atau sambutan pertamamu, akui dan apresiasi silabus yang dibawa user ini! Sebutkan bahwa kamu telah membaca dan memahami poin-poin materi/jadwal latihan tersebut.
- Bedah dan integrasikan seluruh poin latihan tersebut ke dalam fase latihan (phases), rutinitas mingguan (weekly_routine), dan subtask harian.
- JANGAN PERNAH menyuruh user untuk mengetik ulang apa yang sudah tertera di silabus tersebut!";
        }

        if ($aiChatUrl) {
            $chatMeta = \App\Services\AiChatReferenceService::fetchChatMetadata($aiChatUrl);
            $provider = $chatMeta['provider'] ?? 'Chat AI';
            $topic = $chatMeta['topic'] ?? null;
            $desc = $chatMeta['description'] ?? null;

            $systemInstruction .= "\nThe user provided a shared AI chat reference URL: {$aiChatUrl} ({$provider}).";
            if (!empty($topic)) {
                $systemInstruction .= "\nINTISARI TOPIK CHAT AI: '{$topic}'.";
            }
            if (!empty($desc)) {
                $systemInstruction .= "\nRINGKASAN TOPIK: '{$desc}'.";
            }
            $systemInstruction .= "
            PANDUAN COACH TENTANG REFERENSI CHAT INI:
            - Sambut user dengan antusias" . (!empty($topic) ? " dan sebutkan bahwa kamu langsung fokus mengintegrasikan metode/topik '{$topic}' dari referensi AI tersebut" : "") . "!
            - JANGAN PERNAH menyuruh user untuk copy-paste obrolan lagi! Sebagai Coach profesional, ambil inisiatif penuh untuk langsung meracik fase latihan, mobilitas, dan latihan pendukung sesuai topik '{$topic}' ini ke dalam jadwal mingguan dan subtask harian secara mandiri.";
        }
        
        $systemInstruction .= "\n\nCRITICAL INSTRUCTION: You MUST ask at least one or two questions first (e.g., about their current fitness level, equipment, or specific days they want to rest) BEFORE creating the plan. DO NOT output the JSON plan on your very first reply!
        Once you have gathered enough information through the chat, OR if they say they agree/are ready, you MUST output a JSON block containing the summary of the plan. DO NOT ask any more questions at that point. The JSON block MUST be formatted exactly like this and start with { and end with }:
{
    \"status\": \"READY\",
    \"plan_parameters\": {
        \"title\": \"Judul goal atau keahlian yang disepakati (string)\",
        \"target_days\": 90,
        \"start_date\": \"{$todayDateStr}\",
        \"training_days\": [\"2\", \"3\", \"4\", \"5\", \"6\", \"7\"],
        \"mode\": \"systematic\"
    },
    \"summary\": {
        \"youtube_insight\": \"Tulis kesimpulanmu dari video YouTube yang diberikan (jika ada, jika tidak ada tulis 'Tidak ada referensi khusus').\",
        \"ai_chat_insight\": \"Tulis intisari atau topik yang dipadukan dari referensi chat AI (jika ada, jika tidak ada tulis 'Tidak ada referensi chat AI khusus').\",
        \"training_schedule\": \"Jelaskan dengan spesifik HARI APA SAJA latihan dan HARI APA SAJA istirahat (sesuai kesepakatan).\",
        \"overview\": \"Tuliskan kesimpulan metode latihan, tugas-tugas utama yang akan dilaksanakan, atau strategi besarnya secara komprehensif.\",
        \"weekly_routine\": [
            {\"day_name\": \"Senin\", \"iso\": 1, \"type\": \"rest\", \"label\": \"Rest / Kuliah full\"},
            {\"day_name\": \"Selasa\", \"iso\": 2, \"type\": \"workout\", \"label\": \"Sore Workout A (Knee Armor)\"},
            {\"day_name\": \"Rabu\", \"iso\": 3, \"type\": \"workout\", \"label\": \"Pagi Lari Base Sesi 1 + Kuliah\"},
            {\"day_name\": \"Kamis\", \"iso\": 4, \"type\": \"workout\", \"label\": \"Pagi Lari Form Sesi 2 + Kuliah\"},
            {\"day_name\": \"Jumat\", \"iso\": 5, \"type\": \"workout\", \"label\": \"Sore Workout B (Core & Upper)\"},
            {\"day_name\": \"Sabtu\", \"iso\": 6, \"type\": \"workout\", \"label\": \"Pagi Sesi Aerobik Panjang / Lari / Renang\"},
            {\"day_name\": \"Minggu\", \"iso\": 7, \"type\": \"active_recovery\", \"label\": \"Active Recovery (sepeda/jalan santai)\"}
        ],
        \"phases\": [
            {\"phase\": 1, \"name\": \"Fondasi & Adaptasi\", \"days\": \"Hari 1-22\", \"focus\": \"Penyesuaian ritme & teknik dasar mobilitas\"},
            {\"phase\": 2, \"name\": \"Pembentukan Teknik & Konsistensi\", \"days\": \"Hari 23-45\", \"focus\": \"Peningkatan stabilitas form & volume latihan\"},
            {\"phase\": 3, \"name\": \"Latihan Terapan & Intensitas\", \"days\": \"Hari 46-68\", \"focus\": \"Penguatan kapasitas fisik & variasi beban\"},
            {\"phase\": 4, \"name\": \"Uji Penguasaan & Kebiasaan Permanen\", \"days\": \"Hari 69-90\", \"focus\": \"Evaluasi pencapaian akhir, PR & sertifikasi\"}
        ]
    },
    \"final_prompt\": \"Tulis deskripsi detail kebutuhan user (digunakan oleh AI pembuat jadwal nantinya).\"
}";

        $contents = [
            [
                'role' => 'user',
                'parts' => [['text' => $systemInstruction]]
            ],
            [
                'role' => 'model',
                'parts' => [['text' => 'Understood.']]
            ]
        ];

        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'ai' ? 'model' : 'user',
                'parts' => [['text' => $msg['text']]]
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        $modelPool = AiLoggerService::getModelPool();
        $userId = auth()->id();

        foreach ($modelPool as $model) {
            if (Cache::has("gemini_cooldown_{$model}")) {
                Log::info("Skipping model $model due to active cooldown cache.");
                continue;
            }

            $startTime = microtime(true);

            try {
                $response = Http::timeout(15)
                    ->connectTimeout(5)
                    ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
                    [
                        'contents' => $contents
                    ]
                );

                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $text = trim($response->json('candidates.0.content.parts.0.text') ?? '');

                    AiLoggerService::logResponse(
                        userId: $userId,
                        feature: 'skill_planner_chat',
                        model: $model,
                        response: $response,
                        executionTimeMs: $durationMs,
                        promptText: $userMessage,
                        responseText: $text
                    );
                    
                    if (!empty($text)) {
                        $cleanPlanParams = function(array $params) use ($todayDateStr, $currentYear) {
                            if (!empty($params['start_date'])) {
                                try {
                                    $pDate = \Carbon\Carbon::parse($params['start_date']);
                                    if ($pDate->year < $currentYear) {
                                        $params['start_date'] = $pDate->setYear($currentYear)->format('Y-m-d');
                                    }
                                } catch (\Exception $e) {
                                    $params['start_date'] = $todayDateStr;
                                }
                            } else {
                                $params['start_date'] = $todayDateStr;
                            }
                            return $params;
                        };

                        // Coba parse JSON
                        $parsed = json_decode($text, true);
                        if (is_array($parsed) && isset($parsed['status']) && $parsed['status'] === 'READY') {
                            $planParams = $cleanPlanParams($parsed['plan_parameters'] ?? []);
                            $effDays = (int) ($planParams['target_days'] ?? $days);
                            $effTitle = !empty($planParams['title']) ? $planParams['title'] : $title;
                            $effDaysActive = !empty($planParams['training_days']) ? (array) $planParams['training_days'] : [];
                            return [
                                'reply' => "Sip! Aku sudah punya strateginya. Silakan cek ringkasannya!",
                                'is_ready' => true,
                                'plan_parameters' => $planParams,
                                'summary' => self::normalizeSummary($parsed['summary'] ?? [], $effDays, $effTitle, $effDaysActive),
                                'final_prompt' => $parsed['final_prompt'] ?? ''
                            ];
                        }

                        // Fallback: Cari blok JSON di dalam teks menggunakan regex
                        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                            $jsonStr = $matches[0];
                            $parsedRegex = json_decode($jsonStr, true);
                            if (is_array($parsedRegex) && isset($parsedRegex['status']) && $parsedRegex['status'] === 'READY') {
                                $planParams = $cleanPlanParams($parsedRegex['plan_parameters'] ?? []);
                                $effDays = (int) ($planParams['target_days'] ?? $days);
                                $effTitle = !empty($planParams['title']) ? $planParams['title'] : $title;
                                $effDaysActive = !empty($planParams['training_days']) ? (array) $planParams['training_days'] : [];
                                return [
                                    'reply' => "Sip! Aku sudah punya strateginya. Silakan cek ringkasannya!",
                                    'is_ready' => true,
                                    'plan_parameters' => $planParams,
                                    'summary' => self::normalizeSummary($parsedRegex['summary'] ?? [], $effDays, $effTitle, $effDaysActive),
                                    'final_prompt' => $parsedRegex['final_prompt'] ?? ''
                                ];
                            }
                        }

                        return [
                            'reply' => $text,
                            'is_ready' => false,
                            'plan_parameters' => [],
                            'final_prompt' => null,
                            'summary' => []
                        ];
                    }
                } elseif ($response->status() === 429) {
                    Log::warning("Model $model hit 429 quota, setting 2-minute cooldown cache.");
                    Cache::put("gemini_cooldown_{$model}", true, now()->addMinutes(2));
                    AiLoggerService::logResponse(
                        userId: $userId,
                        feature: 'skill_planner_chat',
                        model: $model,
                        response: $response,
                        executionTimeMs: $durationMs,
                        promptText: $userMessage
                    );
                    continue;
                } else {
                    AiLoggerService::logResponse(
                        userId: $userId,
                        feature: 'skill_planner_chat',
                        model: $model,
                        response: $response,
                        executionTimeMs: $durationMs,
                        promptText: $userMessage
                    );
                }
            } catch (\Exception $e) {
                Log::warning("Model $model failed: " . $e->getMessage());
                AiLoggerService::log(
                    userId: $userId,
                    feature: 'skill_planner_chat',
                    promptText: $userMessage,
                    model: $model,
                    executionTimeMs: (int) round((microtime(true) - $startTime) * 1000),
                    status: 'error',
                    errorMessage: $e->getMessage()
                );
                continue;
            }
        }

        return [
            'reply' => "Halo! Senang bisa mendampingimu. Mari kita buat strategi latihan $title selama $days hari!",
            'is_ready' => true,
            'plan_parameters' => [
                'title' => $title,
                'target_days' => $days,
                'start_date' => now()->toDateString(),
                'training_days' => ['1', '2', '3', '4', '5', '6', '7'],
                'mode' => 'systematic',
            ],
            'final_prompt' => $userMessage,
            'summary' => self::normalizeSummary([
                'youtube_insight' => $referenceUrl ? 'Menggunakan referensi metode dari URL YouTube yang Anda sertakan.' : 'Tidak ada referensi khusus.',
                'ai_chat_insight' => $aiChatUrl ? 'Mengintegrasikan topik dari sesi chat AI yang diberikan.' : 'Tidak ada referensi chat AI khusus.',
                'training_schedule' => 'Latihan rutin bertahap sesuai jadwal harian Anda.',
                'overview' => 'Target: Mempelajari dan menguasai ' . $title . ' dalam ' . $days . ' hari.'
            ], $days, $title)
        ];
    }

    /**
     * Generate the FULL day-by-day roadmap in a single one-shot AI request.
     * Generates all targets from Day 1 to Day N efficiently.
     *
     * @param Skill $skill
     * @return array Array of target definitions
     */
    public function generateFullRoadmap(Skill $skill): array
    {
        $days = (int) ($skill->target_days ?: 30);
        $userId = $skill->user_id ?? auth()->id();

        // 1. For milestones > 14 days (e.g. 30, 60, 90 days), directly synthesize from the
        // AI Coach blueprint (phases, weekly routine, reference URLs).
        // This generates all days instantaneously (< 10ms) and completely prevents Nginx 502 Bad Gateway timeouts.
        if ($days > 14 || !$this->apiKey) {
            AiLoggerService::log(
                userId: $userId,
                feature: 'skill_planner_roadmap',
                promptText: "Blueprint Synthesis: {$skill->title} ({$days} days)",
                model: 'blueprint_synthesizer',
                status: 'success'
            );
            return $this->generateFallbackRoadmap($skill);
        }

        // 2. For short milestones (<= 14 days), attempt fast one-shot generation
        // using only the primary high-speed model with a strict 8-second timeout.
        $primaryModel = AiLoggerService::getPrimaryModel();
        if (Cache::has("gemini_cooldown_{$primaryModel}")) {
            return $this->generateFallbackRoadmap($skill);
        }

        $userTz = config('app.timezone', 'Asia/Jakarta');
        $baseDate = \Carbon\Carbon::parse($skill->start_date ?: $skill->created_at)->timezone($userTz)->startOfDay();
        $startDayName = $baseDate->translatedFormat('l');
        $startDateStr = $baseDate->format('Y-m-d');
        $activeDaysList = !empty($skill->training_days) ? (array) $skill->training_days : ['1', '2', '3', '4', '5', '6', '7'];
        $activeDaysStr = implode(', ', $activeDaysList);

        $prompt = "Kamu adalah ATOMA Curriculum Architect. Pengguna ingin mempelajari/menguasai skill: '{$skill->title}' dalam {$days} hari.
Prompt & Strategi: '{$skill->prompt}'.
Tanggal Mulai Hari 1: {$startDateStr} (Hari {$startDayName}).
Hari Latihan Aktif (1=Senin, 2=Selasa, 3=Rabu, 4=Kamis, 5=Jumat, 6=Sabtu, 7=Minggu): [{$activeDaysStr}].
Referensi Materi: " . ($skill->reference_url ?: 'Tidak ada') . "
Referensi Chat AI: " . ($skill->ai_chat_url ?: 'Tidak ada') . "

TUGAS:
Kembalikan HANYA JSON array valid (tanpa markdown backticks) berisi tepat {$days} objek dengan format:
[
  {
    \"day_number\": 1,
    \"title\": \"Judul target hari ini (maks 50 karakter)\",
    \"description\": \"Ringkasan materi/fokus hari ini dalam 1-2 kalimat.\",
    \"is_rest_day\": false,
    \"sub_tasks\": [
      \"Pelajari konsep dasar bab 1\",
      \"Praktik mandiri 1 sesi Pomodoro 25 menit\",
      \"Catat evaluasi dan ringkasan di jurnal\"
    ],
    \"reference_url\": null,
    \"ai_chat_url\": null
  }
]";

        $startTime = microtime(true);
        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$primaryModel}:generateContent?key={$this->apiKey}",
                [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.3,
                        'maxOutputTokens' => 4096,
                    ]
                ]
            );

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                $clean = trim(preg_replace('/^```json|```$/m', '', $rawText ?? ''));
                $decoded = json_decode($clean, true);

                AiLoggerService::logResponse(
                    userId: $userId,
                    feature: 'skill_planner_roadmap',
                    model: $primaryModel,
                    response: $response,
                    executionTimeMs: $durationMs,
                    promptText: "Roadmap: {$skill->title} ({$days} days)",
                    responseText: mb_substr($rawText ?? '', 0, 500)
                );

                if (is_array($decoded) && count($decoded) > 0) {
                    $structured = [];
                    foreach ($decoded as $idx => $item) {
                        $dayNum = (int) ($item['day_number'] ?? ($idx + 1));
                        $isRest = (bool) ($item['is_rest_day'] ?? false);
                        
                        $subTasks = [];
                        if (!empty($item['sub_tasks']) && is_array($item['sub_tasks'])) {
                            foreach ($item['sub_tasks'] as $st) {
                                $stTitle = is_array($st) ? ($st['title'] ?? '') : (string)$st;
                                if (trim($stTitle) !== '') {
                                    $subTasks[] = trim($stTitle);
                                }
                            }
                        }
                        if (empty($subTasks) && !empty($item['description'])) {
                            $lines = explode("\n", $item['description']);
                            foreach ($lines as $line) {
                                $cleanLine = trim(ltrim(trim($line), '•-*0123456789. '));
                                if (strlen($cleanLine) >= 4) {
                                    $subTasks[] = $cleanLine;
                                }
                            }
                        }
                        if (empty($subTasks)) {
                            $subTasks = $isRest 
                                ? ['Istirahat fisik & peregangan santai', 'Review materi sebelumnya dengan santai']
                                : ["Materi inti {$skill->title} hari ke-{$dayNum}", 'Selesaikan 1 sesi fokus Pomodoro 25 menit', 'Catat refleksi harian di jurnal'];
                        }

                        $structured[] = [
                            'day_number' => $dayNum,
                            'title' => !empty($item['title']) ? $item['title'] : "Hari {$dayNum}: Pendalaman {$skill->title}",
                            'description' => !empty($item['description']) ? $item['description'] : "• Pelajari materi {$skill->title} hari ke-{$dayNum}.\n• Selesaikan 1 sesi fokus Pomodoro 25 menit.",
                            'is_rest_day' => $isRest,
                            'sub_tasks' => $subTasks,
                            'reference_url' => $item['reference_url'] ?? $skill->reference_url,
                            'ai_chat_url' => $item['ai_chat_url'] ?? $skill->ai_chat_url,
                            'is_ai_generated' => true,
                        ];
                    }

                    if (count($structured) < $days) {
                        $fallback = $this->generateFallbackRoadmap($skill);
                        for ($i = count($structured); $i < $days; $i++) {
                            if (isset($fallback[$i])) {
                                $structured[] = $fallback[$i];
                            }
                        }
                    }

                    return $structured;
                }
            } elseif ($response->status() === 429) {
                Cache::put("gemini_cooldown_{$primaryModel}", true, now()->addMinutes(2));
            }
        } catch (\Exception $e) {
            Log::warning("Primary model {$primaryModel} failed in generateFullRoadmap: " . $e->getMessage());
        }

        // Seamless, instantaneous fallback
        AiLoggerService::log(
            userId: $userId,
            feature: 'skill_planner_roadmap',
            promptText: "Roadmap: {$skill->title} ({$days} days)",
            model: 'offline_heuristic',
            status: 'fallback'
        );

        return $this->generateFallbackRoadmap($skill);
    }

    /**
     * Parse imported syllabus text into structured daily habits and weekly training matrix.
     */
    public static function parseSyllabusContent(?string $text): array
    {
        if (empty($text)) {
            return ['habits' => [], 'weekly_matrix' => []];
        }

        $habits = [];
        $weeklyMatrix = [];

        // 1. Parse Hourly Architecture / Daily Habits Table:
        // Format: | **04.00 – 04.30** | **1. Bangun Tidur + 30 Menit Off-Screen**<br><br>**2. Beberes Kamar** | ... |
        // or: | 04.00 - 04.30 | 1. Bangun Tidur | ... |
        if (preg_match_all('/\|\s*\**([0-9]{1,2}[.:][0-9]{2}\s*[–\-\x{2013}\x{2014}]\s*[0-9]{1,2}[.:][0-9]{2})\**\s*\|([^\|]+)\|/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $time = trim($m[1]);
                $rawActivities = trim($m[2]);
                $parts = preg_split('/<br\s*\/?>/i', $rawActivities);
                foreach ($parts as $p) {
                    $clean = trim(preg_replace('/\*\*/', '', $p));
                    $clean = trim(preg_replace('/^[0-9]+\.\s*/', '', $clean));
                    if (!empty($clean)) {
                        $habits[] = [
                            'time' => $time,
                            'title' => $clean,
                            'full' => "[{$time}] {$clean}",
                        ];
                    }
                }
            }
        }

        // Fallback for list format: e.g. - 04.00 - 04.30: Bangun Tidur or 1. 04.00 - 04.30: Bangun Tidur
        if (empty($habits) && preg_match_all('/(?:^|\n)\s*(?:[-*•]|\d+\.)\s*(?:\[|\()?([0-9]{1,2}[.:][0-9]{2}\s*[–\-\x{2013}\x{2014}]\s*[0-9]{1,2}[.:][0-9]{2})(?:\]|\))?[:\s\-]+([^\n\r]+)/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $time = trim($m[1]);
                $clean = trim(preg_replace('/\*\*/', '', $m[2]));
                $clean = trim(preg_replace('/^[0-9]+\.\s*/', '', $clean));
                if (!empty($clean)) {
                    $habits[] = [
                        'time' => $time,
                        'title' => $clean,
                        'full' => "[{$time}] {$clean}",
                    ];
                }
            }
        }

        // 2. Parse Weekly Physical Training Matrix
        // Format: | **Senin** *(...)* | **05.00–05.35:** **Lari #1** ... | ... | ... | ... |
        $dayNameMap = [
            'senin' => 1, 'monday' => 1,
            'selasa' => 2, 'tuesday' => 2,
            'rabu' => 3, 'wednesday' => 3,
            'kamis' => 4, 'thursday' => 4,
            'jumat' => 5, 'friday' => 5,
            'sabtu' => 6, 'saturday' => 6,
            'minggu' => 7, 'sunday' => 7,
        ];

        if (preg_match_all('/\|\s*\*\*([A-Za-z]+)\*\*[^\|]*\|\s*([^\|]+)\|\s*([^\|]+)\|\s*([^\|]+)\|\s*([^\|]+)\|/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $dayName = strtolower(trim($m[1]));
                if (isset($dayNameMap[$dayName])) {
                    $iso = $dayNameMap[$dayName];
                    $pagi = trim(preg_replace('/\*\*/', '', $m[2]));
                    $siang = trim(preg_replace('/\*\*/', '', $m[3]));
                    $sore = trim(preg_replace('/\*\*/', '', $m[4]));
                    $gtg = trim(preg_replace('/\*\*/', '', $m[5]));
                    $isRest = str_contains(strtolower($pagi), 'istirahat total') || str_contains(strtolower($pagi), 'rest');

                    $weeklyMatrix[$iso] = [
                        'day_name' => ucfirst($dayName),
                        'iso' => $iso,
                        'pagi' => $pagi,
                        'siang' => $siang,
                        'sore' => $sore,
                        'gtg' => $gtg,
                        'is_rest' => $isRest,
                    ];
                }
            }
        }

        return [
            'habits' => $habits,
            'weekly_matrix' => $weeklyMatrix,
        ];
    }

    /**
     * Build day-specific subtasks tailored with daily habits and weekly routines.
     */
    public static function buildDaySubtasksFromSyllabus(int $iso, array $parsed, string $routineLabel = '', bool $isRestDay = false): array
    {
        $habits = $parsed['habits'] ?? [];
        $wm = $parsed['weekly_matrix'][$iso] ?? null;
        $isRest = $isRestDay || ($wm ? $wm['is_rest'] : false);

        if (empty($habits)) {
            if ($isRest) {
                return [
                    "Istirahat terencana: {$routineLabel}",
                    "Peregangan ringan (stretching) / relaksasi 10-15 menit",
                    "Hidrasi optimal dan evaluasi progres di jurnal",
                ];
            }
            return [
                "Sesi utama: {$routineLabel}",
                "Pemanasan dinamis & pendinginan teratur",
                "Selesaikan sesi fokus Pomodoro terencana",
                "Catat evaluasi latihan dan progres harian di jurnal",
            ];
        }

        $tasks = [];
        foreach ($habits as $h) {
            $titleLower = strtolower($h['title']);

            // Workout pagi / slot latihan utama
            if (str_contains($titleLower, 'workout pagi') || str_contains($titleLower, 'lari / sepeda') || str_contains($titleLower, 'latihan fisik')) {
                if ($isRest) {
                    $tasks[] = "[Pagi] Istirahat Total / Jalan Santai (Libur GtG & Pemulihan)";
                } elseif ($wm && !empty($wm['pagi'])) {
                    if (preg_match('/^([0-9]{1,2}[.:][0-9]{2}\s*[–\-\x{2013}\x{2014}]\s*[0-9]{1,2}[.:][0-9]{2})[:\s]+(.*)$/u', $wm['pagi'], $pm)) {
                        $pagiTime = trim($pm[1]);
                        $pagiTask = trim($pm[2]);
                        $tasks[] = "[{$pagiTime}] {$pagiTask}" . (!empty($wm['gtg']) ? " ({$wm['gtg']})" : "");
                    } else {
                        $tasks[] = (!empty($h['time']) ? "[{$h['time']}] " : "") . $wm['pagi'] . (!empty($wm['gtg']) ? " ({$wm['gtg']})" : "");
                    }
                } else {
                    $tasks[] = $h['full'];
                }
                continue;
            }

            // Laundry check (Selasa, Kamis, Sabtu)
            if (str_contains($titleLower, 'nyuci pakaian') || str_contains($titleLower, 'cuci pakaian')) {
                if (in_array($iso, [2, 4, 6])) {
                    $tasks[] = $h['full'];
                }
                continue;
            }

            // Tuesday special: 05.00-07.30 is coding morning, renang is 08.00
            if ($iso === 2 && str_contains($titleLower, 'build web')) {
                $tasks[] = "[05.00 – 07.30] Coding Pagi (Persiapan Renang Jam 08.00)";
                continue;
            }

            $tasks[] = $h['full'];
        }

        // Sore workout check (e.g. Workout A on Tuesday, Workout B on Saturday, Renang #2 on Friday)
        if ($wm && !empty($wm['sore']) && (str_contains(strtolower($wm['sore']), 'workout otot') || str_contains(strtolower($wm['sore']), 'renang #2') || str_contains(strtolower($wm['sore']), 'abs #'))) {
            if (preg_match('/^([0-9]{1,2}[.:][0-9]{2}\s*[–\-\x{2013}\x{2014}]\s*[0-9]{1,2}[.:][0-9]{2})[:\s]+(.*)$/u', $wm['sore'], $sm)) {
                $soreTime = trim($sm[1]);
                $soreTask = trim($sm[2]);
                $tasks[] = "[{$soreTime}] {$soreTask}";
            } else {
                $tasks[] = "[Sore] " . $wm['sore'];
            }
        }

        return $tasks;
    }

    /**
     * Generate structured, high-fidelity roadmap synthesizing the AI Coach blueprint
     * (phases, weekly routine, reference URLs, and active training days).
     */
    public function generateFallbackRoadmap(Skill $skill): array
    {
        $days = (int) ($skill->target_days ?: 30);
        $trainingDays = !empty($skill->training_days) ? (array) $skill->training_days : ['1', '2', '3', '4', '5', '6', '7'];
        $title = $skill->title;

        $userTz = config('app.timezone', 'Asia/Jakarta');
        $baseDate = \Carbon\Carbon::parse($skill->start_date ?: $skill->created_at)->timezone($userTz)->startOfDay();

        // Extract and normalize AI Coach blueprint summary
        $rawSummary = [];
        if (is_array($skill->interview_context)) {
            $rawSummary = $skill->interview_context['summary'] ?? (isset($skill->interview_context['weekly_routine']) ? $skill->interview_context : []);
        }
        $summary = self::normalizeSummary($rawSummary, $days, $title, $trainingDays);

        // Index weekly routine by ISO day of week (1 = Monday, 7 = Sunday)
        $routineByIso = [];
        if (!empty($summary['weekly_routine']) && is_array($summary['weekly_routine'])) {
            $dayNameMap = [
                'senin' => 1, 'monday' => 1,
                'selasa' => 2, 'tuesday' => 2,
                'rabu' => 3, 'wednesday' => 3,
                'kamis' => 4, 'thursday' => 4,
                'jumat' => 5, 'friday' => 5,
                'sabtu' => 6, 'saturday' => 6,
                'minggu' => 7, 'sunday' => 7,
            ];
            foreach ($summary['weekly_routine'] as $item) {
                if (isset($item['iso'])) {
                    $routineByIso[(int) $item['iso']] = $item;
                } elseif (isset($item['day_name'])) {
                    $nameLower = strtolower(trim($item['day_name']));
                    if (isset($dayNameMap[$nameLower])) {
                        $routineByIso[$dayNameMap[$nameLower]] = $item;
                    }
                }
            }
        }

        // Prepare phases
        $phases = !empty($summary['phases']) && is_array($summary['phases']) ? $summary['phases'] : [];
        $numPhases = count($phases);

        $roadmap = [];

        // Find syllabus text from interview_context, chat_history, or prompt
        $syllabusText = null;
        if (!empty($skill->interview_context['syllabus_text'])) {
            $syllabusText = $skill->interview_context['syllabus_text'];
        } elseif (!empty($skill->interview_context['chat_history']) && is_array($skill->interview_context['chat_history'])) {
            foreach ($skill->interview_context['chat_history'] as $msg) {
                $txt = $msg['text'] ?? '';
                if (str_contains($txt, 'Matriks Mingguan') || str_contains($txt, 'Rutinitas Harian') || str_contains($txt, 'Tabel Urutan') || str_contains($txt, 'Dopamine Detox') || str_contains($txt, 'BAGIAN 1') || str_contains($txt, 'BAGIAN 2')) {
                    $syllabusText = $txt;
                    break;
                }
            }
        }
        if (!$syllabusText && (str_contains($skill->prompt, 'Matriks Mingguan') || str_contains($skill->prompt, 'Rutinitas Harian') || str_contains($skill->prompt, 'Tabel Urutan'))) {
            $syllabusText = $skill->prompt;
        }

        $parsedSyllabus = self::parseSyllabusContent($syllabusText);

        for ($i = 1; $i <= $days; $i++) {
            $currentDate = (clone $baseDate)->addDays($i - 1);
            $dayOfWeekIso = (int) $currentDate->dayOfWeekIso; // 1 = Senin, ..., 7 = Minggu
            $dayName = $currentDate->translatedFormat('l');

            // Find matching routine
            $routine = $routineByIso[$dayOfWeekIso] ?? null;
            $routineType = strtolower($routine['type'] ?? '');
            $routineLabel = trim($routine['label'] ?? '');

            $isRestDay = false;
            if ($routineType === 'rest') {
                $isRestDay = true;
            } elseif (!empty($trainingDays)) {
                if (!in_array((string)$dayOfWeekIso, $trainingDays) && !in_array($dayOfWeekIso, $trainingDays)) {
                    $isRestDay = true;
                }
            }

            $wm = $parsedSyllabus['weekly_matrix'][$dayOfWeekIso] ?? null;
            if ($wm && !empty($wm['is_rest'])) {
                $isRestDay = true;
            }

            // Determine active phase
            if ($numPhases > 0) {
                $phaseIdx = (int) min($numPhases - 1, floor(($i - 1) / max(1, $days) * $numPhases));
                $activePhase = $phases[$phaseIdx] ?? $phases[0];
                $phaseName = $activePhase['name'] ?? ("Fase " . ($phaseIdx + 1));
                $phaseFocus = $activePhase['focus'] ?? "Fokus penguasaan {$title}";
            } else {
                $phaseName = "Fase Inti";
                $phaseFocus = "Fokus konsistensi dan teknik {$title}";
            }

            if (empty($routineLabel)) {
                $routineLabel = $isRestDay ? 'Rest & Recovery' : "Latihan {$title}";
            }

            // Generate high-fidelity bulk subtasks from parsed syllabus if available
            if (!empty($parsedSyllabus['habits'])) {
                $subTasks = self::buildDaySubtasksFromSyllabus($dayOfWeekIso, $parsedSyllabus, $routineLabel, $isRestDay);
            } else {
                if ($isRestDay) {
                    $subTasks = [
                        "Istirahat terencana: {$routineLabel}",
                        "Peregangan ringan (stretching) / relaksasi 10-15 menit",
                        "Review singkat progres {$phaseName} & catat refleksi santai",
                    ];
                } elseif ($i === $days) {
                    $subTasks = [
                        "Sesi Uji Capstone: {$routineLabel}",
                        "Evaluasi komprehensif performa dan pencapaian",
                        "Refleksi keberhasilan {$days} hari dan rencana keberlanjutan",
                    ];
                } else {
                    $subTasks = [
                        "Sesi utama: {$routineLabel}",
                        "Pemanasan dinamis & pendinginan teratur",
                        "Fokus fase ({$phaseName}): {$phaseFocus}",
                        "Catat evaluasi latihan dan progres harian di jurnal",
                    ];
                }
            }

            $taskCount = count($subTasks);

            if ($wm) {
                if ($isRestDay) {
                    $dayTitle = "Hari {$i} ({$dayName}): Istirahat Total & Pemulihan Fisik";
                    $dayDesc = "Hari {$i} dari tantangan {$title} • {$taskCount} tugas harian terjadwal (0 selesai). Fokus hari ini: Istirahat total, pemulihan pasif jaringan ikat, dan dekompresi mental.";
                } else {
                    $headline = $wm['pagi'];
                    if (!empty($wm['sore']) && (str_contains(strtolower($wm['sore']), 'workout otot') || str_contains(strtolower($wm['sore']), 'renang #2') || str_contains(strtolower($wm['sore']), 'abs #'))) {
                        $headline .= " + " . $wm['sore'];
                    }
                    if (!empty($wm['gtg'])) {
                        $headline .= " + " . $wm['gtg'];
                    }
                    $dayTitle = "Hari {$i} ({$dayName}): {$headline}";
                    $dayDesc = "Hari {$i} dari tantangan {$title} • {$taskCount} tugas harian terjadwal (0 selesai). Fokus utama hari ini: {$headline}.";
                }
            } elseif ($isRestDay) {
                $dayTitle = "Hari {$i} ({$dayName}): {$routineLabel}";
                $dayDesc = "Hari {$i} dari tantangan {$title} • {$taskCount} tugas harian terjadwal (0 selesai). Fokus hari ini: Pemulihan terencana {$routineLabel}.";
            } else {
                if ($i === $days) {
                    $dayTitle = "Hari {$i} ({$dayName}): Uji Capstone & Penguasaan {$title}";
                    $dayDesc = "Hari {$i} dari tantangan {$title} • {$taskCount} tugas harian terjadwal (0 selesai). Hari pembuktian akhir untuk validasi seluruh target.";
                } else {
                    $dayTitle = "Hari {$i} ({$dayName}): {$routineLabel}";
                    $dayDesc = "Hari {$i} dari tantangan {$title} • {$taskCount} tugas harian terjadwal (0 selesai). Fokus hari ini: {$routineLabel} ({$phaseName}).";
                }
            }

            // Include references if available
            $refUrl = $skill->reference_url;
            $aiUrl = $skill->ai_chat_url;

            $roadmap[] = [
                'day_number' => $i,
                'title' => $dayTitle,
                'description' => $dayDesc,
                'is_rest_day' => $isRestDay,
                'sub_tasks' => $subTasks,
                'reference_url' => $refUrl,
                'ai_chat_url' => $aiUrl,
                'is_ai_generated' => true,
            ];
        }

        return $roadmap;
    }

    /**
     * Create bulk individual tasks on the Task Board for a given SkillTarget.
     * Intelligently distinguishes Tasks vs SubTasks based on schedule and structure.
     */
    public static function createBulkTasksForTarget(Skill $skill, SkillTarget $target, $user): array
    {
        $userTz = config('app.timezone', 'Asia/Jakarta');
        $baseDate = \Carbon\Carbon::parse($skill->start_date ?: $skill->created_at)->timezone($userTz)->startOfDay();
        $targetDate = (clone $baseDate)->addDays($target->day_number - 1)->format('Y-m-d');

        // Check if tasks already exist for this target to prevent duplicate creation
        $existing = \App\Models\Task::where('user_id', $user->id)
            ->where('skill_target_id', $target->id)
            ->get();
        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        $skillMode = ($skill->mode === 'zen') ? 'zen' : 'sistematis';
        $createdTasks = [];

        // Helper to parse time range and clean title
        $parseTime = function (string $raw) {
            $startTime = null;
            $deadline = null;
            $cleanTitle = trim($raw);

            // Pattern: [04.00 – 04.30] Title or [04:00 - 04:30] Title
            if (preg_match('/^\[([0-9]{1,2})[.:]([0-9]{2})\s*[–\-\x{2013}\x{2014}]\s*([0-9]{1,2})[.:]([0-9]{2})\]\s*(.*)$/u', $raw, $m)) {
                $startTime = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
                $deadline = sprintf('%02d:%02d', (int)$m[3], (int)$m[4]);
                $cleanTitle = trim($m[5]);
            } elseif (preg_match('/^([0-9]{1,2})[.:]([0-9]{2})\s*[–\-\x{2013}\x{2014}]\s*([0-9]{1,2})[.:]([0-9]{2})[:\s\-]+(.*)$/u', $raw, $m)) {
                $startTime = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
                $deadline = sprintf('%02d:%02d', (int)$m[3], (int)$m[4]);
                $cleanTitle = trim($m[5]);
            } elseif (preg_match('/^\[([0-9]{1,2})[.:]([0-9]{2})\]\s*(.*)$/u', $raw, $m)) {
                $startTime = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
                $cleanTitle = trim($m[3]);
            } elseif (preg_match('/^\[(Pagi|Siang|Sore|Malam)\]\s*(.*)$/ui', $raw, $m)) {
                $cleanTitle = trim($m[2]);
                $period = strtolower($m[1]);
                if ($period === 'pagi') $startTime = '06:00';
                elseif ($period === 'siang') $startTime = '12:00';
                elseif ($period === 'sore') $startTime = '16:00';
                elseif ($period === 'malam') $startTime = '19:00';
            }

            return [
                'start_time' => $startTime,
                'deadline' => $deadline,
                'clean_title' => $cleanTitle ?: $raw,
            ];
        };

        // Helper to infer category and priority
        $categorize = function (string $title) {
            $t = strtolower($title);
            if (str_contains($t, 'workout') || str_contains($t, 'lari') || str_contains($t, 'renang') || str_contains($t, 'sepeda') 
                || str_contains($t, 'otot') || str_contains($t, 'gtg') || str_contains($t, 'abs') || str_contains($t, 'mandi') 
                || str_contains($t, 'sarapan') || str_contains($t, 'vitamin') || str_contains($t, 'tidur')) {
                return ['category' => 'Health', 'priority' => (str_contains($t, 'workout') || str_contains($t, 'lari') || str_contains($t, 'renang')) ? 'high' : 'normal'];
            }
            if (str_contains($t, 'build web') || str_contains($t, 'coding') || str_contains($t, 'programming') || str_contains($t, 'deep work') 
                || str_contains($t, 'kuliah') || str_contains($t, 'akademik') || str_contains($t, 'konten')) {
                return ['category' => 'Work', 'priority' => 'high'];
            }
            if (str_contains($t, 'qur\'an') || str_contains($t, 'tes koran') || str_contains($t, 'pauli') || str_contains($t, 'baca buku') || str_contains($t, 'belajar')) {
                return ['category' => 'Study', 'priority' => 'normal'];
            }
            if (str_contains($t, 'gaming') || str_contains($t, 'genshin') || str_contains($t, 'ea fc') || str_contains($t, 'journaling') 
                || str_contains($t, 'evaluasi') || str_contains($t, 'sholat') || str_contains($t, 'beberes') || str_contains($t, 'wind-down')) {
                return ['category' => 'Personal', 'priority' => 'normal'];
            }
            return ['category' => 'Skill', 'priority' => 'normal'];
        };

        // Extract sub-items (e.g. from workout GtG, multi-line activity, or sub-steps)
        $extractSubItems = function (string $rawTitle) {
            $subItems = [];
            $t = strtolower($rawTitle);

            if (str_contains($t, 'bangun tidur') && str_contains($t, 'off-screen')) {
                $subItems = [
                    'Zero Digital Input (Bangun & minum air suhu ruang)',
                    'Rapikan kasur (2 menit)',
                    'Sholat Subuh saat masuk waktu',
                ];
            } elseif (str_contains($t, 'workout') || str_contains($t, 'lari') || str_contains($t, 'renang') || str_contains($t, 'sepeda')) {
                if (str_contains($t, 'gtg')) {
                    $subItems = [
                        'Pemanasan dinamis & minum air/madu',
                        'Sesi utama sesuai target zona latihan',
                        'GtG: Pull-Up (3–5 rep) + Push-Up (8–12 rep) + Leg Raises (6–8 rep)',
                    ];
                }
            } elseif (str_contains($t, 'kuliah') && str_contains($t, 'akademik')) {
                $subItems = [
                    'Kuliah / sesi akademik aktif',
                    'Sholat Dzuhur & Ashar tepat waktu',
                    'GtG Set 2 & 3 di jeda kos',
                ];
            } elseif (str_contains($t, 'gaming') || str_contains($t, 'genshin')) {
                $subItems = [
                    'Genshin Daily (15 menit)',
                    'Compe EA FC (45 menit) — stop pukul 19.30',
                ];
            }

            return $subItems;
        };

        $subs = $target->subTasks;
        if ($subs->isNotEmpty()) {
            foreach ($subs as $sub) {
                $parsed = $parseTime($sub->title);
                $catMeta = $categorize($parsed['clean_title']);
                $subItems = $extractSubItems($sub->title);

                // Estimate minutes from times or default 30
                $estMin = 30;
                if ($parsed['start_time'] && $parsed['deadline']) {
                    $st = \Carbon\Carbon::parse($targetDate . ' ' . $parsed['start_time']);
                    $et = \Carbon\Carbon::parse($targetDate . ' ' . $parsed['deadline']);
                    $diff = (int) $st->diffInMinutes($et, false);
                    if ($diff > 0 && $diff <= 480) {
                        $estMin = $diff;
                    }
                }

                $task = $user->tasks()->create([
                    'skill_id' => $skill->id,
                    'skill_target_id' => $target->id,
                    'title' => $sub->title, // Preserves time badge like [04.00 – 04.30] Bangun Tidur
                    'description' => "Bagian dari milestone: {$skill->title} (Hari ke-{$target->day_number})",
                    'date' => $targetDate,
                    'start_time' => $parsed['start_time'],
                    'deadline' => $parsed['deadline'],
                    'category' => $catMeta['category'],
                    'priority' => $catMeta['priority'],
                    'estimated_minutes' => $estMin,
                    'is_completed' => (bool) $sub->is_completed,
                    'mode' => $skillMode,
                ]);

                // Attach subtasks to this task if structured breakdown exists
                foreach ($subItems as $itemTitle) {
                    $task->subTasks()->create([
                        'title' => $itemTitle,
                        'is_completed' => (bool) $sub->is_completed,
                    ]);
                }

                $createdTasks[] = $task;
            }
        } else {
            // Fallback: single task for target without subtasks
            $parsed = $parseTime($target->title);
            $catMeta = $categorize($parsed['clean_title']);

            $task = $user->tasks()->create([
                'skill_id' => $skill->id,
                'skill_target_id' => $target->id,
                'title' => "[Milestone] {$skill->title}: {$target->title}",
                'description' => $target->description,
                'date' => $targetDate,
                'start_time' => $parsed['start_time'],
                'deadline' => $parsed['deadline'],
                'category' => $catMeta['category'],
                'priority' => $catMeta['priority'],
                'estimated_minutes' => 45,
                'is_completed' => $target->status === 'completed',
                'mode' => $skillMode,
            ]);

            $createdTasks[] = $task;
        }

        return $createdTasks;
    }

    /**
     * Interactive consultation chat with AI Coach for an active milestone.
     */
    public function chatWithCoach(Skill $skill, array $chatHistory, string $userMessage): array
    {
        $userTz = config('app.timezone', 'Asia/Jakarta');
        $today = now($userTz);
        $currDay = (int) ($skill->current_day ?: 1);
        $totalDays = (int) ($skill->target_days ?: 30);
        $trainingDays = !empty($skill->training_days) ? array_map('strval', (array) $skill->training_days) : ['1', '2', '3', '4', '5', '6', '7'];

        // Normalize current baseline weekly routine
        $rawSummary = [];
        if (is_array($skill->interview_context)) {
            $rawSummary = $skill->interview_context['summary'] ?? (isset($skill->interview_context['weekly_routine']) ? $skill->interview_context : []);
        }
        $summary = self::normalizeSummary($rawSummary, $totalDays, $skill->title, $trainingDays);
        $currentRoutine = $summary['weekly_routine'] ?? [];

        $isAdjustRequest = (bool) preg_match('/(sesuai|adjust|re-adjust|re adjust|jadwal|kuliah|istirahat|rest day|ganti hari|geser|rubah|atur ulang|terlalu berat|kurangi)/i', $userMessage);

        if (!$this->apiKey) {
            if ($isAdjustRequest) {
                $adj = self::buildFallbackScheduleAdjustment($skill, $userMessage);
                return [
                    'reply' => "Siap! Aku sudah menyusun penyesuaian jadwal rutinitas mingguan dan kartu target milestone {$skill->title} agar pas dengan kesibukan dan energimu. Silakan cek preview di bawah dan terapkan perubahannya!",
                    'adjustment' => $adj
                ];
            }
            return [
                'reply' => "Semangat! Tetap konsisten dengan ritme latihanmu untuk menguasai {$skill->title}.",
                'adjustment' => null
            ];
        }

        $routineJson = json_encode($currentRoutine, JSON_UNESCAPED_UNICODE);
        $trainingDaysJson = json_encode($trainingDays);

        $systemInstruction = "You are an encouraging, pragmatic personal coach in ATOMA supporting a user on their milestone: '{$skill->title}'.
Currently they are on Day {$currDay} of {$totalDays}.
Goal Prompt: '{$skill->prompt}'.
Today is: {$today->translatedFormat('l, d F Y')}.
Current Training Days (ISO 1=Mon..7=Sun): {$trainingDaysJson}
Current Weekly Routine: {$routineJson}

The user is consulting/curhat with you about their progress, schedule constraints, college/work routine, fatigue, or questions.
Answer with warmth, practical advice, and actionable encouragement in Indonesian. Keep your responses concise (2-4 paragraphs).

YOU HAVE FULL COACHING AUTHORITY TO RE-ADJUST THE MILESTONE SCHEDULE, WEEKLY ROUTINE, AND TARGET CARDS.
If the user asks or hints at adjusting/re-arranging their schedule (e.g. college class schedule, work shifts, moving workouts to morning/afternoon, changing rest days, adding rest day, reducing intensity, re-adjusting cards):
1. Explain clearly in friendly Indonesian what you recommend and how the schedule is re-adjusted.
2. AT THE VERY END of your reply, you MUST output a JSON block wrapped in ```json ... ``` with this exact structure:
```json
{
  \"has_adjustment\": true,
  \"summary\": \"Ringkasan penyesuaian jadwal (contoh: Menggeser rest day ke hari kuliah padat dan memindahkan latihan renang)\",
  \"training_days\": [\"1\", \"2\", \"4\", \"5\", \"6\"],
  \"weekly_routine\": [
    {\"iso\": 1, \"day_name\": \"Senin\", \"type\": \"workout\", \"label\": \"Lari Zona 2 Pagi + Kuliah\"},
    {\"iso\": 2, \"day_name\": \"Selasa\", \"type\": \"workout\", \"label\": \"Workout Upper & Abs\"},
    {\"iso\": 3, \"day_name\": \"Rabu\", \"type\": \"rest\", \"label\": \"Rest & Kuliah Padat\"},
    {\"iso\": 4, \"day_name\": \"Kamis\", \"type\": \"workout\", \"label\": \"Sepeda Kadensi + Sesi Kuliah\"},
    {\"iso\": 5, \"day_name\": \"Jumat\", \"type\": \"workout\", \"label\": \"Renang & Core\"},
    {\"iso\": 6, \"day_name\": \"Sabtu\", \"type\": \"workout\", \"label\": \"Long Run / Latihan Otot B\"},
    {\"iso\": 7, \"day_name\": \"Minggu\", \"type\": \"rest\", \"label\": \"Full Rest Day\"}
  ],
  \"target_adjustments\": [
    {\"day_number\": {$currDay}, \"title\": \"Hari {$currDay}: Latihan Sesuai Jadwal Baru\", \"sub_tasks\": [\"Eksekusi sesi adaptif\", \"Catat progres di jurnal\"]}
  ]
}
```
If the user is NOT asking to adjust schedule or routine, do NOT output any json block.";

        $contents = [
            ['role' => 'user', 'parts' => [['text' => $systemInstruction]]],
            ['role' => 'model', 'parts' => [['text' => "Understood Coach. I am ready to advise and adjust the milestone schedule."]]]
        ];

        foreach ($chatHistory as $msg) {
            $contents[] = [
                'role' => ($msg['role'] ?? '') === 'ai' ? 'model' : 'user',
                'parts' => [['text' => $msg['text'] ?? '']]
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        $modelPool = AiLoggerService::getModelPool();
        foreach ($modelPool as $model) {
            if (Cache::has("gemini_cooldown_{$model}")) continue;
            try {
                $response = Http::timeout(15)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
                    ['contents' => $contents]
                );
                if ($response->successful()) {
                    $rawReply = trim($response->json('candidates.0.content.parts.0.text') ?? '');
                    if ($rawReply) {
                        $adjustment = null;
                        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/u', $rawReply, $matches)) {
                            $decoded = json_decode($matches[1], true);
                            if (is_array($decoded) && !empty($decoded['has_adjustment'])) {
                                $adjustment = $decoded;
                                $rawReply = trim(str_replace($matches[0], '', $rawReply));
                            }
                        }

                        // If user wanted an adjustment but LLM didn't return json, use fallback adjustment
                        if (!$adjustment && $isAdjustRequest) {
                            $adjustment = self::buildFallbackScheduleAdjustment($skill, $userMessage);
                        }

                        return [
                            'reply' => $rawReply,
                            'adjustment' => $adjustment
                        ];
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $fallbackAdj = $isAdjustRequest ? self::buildFallbackScheduleAdjustment($skill, $userMessage) : null;
        return [
            'reply' => "Tetap semangat! Ingat bahwa konsistensi kecil setiap hari jauh lebih berarti daripada intensitas sesaat. Aku siap membantumu menyesuaikan jadwal dan kartu milestone kapan pun dibutuhkan!",
            'adjustment' => $fallbackAdj
        ];
    }

    /**
     * Build intelligent fallback schedule adjustment when user requests re-adjusting
     * their schedule or routine.
     */
    public static function buildFallbackScheduleAdjustment(Skill $skill, string $userMessage): array
    {
        $currDay = (int) ($skill->current_day ?: 1);
        $totalDays = (int) ($skill->target_days ?: 30);
        $trainingDays = !empty($skill->training_days) ? array_map('strval', (array) $skill->training_days) : ['1', '2', '3', '4', '5', '6', '7'];

        $rawSummary = [];
        if (is_array($skill->interview_context)) {
            $rawSummary = $skill->interview_context['summary'] ?? (isset($skill->interview_context['weekly_routine']) ? $skill->interview_context : []);
        }
        $summary = self::normalizeSummary($rawSummary, $totalDays, $skill->title, $trainingDays);
        $routine = $summary['weekly_routine'] ?? [];

        $lowerMsg = strtolower($userMessage);

        $dayKeywords = [
            1 => ['senin', 'monday'],
            2 => ['selasa', 'tuesday'],
            3 => ['rabu', 'wednesday'],
            4 => ['kamis', 'thursday'],
            5 => ['jumat', 'jum\'at', 'friday'],
            6 => ['sabtu', 'saturday'],
            7 => ['minggu', 'ahad', 'sunday'],
        ];

        $modifiedDays = [];
        foreach ($dayKeywords as $iso => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($lowerMsg, $kw)) {
                    $modifiedDays[] = $iso;
                    break;
                }
            }
        }

        // If no specific day mentioned but mentions kuliah or capek or rest, shift Wednesday
        if (empty($modifiedDays)) {
            if (str_contains($lowerMsg, 'kuliah') || str_contains($lowerMsg, 'berat') || str_contains($lowerMsg, 'capek') || str_contains($lowerMsg, 'sesuai')) {
                $modifiedDays = [3]; // Rabu jadikan rest / kuliah
            }
        }

        $updatedRoutine = [];
        $updatedTrainingDays = $trainingDays;

        foreach ($routine as $r) {
            $iso = (int) ($r['iso'] ?? 0);
            $dayName = $r['day_name'] ?? 'Hari';
            $type = $r['type'] ?? 'workout';
            $label = $r['label'] ?? 'Latihan Fokus';

            if (in_array($iso, $modifiedDays)) {
                if (str_contains($lowerMsg, 'kuliah') || str_contains($lowerMsg, 'istirahat') || str_contains($lowerMsg, 'rest') || str_contains($lowerMsg, 'libur')) {
                    $type = 'rest';
                    $label = "Rest & Kuliah / Pemulihan ({$dayName})";
                    $updatedTrainingDays = array_values(array_filter($updatedTrainingDays, fn($d) => (int)$d !== $iso));
                } else {
                    $type = 'workout';
                    $label = "Sesi Latihan Adaptif ({$dayName})";
                    if (!in_array((string)$iso, $updatedTrainingDays)) {
                        $updatedTrainingDays[] = (string)$iso;
                    }
                }
            }

            $updatedRoutine[] = [
                'iso' => $iso,
                'day_name' => $dayName,
                'type' => $type,
                'label' => $label,
            ];
        }

        sort($updatedTrainingDays);

        return [
            'has_adjustment' => true,
            'summary' => "Penyesuaian ritme mingguan: menyelaraskan jadwal kuliah dan istirahat agar energi optimal tanpa risiko burnout.",
            'training_days' => array_values(array_unique($updatedTrainingDays)),
            'weekly_routine' => $updatedRoutine,
            'target_adjustments' => [
                [
                    'day_number' => $currDay,
                    'title' => "Hari {$currDay}: Penyesuaian Ritme Latihan & Kuliah",
                    'description' => "• Sesi terpandu hasil konsultasi AI Coach.\n• Selesaikan 1-2 blok fokus tanpa membebani jadwal kuliah.",
                    'sub_tasks' => [
                        ['title' => "Eksekusi sesi adaptif Hari {$currDay}", 'is_completed' => false],
                        ['title' => 'Review kesesuaian ritme baru di jurnal', 'is_completed' => false],
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate dynamic bulk tasks for a milestone using AI Coach.
     * Incorporates current day, phase, weekly routine, and optional focus topic.
     *
     * @param Skill $skill
     * @param string|null $focus
     * @param int $count
     * @return array Array of task title strings
     */
    public function generateBulkTasksForSkill(Skill $skill, ?string $focus = null, int $count = 5): array
    {
        $userId = $skill->user_id ?? auth()->id();
        $currDay = $skill->current_day ?: 1;
        $totalDays = $skill->target_days ?: 30;
        $title = $skill->title;

        // Try fast Gemini generation if apiKey available
        if ($this->apiKey) {
            $primaryModel = AiLoggerService::getPrimaryModel();
            if (!Cache::has("gemini_cooldown_{$primaryModel}")) {
                $prompt = "Kamu adalah ATOMA AI Coach. Buatkan {$count} tugas (tasks) spesifik, ringkas, dan sangat actionable untuk milestone: '{$title}'.
Pengguna saat ini berada di Hari Ke-{$currDay} dari {$totalDays} hari.
" . ($focus ? "Fokus khusus yang diminta user: '{$focus}'." : "Fokus: latihan harian progresif sesuai tahapannya.") . "
Kembalikan HANYA JSON array valid berupa string judul tugas (maks 60 karakter per tugas, tanpa nomor/bullet):
[
  \"Judul tugas 1\",
  \"Judul tugas 2\"
]";

                $startTime = microtime(true);
                try {
                    $response = Http::timeout(6)
                        ->connectTimeout(2)
                        ->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/{$primaryModel}:generateContent?key={$this->apiKey}",
                        [
                            'contents' => [['parts' => [['text' => $prompt]]]],
                            'generationConfig' => [
                                'responseMimeType' => 'application/json',
                                'temperature' => 0.4,
                                'maxOutputTokens' => 1024,
                            ]
                        ]
                    );

                    if ($response->successful()) {
                        $rawText = $response->json('candidates.0.content.parts.0.text');
                        $clean = trim(preg_replace('/^```json|```$/m', '', $rawText ?? ''));
                        $decoded = json_decode($clean, true);

                        if (is_array($decoded) && count($decoded) > 0) {
                            $tasks = [];
                            foreach ($decoded as $item) {
                                $tTitle = is_array($item) ? ($item['title'] ?? '') : (string)$item;
                                $tTitle = trim(preg_replace('/^([•\-*]|\d+[\.)])\s*/u', '', $tTitle));
                                if (!empty($tTitle)) {
                                    $tasks[] = $tTitle;
                                }
                            }
                            if (count($tasks) >= 2) {
                                AiLoggerService::logResponse(
                                    userId: $userId,
                                    feature: 'skill_bulk_tasks',
                                    model: $primaryModel,
                                    response: $response,
                                    executionTimeMs: (int) round((microtime(true) - $startTime) * 1000),
                                    promptText: "Bulk Tasks: {$title} ({$focus})",
                                    responseText: json_encode($tasks)
                                );
                                return array_slice($tasks, 0, $count);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("AI bulk task generation failed: " . $e->getMessage());
                }
            }
        }

        // High-Fidelity Local Fallback Synthesis
        $routineLabel = null;
        if (!empty($skill->interview_context['summary']['weekly_routine'])) {
            $userTz = config('app.timezone', 'Asia/Jakarta');
            $dayIso = now($userTz)->dayOfWeekIso;
            foreach ($skill->interview_context['summary']['weekly_routine'] as $r) {
                if (($r['iso'] ?? 0) === $dayIso) {
                    $routineLabel = $r['label'] ?? null;
                    break;
                }
            }
        }

        $baseFocus = $focus ?: ($routineLabel ?: $title);

        return [
            "Sesi Utama: " . $baseFocus . " (25-30 Menit)",
            "Pemanasan dinamis & mobilitas sendi 10 menit",
            "Praktik teknik inti & repetisi terarah {$title}",
            "Pendinginan statis (stretching) & relaksasi otot",
            "Catat refleksi capaian dan evaluasi sesi di jurnal",
        ];
    }

    /**
     * Normalize AI summary ensuring phases and weekly_routine are always well-formed.
     */
    public static function normalizeSummary(array $summary, int $days, string $title, array $trainingDays = []): array
    {
        if (empty($trainingDays)) {
            $trainingDays = ['1', '2', '3', '4', '5', '6', '7'];
        }

        // 1. Ensure phases exist
        if (empty($summary['phases']) || !is_array($summary['phases'])) {
            $p1End = max(1, (int) round($days * 0.25));
            $p2End = max($p1End + 1, (int) round($days * 0.50));
            $p3End = max($p2End + 1, (int) round($days * 0.75));
            $summary['phases'] = [
                [
                    'phase' => 1,
                    'name' => 'Fondasi & Adaptasi',
                    'days' => "Hari 1 - {$p1End}",
                    'focus' => "Pembiasaan ritme awal, teknik dasar mobilitas, dan penyesuaian beban {$title}."
                ],
                [
                    'phase' => 2,
                    'name' => 'Pembentukan Teknik & Konsistensi',
                    'days' => 'Hari ' . ($p1End + 1) . " - {$p2End}",
                    'focus' => "Peningkatan stabilitas form, repetisi terpandu, dan konsistensi harian."
                ],
                [
                    'phase' => 3,
                    'name' => 'Latihan Terapan & Intensitas',
                    'days' => 'Hari ' . ($p2End + 1) . " - {$p3End}",
                    'focus' => "Penguatan kapasitas fisik, variasi tantangan, dan ketahanan tubuh."
                ],
                [
                    'phase' => 4,
                    'name' => 'Uji Penguasaan & Kebiasaan Permanen',
                    'days' => 'Hari ' . ($p3End + 1) . " - {$days}",
                    'focus' => "Evaluasi pencapaian akhir, pembuktian performa personal record (PR), & sertifikasi."
                ],
            ];
        }

        // 2. Ensure weekly routine exists
        if (empty($summary['weekly_routine']) || !is_array($summary['weekly_routine'])) {
            $dayNames = [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu',
            ];
            $routine = [];
            foreach ($dayNames as $iso => $name) {
                $isGo = in_array((string)$iso, $trainingDays) || in_array($iso, $trainingDays);
                $routine[] = [
                    'day_name' => $name,
                    'iso' => $iso,
                    'type' => $isGo ? 'workout' : 'rest',
                    'label' => $isGo ? 'Latihan / Fokus Utama' : 'Rest & Recovery',
                ];
            }
            $summary['weekly_routine'] = $routine;
        }

        return $summary;
    }

    /**
     * Backward-compatible single day target generation.
     */
    public function generateNextDayTarget(Skill $skill): array
    {
        $dayNumber = $skill->current_day;
        $roadmap = $this->generateFallbackRoadmap($skill);

        foreach ($roadmap as $r) {
            if ($r['day_number'] === $dayNumber) {
                return $r;
            }
        }

        return [
            'day_number' => $dayNumber,
            'title' => "Hari {$dayNumber}: Pendalaman {$skill->title}",
            'description' => "• Pelajari struktur materi {$skill->title}.\n• Selesaikan 1 sesi fokus Pomodoro 25 menit.",
            'is_rest_day' => false,
            'reference_url' => $skill->reference_url,
            'ai_chat_url' => $skill->ai_chat_url,
            'is_ai_generated' => false,
        ];
    }

    /**
     * Evaluate daily progress based on user's feedback.
     * 100% Deterministic Local Heuristic (0 Token, 0 Latency).
     * Eliminates micro-feedback token waste while providing rich contextual motivation.
     */
    public function evaluateDailyProgress(string $skillTitle, string $targetTitle, string $targetDesc, string $userFeedback): string
    {
        $clean = trim($userFeedback);
        $lower = strtolower($clean);

        if (empty($clean)) {
            return "Langkah nyata sudah diambil hari ini. Istirahat sejenak dan pertahankan ritme esok hari! 💪";
        }

        // 1. Detection: Struggle / Fatigue
        if (str_contains($lower, 'capek') || str_contains($lower, 'lelah') || str_contains($lower, 'berat') || str_contains($lower, 'pusing') || str_contains($lower, 'sulit') || str_contains($lower, 'susah') || str_contains($lower, 'gagal')) {
            $responses = [
                "Rasa lelah adalah tanda bahwa batas kemampuanmu sedang bertumbuh. Hebat sudah bertahan menyelesaikan target hari ini!",
                "Hari yang menantang! Yang terpenting kamu tidak menyerah dan tetap menuntaskannya. Istirahat yang cukup malam ini ya.",
                "Kemajuan sejati lahir dari hari-hari yang terasa berat. Banggalah pada usahamu hari ini! 🌟",
            ];
            return $responses[abs(crc32($clean)) % count($responses)];
        }

        // 2. Detection: Success / High Momentum
        if (str_contains($lower, 'lancar') || str_contains($lower, 'mudah') || str_contains($lower, 'berhasil') || str_contains($lower, 'paham') || str_contains($lower, 'bisa') || str_contains($lower, 'senang') || str_contains($lower, 'keren')) {
            $responses = [
                "Luar biasa! Pemahamanmu semakin solid hari demi hari. Pertahankan momentum positif ini untuk target esok!",
                "Ritme yang sempurna! Menguasai {$skillTitle} terasa makin dekat ketika kamu konsisten menikmati prosesnya.",
                "Pencapaian mantap! Kamu membuktikan bahwa dengan fokus terarah, materi ini dapat kamu kuasai dengan baik. ✨",
            ];
            return $responses[abs(crc32($clean)) % count($responses)];
        }

        // 3. General Stoic & Adaptive Encouragement
        $generalQuotes = [
            "Konsistensi harian adalah kunci penguasaan sejati. Satu langkah lagi menuju tuntasnya {$skillTitle}!",
            "Latihan hari ini tercatat dengan baik. Setiap repetisi kecil akan mengakumulasi hasil besar di akhir program.",
            "Usaha yang konsisten tidak pernah sia-sia. Tetap jaga fokus dan energi untuk target berikutnya!",
            "Hebat sudah hadir dan menyelesaikan target hari ini. Progresmu nyata dan bergerak maju!",
        ];

        return $generalQuotes[abs(crc32($clean)) % count($generalQuotes)];
    }

    /**
     * Generate final badge for completion.
     */
    public function generateBadge(string $skillTitle, string $promptContext): array
    {
        $userId = auth()->id();

        if (!$this->apiKey) {
            return ['name' => 'Master of ' . $skillTitle, 'icon' => '🏆', 'date' => now()->toIso8601String()];
        }

        $prompt = "A user just completed a milestone challenge: '$skillTitle'.
Their initial goal was: '$promptContext'.
Reward them with a unique, creative badge.
Return ONLY a JSON object with 'name' (string) and 'icon' (a single emoji that best represents it).";

        $modelPool = AiLoggerService::getModelPool();

        foreach ($modelPool as $model) {
            if (Cache::has("gemini_cooldown_{$model}")) {
                continue;
            }

            $startTime = microtime(true);

            try {
                $response = Http::timeout(8)
                    ->connectTimeout(3)
                    ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
                    [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json']
                    ]
                );

                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $content = $response->json('candidates.0.content.parts.0.text');
                    if ($content) {
                        $decoded = json_decode($content, true);
                        if (is_array($decoded) && isset($decoded['name']) && isset($decoded['icon'])) {
                            AiLoggerService::logResponse(
                                userId: $userId,
                                feature: 'skill_planner_badge',
                                model: $model,
                                response: $response,
                                executionTimeMs: $durationMs,
                                promptText: $prompt,
                                responseText: $content
                            );

                            return [
                                'name' => $decoded['name'],
                                'icon' => $decoded['icon'],
                                'date' => now()->toIso8601String()
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return ['name' => 'Master of ' . $skillTitle, 'icon' => '🏆', 'date' => now()->toIso8601String()];
    }
}
