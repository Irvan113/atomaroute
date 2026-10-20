<?php

namespace App\Services;

use App\Models\User;
use App\Models\Task;
use App\Models\Journal;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class McpServerService
{
    /**
     * Handle incoming JSON-RPC 2.0 request for an authenticated user.
     */
    public static function handleJsonRpc(array $payload, User $user): array
    {
        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? '';
        $params = $payload['params'] ?? [];

        try {
            switch ($method) {
                case 'initialize':
                    return self::formatSuccessResponse($id, [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => [
                            'tools' => [
                                'listChanged' => false,
                            ],
                            'resources' => [
                                'listChanged' => false,
                            ],
                        ],
                        'serverInfo' => [
                            'name' => 'atoma-mcp-server',
                            'version' => '1.0.0',
                        ],
                    ]);

                case 'notifications/initialized':
                case 'ping':
                    return self::formatSuccessResponse($id, new \stdClass());

                case 'tools/list':
                    return self::formatSuccessResponse($id, [
                        'tools' => self::getAvailableTools(),
                    ]);

                case 'tools/call':
                    $toolName = $params['name'] ?? '';
                    $arguments = $params['arguments'] ?? [];
                    $result = self::executeTool($toolName, $arguments, $user);
                    return self::formatSuccessResponse($id, $result);

                default:
                    return self::formatErrorResponse($id, -32601, "Method '{$method}' not found.");
            }
        } catch (\Throwable $e) {
            Log::error("MCP Server Error ({$method}): " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return self::formatErrorResponse($id, -32603, "Internal MCP server error: " . $e->getMessage());
        }
    }

    /**
     * Returns schema definition of tools available on ATOMA MCP Server.
     */
    public static function getAvailableTools(): array
    {
        return [
            [
                'name' => 'atoma_get_tasks',
                'description' => 'Mengambil daftar to-do / tasks pengguna dari ATOMA untuk tanggal tertentu (default hari ini).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'Tanggal tugas format YYYY-MM-DD (contoh: 2026-10-08). Kosongkan untuk hari ini.',
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['all', 'pending', 'completed'],
                            'description' => "Filter status tugas: 'pending', 'completed', atau 'all' (default 'all').",
                        ],
                    ],
                ],
            ],
            [
                'name' => 'atoma_create_task',
                'description' => 'Membuat to-do / task baru di ATOMA untuk pengguna.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Judul tugas (wajib).',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Keterangan lengkap, link, atau catatan tugas.',
                        ],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['low', 'normal', 'high'],
                            'description' => "Tingkat prioritas tugas: 'low', 'normal', atau 'high' (default 'normal').",
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Kategori tugas, misal: Work, Personal, Study, Finance, Health (default Work).',
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Tanggal jadwal tugas format YYYY-MM-DD (default hari ini).',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'Jam mulai tugas format HH:MM (contoh: 14:00).',
                        ],
                        'deadline' => [
                            'type' => 'string',
                            'description' => 'Batas waktu tugas format HH:MM (contoh: 17:30).',
                        ],
                        'estimated_minutes' => [
                            'type' => 'integer',
                            'description' => 'Estimasi durasi pengerjaan dalam menit (contoh: 30).',
                        ],
                        'mode' => [
                            'type' => 'string',
                            'enum' => ['zen', 'sistematis'],
                            'description' => "Mode tugas: 'zen' (fleksibel) atau 'sistematis' (terjadwal).",
                        ],
                    ],
                    'required' => ['title'],
                ],
            ],
            [
                'name' => 'atoma_complete_task',
                'description' => 'Menandai to-do sebagai selesai di ATOMA dan memberikan reward gamifikasi XP & streak.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'integer',
                            'description' => 'ID tugas yang akan diselesaikan.',
                        ],
                    ],
                    'required' => ['task_id'],
                ],
            ],
            [
                'name' => 'atoma_search_notes',
                'description' => 'Mencari dokumen catatan di Journal / Knowledge Base ATOMA berdasarkan kata kunci.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Kata kunci atau topik yang dicari dalam catatan.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Batas jumlah catatan yang dikembalikan (default 5, maks 20).',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'atoma_create_note',
                'description' => 'Menyimpan catatan atau dokumen baru ke Journal ATOMA pengguna.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Judul catatan.',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Konten isi catatan (teks, markdown, atau HTML).',
                        ],
                    ],
                    'required' => ['title', 'content'],
                ],
            ],
            [
                'name' => 'atoma_get_user_stats',
                'description' => 'Melihat ringkasan statistik produktivitas dan gamifikasi pengguna (Level, XP, Streak, League).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
        ];
    }

    /**
     * Dispatches and executes a specific tool.
     */
    private static function executeTool(string $toolName, array $args, User $user): array
    {
        switch ($toolName) {
            case 'atoma_get_tasks':
                return self::toolGetTasks($args, $user);

            case 'atoma_create_task':
                return self::toolCreateTask($args, $user);

            case 'atoma_complete_task':
                return self::toolCompleteTask($args, $user);

            case 'atoma_search_notes':
                return self::toolSearchNotes($args, $user);

            case 'atoma_create_note':
                return self::toolCreateNote($args, $user);

            case 'atoma_get_user_stats':
                return self::toolGetUserStats($user);

            default:
                throw new \InvalidArgumentException("Unknown tool name: '{$toolName}'");
        }
    }

    /**
     * Tool: atoma_get_tasks
     */
    private static function toolGetTasks(array $args, User $user): array
    {
        $dateStr = !empty($args['date']) ? Carbon::parse($args['date'])->toDateString() : Carbon::today()->toDateString();
        $status = $args['status'] ?? 'all';

        $query = $user->tasks()->whereDate('date', $dateStr)->orderBy('start_time')->orderBy('created_at');

        if ($status === 'pending') {
            $query->where('is_completed', false);
        } elseif ($status === 'completed') {
            $query->where('is_completed', true);
        }

        $tasks = $query->get();

        if ($tasks->isEmpty()) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Tidak ada tugas untuk tanggal {$dateStr} (filter: {$status}).",
                    ],
                ],
            ];
        }

        $lines = ["Daftar tugas ATOMA untuk tanggal {$dateStr} (Total: " . $tasks->count() . "):"];
        foreach ($tasks as $idx => $t) {
            $check = $t->is_completed ? '[x]' : '[ ]';
            $time = $t->start_time ? " (Jam: " . substr($t->start_time, 0, 5) . ($t->deadline ? " - " . substr($t->deadline, 0, 5) : "") . ")" : "";
            $prio = $t->priority ? " [Prio: {$t->priority}]" : "";
            $cat = $t->category ? " #{$t->category}" : "";
            $lines[] = ($idx + 1) . ". {$check} (ID: {$t->id}) {$t->title}{$time}{$prio}{$cat}";

            if (!empty($t->description)) {
                $lines[] = "   Keterangan: " . str_replace("\n", " ", mb_substr($t->description, 0, 150));
            }
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => implode("\n", $lines),
                ],
            ],
        ];
    }

    /**
     * Tool: atoma_create_task
     */
    private static function toolCreateTask(array $args, User $user): array
    {
        $title = trim($args['title'] ?? '');
        if (empty($title)) {
            throw new \InvalidArgumentException("Parameter 'title' wajib diisi.");
        }

        $date = !empty($args['date']) ? Carbon::parse($args['date'])->toDateString() : Carbon::today()->toDateString();
        $priority = in_array($args['priority'] ?? '', ['low', 'normal', 'high']) ? $args['priority'] : 'normal';
        $category = !empty($args['category']) ? trim($args['category']) : 'Work';
        $mode = in_array($args['mode'] ?? '', ['zen', 'sistematis']) ? $args['mode'] : 'zen';
        $estimatedMinutes = isset($args['estimated_minutes']) ? (int) $args['estimated_minutes'] : 30;

        $task = $user->tasks()->create([
            'title' => mb_substr($title, 0, 200),
            'description' => !empty($args['description']) ? trim($args['description']) : null,
            'category' => $category,
            'priority' => $priority,
            'date' => $date,
            'start_time' => !empty($args['start_time']) ? $args['start_time'] : null,
            'deadline' => !empty($args['deadline']) ? $args['deadline'] : null,
            'estimated_minutes' => $estimatedMinutes,
            'mode' => $mode,
            'is_completed' => false,
        ]);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "✨ Tugas berhasil dibuat di ATOMA!\n• ID: {$task->id}\n• Judul: {$task->title}\n• Tanggal: {$task->date->toDateString()}\n• Prioritas: {$task->priority}\n• Kategori: {$task->category}\n• Mode: {$task->mode}",
                ],
            ],
        ];
    }

    /**
     * Tool: atoma_complete_task
     */
    private static function toolCompleteTask(array $args, User $user): array
    {
        $taskId = (int) ($args['task_id'] ?? 0);
        if ($taskId <= 0) {
            throw new \InvalidArgumentException("Parameter 'task_id' tidak valid.");
        }

        $task = $user->tasks()->find($taskId);
        if (!$task) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "❌ Tugas dengan ID {$taskId} tidak ditemukan di akun Anda.",
                    ],
                ],
            ];
        }

        if ($task->is_completed) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "ℹ️ Tugas '{$task->title}' (ID: {$task->id}) sudah dalam status selesai sebelumnya.",
                    ],
                ],
            ];
        }

        $task->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        // Award XP and evaluate gamification
        $evaluation = TaskXpService::evaluateTaskCompletion($task, $user);
        $awardedXp = (int) ($evaluation['xp'] ?? 0);
        $message = $evaluation['message'] ?? 'Tugas selesai!';

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "🎉 Tugas '{$task->title}' (ID: {$task->id}) berhasil diselesaikan!\n• Status: {$message}\n• Reward: +{$awardedXp} XP\n• Level Akun: {$user->fresh()->level} (Total XP: {$user->fresh()->xp})",
                ],
            ],
        ];
    }

    /**
     * Tool: atoma_search_notes
     */
    private static function toolSearchNotes(array $args, User $user): array
    {
        $query = trim($args['query'] ?? '');
        if (empty($query)) {
            throw new \InvalidArgumentException("Parameter 'query' wajib diisi.");
        }

        $limit = min(max((int) ($args['limit'] ?? 5), 1), 20);

        $notes = $user->journals()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        if ($notes->isEmpty()) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Tidak ditemukan catatan dengan kata kunci '{$query}'.",
                    ],
                ],
            ];
        }

        $lines = ["Ditemukan {$notes->count()} catatan di Journal ATOMA:"];
        foreach ($notes as $idx => $n) {
            $cleanContent = trim(strip_tags($n->content));
            $snippet = mb_substr($cleanContent, 0, 160) . (mb_strlen($cleanContent) > 160 ? '...' : '');
            $lines[] = ($idx + 1) . ". 📝 [ID: {$n->id}] **{$n->title}** (Diperbarui: {$n->updated_at->format('Y-m-d H:i')})\n   {$snippet}";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => implode("\n\n", $lines),
                ],
            ],
        ];
    }

    /**
     * Tool: atoma_create_note
     */
    private static function toolCreateNote(array $args, User $user): array
    {
        $title = trim($args['title'] ?? '');
        $content = trim($args['content'] ?? '');

        if (empty($title) || empty($content)) {
            throw new \InvalidArgumentException("Parameter 'title' dan 'content' wajib diisi.");
        }

        $journal = $user->journals()->create([
            'title' => mb_substr($title, 0, 200),
            'content' => $content,
        ]);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "📝 Catatan baru berhasil disimpan di Journal ATOMA!\n• ID: {$journal->id}\n• Judul: {$journal->title}\n• Waktu: {$journal->created_at->format('Y-m-d H:i')}",
                ],
            ],
        ];
    }

    /**
     * Tool: atoma_get_user_stats
     */
    private static function toolGetUserStats(User $user): array
    {
        $user->refresh();
        $todayTasks = $user->tasks()->whereDate('date', Carbon::today()->toDateString())->get();
        $completedToday = $todayTasks->where('is_completed', true)->count();
        $totalToday = $todayTasks->count();

        $statsText = "📊 Statistik Akun ATOMA:\n" .
            "• Pengguna: {$user->name} ({$user->email})\n" .
            "• Level: {$user->level} (XP: {$user->xp})\n" .
            "• Daily Streak: {$user->current_streak} hari (Rekor: {$user->longest_streak} hari)\n" .
            "• Liga: " . ucfirst($user->league ?? 'Bronze') . "\n" .
            "• Progres Tugas Hari Ini: {$completedToday}/{$totalToday} selesai (" .
            ($totalToday > 0 ? round(($completedToday / $totalToday) * 100) : 0) . "%)";

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $statsText,
                ],
            ],
        ];
    }

    /**
     * Helper to format JSON-RPC success response.
     */
    private static function formatSuccessResponse($id, $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * Helper to format JSON-RPC error response.
     */
    private static function formatErrorResponse($id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
