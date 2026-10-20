<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailMagicService
{
    /**
     * Parses an email into structured tasks with links and metadata using AI + Heuristic Fallback.
     */
    public static function parseEmailToTasks(string $emailContent, int $userId, ?string $targetDate = null): array
    {
        $cleanEmail = trim($emailContent);
        if (mb_strlen($cleanEmail) < 5) {
            return [];
        }

        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY') ?? '';
        $extractedTasks = null;

        // Extract raw URLs directly for safeguarding
        preg_match_all('/https?:\/\/[^\s<>"\'\)]+/i', $cleanEmail, $urlMatches);
        $foundUrls = array_values(array_unique($urlMatches[0] ?? []));

        // 1. Try Gemini AI if API key is present and not on quota cooldown
        if ($apiKey && !Cache::get('gemini_quota_reset_time')) {
            $promptText = "Kamu adalah ATOMA Email Magic AI. Ubah email berikut menjadi daftar to-do list terorganisir dengan tautan link lengkap:
---
{$cleanEmail}
---
Aturan:
1. Ekstrak 1 hingga 5 to-do spesifik yang actionable dari email ini.
2. Temukan SEMUA link/URL penting (link Zoom, Meet, Google Docs, Figma, Jira, Invoice, referensi web) dan sertakan ke dalam description atau field extracted_links.
3. Tentukan priority ('high' jika ada kata urgent/segera/deadline dekat, 'normal', atau 'low').
4. Tentukan category ('Work', 'Project', 'Finance', 'Study', atau 'Personal').
5. Tentukan estimated_minutes (misal 15, 30, 45, 60).

Kembalikan HANYA JSON array murni tanpa markdown backticks:
[
  {
    \"title\": \"Judul to-do ringkas & jelas\",
    \"description\": \"Konteks tugas dan keterangan link terkait\",
    \"category\": \"Work\",
    \"priority\": \"normal\",
    \"estimated_minutes\": 30,
    \"extracted_links\": [\"https://...\"]
  }
]";

            $modelPool = AiLoggerService::getModelPool();

            foreach ($modelPool as $model) {
                if (Cache::has("gemini_cooldown_{$model}")) {
                    continue;
                }

                $startTime = microtime(true);
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                    ])->timeout(12)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $promptText]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 1200,
                        ]
                    ]);

                    $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                    if ($response->successful()) {
                        $json = $response->json();
                        $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        $parsed = self::parseAiJsonResponse($rawText);

                        AiLoggerService::logResponse(
                            userId: $userId,
                            feature: 'email_magic_parser',
                            model: $model,
                            response: $response,
                            executionTimeMs: $durationMs,
                            promptText: $promptText,
                            responseText: $rawText
                        );

                        if (!empty($parsed) && is_array($parsed)) {
                            $extractedTasks = $parsed;
                            break;
                        }
                    } elseif ($response->status() === 429) {
                        Cache::put("gemini_cooldown_{$model}", true, now()->addMinutes(15));
                        AiLoggerService::logResponse(
                            userId: $userId,
                            feature: 'email_magic_parser',
                            model: $model,
                            response: $response,
                            executionTimeMs: $durationMs,
                            promptText: $promptText
                        );
                    } else {
                        AiLoggerService::logResponse(
                            userId: $userId,
                            feature: 'email_magic_parser',
                            model: $model,
                            response: $response,
                            executionTimeMs: $durationMs,
                            promptText: $promptText
                        );
                    }
                } catch (\Throwable $e) {
                    AiLoggerService::log(
                        userId: $userId,
                        feature: 'email_magic_parser',
                        promptText: $promptText,
                        model: $model,
                        executionTimeMs: (int) round((microtime(true) - $startTime) * 1000),
                        status: 'error',
                        errorMessage: $e->getMessage()
                    );
                }
            }
        }

        // 2. Smart Heuristic Fallback if AI was unavailable or failed
        if (empty($extractedTasks)) {
            $extractedTasks = self::fallbackHeuristicParse($cleanEmail, $foundUrls);
        }

        // Ensure links found in raw email are not omitted
        foreach ($extractedTasks as &$task) {
            if (empty($task['extracted_links']) && !empty($foundUrls)) {
                $task['extracted_links'] = $foundUrls;
            }
            if (!empty($task['extracted_links'])) {
                $linkText = implode("\n• ", array_map(fn($u) => "[Link Terkait]({$u})", $task['extracted_links']));
                if (!str_contains($task['description'] ?? '', 'http')) {
                    $task['description'] = trim(($task['description'] ?? '') . "\n\n🔗 Tautan Penting:\n• " . $linkText);
                }
            }
        }

        return $extractedTasks;
    }

    /**
     * Inserts extracted email tasks directly into user's tasks table.
     */
    public static function insertTasks(array $tasks, int $userId, ?string $targetDate = null): int
    {
        $user = User::find($userId);
        if (!$user || empty($tasks)) {
            return 0;
        }

        $date = $targetDate ? Carbon::parse($targetDate)->toDateString() : Carbon::today()->toDateString();
        $insertedCount = 0;

        foreach ($tasks as $t) {
            if (empty($t['title'])) continue;

            $task = $user->tasks()->create([
                'title' => mb_substr(trim($t['title']), 0, 200),
                'description' => !empty($t['description']) ? trim($t['description']) : null,
                'category' => !empty($t['category']) ? $t['category'] : 'Work',
                'priority' => in_array($t['priority'] ?? '', ['high', 'normal', 'low']) ? $t['priority'] : 'normal',
                'estimated_minutes' => isset($t['estimated_minutes']) ? (int) $t['estimated_minutes'] : 30,
                'date' => $date,
                'mode' => 'zen',
                'is_completed' => false,
            ]);

            $insertedCount++;
        }

        return $insertedCount;
    }

    /**
     * Safely parse JSON returned by Gemini AI.
     */
    private static function parseAiJsonResponse(string $rawText): ?array
    {
        $clean = trim($rawText);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);

        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            // If wrapped in an object { "tasks": [...] }
            if (isset($decoded['tasks']) && is_array($decoded['tasks'])) {
                return $decoded['tasks'];
            }
            // If single task returned as associative array
            if (isset($decoded['title'])) {
                return [$decoded];
            }
            return $decoded;
        }

        // Try extracting json bracket array
        if (preg_match('/\[\s*\{.*\}\s*\]/s', $clean, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (is_array($parsed)) return $parsed;
        }

        return null;
    }

    /**
     * Rule-based heuristic parser for emails when AI API is unavailable.
     */
    private static function fallbackHeuristicParse(string $email, array $foundUrls): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $email))));
        if (empty($lines)) return [];

        $subject = 'Email Task';
        $bodyLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^(?:Subject|Subjek|Re|Fwd|Judul):\s*(.*)$/i', $line, $subMatch)) {
                $subject = trim($subMatch[1]);
            } else {
                $bodyLines[] = $line;
            }
        }

        if ($subject === 'Email Task' && !empty($lines[0])) {
            $subject = mb_substr($lines[0], 0, 70);
        }

        $priority = 'normal';
        $category = 'Work';

        $lowerEmail = strtolower($email);
        if (str_contains($lowerEmail, 'urgent') || str_contains($lowerEmail, 'segera') || str_contains($lowerEmail, 'asap') || str_contains($lowerEmail, 'penting')) {
            $priority = 'high';
        }
        if (str_contains($lowerEmail, 'invoice') || str_contains($lowerEmail, 'bayar') || str_contains($lowerEmail, 'tagihan') || str_contains($lowerEmail, 'payment')) {
            $category = 'Finance';
        } elseif (str_contains($lowerEmail, 'kuliah') || str_contains($lowerEmail, 'tugas') || str_contains($lowerEmail, 'belajar')) {
            $category = 'Study';
        }

        $desc = implode("\n", array_slice($bodyLines, 0, 6));

        return [
            [
                'title' => $subject ?: 'Tugas dari Email',
                'description' => $desc,
                'category' => $category,
                'priority' => $priority,
                'estimated_minutes' => 30,
                'extracted_links' => $foundUrls,
            ]
        ];
    }
}
