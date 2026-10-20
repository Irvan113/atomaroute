<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiTaskDecomposerService
{
    /**
     * Decomposes a raw goal/prompt into a structured task with subtasks, category, priority, and time.
     */
    public static function decompose(string $prompt, ?string $selectedDate = null): array
    {
        $cleanPrompt = trim($prompt);
        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY') ?? '';

        // Attempt Gemini API if available and quota not exceeded
        if ($apiKey && !Cache::get('gemini_quota_reset_time')) {
            $promptText = "Kamu adalah ATOMA Productivity AI. Pecah tujuan atau ide tugas berikut menjadi rencana kerja terstruktur dengan langkah-langkah yang sangat spesifik dan aplikatif: '{$cleanPrompt}'.
Kembalikan HANYA JSON murni (tanpa markdown backticks) dengan format:
{
  \"title\": \"Judul ringkas & actionable (maks 60 karakter)\",
  \"description\": \"Penjelasan 1 kalimat mengenai fokus utama tugas ini\",
  \"category\": \"Work\" atau \"Study\" atau \"Health\" atau \"Finance\" atau \"Personal\",
  \"priority\": \"low\" atau \"normal\" atau \"high\",
  \"estimated_minutes\": integer (misal 30, 45, 60, atau 90),
  \"subtasks\": [
    \"Langkah detail 1\",
    \"Langkah detail 2\",
    \"Langkah detail 3\",
    \"Langkah detail 4\"
  ]
}";

            $modelPool = AiLoggerService::getModelPool();
            $userId = auth()->id();

            foreach ($modelPool as $model) {
                if (Cache::has("gemini_cooldown_{$model}")) {
                    continue;
                }

                $startTime = microtime(true);

                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                    ])->timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $promptText]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'maxOutputTokens' => 600,
                            'responseMimeType' => 'application/json',
                        ]
                    ]);

                    $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                    if ($response->successful()) {
                        $jsonText = $response->json('candidates.0.content.parts.0.text');
                        $cleanJson = trim(preg_replace('/^```json|```$/m', '', $jsonText ?? ''));
                        $data = json_decode($cleanJson, true);

                        AiLoggerService::logResponse(
                            userId: $userId,
                            feature: 'task_decomposer',
                            model: $model,
                            response: $response,
                            executionTimeMs: $durationMs,
                            promptText: $cleanPrompt,
                            responseText: $jsonText
                        );

                        if (is_array($data) && !empty($data['title']) && !empty($data['subtasks'])) {
                            return [
                                'title' => ucfirst($data['title']),
                                'description' => $data['description'] ?? 'Tugas otomatis di-generate oleh ATOMA AI Decomposer.',
                                'category' => in_array($data['category'] ?? '', ['Work', 'Study', 'Health', 'Finance', 'Personal']) ? $data['category'] : 'Personal',
                                'priority' => in_array($data['priority'] ?? '', ['low', 'normal', 'high']) ? $data['priority'] : 'normal',
                                'estimated_minutes' => (int) ($data['estimated_minutes'] ?? 45),
                                'subtasks' => array_values(array_filter(array_map('trim', (array) $data['subtasks']))),
                                'mode' => 'sistematis',
                            ];
                        }
                    } elseif ($response->status() === 429) {
                        Cache::put("gemini_cooldown_{$model}", true, now()->addMinutes(2));
                        AiLoggerService::logResponse(
                            userId: $userId,
                            feature: 'task_decomposer',
                            model: $model,
                            response: $response,
                            executionTimeMs: $durationMs,
                            promptText: $cleanPrompt
                        );
                        continue;
                    } else {
                        AiLoggerService::logResponse(
                            userId: $userId,
                            feature: 'task_decomposer',
                            model: $model,
                            response: $response,
                            executionTimeMs: $durationMs,
                            promptText: $cleanPrompt
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning("AI Decomposer {$model} error: " . $e->getMessage());
                    AiLoggerService::log(
                        userId: $userId,
                        feature: 'task_decomposer',
                        promptText: $cleanPrompt,
                        model: $model,
                        executionTimeMs: (int) round((microtime(true) - $startTime) * 1000),
                        status: 'error',
                        errorMessage: $e->getMessage()
                    );
                    continue;
                }
            }
        }

        // Offline / Keyword-based smart fallback
        AiLoggerService::log(
            userId: auth()->id(),
            feature: 'task_decomposer',
            promptText: $cleanPrompt,
            model: 'offline_heuristic',
            status: 'fallback'
        );

        // Offline / Keyword-based smart fallback
        return self::fallbackDecompose($cleanPrompt);
    }

    protected static function fallbackDecompose(string $cleanPrompt): array
    {
        $lower = strtolower($cleanPrompt);

        $category = 'General';
        $priority = 'normal';
        $estimatedMinutes = 45;
        $subtasks = [];
        $title = $cleanPrompt;
        $description = 'Tugas cerdas di-generate oleh ATOMA AI Decomposer.';

        if (str_contains($lower, 'belajar') || str_contains($lower, 'buku') || str_contains($lower, 'kuliah') || str_contains($lower, 'ujian') || str_contains($lower, 'study') || str_contains($lower, 'kursus') || str_contains($lower, 'docker') || str_contains($lower, 'laravel') || str_contains($lower, 'react') || str_contains($lower, 'python') || str_contains($lower, 'ai') || str_contains($lower, 'baca')) {
            $category = 'Study';
            $estimatedMinutes = 60;
            $subtasks = [
                'Pahami konsep fundamental & terminologi utama',
                'Buat rangkuman catatan & ekstrak Flashcard SRS',
                'Praktik hands-on / implementasi mini-project',
                'Review kendala & evaluasi pemahaman mandiri',
            ];
        } elseif (str_contains($lower, 'keuangan') || str_contains($lower, 'uang') || str_contains($lower, 'duit') || str_contains($lower, 'budget') || str_contains($lower, 'tagihan') || str_contains($lower, 'bayar') || str_contains($lower, 'investasi') || str_contains($lower, 'gaji')) {
            $category = 'Finance';
            $estimatedMinutes = 30;
            $subtasks = [
                'Rekap seluruh bukti transaksi & mutasi rekening',
                'Hitung selisih pemasukan vs pengeluaran',
                'Input dan update pos anggaran di Atoma Finance',
                'Set reminder untuk tagihan jatuh tempo berikutnya',
            ];
        } elseif (str_contains($lower, 'coding') || str_contains($lower, 'project') || str_contains($lower, 'fitur') || str_contains($lower, 'bug') || str_contains($lower, 'kerja') || str_contains($lower, 'meeting') || str_contains($lower, 'klien') || str_contains($lower, 'deploy')) {
            $category = 'Work';
            $estimatedMinutes = 90;
            $subtasks = [
                'Analisis requirement & rancang alur logic',
                'Coding implementasi modul atau fitur inti',
                'Testing skenario positif & edge cases',
                'Review code & commit dengan dokumentasi rapi',
            ];
        } elseif (str_contains($lower, 'olahraga') || str_contains($lower, 'gym') || str_contains($lower, 'lari') || str_contains($lower, 'workout') || str_contains($lower, 'sehat') || str_contains($lower, 'diet') || str_contains($lower, 'jogging')) {
            $category = 'Health';
            $estimatedMinutes = 40;
            $subtasks = [
                'Pemanasan dinamis & peregangan sendi (5-7 menit)',
                'Eksekusi menu latihan utama (25-30 menit)',
                'Pendinginan statis & stretching (5 menit)',
                'Rehidrasi air putih & catat log progres latihan',
            ];
        } else {
            $category = 'Personal';
            $estimatedMinutes = 45;
            $subtasks = [
                'Riset & siapkan perlengkapan/kebutuhan awal',
                'Eksekusi langkah tahap pertama',
                'Selesaikan bagian inti kegiatan dengan fokus',
                'Review hasil akhir & rapikan kembali',
            ];
        }

        if (str_contains($lower, 'urgent') || str_contains($lower, 'penting') || str_contains($lower, 'segera') || str_contains($lower, 'darurat') || str_contains($lower, 'deadline')) {
            $priority = 'high';
        } elseif (str_contains($lower, 'santai') || str_contains($lower, 'iseng') || str_contains($lower, 'nanti')) {
            $priority = 'low';
        }

        if (strlen($title) > 80) {
            $title = mb_substr($title, 0, 77) . '...';
        }
        $title = ucfirst($title);

        return [
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'priority' => $priority,
            'estimated_minutes' => $estimatedMinutes,
            'subtasks' => $subtasks,
            'mode' => 'sistematis',
        ];
    }
}
