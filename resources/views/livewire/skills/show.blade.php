<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Skill;
use App\Models\SkillTarget;
use App\Models\Task;
use App\Services\AiSkillPlannerService;
use App\Services\TaskXpService;

new #[Layout('layouts.app')] class extends Component {
    public Skill $skill;
    
    // Milestone Task Multi-Task Management State
    public $activeTab = 'roadmap'; // 'roadmap' or 'tasks'
    public $taskFilter = 'all'; // 'all', 'pending', 'completed'
    public $showTaskModal = false;
    public $editingTaskId = null;
    public $taskTitle = '';
    public $taskDescription = '';
    public $taskDate = '';
    public $taskStartTime = '';
    public $taskDeadline = '';
    public $taskPriority = 'normal';
    public $taskEstimatedMinutes = 30;

    // Bulk / Multi-Task State for Milestone
    public $taskModalTab = 'single'; // 'single' or 'bulk'
    public $bulkTasksInput = '';
    public $bulkTaskDate = '';
    public $bulkTaskDateOption = 'same_day'; // 'same_day' or 'sequential'
    public $bulkTaskPriority = 'normal';
    public $bulkTaskEstimatedMinutes = 30;
    public $bulkTaskAiFocus = '';
    public $isGeneratingBulkTasks = false;

    public $showEvalModal = false;
    public $evalDayId = null;
    public $userFeedback = '';

    // Target CRUD State
    public $showEditModal = false;
    public $editingTargetId = null;
    public $editTitle = '';
    public $editDescription = '';
    public $editReferenceUrl = '';
    public $editAiChatUrl = '';
    public $editMode = 'sistematis';
    public $editSubTasksList = [];

    public $showAddModal = false;
    public $newDayNumber = 1;
    public $newTitle = '';
    public $newDescription = '';
    public $newReferenceUrl = '';
    public $newAiChatUrl = '';
    public $newMode = 'sistematis';
    public $newSubTasksList = [];

    public $selectedWeek = 1;
    public $viewMode = 'timeline'; // 'timeline' or 'cards'
    public $showCertificateModal = false;
    public $showRestModal = false;
    public $restDayId = null;
    public $restNotes = '';

    // AI Coach Chat & Consultation State
    public $showCoachChatModal = false;
    public $coachUserMessage = '';
    public $isCoachChatLoading = false;
    public $coachChatHistory = [];
    public $pendingCoachAdjustment = null;
    public $showCoachTimelinePreview = false;
    public $coachPreviewFilter = 'all';
    public $showCoachRevisionBox = false;
    public $coachRevisionText = '';

    // Milestone Extension State
    public $showExtendModal = false;
    public $extendAdditionalDays = 14;

    public function quickAddSubTask($targetId, $title)
    {
        $title = trim($title);
        if (strlen($title) < 2) return;

        $target = $this->skill->targets()->findOrFail($targetId);
        $target->subTasks()->create([
            'title' => $title,
            'is_completed' => false,
        ]);

        $this->skill->load(['targets.subTasks', 'targets.task']);
        $this->dispatch('toast', message: 'Tugas baru berhasil ditambahkan ke card!', type: 'success');
    }

    public function openCoachChat()
    {
        $this->showCoachChatModal = true;
        $this->coachUserMessage = '';
        if (empty($this->coachChatHistory)) {
            $curr = $this->skill->current_day ?: 1;
            $this->coachChatHistory[] = [
                'role' => 'ai',
                'text' => "Halo! Aku Coach pribadimu untuk milestone {$this->skill->title}. Sekarang kamu ada di Hari Ke-{$curr}. Ada kendala dengan materi, jadwal, atau ingin evaluasi ritme latihanmu hari ini?"
            ];
        }
    }

    public function closeCoachChat()
    {
        $this->showCoachChatModal = false;
    }

    public function sendCoachMessage(AiSkillPlannerService $aiService)
    {
        $msg = trim($this->coachUserMessage);
        if (strlen($msg) < 2) return;

        if (auth()->check() && !auth()->user()->isPro() && count($this->coachChatHistory) >= 6) {
            $this->dispatch('show-toast', [
                'title' => 'Batas Chat Coach Akun Free',
                'message' => 'Akun Free dibatasi 3 sesi tanya jawab dengan AI Coach. Upgrade ke ATOMA PRO untuk konsultasi tanpa batas!',
                'icon' => 'crown'
            ]);
            $this->dispatch('open-pro-modal');
            return;
        }

        $this->coachChatHistory[] = ['role' => 'user', 'text' => $msg];
        $this->coachUserMessage = '';
        $this->isCoachChatLoading = true;

        $resp = $aiService->chatWithCoach($this->skill, $this->coachChatHistory, $msg);
        $adj = $resp['adjustment'] ?? null;
        $this->pendingCoachAdjustment = $adj;

        $this->coachChatHistory[] = [
            'role' => 'ai',
            'text' => $resp['reply'] ?? 'Tetap semangat!',
            'adjustment' => $adj
        ];
        $this->isCoachChatLoading = false;
    }

    public function applyCoachAdjustment($chatIndex = null)
    {
        $adj = null;
        if ($chatIndex !== null && isset($this->coachChatHistory[$chatIndex]['adjustment'])) {
            $adj = $this->coachChatHistory[$chatIndex]['adjustment'];
        }
        if (!$adj && $this->pendingCoachAdjustment) {
            $adj = $this->pendingCoachAdjustment;
        }

        if (!$adj) return;

        $userTz = config('app.timezone', 'Asia/Jakarta');
        $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();
        $currDay = (int) ($this->skill->current_day ?: 1);

        // 1. Update training days if provided
        if (!empty($adj['training_days']) && is_array($adj['training_days'])) {
            $this->skill->training_days = array_map('strval', $adj['training_days']);
        }

        // 2. Update interview_context weekly_routine and summary
        $ctx = $this->skill->interview_context ?: [];
        if (!isset($ctx['summary'])) {
            $ctx['summary'] = [];
        }
        if (!empty($adj['weekly_routine']) && is_array($adj['weekly_routine'])) {
            $ctx['summary']['weekly_routine'] = $adj['weekly_routine'];
        }
        if (!empty($adj['summary'])) {
            $ctx['summary']['training_schedule'] = $adj['summary'];
        }
        $this->skill->interview_context = $ctx;
        $this->skill->save();

        // 3. Update milestone targets from current_day onwards (preserving completed targets)
        $routineByIso = [];
        if (!empty($adj['weekly_routine'])) {
            foreach ($adj['weekly_routine'] as $r) {
                $routineByIso[$r['iso'] ?? 0] = $r;
            }
        }

        $customDays = [];
        if (!empty($adj['target_adjustments']) && is_array($adj['target_adjustments'])) {
            foreach ($adj['target_adjustments'] as $ta) {
                if (!empty($ta['day_number'])) {
                    $customDays[(int)$ta['day_number']] = $ta;
                }
            }
        }

        $trainingDays = $this->skill->training_days ?: ['1', '2', '3', '4', '5', '6', '7'];

        \Illuminate\Support\Facades\DB::transaction(function () use ($currDay, $baseDate, $trainingDays, $routineByIso, $customDays) {
            for ($d = $currDay; $d <= $this->skill->target_days; $d++) {
                $target = $this->skill->targets()->where('day_number', $d)->first();
                if ($target && $target->status === 'completed') {
                    continue;
                }

                $tDate = (clone $baseDate)->addDays($d - 1);
                $dayOfWeekIso = $tDate->dayOfWeekIso;
                $rInfo = $routineByIso[$dayOfWeekIso] ?? null;
                $isRest = $rInfo ? (($rInfo['type'] ?? '') === 'rest') : (!in_array((string)$dayOfWeekIso, $trainingDays));

                if (isset($customDays[$d])) {
                    $cd = $customDays[$d];
                    $targetTitle = $cd['title'] ?? "Hari {$d}";
                    if ($isRest && !str_contains($targetTitle, 'Rest & Recovery')) {
                        $cleanTitle = preg_replace('/^Hari\s+\d+:\s*/i', '', $targetTitle);
                        $targetTitle = "Hari {$d}: Rest & Recovery" . ($cleanTitle ? " ({$cleanTitle})" : "");
                    }
                    if (!$target) {
                        $target = $this->skill->targets()->create([
                            'day_number' => $d,
                            'title' => $targetTitle,
                            'description' => $cd['description'] ?? '',
                            'status' => 'pending',
                            'is_ai_generated' => true,
                        ]);
                    } else {
                        $target->update([
                            'title' => $targetTitle,
                            'description' => $cd['description'] ?? $target->description,
                        ]);
                    }

                    if (!empty($cd['sub_tasks']) && is_array($cd['sub_tasks'])) {
                        $target->subTasks()->delete();
                        foreach ($cd['sub_tasks'] as $st) {
                            $stTitle = is_array($st) ? ($st['title'] ?? '') : (string)$st;
                            if (trim($stTitle)) {
                                $target->subTasks()->create([
                                    'title' => trim($stTitle),
                                    'is_completed' => false,
                                ]);
                            }
                        }
                    }
                } elseif ($isRest) {
                    $restTitle = "Hari {$d}: Rest & Recovery ({$tDate->translatedFormat('l')})";
                    $restDesc = "• Hari pemulihan fisik & kognitif terjadwal ({$tDate->translatedFormat('l')}).\n• Hindari beban berat, lakukan peregangan santai dan review jurnal.";
                    if (!$target) {
                        $target = $this->skill->targets()->create([
                            'day_number' => $d,
                            'title' => $restTitle,
                            'description' => $restDesc,
                            'status' => 'pending',
                            'is_ai_generated' => true,
                        ]);
                    } else {
                        $target->update([
                            'title' => $restTitle,
                            'description' => $restDesc,
                        ]);
                    }

                    $target->subTasks()->delete();
                    $target->subTasks()->createMany([
                        ['title' => 'Istirahat & peregangan santai / jalan santai', 'is_completed' => false],
                        ['title' => 'Review catatan evaluasi dan kesiapan pekan ini', 'is_completed' => false],
                    ]);
                } else {
                    $label = $rInfo['label'] ?? "Latihan Terjadwal";
                    $workoutTitle = "Hari {$d}: {$label}";
                    if (!$target) {
                        $target = $this->skill->targets()->create([
                            'day_number' => $d,
                            'title' => $workoutTitle,
                            'description' => "• Sesi latihan fokus: {$label}\n• Selesaikan 1-2 sesi Pomodoro terarah.",
                            'status' => 'pending',
                            'is_ai_generated' => true,
                        ]);
                    } else {
                        if (str_contains($target->title, 'Rest & Recovery') || str_starts_with($target->title, "Hari {$d}:")) {
                            $target->update(['title' => $workoutTitle]);
                        }
                    }

                    if ($target->subTasks()->count() === 0 || $target->subTasks()->where('title', 'like', '%Istirahat%')->exists()) {
                        $target->subTasks()->delete();
                        $target->subTasks()->createMany([
                            ['title' => "Eksekusi sesi: {$label}", 'is_completed' => false],
                            ['title' => 'Catat progres dan metrik di jurnal', 'is_completed' => false],
                        ]);
                    }
                }

                // If today and systematic mode, sync task
                if ($d === $currDay && ($this->skill->mode === 'systematic' || $this->skill->mode === 'sistematis') && $target) {
                    \App\Services\AiSkillPlannerService::createBulkTasksForTarget($this->skill, $target, auth()->user());
                }
            }
        });

        if ($chatIndex !== null && isset($this->coachChatHistory[$chatIndex]['adjustment'])) {
            $this->coachChatHistory[$chatIndex]['adjustment']['applied'] = true;
        }
        $this->pendingCoachAdjustment = null;
        $this->showCoachTimelinePreview = false;
        $this->showCoachRevisionBox = false;

        $this->skill->load(['tasks', 'targets.subTasks', 'targets.task']);
        $this->skill->refresh();

        $this->coachChatHistory[] = [
            'role' => 'ai',
            'text' => "🎉 **Jadwal & Roadmap Berhasil Disesuaikan!**\n\nSeluruh ritme mingguan dan kartu target mulai Hari Ke-{$currDay} sudah diperbarui sesuai jadwal barumu. Kamu bisa langsung cek perubahannya di timeline tab Roadmap!"
        ];

        $this->dispatch('toast', message: 'Jadwal & roadmap milestone berhasil disesuaikan oleh AI Coach!', type: 'success');
    }

    public function dismissCoachAdjustment($chatIndex = null)
    {
        if ($chatIndex !== null && isset($this->coachChatHistory[$chatIndex]['adjustment'])) {
            $this->coachChatHistory[$chatIndex]['adjustment']['dismissed'] = true;
        }
        $this->pendingCoachAdjustment = null;
        $this->showCoachTimelinePreview = false;
        $this->showCoachRevisionBox = false;
        $this->dispatch('toast', message: 'Penyesuaian jadwal diabaikan.', type: 'info');
    }

    public function toggleCoachTimelinePreview()
    {
        $this->showCoachTimelinePreview = !$this->showCoachTimelinePreview;
    }

    public function toggleCoachRevisionBox()
    {
        $this->showCoachRevisionBox = !$this->showCoachRevisionBox;
        if ($this->showCoachRevisionBox) {
            $this->coachRevisionText = '';
        }
    }

    public function sendCoachRevision(AiSkillPlannerService $aiService, $customText = null)
    {
        $text = trim($customText ?: $this->coachRevisionText);
        if (strlen($text) < 2) return;

        $this->coachUserMessage = $text;
        $this->coachRevisionText = '';
        $this->showCoachRevisionBox = false;
        $this->sendCoachMessage($aiService);
    }

    public function computeScheduleChanges(array $adj): array
    {
        $changes = [];
        $prevTrainingDays = !empty($this->skill->training_days) ? (array)$this->skill->training_days : ['1', '2', '3', '4', '5', '6', '7'];
        $newTrainingDays = !empty($adj['training_days']) ? (array)$adj['training_days'] : $prevTrainingDays;

        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
        ];

        // 1. Ratio difference
        $prevCount = count($prevTrainingDays);
        $newCount = count($newTrainingDays);
        if ($prevCount !== $newCount) {
            $changes[] = [
                'type' => 'ratio',
                'icon' => '⚡',
                'title' => 'Rasio Rutinitas Mingguan',
                'desc' => "Diubah dari {$prevCount} hari menjadi {$newCount} hari latihan / minggu (" . max(0, 7 - $newCount) . " hari rest)."
            ];
        }

        // 2. Weekly routine comparison
        $prevRoutine = [];
        if (!empty($this->skill->interview_context['summary']['weekly_routine'])) {
            foreach ($this->skill->interview_context['summary']['weekly_routine'] as $r) {
                $prevRoutine[$r['iso'] ?? 0] = $r;
            }
        }

        $newRoutine = [];
        if (!empty($adj['weekly_routine'])) {
            foreach ($adj['weekly_routine'] as $r) {
                $newRoutine[$r['iso'] ?? 0] = $r;
            }
        }

        foreach ($dayNames as $iso => $name) {
            $wasTraining = in_array((string)$iso, $prevTrainingDays) || in_array($iso, $prevTrainingDays);
            $isTraining = in_array((string)$iso, $newTrainingDays) || in_array($iso, $newTrainingDays);

            $prevType = $prevRoutine[$iso]['type'] ?? ($wasTraining ? 'workout' : 'rest');
            $newType = $newRoutine[$iso]['type'] ?? ($isTraining ? 'workout' : 'rest');

            $prevLabel = $prevRoutine[$iso]['label'] ?? ($wasTraining ? 'Latihan Terjadwal' : 'Rest & Recovery');
            $newLabel = $newRoutine[$iso]['label'] ?? ($isTraining ? 'Latihan Terjadwal' : 'Rest & Recovery');

            if ($prevType !== $newType || $wasTraining !== $isTraining) {
                $fromBadge = $prevType === 'rest' ? 'Rest Day' : 'Workout';
                $toBadge = $newType === 'rest' ? 'Rest Day' : 'Workout';
                $changes[] = [
                    'type' => 'day',
                    'iso' => $iso,
                    'icon' => $newType === 'rest' ? '🛌' : '💪',
                    'title' => "Hari {$name}",
                    'desc' => "Diubah dari {$fromBadge} ➔ {$toBadge}" . (!empty($newLabel) ? " ({$newLabel})" : "") . "."
                ];
            } elseif ($prevLabel !== $newLabel && !empty($newLabel) && $newLabel !== 'Latihan Terjadwal') {
                $changes[] = [
                    'type' => 'label',
                    'iso' => $iso,
                    'icon' => '🎯',
                    'title' => "Fokus Hari {$name}",
                    'desc' => "Jadwal/jam disesuaikan: {$newLabel}."
                ];
            }
        }

        // 3. Target adjustments
        if (!empty($adj['target_adjustments'])) {
            foreach ($adj['target_adjustments'] as $ta) {
                $d = $ta['day_number'] ?? null;
                if ($d) {
                    $changes[] = [
                        'type' => 'target',
                        'day_number' => $d,
                        'icon' => '📌',
                        'title' => "Kartu Target Hari {$d}",
                        'desc' => "Diadaptasi ke: " . ($ta['title'] ?? 'Sesi adaptif baru') . "."
                    ];
                }
            }
        }

        return $changes;
    }

    public function getCoachPreviewRoadmap(array $adj): array
    {
        $days = (int) ($this->skill->target_days ?: 30);
        $userTz = config('app.timezone', 'Asia/Jakarta');
        $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();
        $currDay = (int) ($this->skill->current_day ?: 1);

        $trainingDays = !empty($adj['training_days']) ? array_map('strval', (array)$adj['training_days']) : ($this->skill->training_days ?: ['1', '2', '3', '4', '5', '6', '7']);

        $routineByIso = [];
        if (!empty($adj['weekly_routine'])) {
            foreach ($adj['weekly_routine'] as $r) {
                $routineByIso[$r['iso'] ?? 0] = $r;
            }
        }

        $customDays = [];
        if (!empty($adj['target_adjustments']) && is_array($adj['target_adjustments'])) {
            foreach ($adj['target_adjustments'] as $ta) {
                if (!empty($ta['day_number'])) {
                    $customDays[(int)$ta['day_number']] = $ta;
                }
            }
        }

        $existingTargets = $this->skill->targets->keyBy('day_number');

        $cards = [];
        for ($d = 1; $d <= $days; $d++) {
            $tDate = (clone $baseDate)->addDays($d - 1);
            $dayOfWeekIso = $tDate->dayOfWeekIso;
            $rInfo = $routineByIso[$dayOfWeekIso] ?? null;
            $isRest = $rInfo ? (($rInfo['type'] ?? '') === 'rest') : (!in_array((string)$dayOfWeekIso, $trainingDays));

            $existing = $existingTargets->get($d);

            if ($existing && $existing->status === 'completed') {
                $cards[] = [
                    'day_number' => $d,
                    'date_string' => $tDate->translatedFormat('d M Y'),
                    'day_name' => $tDate->translatedFormat('l'),
                    'title' => $existing->title,
                    'is_rest' => str_contains($existing->title, 'Rest & Recovery'),
                    'status' => 'completed',
                    'is_changed' => false,
                    'sub_tasks' => $existing->subTasks->pluck('title')->toArray(),
                ];
                continue;
            }

            if (isset($customDays[$d])) {
                $cd = $customDays[$d];
                $title = $cd['title'] ?? "Hari {$d}";
                if ($isRest && !str_contains($title, 'Rest & Recovery')) {
                    $clean = preg_replace('/^Hari\s+\d+:\s*/i', '', $title);
                    $title = "Hari {$d}: Rest & Recovery" . ($clean ? " ({$clean})" : "");
                }
                $subTasks = [];
                if (!empty($cd['sub_tasks'])) {
                    foreach ($cd['sub_tasks'] as $st) {
                        $subTasks[] = is_array($st) ? ($st['title'] ?? '') : (string)$st;
                    }
                }
                if (empty($subTasks)) {
                    $subTasks = $isRest
                        ? ['Istirahat terencana & pemulihan', 'Review jurnal evaluasi']
                        : ["Sesi latihan Hari {$d}", 'Catat progres di jurnal'];
                }
            } elseif ($isRest) {
                $title = "Hari {$d}: Rest & Recovery (" . ($rInfo['label'] ?? $tDate->translatedFormat('l')) . ")";
                $subTasks = [
                    'Istirahat & peregangan santai / jalan santai',
                    'Review catatan evaluasi dan kesiapan pekan ini',
                ];
            } else {
                $label = $rInfo['label'] ?? "Latihan Terjadwal";
                $title = "Hari {$d}: {$label}";
                $subTasks = [
                    "Eksekusi sesi: {$label}",
                    'Catat progres dan metrik di jurnal',
                ];
            }

            $isChanged = false;
            if ($existing) {
                $existingIsRest = str_contains($existing->title, 'Rest & Recovery');
                if ($existingIsRest !== $isRest || $existing->title !== $title) {
                    $isChanged = true;
                }
            } else {
                $isChanged = true;
            }

            $cards[] = [
                'day_number' => $d,
                'date_string' => $tDate->translatedFormat('d M Y'),
                'day_name' => $tDate->translatedFormat('l'),
                'title' => $title,
                'is_rest' => $isRest,
                'status' => $existing ? $existing->status : 'pending',
                'is_changed' => $isChanged,
                'sub_tasks' => $subTasks,
            ];
        }

        return $cards;
    }

    public function openExtendModal()
    {
        $this->showExtendModal = true;
        $this->extendAdditionalDays = 14;
    }

    public function closeExtendModal()
    {
        $this->showExtendModal = false;
    }

    public function extendMilestone()
    {
        $this->validate([
            'extendAdditionalDays' => 'required|integer|min:1|max:180',
        ]);

        $oldDays = (int) $this->skill->target_days;
        $newDays = $oldDays + (int) $this->extendAdditionalDays;

        $this->skill->update([
            'target_days' => $newDays
        ]);

        $userTz = config('app.timezone', 'UTC');
        $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();
        $trainingDays = $this->skill->training_days ?: ['1', '2', '3', '4', '5', '6', '7'];

        for ($d = $oldDays + 1; $d <= $newDays; $d++) {
            $tDate = (clone $baseDate)->addDays($d - 1);
            $dayOfWeekIso = (string) $tDate->dayOfWeekIso;
            $isRest = !empty($trainingDays) && !in_array($dayOfWeekIso, $trainingDays);

            $t = $this->skill->targets()->create([
                'day_number' => $d,
                'title' => $isRest ? "Hari {$d}: Rest & Recovery" : "Hari {$d}: Pendalaman Lanjutan {$this->skill->title}",
                'description' => $isRest
                    ? "• Hari istirahat terjadwal ({$tDate->translatedFormat('l')}).\n• Pemulihan stamina fisik & mental."
                    : "• Pendalaman materi lanjutan {$this->skill->title}.\n• Selesaikan 1 sesi fokus Pomodoro 25 menit.",
                'is_ai_generated' => true,
                'status' => 'pending'
            ]);

            $t->subTasks()->createMany($isRest ? [
                ['title' => 'Istirahat & peregangan santai', 'is_completed' => false],
                ['title' => 'Review materi sebelumnya', 'is_completed' => false],
            ] : [
                ['title' => "Materi lanjutan {$this->skill->title} tahap {$d}", 'is_completed' => false],
                ['title' => 'Praktik mandiri 1 sesi Pomodoro 25 menit', 'is_completed' => false],
                ['title' => 'Catat evaluasi di jurnal', 'is_completed' => false],
            ]);
        }

        $this->skill->load(['targets.subTasks', 'targets.task']);
        $this->showExtendModal = false;
        $this->dispatch('toast', message: "Milestone berhasil diperpanjang +{$this->extendAdditionalDays} hari! Total sekarang {$newDays} hari.", type: 'success');
    }

    public function openTaskModal($taskId = null, $targetId = null)
    {
        $this->editingTaskId = $taskId;
        $this->taskModalTab = 'single';
        $this->bulkTasksInput = '';
        $this->bulkTaskDateOption = 'same_day';
        $this->bulkTaskPriority = 'normal';
        $this->bulkTaskEstimatedMinutes = 30;

        $userTz = config('app.timezone', 'UTC');
        $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();

        if ($taskId) {
            $task = auth()->user()->tasks()->where('skill_id', $this->skill->id)->findOrFail($taskId);
            $this->taskTitle = $task->title;
            $this->taskDescription = $task->description ?? '';
            $this->taskDate = $task->date ? $task->date->format('Y-m-d') : now($userTz)->toDateString();
            $this->taskStartTime = $task->start_time ? substr($task->start_time, 0, 5) : '';
            $this->taskDeadline = $task->deadline ? substr($task->deadline, 0, 5) : '';
            $this->taskPriority = $task->priority ?: 'normal';
            $this->taskEstimatedMinutes = $task->estimated_minutes ?: 30;
            $this->bulkTaskDate = $this->taskDate;
        } else {
            $this->taskTitle = '';
            $this->taskDescription = '';
            
            if ($targetId) {
                $target = $this->skill->targets()->find($targetId);
                if ($target) {
                    $this->taskDate = (clone $baseDate)->addDays($target->day_number - 1)->format('Y-m-d');
                } else {
                    $this->taskDate = now($userTz)->toDateString();
                }
            } else {
                $this->taskDate = now($userTz)->toDateString();
            }

            $this->bulkTaskDate = $this->taskDate;
            $this->taskStartTime = '';
            $this->taskDeadline = '';
            $this->taskPriority = 'normal';
            $this->taskEstimatedMinutes = 30;
        }
        $this->showTaskModal = true;
    }

    public function closeTaskModal()
    {
        $this->showTaskModal = false;
        $this->editingTaskId = null;
        $this->taskModalTab = 'single';
        $this->taskTitle = '';
        $this->taskDescription = '';
        $this->taskDate = '';
        $this->taskStartTime = '';
        $this->taskDeadline = '';
        $this->taskPriority = 'normal';
        $this->taskEstimatedMinutes = 30;
        $this->bulkTasksInput = '';
    }

    public function saveMilestoneTask()
    {
        $this->validate([
            'taskTitle' => 'required|string|max:255',
            'taskDescription' => 'nullable|string|max:1000',
            'taskDate' => 'required|date',
            'taskPriority' => 'required|in:normal,medium,high',
            'taskEstimatedMinutes' => 'nullable|integer|min:5|max:480',
        ]);

        if ($this->editingTaskId) {
            $task = auth()->user()->tasks()->where('skill_id', $this->skill->id)->findOrFail($this->editingTaskId);
            $task->update([
                'title' => $this->taskTitle,
                'description' => $this->taskDescription,
                'date' => $this->taskDate,
                'start_time' => $this->taskStartTime ?: null,
                'deadline' => $this->taskDeadline ?: null,
                'priority' => $this->taskPriority,
                'estimated_minutes' => $this->taskEstimatedMinutes ?: 30,
            ]);
            $this->dispatch('toast', message: 'Tugas milestone berhasil diperbarui!', type: 'success');
        } else {
            auth()->user()->tasks()->create([
                'skill_id' => $this->skill->id,
                'title' => $this->taskTitle,
                'description' => $this->taskDescription,
                'date' => $this->taskDate,
                'start_time' => $this->taskStartTime ?: null,
                'deadline' => $this->taskDeadline ?: null,
                'priority' => $this->taskPriority,
                'category' => 'Milestone',
                'is_completed' => false,
                'estimated_minutes' => $this->taskEstimatedMinutes ?: 30,
                'mode' => $this->skill->mode === 'zen' ? 'zen' : 'sistematis',
            ]);
            $this->dispatch('toast', message: 'Tugas baru berhasil ditambahkan ke milestone!', type: 'success');
        }

        $this->closeTaskModal();
        $this->skill->load(['tasks', 'targets.subTasks', 'targets.task']);
        $this->skill->refresh();
        $this->dispatch('play-sound', preset: 'pop_bubble');
    }

    public function saveBulkMilestoneTasks()
    {
        $this->validate([
            'bulkTasksInput' => 'required|string|min:2',
            'bulkTaskDate' => 'required|date',
            'bulkTaskPriority' => 'required|in:normal,medium,high',
            'bulkTaskEstimatedMinutes' => 'nullable|integer|min:5|max:480',
        ]);

        $rawLines = explode("\n", $this->bulkTasksInput);
        $lines = array_values(array_filter(array_map('trim', $rawLines)));

        if (empty($lines)) {
            $this->dispatch('toast', message: 'Tuliskan minimal 1 judul tugas!', type: 'warning');
            return;
        }

        $userTz = config('app.timezone', 'UTC');
        $baseStartDate = \Carbon\Carbon::parse($this->bulkTaskDate)->timezone($userTz)->startOfDay();
        $skillMode = ($this->skill->mode === 'zen') ? 'zen' : 'sistematis';
        $estMin = (int) ($this->bulkTaskEstimatedMinutes ?: 30);
        $createdCount = 0;

        foreach ($lines as $index => $line) {
            $cleanTitle = trim(preg_replace('/^([•\-*]|\d+[\.)])\s*/u', '', $line));
            if (empty($cleanTitle)) {
                $cleanTitle = $line;
            }

            $targetDate = ($this->bulkTaskDateOption === 'sequential')
                ? (clone $baseStartDate)->addDays($index)->format('Y-m-d')
                : $baseStartDate->format('Y-m-d');

            auth()->user()->tasks()->create([
                'skill_id' => $this->skill->id,
                'title' => $cleanTitle,
                'description' => "Bagian dari milestone: {$this->skill->title}",
                'date' => $targetDate,
                'priority' => $this->bulkTaskPriority,
                'category' => 'Milestone',
                'is_completed' => false,
                'estimated_minutes' => $estMin,
                'mode' => $skillMode,
            ]);
            $createdCount++;
        }

        $this->closeTaskModal();
        $this->skill->load(['tasks', 'targets.subTasks', 'targets.task']);
        $this->skill->refresh();
        $this->dispatch('toast', message: "Berhasil menambahkan {$createdCount} tugas sekaligus ke milestone!", type: 'success');
        $this->dispatch('play-sound', preset: 'pop_bubble');
    }

    public function generateAiBulkTasks(AiSkillPlannerService $aiService, ?string $presetFocus = null)
    {
        if (auth()->check() && !auth()->user()->isPro()) {
            $this->dispatch('show-toast', [
                'title' => 'Fitur ATOMA PRO: AI Bulk Tasks',
                'message' => 'AI Bulk Task Generator adalah fitur eksklusif ATOMA PRO. Upgrade sekarang untuk membuat task otomatis sepuasnya!',
                'icon' => 'crown'
            ]);
            $this->dispatch('open-pro-modal');
            return;
        }

        if ($presetFocus) {
            $this->bulkTaskAiFocus = $presetFocus;
        }

        $this->isGeneratingBulkTasks = true;
        $tasks = $aiService->generateBulkTasksForSkill($this->skill, $this->bulkTaskAiFocus ?: null, 5);

        if (!empty($tasks)) {
            $formatted = implode("\n", $tasks);
            if (!empty(trim($this->bulkTasksInput))) {
                $this->bulkTasksInput = trim($this->bulkTasksInput) . "\n" . $formatted;
            } else {
                $this->bulkTasksInput = $formatted;
            }
            $this->dispatch('toast', message: '✨ AI Coach berhasil menyusun ' . count($tasks) . ' tugas ke daftar bulk!', type: 'success');
            $this->dispatch('play-sound', preset: 'pop_bubble');
        } else {
            $this->dispatch('toast', message: 'Gagal membuat tugas otomatis.', type: 'error');
        }

        $this->isGeneratingBulkTasks = false;
    }

    public function toggleMilestoneTask($taskId)
    {
        $task = auth()->user()->tasks()->where('skill_id', $this->skill->id)->findOrFail($taskId);
        $task->is_completed = !$task->is_completed;
        
        if ($task->is_completed) {
            $task->is_completed_overdue = $task->isOverdue();
            $task->save();
            $evaluation = TaskXpService::evaluateTaskCompletion($task, auth()->user());
            $this->dispatch('play-sound', preset: 'pop_bubble');
            $this->dispatch('toast', message: $evaluation['message'] ?? 'Tugas milestone selesai!', type: 'success');
        } else {
            $task->is_completed_overdue = false;
            $task->xp_settled_at = null;
            $task->save();
            $this->dispatch('toast', message: 'Status tugas milestone dikembalikan.', type: 'info');
        }

        $this->skill->load(['tasks', 'targets.subTasks', 'targets.task']);
        $this->skill->refresh();

        if ($this->skill->calculated_progress >= 100 && $this->skill->status !== 'completed') {
            $this->skill->update(['status' => 'completed']);
            auth()->user()->addPendingXp(100, 'skill', "Penguasaan Milestone Penuh: {$this->skill->title}", "Menuntaskan seluruh target dan tugas milestone", $this->skill->id);
            $this->dispatch('toast', message: 'Selamat! Seluruh tugas milestone berhasil diselesaikan! +100 Pending XP', type: 'success');
            $this->dispatch('play-sound', preset: 'fanfare');
            $this->dispatch('trigger-confetti');
        }
        $this->dispatch('profile-updated');
    }

    public function deleteMilestoneTask($taskId)
    {
        $task = auth()->user()->tasks()->where('skill_id', $this->skill->id)->findOrFail($taskId);
        $task->delete();
        $this->skill->load(['tasks', 'targets.subTasks', 'targets.task']);
        $this->skill->refresh();
        $this->dispatch('toast', message: 'Tugas milestone berhasil dihapus.', type: 'info');
    }

    public function getMilestoneTasksProperty()
    {
        $query = $this->skill->tasks()->orderBy('date', 'asc')->orderBy('created_at', 'asc');
        if ($this->taskFilter === 'pending') {
            $query->where('is_completed', false);
        } elseif ($this->taskFilter === 'completed') {
            $query->where('is_completed', true);
        }
        return $query->get();
    }

    public function setViewMode($mode)
    {
        $this->viewMode = in_array($mode, ['timeline', 'cards']) ? $mode : 'timeline';
    }

    public function openRestModal($targetId)
    {
        $target = $this->skill->targets()->findOrFail($targetId);
        $this->restDayId = $targetId;
        $this->restNotes = $target->evaluation_feedback && !str_starts_with($target->evaluation_feedback, 'Gagal') 
            ? $target->evaluation_feedback 
            : 'Hari istirahat & mindful recovery. Energi dipulihkan secara optimal!';
        $this->showRestModal = true;
    }

    public function confirmRestDay()
    {
        if (!$this->restDayId) return;
        $target = $this->skill->targets()->findOrFail($this->restDayId);
        
        $target->update([
            'evaluation_feedback' => $this->restNotes ?: 'Rest & Recovery: Hari pemulihan selesai dengan baik.',
            'status' => 'completed'
        ]);

        if ($task = $target->task) {
            $task->update(['is_completed' => true]);
        }

        $this->showRestModal = false;

        if ($target->day_number === $this->skill->current_day) {
            $this->advanceDay();
        } else {
            $this->dispatch('play-sound', preset: 'pop_bubble');
            $this->dispatch('toast', message: 'Rest Day berhasil dikonfirmasi! Energi siap untuk target berikutnya.', type: 'success');
        }
        
        $this->skill->load(['targets.subTasks', 'targets.task']);
        $this->skill->refresh();
    }

    public function getMilestoneVelocityProperty()
    {
        $userTz = config('app.timezone', 'UTC');
        $today = \Carbon\Carbon::now($userTz)->startOfDay();
        $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();
        $realtimeDay = (int) $baseDate->diffInDays($today) + 1;
        $expectedDay = min(max(1, $realtimeDay), $this->skill->target_days);
        $completedCount = $this->skill->targets->where('status', 'completed')->count();

        if ($this->skill->status === 'completed' || $completedCount >= $this->skill->target_days) {
            return [
                'status' => 'completed',
                'label' => 'Selesai 100%',
                'color' => 'emerald',
                'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                'description' => 'Seluruh target telah dikuasai!'
            ];
        }

        if ($completedCount > $expectedDay) {
            $diff = $completedCount - $expectedDay;
            return [
                'status' => 'ahead',
                'label' => "+{$diff} Hari Lebih Cepat",
                'color' => 'emerald',
                'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                'description' => "Luar biasa! Kamu menyelesaikan target lebih awal dari jadwal."
            ];
        }

        if ($completedCount >= ($expectedDay - 1)) {
            return [
                'status' => 'on_track',
                'label' => 'Sesuai Jadwal',
                'color' => 'blue',
                'badge' => 'bg-atoma-ice text-atoma-blue dark:bg-atoma-blue/20 dark:text-atoma-cyan border-atoma-aqua/50 dark:border-atoma-blue/40',
                'description' => 'Ritme belajarmu tepat sesuai rencana.'
            ];
        }

        $lag = $expectedDay - $completedCount;
        return [
            'status' => 'behind',
            'label' => "Perlu Dikejar (-{$lag} Hari)",
            'color' => 'amber',
            'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            'description' => "Ada beberapa target tertunda. Selesaikan sesi fokus untuk mengejar."
        ];
    }

    public function getMilestoneStreakProperty()
    {
        $currentDay = $this->skill->current_day;
        $targets = $this->skill->targets->keyBy('day_number');
        $streak = 0;

        for ($d = $currentDay; $d >= 1; $d--) {
            $t = $targets->get($d);
            if ($t && $t->status === 'completed') {
                $streak++;
            } elseif ($d === $currentDay && (!$t || $t->status !== 'completed')) {
                continue;
            } else {
                break;
            }
        }

        $bonusPct = $streak >= 7 ? 20 : ($streak >= 3 ? 10 : 0);

        return [
            'streak' => $streak,
            'bonus_pct' => $bonusPct,
        ];
    }

    public function openEditModal($targetId)
    {
        $target = $this->skill->targets()->with('subTasks')->findOrFail($targetId);
        $this->editingTargetId = $target->id;
        $this->editTitle = $target->title;
        $this->editDescription = $target->description ?? '';
        $this->editReferenceUrl = $target->reference_url ?? '';
        $this->editAiChatUrl = $target->ai_chat_url ?? '';
        
        $this->editSubTasksList = $target->subTasks->map(function($st) {
            return ['id' => $st->id, 'title' => $st->title, 'is_completed' => $st->is_completed];
        })->toArray();

        $tTask = $target->task;
        $this->editMode = $tTask ? $tTask->mode : ($this->skill->mode === 'systematic' || $this->skill->mode === 'sistematis' ? 'sistematis' : 'zen');
        $this->showEditModal = true;
    }

    public function addEditSubTask()
    {
        $this->editSubTasksList[] = ['id' => null, 'title' => '', 'is_completed' => false];
    }

    public function removeEditSubTask($index)
    {
        unset($this->editSubTasksList[$index]);
        $this->editSubTasksList = array_values($this->editSubTasksList);
    }

    public function updateTarget()
    {
        $this->validate([
            'editTitle' => 'required|string|min:2',
            'editDescription' => 'nullable|string',
            'editReferenceUrl' => 'nullable|url',
            'editAiChatUrl' => 'nullable|url',
        ]);

        $target = $this->skill->targets()->findOrFail($this->editingTargetId);
        $target->update([
            'title' => $this->editTitle,
            'description' => $this->editDescription,
            'reference_url' => $this->editReferenceUrl ?: null,
            'ai_chat_url' => $this->editAiChatUrl ?: null,
        ]);

        // Sync Sub-tasks for Milestone Target
        if (is_array($this->editSubTasksList)) {
            $existingIds = collect($this->editSubTasksList)->pluck('id')->filter()->toArray();
            $target->subTasks()->whereNotIn('id', $existingIds)->delete();

            foreach ($this->editSubTasksList as $st) {
                $stTitle = trim($st['title'] ?? '');
                if ($stTitle === '') continue;

                if (!empty($st['id'])) {
                    $target->subTasks()->where('id', $st['id'])->update(['title' => $stTitle]);
                } else {
                    $target->subTasks()->create(['title' => $stTitle, 'is_completed' => false]);
                }
            }
        }

        if ($this->editMode === 'sistematis') {
            if ($task = $target->task) {
                $task->update([
                    'title' => "[Milestone] {$this->skill->title}: {$this->editTitle}",
                    'description' => $this->editDescription,
                    'mode' => 'sistematis',
                ]);
            } else {
                \App\Services\AiSkillPlannerService::createBulkTasksForTarget($this->skill, $target, auth()->user());
            }
        } else {
            if ($task = $target->task) {
                $task->update(['mode' => 'zen']);
            }
        }

        $this->showEditModal = false;
        $this->editingTargetId = null;
        $this->skill->load(['targets.subTasks', 'targets.task']);
        $this->dispatch('toast', message: 'Target harian berhasil diperbarui!', type: 'success');
    }

    public function deleteTarget($targetId)
    {
        $target = $this->skill->targets()->findOrFail($targetId);
        if ($task = $target->task) {
            $task->delete();
        }
        $target->delete();

        $this->skill->load(['targets.subTasks', 'targets.task']);
        $this->dispatch('toast', message: 'Target harian dihapus.', type: 'nudge');
    }

    public function openAddModal($dayNumber = null)
    {
        $this->newDayNumber = $dayNumber ?: ($this->skill->targets()->max('day_number') + 1);
        $this->newTitle = '';
        $this->newDescription = '';
        $this->newReferenceUrl = '';
        $this->newAiChatUrl = '';
        $this->newSubTasksList = [];
        $this->newMode = ($this->skill->mode === 'systematic' || $this->skill->mode === 'sistematis') ? 'sistematis' : 'zen';
        $this->showAddModal = true;
    }

    public function addNewSubTask()
    {
        $this->newSubTasksList[] = ['title' => '', 'is_completed' => false];
    }

    public function removeNewSubTask($index)
    {
        unset($this->newSubTasksList[$index]);
        $this->newSubTasksList = array_values($this->newSubTasksList);
    }

    public function createTarget()
    {
        $this->validate([
            'newDayNumber' => 'required|integer|min:1',
            'newTitle' => 'required|string|min:2',
            'newDescription' => 'nullable|string',
            'newReferenceUrl' => 'nullable|url',
            'newAiChatUrl' => 'nullable|url',
        ]);

        $target = $this->skill->targets()->create([
            'day_number' => $this->newDayNumber,
            'title' => $this->newTitle,
            'description' => $this->newDescription,
            'reference_url' => $this->newReferenceUrl ?: null,
            'ai_chat_url' => $this->newAiChatUrl ?: null,
            'is_ai_generated' => false,
            'status' => 'pending',
        ]);

        // Save Sub-tasks
        if (is_array($this->newSubTasksList)) {
            foreach ($this->newSubTasksList as $st) {
                $stTitle = trim($st['title'] ?? '');
                if ($stTitle !== '') {
                    $target->subTasks()->create(['title' => $stTitle, 'is_completed' => false]);
                }
            }
        }

        if ($this->newMode === 'sistematis') {
            $userTz = config('app.timezone', 'UTC');
            $baseDate = $this->skill->start_date ?: $this->skill->created_at;
            $targetDate = \Carbon\Carbon::parse($baseDate)->timezone($userTz)->startOfDay()->addDays($target->day_number - 1)->format('Y-m-d');

            auth()->user()->tasks()->create([
                'skill_id' => $this->skill->id,
                'skill_target_id' => $target->id,
                'title' => "[Milestone] {$this->skill->title}: {$target->title}",
                'description' => $target->description,
                'date' => $targetDate,
                'category' => 'Skill',
                'priority' => 'medium',
                'is_completed' => false,
                'mode' => 'sistematis',
            ]);
        }

        $this->showAddModal = false;
        $this->skill->load(['targets.subTasks', 'targets.task']);
        $this->dispatch('toast', message: 'Target harian baru berhasil ditambahkan!', type: 'success');
    }

    public function toggleTargetSubTask($targetId, $subTaskId)
    {
        $target = $this->skill->targets()->findOrFail($targetId);
        $subTask = $target->subTasks()->findOrFail($subTaskId);
        $subTask->is_completed = !$subTask->is_completed;
        $subTask->save();

        // Also sync corresponding Task in tasks table if bulk tasks exist
        $matchingTask = \App\Models\Task::where('skill_target_id', $target->id)
            ->where('title', $subTask->title)
            ->first();
        if ($matchingTask) {
            $matchingTask->update(['is_completed' => $subTask->is_completed]);
        }

        // Auto-sync Target completion based on subtasks
        $totalSubTasks = $target->subTasks()->count();
        $completedSubTasks = $target->subTasks()->where('is_completed', true)->count();
        
        if ($totalSubTasks > 0) {
            if ($completedSubTasks === $totalSubTasks) {
                $target->update(['status' => 'completed']);
                if ($task = $target->task) {
                    $task->update(['is_completed' => true]);
                }
                $this->dispatch('play-sound', preset: 'pop_bubble');
            } else {
                if ($target->status === 'completed') {
                    $target->update(['status' => 'pending']);
                    if ($task = $target->task) {
                        $task->update(['is_completed' => false]);
                    }
                }
            }
        }

        $this->skill->load(['targets.subTasks', 'targets.task']);
        $this->dispatch('toast', message: "Sub-task '{$subTask->title}' (" . ($subTask->is_completed ? 'Selesai' : 'Belum Selesai') . ")", type: 'nudge');
    }

    public function switchMode($newMode)
    {
        if (in_array($newMode, ['systematic', 'zen'])) {
            $this->skill->update(['mode' => $newMode]);
            $this->skill->refresh();
            $this->dispatch('toast', message: "Mode berhasil diubah ke Mode " . ucfirst($newMode) . "!", type: 'success');
        }
    }

    public function toggleTargetMode($targetId)
    {
        $target = $this->skill->targets()->findOrFail($targetId);
        
        $existingTasks = \App\Models\Task::where('skill_target_id', $target->id)->get();
        if ($existingTasks->isNotEmpty()) {
            $newMode = $existingTasks->first()->mode === 'zen' ? 'sistematis' : 'zen';
            foreach ($existingTasks as $t) {
                $t->update(['mode' => $newMode]);
            }
            $msg = $newMode === 'sistematis' ? 'Target dimasukkan ke Sistematis Task List!' : 'Target diubah ke Zen Mode';
        } else {
            \App\Services\AiSkillPlannerService::createBulkTasksForTarget($this->skill, $target, auth()->user());
            $msg = 'Target dimasukkan ke Sistematis Task List!';
        }
        
        $this->skill->load('targets');
        $this->dispatch('toast', message: $msg, type: 'success');
    }

    public $showWeeklyEvalModal = false;
    public $weeklyEvalData = null;
    public $isEvaluatingWeekly = false;

    public function evaluateOverdueTargets()
    {
        $userTz = config('app.timezone', 'UTC');
        $today = \Carbon\Carbon::now($userTz)->startOfDay();
        $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();

        // 1. Calculate real-time today day number
        $realtimeDay = (int) $baseDate->diffInDays($today) + 1;
        // If milestone is a task-based milestone with custom tasks and no targets, don't generate dummy targets
        if ($this->skill->tasks()->whereNull('skill_target_id')->exists() && $this->skill->targets()->count() === 0) {
            return;
        }

        // 2. Ensure targets up to today exist in DB
        $current_day = (int) ($this->skill->current_day ?: 1);
        $target_days = (int) ($this->skill->target_days ?: 30);
        $maxDayToEnsure = min(max($realtimeDay, $current_day), $target_days);
        $trainingDays = $this->skill->training_days ?: ['1', '2', '3', '4', '5', '6', '7'];
        for ($d = 1; $d <= $maxDayToEnsure; $d++) {
            $t = $this->skill->targets()->where('day_number', $d)->first();
            if (!$t) {
                $tDate = (clone $baseDate)->addDays($d - 1);
                $dayOfWeekIso = (string) $tDate->dayOfWeekIso;
                $isRest = !empty($trainingDays) && !in_array($dayOfWeekIso, $trainingDays);

                $t = $this->skill->targets()->create([
                    'day_number' => $d,
                    'title' => $isRest ? "Hari {$d}: Rest & Recovery" : "Hari {$d}: Pendalaman {$this->skill->title}",
                    'description' => $isRest
                        ? "• Hari istirahat terjadwal ({$tDate->translatedFormat('l')}).\n• Manfaatkan hari ini untuk pemulihan fisik & mental."
                        : "• Pelajari materi {$this->skill->title} tahap ke-{$d}.\n• Selesaikan 1 sesi fokus Pomodoro 25 menit.",
                    'is_ai_generated' => false,
                    'status' => 'pending'
                ]);

                // Create default subtasks
                $t->subTasks()->createMany($isRest ? [
                    ['title' => 'Istirahat fisik & tidur berkualitas', 'is_completed' => false],
                    ['title' => 'Review ringan materi sebelumnya', 'is_completed' => false],
                ] : [
                    ['title' => "Materi utama {$this->skill->title} hari ke-{$d}", 'is_completed' => false],
                    ['title' => 'Praktik mandiri 1 sesi Pomodoro 25 menit', 'is_completed' => false],
                    ['title' => 'Catat evaluasi ringkas di jurnal', 'is_completed' => false],
                ]);

                $defaultMode = ($this->skill->mode === 'systematic' || $this->skill->mode === 'sistematis') ? 'sistematis' : 'zen';
                if ($defaultMode === 'sistematis' && $d === 1) {
                    $tDateStr = $tDate->format('Y-m-d');
                    auth()->user()->tasks()->create([
                        'skill_id' => $this->skill->id,
                        'skill_target_id' => $t->id,
                        'title' => "[Milestone] {$this->skill->title}: {$t->title}",
                        'description' => $t->description,
                        'date' => $tDateStr,
                        'category' => 'Skill',
                        'priority' => 'medium',
                        'is_completed' => false,
                        'mode' => 'sistematis',
                    ]);
                }
            }
        }

        // 3. Mark past target days (before today) as skipped if still pending
        // Only do this if the skill is NOT already completed
        if ($this->skill->status !== 'completed') {
            foreach ($this->skill->targets as $target) {
                $targetDate = (clone $baseDate)->addDays($target->day_number - 1);
                // Don't mark skipped: (a) future days, (b) today's active day, (c) already completed/skipped
                $isActiveDay = $target->day_number === $this->skill->current_day;
                if ($targetDate->lt($today) && $target->status === 'pending' && !$isActiveDay) {
                    $target->update([
                        'status' => 'skipped',
                        'evaluation_feedback' => 'Gagal - Melewati batas waktu (No Reason)'
                    ]);
                    if ($task = $target->task) {
                        $task->update(['is_completed' => false]);
                    }
                }
            }
        }

        // 4. Update $skill->current_day to real-time day number (up to target_days)
        $clampedDay = min($realtimeDay, $this->skill->target_days);
        if ($clampedDay > $this->skill->current_day) {
            $this->skill->update(['current_day' => $clampedDay]);
        }

        $this->skill->load('targets');
    }

    public function getWeekCyclesProperty()
    {
        $targetDays = $this->skill->target_days;
        $weeks = [];
        $wNum = 1;
        
        for ($start = 1; $start <= $targetDays; $start += 7) {
            $end = min($start + 6, $targetDays);
            $weeks[$wNum] = [
                'week_number' => $wNum,
                'start_day' => $start,
                'end_day' => $end,
                'label' => $end == $targetDays && $start > 28 
                    ? "Week {$wNum} / Evaluasi Bulanan (Hari {$start} - {$end})" 
                    : "Week {$wNum} (Hari {$start} - {$end})"
            ];
            $wNum++;
        }
        
        return $weeks;
    }

    public function getSelectedWeekStatusProperty()
    {
        $cycles = $this->weekCycles;
        $wNum = (int) $this->selectedWeek;
        if (!isset($cycles[$wNum])) {
            $wNum = 1;
        }

        $wInfo = $cycles[$wNum];
        $startDay = $wInfo['start_day'];
        $endDay = $wInfo['end_day'];

        $userTz = config('app.timezone', 'UTC');
        $today = \Carbon\Carbon::now($userTz)->startOfDay();
        $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();
        $realtimeDay = (int) $baseDate->diffInDays($today) + 1;

        $targetCP = $this->skill->targets()->where('day_number', $endDay)->first();
        $hasResult = $targetCP && !empty($targetCP->evaluation_ai_suggestion);

        // Week is locked if realtime current day has NOT reached endDay, AND there is no existing result
        $isLocked = $realtimeDay < $endDay && !$hasResult;

        return [
            'week_number' => $wNum,
            'start_day' => $startDay,
            'end_day' => $endDay,
            'label' => $wInfo['label'],
            'has_result' => $hasResult,
            'is_locked' => $isLocked,
            'target' => $targetCP,
            'result' => $targetCP ? $targetCP->evaluation_ai_suggestion : null,
        ];
    }

    public function viewWeeklyEvalForWeek($weekNum)
    {
        $cycles = $this->weekCycles;
        if (!isset($cycles[$weekNum])) return;
        $wInfo = $cycles[$weekNum];
        $targetCP = $this->skill->targets()->where('day_number', $wInfo['end_day'])->first();

        if ($targetCP && !empty($targetCP->evaluation_ai_suggestion)) {
            $this->weeklyEvalData = $targetCP->evaluation_ai_suggestion;
            $this->showWeeklyEvalModal = true;
        } else {
            $this->dispatch('toast', message: "Hasil evaluasi Week #{$weekNum} belum tersedia.", type: 'error');
        }
    }

    public function runWeeklyEvaluation($dayCheckpoint = null)
    {
        $wStatus = $this->selectedWeekStatus;
        $checkpointDay = $dayCheckpoint ?: ($wStatus ? $wStatus['end_day'] : 7);
        $startDay = $wStatus ? $wStatus['start_day'] : ($checkpointDay == 7 ? 1 : 8);

        $this->isEvaluatingWeekly = true;
        
        // 1. Gather historical evaluations from previous checkpoints as guidelines
        $previousEvals = [];
        foreach ($this->skill->targets()->where('day_number', '<', $startDay)->get() as $prevT) {
            if ($prevT->evaluation_ai_suggestion && is_array($prevT->evaluation_ai_suggestion)) {
                $s = $prevT->evaluation_ai_suggestion;
                $previousEvals[] = "• Evaluasi Hari 1-{$prevT->day_number}: Score " . ($s['score'] ?? 85) . "/100 | Ringkasan: " . ($s['summary'] ?? '') . " | Rekomendasi: " . ($s['recommendations'] ?? '');
            }
        }
        $guidelinesText = count($previousEvals) > 0 ? "PEDOMAN EVALUASI MINGGUAN SEBELUMNYA (HISTORICAL GUIDELINES):\n" . implode("\n", $previousEvals) . "\n\n" : "";

        // 2. Gather current cycle target history & reflections
        $cycleTargets = $this->skill->targets()
            ->whereBetween('day_number', [$startDay, $checkpointDay])
            ->get();

        $historySummary = "";
        foreach ($cycleTargets as $t) {
            $refText = !empty($t->evaluation_feedback) && !str_starts_with($t->evaluation_feedback, 'Gagal') ? $t->evaluation_feedback : 'Tidak ada refleksi (skipped/gagal)';
            $historySummary .= "Hari {$t->day_number}: {$t->title} - [Status: {$t->status}] | Refleksi: {$refText}\n";
        }

        $apiKey = config('services.gemini.key');
        $isMonthly = $checkpointDay >= $this->skill->target_days;
        $feedbackResult = [
            'score' => 88,
            'summary' => $isMonthly 
                ? "Evaluasi Bulanan Selesai! Berdasarkan pedoman evaluasi mingguan H1-{$checkpointDay}, kamu telah menunjukkan ketahanan belajar yang baik."
                : "Evaluasi AI Mingguan (Hari {$startDay}-{$checkpointDay}) telah dianalisis. Progres latihan dicatat dan dijadikan pedoman untuk siklus berikutnya.",
            'strengths' => "Konsistensi pencatatan dan partisipasi aktif dalam milestone skill.",
            'recommendations' => $isMonthly 
                ? "Pertahankan penguasaan skill ini dan mulailah menerapkan ke proyek nyata atau tantangan tingkat lanjut."
                : "Fokus pada penguatan teknik dasar di minggu berikutnya dengan 1 sesi fokus harian."
        ];

        if ($apiKey && !\Illuminate\Support\Facades\Cache::get('gemini_quota_reset_time')) {
            $prompt = "You are a Senior Learning Coach. Evaluate the user's progress for skill '{$this->skill->title}' during cycle (Days {$startDay} to {$checkpointDay}).\n\n{$guidelinesText}CURRENT CYCLE HISTORY (Days {$startDay} - {$checkpointDay}):\n{$historySummary}\n\nReturn ONLY a raw JSON object with keys: 'score' (number 1-100), 'summary' (string in Indonesian), 'strengths' (string), and 'recommendations' (string for next cycle).";
            
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(15)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key={$apiKey}",
                    ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json']]
                );

                if ($response->successful()) {
                    $parsed = json_decode(trim($response->json('candidates.0.content.parts.0.text') ?? ''), true);
                    if (is_array($parsed)) {
                        $feedbackResult = array_merge($feedbackResult, $parsed);
                    }
                }
            } catch (\Exception $e) {}
        }

        // Save evaluation result on checkpoint target day
        $targetCP = $this->skill->targets()->where('day_number', $checkpointDay)->first();
        if (!$targetCP) {
            $targetCP = $this->skill->targets()->create([
                'day_number' => $checkpointDay,
                'title' => "Hari {$checkpointDay}: Checkpoint Evaluasi",
                'description' => "Evaluasi AI Coach untuk siklus Hari {$startDay}-{$checkpointDay}",
                'is_ai_generated' => true,
                'status' => 'completed'
            ]);
        }

        $targetCP->update([
            'evaluation_ai_suggestion' => $feedbackResult,
            'evaluation_feedback' => "[EVALUASI_MINGGUAN_SELESAI] " . ($feedbackResult['summary'] ?? '')
        ]);

        $this->weeklyEvalData = $feedbackResult;
        $this->isEvaluatingWeekly = false;
        $this->showWeeklyEvalModal = true;

        auth()->user()->addPendingXp(100, 'skill', "Evaluasi AI Skill: {$this->skill->title}", "Evaluasi checkpoint berkala modul skill", $this->skill->id);
        auth()->user()->increment('coins', 50);

        $this->skill->load('targets');
        $this->skill->refresh();

        $this->dispatch('trigger-confetti');
        $this->dispatch('toast', message: "Evaluasi AI (Hari {$startDay}-{$checkpointDay}) Selesai! Disimpan sebagai pedoman bulanan. +100 XP & +50 Coins!", type: 'success');
    }
    
    public function mount(Skill $skill)
    {
        if ($skill->user_id !== auth()->id()) {
            abort(403);
        }
        
        $this->skill = $skill;
        $this->skill->load(['tasks', 'targets.subTasks', 'targets.task']);
        $this->evaluateOverdueTargets();

        if ($this->skill->tasks()->count() > 0 && $this->skill->targets()->count() === 0) {
            $this->activeTab = 'tasks';
        } else {
            $this->activeTab = 'roadmap';
        }

        // Calculate default selected week based on realtime current day
        $userTz = config('app.timezone', 'UTC');
        $today = \Carbon\Carbon::now($userTz)->startOfDay();
        $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();
        $realtimeDay = (int) $baseDate->diffInDays($today) + 1;

        if ($realtimeDay <= 7) {
            $this->selectedWeek = 1;
        } elseif ($realtimeDay <= 14) {
            $this->selectedWeek = 2;
        } elseif ($realtimeDay <= 21) {
            $this->selectedWeek = 3;
        } elseif ($realtimeDay <= 28) {
            $this->selectedWeek = 4;
        } else {
            $this->selectedWeek = 5;
        }
    }

    public function resolveAiEvaluationChoice($targetId, $choice)
    {
        $target = $this->skill->targets()->findOrFail($targetId);
        
        if ($choice === 'use_ai' && is_array($target->evaluation_ai_suggestion)) {
            $sug = $target->evaluation_ai_suggestion;
            $nextDayNum = $target->day_number + 1;
            $nextTarget = $this->skill->targets()->where('day_number', $nextDayNum)->first();
            
            $aiTitle = !empty($sug['recommendations']) ? "Hari {$nextDayNum}: " . \Illuminate\Support\Str::limit($sug['recommendations'], 55) : "Hari {$nextDayNum}: Target Adaptif AI Coach";
            $aiDesc = $sug['recommendations'] ?? ($sug['summary'] ?? '');

            if ($nextTarget) {
                $nextTarget->update([
                    'title' => $aiTitle,
                    'description' => $aiDesc,
                    'is_ai_generated' => true,
                ]);
                if ($task = $nextTarget->task) {
                    $task->update([
                        'title' => "[Milestone] {$this->skill->title}: " . $aiTitle,
                        'description' => $aiDesc,
                    ]);
                }
            } else {
                $nextTarget = $this->skill->targets()->create([
                    'day_number' => $nextDayNum,
                    'title' => $aiTitle,
                    'description' => $aiDesc,
                    'is_ai_generated' => true,
                    'status' => 'pending'
                ]);

                $defaultMode = ($this->skill->mode === 'systematic' || $this->skill->mode === 'sistematis') ? 'sistematis' : 'zen';
                if ($defaultMode === 'sistematis') {
                    $userTz = config('app.timezone', 'UTC');
                    $baseDate = \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->timezone($userTz)->startOfDay();
                    $tDate = (clone $baseDate)->addDays($nextDayNum - 1)->format('Y-m-d');

                    auth()->user()->tasks()->create([
                        'skill_id' => $this->skill->id,
                        'skill_target_id' => $nextTarget->id,
                        'title' => "[Milestone] {$this->skill->title}: {$nextTarget->title}",
                        'description' => $nextTarget->description,
                        'date' => $tDate,
                        'category' => 'Skill',
                        'priority' => 'medium',
                        'is_completed' => false,
                        'mode' => 'sistematis',
                    ]);
                }
            }
            $this->dispatch('toast', message: 'Target Hari Ke-' . $nextDayNum . ' berhasil diperbarui dengan hasil AI Coach!', type: 'success');
        } else {
            $this->dispatch('toast', message: 'Tetap menggunakan template awal.', type: 'nudge');
        }

        $target->update(['evaluation_ai_suggestion' => null]);
        $this->skill->load('targets');
        $this->skill->refresh();
    }

    public function openEvalModal($targetId)
    {
        $target = $this->skill->targets()->findOrFail($targetId);
        
        if (str_contains($target->title, 'Rest & Recovery')) {
            $this->openRestModal($targetId);
            return;
        }

        $this->evalDayId = $targetId;
        $this->userFeedback = $target->evaluation_feedback && !str_starts_with($target->evaluation_feedback, 'Gagal') ? $target->evaluation_feedback : '';
        $this->showEvalModal = true;
    }

    public function submitEval()
    {
        $quality = \App\Services\ContentQualityService::validateText($this->userFeedback, 3, 10);
        if (!$quality['valid']) {
            $this->dispatch('toast', message: $quality['message'], type: 'error');
            return;
        }

        $target = $this->skill->targets()->findOrFail($this->evalDayId);
        
        $target->update([
            'evaluation_feedback' => $this->userFeedback,
            'status' => 'completed'
        ]);
        
        if ($task = $target->task) {
            $task->update(['is_completed' => true]);
        }

        $this->showEvalModal = false;
        
        if ($target->day_number === $this->skill->current_day) {
            $this->advanceDay();
        } else {
            $this->dispatch('toast', message: 'Refleksi harian berhasil disimpan!', type: 'success');
        }
    }

    public function completeDay($targetId)
    {
        $target = $this->skill->targets()->findOrFail($targetId);
        
        if ($target->day_number !== $this->skill->current_day) {
            $this->dispatch('toast', message: 'Kamu harus menyelesaikan hari sebelumnya terlebih dahulu!', type: 'error');
            return;
        }

        $target->update(['status' => 'completed']);
        
        if ($task = $target->task) {
            $task->update(['is_completed' => true]);
        }

        $this->advanceDay();
    }

    private function advanceDay()
    {
        if ($this->skill->current_day < $this->skill->target_days) {
            $this->skill->increment('current_day');
            auth()->user()->addPendingXp(20, 'skill', "Target Harian Skill: {$this->skill->title}", "Penyelesaian modul hari ke-" . $this->skill->current_day, $this->skill->id);
            
            $existingNext = $this->skill->targets()->where('day_number', $this->skill->current_day)->first();
            if (!$existingNext) {
                $aiService = app(\App\Services\AiSkillPlannerService::class);
                $fallbackTarget = $aiService->generateNextDayTarget($this->skill);

                $existingNext = $this->skill->targets()->create([
                    'day_number' => $this->skill->current_day,
                    'title' => $fallbackTarget['title'],
                    'description' => $fallbackTarget['description'],
                    'is_ai_generated' => $fallbackTarget['is_ai_generated'] ?? false,
                    'reference_url' => $fallbackTarget['reference_url'] ?? null,
                    'status' => 'pending'
                ]);
            }

            $defaultMode = ($this->skill->mode === 'systematic' || $this->skill->mode === 'sistematis') ? 'sistematis' : 'zen';
            if ($defaultMode === 'sistematis') {
                \App\Services\AiSkillPlannerService::createBulkTasksForTarget($this->skill, $existingNext, auth()->user());
            }

            $this->dispatch('toast', message: "Target selesai! +20 Pending XP. Target hari ke-" . $this->skill->current_day . " disiapkan!", type: 'success');
        } else {
            $this->skill->update(['status' => 'completed', 'current_day' => $this->skill->target_days]);
            auth()->user()->addPendingXp(100, 'skill', "Penguasaan Skill Penuh: {$this->skill->title}", "Menuntaskan seluruh target milestone skill", $this->skill->id);
            $this->dispatch('toast', message: 'Selamat! Skill berhasil dikuasai! +100 Pending XP', type: 'success');
        }
        
        $this->skill->load(['targets.subTasks', 'targets.task']);
        $this->skill->refresh();
        $this->dispatch('profile-updated');
    }
}; ?>

<div class="relative pb-24 max-w-4xl mx-auto px-4 sm:px-6">
    @php
        $userTz = config('app.timezone', 'UTC');
        $today = \Carbon\Carbon::now($userTz)->startOfDay();
        $baseDate = \Carbon\Carbon::parse($skill->start_date ?: $skill->created_at)->timezone($userTz)->startOfDay();
        $realtimeDay = (int) $baseDate->diffInDays($today) + 1;
    @endphp

    <!-- Header -->
    <div class="mb-6 sticky top-[60px] sm:top-[72px] bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-md z-20 py-3 border-b border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <a href="{{ route('skills.index') }}" wire:navigate class="p-1.5 rounded-xl text-gray-400 hover:text-atoma-blue dark:hover:text-atoma-cyan hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors shrink-0" title="Kembali ke Daftar Milestone">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div class="min-w-0">
                    <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-gray-900 dark:text-gray-100 tracking-tight truncate">{{ $skill->title }}</h2>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Milestone: {{ $skill->target_days }} Hari</span>
                        <span class="text-[10px] sm:text-xs px-2 py-0.5 rounded-full font-bold inline-flex items-center gap-1 {{ $skill->mode === 'zen' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' : 'bg-atoma-ice text-atoma-blue dark:bg-atoma-blue/20 dark:text-atoma-cyan border border-atoma-aqua/50' }}">
                            @if($skill->mode === 'zen')
                                <x-icon.sparkles class="w-3 h-3" /> Mode Zen
                            @else
                                <x-icon.target class="w-3 h-3" /> Mode Sistematis
                            @endif
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-2.5 shrink-0">
                @php
                    $progressPct = $skill->calculated_progress;
                    $tTasks = $skill->tasks->count();
                    $dTasks = $skill->tasks->where('is_completed', true)->count();
                    $totalSubs = $skill->total_sub_tasks_count;
                    $completedSubs = $skill->completed_sub_tasks_count;
                    $completedTargets = $skill->targets->where('status', 'completed')->count();
                    $isSkillCompleted = $skill->status === 'completed' || $progressPct >= 100;
                @endphp

                @if($isSkillCompleted)
                    <button type="button" wire:click="$set('showCertificateModal', true)" class="px-3 sm:px-3.5 py-2 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-600 hover:to-yellow-600 text-white rounded-xl font-bold text-xs shadow-md shadow-amber-500/25 transition-all flex items-center gap-1.5 shrink-0 cursor-pointer active:scale-95" title="{{ __('Buka Sertifikat Digital') }}">
                        <x-icon.trophy class="w-3.5 h-3.5 text-white" />
                        <span class="hidden sm:inline">{{ __('Sertifikat') }}</span>
                    </button>
                @endif

                <button type="button" wire:click="openCoachChat()" class="px-3 sm:px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 rounded-xl font-bold text-xs border border-indigo-200 dark:border-indigo-800 transition-all flex items-center gap-1.5 shrink-0 cursor-pointer" title="{{ __('Konsultasi / Curhat dengan AI Coach') }}">
                    <x-icon.bot class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                    <span class="hidden sm:inline">{{ __('Curhat Coach') }}</span>
                </button>

                <button type="button" wire:click="openExtendModal()" class="px-3 sm:px-3.5 py-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-700 dark:text-gray-300 rounded-xl font-bold text-xs border border-gray-200 dark:border-gray-700 transition-all flex items-center gap-1.5 shrink-0 cursor-pointer" title="{{ __('Perpanjang Durasi Milestone') }}">
                    <x-icon.calendar class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
                    <span class="hidden sm:inline">{{ __('Extend') }}</span>
                </button>

                @if($activeTab === 'tasks')
                    <button type="button" wire:click="openTaskModal()" class="px-3 sm:px-4 py-2 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-xl font-bold text-xs shadow-md shadow-atoma-blue/25 transition-all flex items-center shrink-0 cursor-pointer">
                        <span class="mr-1 text-sm font-black">+</span> <span class="hidden sm:inline">{{ __('Tambah') }}</span> {{ __('Tugas') }}
                    </button>
                @else
                    <button type="button" wire:click="openAddModal()" class="px-3 sm:px-4 py-2 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-xl font-bold text-xs shadow-md shadow-atoma-blue/25 transition-all flex items-center shrink-0 cursor-pointer">
                        <span class="mr-1 text-sm font-black">+</span> <span class="hidden sm:inline">{{ __('Tambah') }}</span> {{ __('Target') }}
                    </button>
                @endif

                <div class="text-right pl-2 border-l border-gray-200 dark:border-gray-700">
                    <div class="text-lg sm:text-2xl font-black text-atoma-blue dark:text-atoma-cyan leading-tight">{{ $progressPct }}%</div>
                    <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">
                        @if($tTasks > 0)
                            {{ $dTasks }}/{{ $tTasks }} {{ __('Tugas') }}
                        @elseif($totalSubs > 0)
                            {{ $completedSubs }}/{{ $totalSubs }} {{ __('Subtask') }}
                        @else
                            {{ $completedTargets }}/{{ $skill->target_days }} {{ __('Done') }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @if($skill->description)
            <p class="text-xs text-atoma-blue dark:text-atoma-cyan font-medium mt-1.5 ml-9 max-w-xl truncate">{{ $skill->description }}</p>
        @endif
    </div>

    @if($isSkillCompleted)
        <div class="mb-6 bg-gradient-to-r from-amber-500 via-orange-500 to-yellow-500 rounded-3xl p-6 text-center text-white shadow-xl relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/15 rounded-full blur-2xl pointer-events-none"></div>
            <h3 class="text-2xl sm:text-3xl font-black mb-1 flex items-center justify-center gap-2">
                <x-icon.trophy class="w-7 h-7 text-amber-200 inline" />
                <span>{{ __('Selamat! Skill Berhasil Dikuasai!') }}</span>
            </h3>
            <p class="text-xs sm:text-sm font-medium opacity-95 max-w-lg mx-auto leading-relaxed">
                {{ __('Kamu telah menyelesaikan seluruh target dalam modul :title. Klaim sertifikat penguasaan digital resmimu dan bagikan pencapaianmu!', ['title' => $skill->title]) }}
            </p>
            <div class="mt-4 flex items-center justify-center gap-3 flex-wrap">
                <button type="button" wire:click="$set('showCertificateModal', true)" class="px-6 py-3 bg-white text-amber-700 hover:bg-amber-50 font-black text-xs sm:text-sm rounded-2xl shadow-xl transition-all inline-flex items-center gap-2 cursor-pointer active:scale-95">
                    <x-icon.trophy class="w-4 h-4 text-amber-600" />
                    <span>{{ __('Lihat & Bagikan Sertifikat Penguasaan') }}</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Milestone Mode Tabs: Tasks List vs Roadmap & Daily Target -->
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white/60 dark:bg-gray-800/60 p-2 sm:p-2.5 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
        <div class="flex items-center gap-2">
            <button type="button" 
                    wire:click="$set('activeTab', 'tasks')" 
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-black transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'tasks' ? 'bg-atoma-blue text-white shadow-md shadow-atoma-blue/25' : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700/60 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                <span>📋</span>
                <span>{{ __('Daftar Tugas') }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $activeTab === 'tasks' ? 'bg-white/20 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200' }}">
                    {{ $skill->tasks->count() }}
                </span>
            </button>

            <button type="button" 
                    wire:click="$set('activeTab', 'roadmap')" 
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-black transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'roadmap' ? 'bg-atoma-blue text-white shadow-md shadow-atoma-blue/25' : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700/60 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                <span>🎯</span>
                <span>{{ __('Roadmap & Target Harian') }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $activeTab === 'roadmap' ? 'bg-white/20 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200' }}">
                    {{ $skill->targets->count() }}
                </span>
            </button>
        </div>

        @if($activeTab === 'tasks')
            <button type="button" wire:click="openTaskModal()" class="px-3.5 py-2 bg-gradient-to-r from-atoma-blue to-[#284fd6] text-white rounded-xl font-bold text-xs shadow-md shadow-atoma-blue/25 hover:opacity-95 transition-all flex items-center gap-1.5 cursor-pointer self-start sm:self-auto">
                <span class="text-sm font-black">+</span>
                <span>{{ __('Tambah Tugas') }}</span>
            </button>
        @endif
    </div>

    @if($activeTab === 'tasks')
        <div class="space-y-4">
            <!-- Filter Bar & Summary -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-gray-800/90 rounded-2xl p-3 sm:p-4 border border-gray-100 dark:border-gray-800 shadow-xs">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-gray-800 dark:text-gray-200 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🎯</span>
                        <span>{{ __('Tugas dalam Milestone Ini') }}</span>
                    </span>
                    <span class="text-xs text-gray-400 font-bold">
                        ({{ $this->milestoneTasks->where('is_completed', true)->count() }}/{{ $this->milestoneTasks->count() }} {{ __('selesai') }})
                    </span>
                </div>

                <div class="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-700/60 rounded-xl border border-gray-200/60 dark:border-gray-700 self-start sm:self-auto">
                    <button type="button" 
                            wire:click="$set('taskFilter', 'all')" 
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $taskFilter === 'all' ? 'bg-white dark:bg-gray-800 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                        {{ __('Semua') }} ({{ $skill->tasks->count() }})
                    </button>
                    <button type="button" 
                            wire:click="$set('taskFilter', 'pending')" 
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $taskFilter === 'pending' ? 'bg-white dark:bg-gray-800 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                        {{ __('Belum') }} ({{ $skill->tasks->where('is_completed', false)->count() }})
                    </button>
                    <button type="button" 
                            wire:click="$set('taskFilter', 'completed')" 
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $taskFilter === 'completed' ? 'bg-white dark:bg-gray-800 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                        {{ __('Selesai') }} ({{ $skill->tasks->where('is_completed', true)->count() }})
                    </button>
                </div>
            </div>

            <!-- Task Items List -->
            @php $filteredTasks = $this->milestoneTasks; @endphp
            @if($filteredTasks->isEmpty())
                <div class="bg-white dark:bg-gray-800/90 rounded-3xl p-8 sm:p-12 text-center border border-gray-100 dark:border-gray-800 shadow-sm">
                    <div class="w-16 h-16 mx-auto mb-4 bg-atoma-ice dark:bg-atoma-blue/20 rounded-2xl flex items-center justify-center text-3xl">
                        🎯
                    </div>
                    <h4 class="text-base sm:text-lg font-bold text-gray-800 dark:text-gray-200 mb-1">
                        {{ $taskFilter !== 'all' ? __('Tidak ada tugas dengan filter ini') : __('Belum Ada Tugas di Milestone Ini') }}
                    </h4>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                        {{ __('Kamu bisa menambahkan banyak tugas sekaligus dan menentukan hari pengerjaannya secara dinamis agar rencana milestone ini tercapai dengan rapi.') }}
                    </p>
                    <button type="button" wire:click="openTaskModal()" class="px-5 py-2.5 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-xl font-bold text-xs shadow-md shadow-atoma-blue/25 transition-all inline-flex items-center gap-2 cursor-pointer">
                        <span class="text-sm font-black">+</span>
                        <span>{{ __('Tambah Tugas Sekarang') }}</span>
                    </button>
                </div>
            @else
                <div class="space-y-2.5">
                    @foreach($filteredTasks as $t)
                        @php
                            $tDate = $t->date ? \Carbon\Carbon::parse($t->date)->timezone($userTz)->startOfDay() : null;
                            $isToday = $tDate && $tDate->isToday();
                            $isTomorrow = $tDate && $tDate->isTomorrow();
                            $isPast = $tDate && $tDate->isPast() && !$isToday;
                        @endphp
                        <div wire:key="milestone-task-{{ $t->id }}" class="group bg-white dark:bg-gray-800/90 rounded-2xl p-3.5 sm:p-4 border transition-all duration-200 hover:shadow-md flex items-start gap-3.5 {{ $t->is_completed ? 'border-emerald-200/70 dark:border-emerald-900/40 bg-emerald-50/20 dark:bg-emerald-950/10' : 'border-gray-100 dark:border-gray-800 hover:border-atoma-aqua/40' }}">
                            <!-- Checkbox -->
                            <button type="button" 
                                    wire:click="toggleMilestoneTask({{ $t->id }})" 
                                    class="mt-0.5 w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer shrink-0 {{ $t->is_completed ? 'bg-emerald-500 border-emerald-500 text-white shadow-xs' : 'border-gray-300 dark:border-gray-600 hover:border-atoma-blue' }}"
                                    title="{{ $t->is_completed ? __('Tandai belum selesai') : __('Tandai selesai') }}">
                                @if($t->is_completed)
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                @endif
                            </button>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-snug {{ $t->is_completed ? 'line-through text-gray-400 dark:text-gray-500' : '' }}">
                                        {{ $t->title }}
                                    </h4>
                                    
                                    <!-- Action Buttons -->
                                    <div class="flex items-center gap-1 shrink-0 opacity-80 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button type="button" wire:click="openTaskModal({{ $t->id }})" class="p-1.5 rounded-lg text-gray-400 hover:text-atoma-blue dark:hover:text-atoma-cyan hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors cursor-pointer" title="{{ __('Edit Tugas') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button type="button" wire:click="deleteMilestoneTask({{ $t->id }})" wire:confirm="{{ __('Yakin ingin menghapus tugas ini dari milestone?') }}" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors cursor-pointer" title="{{ __('Hapus Tugas') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>

                                @if($t->description)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2 {{ $t->is_completed ? 'line-through opacity-70' : '' }}">
                                        {{ $t->description }}
                                    </p>
                                @endif

                                <!-- Meta Badges Row -->
                                <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
                                    <!-- Date Badge -->
                                    @if($tDate)
                                        <span class="inline-flex items-center gap-1 font-semibold px-2 py-0.5 rounded-md text-[11px] {{ $isToday ? 'bg-atoma-ice text-atoma-blue dark:bg-atoma-blue/20 dark:text-atoma-cyan border border-atoma-aqua/40' : ($isTomorrow ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300' : ($isPast && !$t->is_completed ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300')) }}">
                                            <span>📅</span>
                                            <span>
                                                @if($isToday)
                                                    {{ __('Hari Ini') }}
                                                @elseif($isTomorrow)
                                                    {{ __('Besok') }}
                                                @else
                                                    {{ $tDate->translatedFormat('D, d M Y') }}
                                                @endif
                                            </span>
                                        </span>
                                    @endif

                                    <!-- Time / Duration -->
                                    @if($t->start_time || $t->deadline || $t->estimated_minutes)
                                        <span class="inline-flex items-center gap-1 font-mono text-[11px] text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 px-2 py-0.5 rounded-md border border-gray-200/50 dark:border-gray-700/50">
                                            <span>⏱️</span>
                                            @if($t->start_time && $t->deadline)
                                                <span>{{ substr($t->start_time, 0, 5) }} - {{ substr($t->deadline, 0, 5) }}</span>
                                            @elseif($t->deadline)
                                                <span>Deadline {{ substr($t->deadline, 0, 5) }}</span>
                                            @elseif($t->start_time)
                                                <span>Mulai {{ substr($t->start_time, 0, 5) }}</span>
                                            @endif
                                            @if($t->estimated_minutes)
                                                <span>({{ $t->estimated_minutes }}m)</span>
                                            @endif
                                        </span>
                                    @endif

                                    <!-- Priority -->
                                    @if($t->priority === 'high')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 px-2 py-0.5 rounded-md border border-red-200 dark:border-red-900/40">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                            {{ __('Tinggi') }}
                                        </span>
                                    @elseif($t->priority === 'medium')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-md border border-amber-200 dark:border-amber-900/40">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            {{ __('Sedang') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Quick Add Row -->
                <button type="button" wire:click="openTaskModal()" class="w-full py-3.5 border-2 border-dashed border-gray-200 dark:border-gray-700 hover:border-atoma-blue dark:hover:border-atoma-cyan rounded-2xl text-xs sm:text-sm font-bold text-gray-500 hover:text-atoma-blue dark:text-gray-400 dark:hover:text-atoma-cyan transition-all flex items-center justify-center gap-2 cursor-pointer mt-4">
                    <span class="text-base font-black">+</span>
                    <span>{{ __('Tambah Tugas Baru ke Milestone Ini') }}</span>
                </button>
            @endif
        </div>
    @else
    <!-- Milestone Velocity, Momentum Streak & View Mode Control (Mobile Design First) -->
    @php
        $velocity = $this->milestoneVelocity;
        $streakData = $this->milestoneStreak;
    @endphp
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white/80 dark:bg-gray-800/80 backdrop-blur-xs p-3 sm:p-3.5 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-xs">
        <!-- Velocity & Momentum Badges -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Velocity Badge -->
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-black border {{ $velocity['badge'] }}" title="{{ $velocity['description'] }}">
                <x-icon.zap class="w-3.5 h-3.5 shrink-0" />
                <span>{{ $velocity['label'] }}</span>
            </div>

            <!-- Streak & Momentum Bonus -->
            @if($streakData['streak'] > 0)
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-black bg-orange-50 dark:bg-orange-950/60 text-orange-600 dark:text-orange-300 border border-orange-200 dark:border-orange-800/60" title="{{ __('Streak konsistensi target harian berturut-turut') }}">
                    <x-icon.flame class="w-3.5 h-3.5 text-orange-500 shrink-0" />
                    <span>{{ __(':count Hari Beruntun', ['count' => $streakData['streak']]) }}</span>
                    @if($streakData['bonus_pct'] > 0)
                        <span class="text-[10px] px-1.5 py-0.5 rounded-md bg-orange-500 text-white font-extrabold">+{{ $streakData['bonus_pct'] }}% XP</span>
                    @endif
                </div>
            @endif
        </div>

        <!-- View Mode Switcher (Timeline vs Cards) -->
        <div class="flex items-center p-1 bg-gray-100 dark:bg-gray-700/60 rounded-xl border border-gray-200/60 dark:border-gray-700 shrink-0 self-start sm:self-auto">
            <button type="button" 
                    wire:click="setViewMode('timeline')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer {{ $viewMode === 'timeline' ? 'bg-white dark:bg-gray-800 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                <x-icon.activity class="w-3.5 h-3.5" />
                <span>{{ __('Timeline') }}</span>
            </button>
            <button type="button" 
                    wire:click="setViewMode('cards')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer {{ $viewMode === 'cards' ? 'bg-white dark:bg-gray-800 text-atoma-blue dark:text-atoma-cyan shadow-xs' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                <x-icon.cards class="w-3.5 h-3.5" />
                <span>{{ __('Kartu Mingguan') }}</span>
            </button>
        </div>
    </div>

    @php
        $ctxSummary = $skill->interview_context['summary'] ?? null;
        $hasTelemetry = !empty($ctxSummary) && (!empty($ctxSummary['phases']) || !empty($ctxSummary['weekly_routine']));
    @endphp

    @if($hasTelemetry)
        <div x-data="{ expandedTelemetry: false }" class="mb-5 bg-gradient-to-br from-indigo-50/80 via-white to-sky-50/60 dark:from-gray-850 dark:via-gray-800 dark:to-indigo-950/40 rounded-3xl p-4 sm:p-5 border border-indigo-100 dark:border-indigo-900/50 shadow-xs">
            <div class="flex items-center justify-between cursor-pointer select-none" @click="expandedTelemetry = !expandedTelemetry">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black text-xs shadow-xs">
                        <x-icon.sparkles class="w-4 h-4 text-white" />
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <span>{{ __('Kurikulum & Telemetri Rencana AI') }}</span>
                            @if(!empty($ctxSummary['phases']))
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                    {{ count($ctxSummary['phases']) }} {{ __('Fase') }}
                                </span>
                            @endif
                            @if(!empty($ctxSummary['daily_commitment_minutes']))
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-300 hidden xs:inline">
                                    ⏱️ {{ $ctxSummary['daily_commitment_minutes'] }} {{ __('menit/hari') }}
                                </span>
                            @endif
                        </h4>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            {{ __('Struktur tahapan belajar & komitmen harian yang disusun AI Coach') }}
                        </p>
                    </div>
                </div>
                <button type="button" class="p-1 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer">
                    <svg class="w-5 h-5 transition-transform duration-200" :class="expandedTelemetry ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
            </div>

            <div x-show="expandedTelemetry" x-collapse class="mt-4 pt-4 border-t border-indigo-100/80 dark:border-gray-700/60 space-y-4">
                @if(!empty($ctxSummary['phases']))
                    <div>
                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-2 flex items-center gap-1.5">
                            <x-icon.target class="w-3.5 h-3.5" />
                            <span>{{ __('Tahapan Pembelajaran (Phases)') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                            @foreach($ctxSummary['phases'] as $idx => $ph)
                                <div class="p-3 bg-white/90 dark:bg-gray-800/90 rounded-2xl border border-indigo-50 dark:border-gray-700/60 shadow-2xs">
                                    <div class="text-[10px] font-black text-indigo-600 dark:text-indigo-300 uppercase tracking-wider">
                                        {{ __('Fase :n', ['n' => $ph['phase_number'] ?? ($idx + 1)]) }}
                                    </div>
                                    <div class="font-bold text-xs text-gray-800 dark:text-gray-200 mt-0.5">
                                        {{ $ph['name'] ?? ($ph['title'] ?? '') }}
                                    </div>
                                    @if(!empty($ph['description']))
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 line-clamp-2 leading-relaxed">
                                            {{ $ph['description'] }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($ctxSummary['weekly_routine']))
                    <div>
                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-sky-600 dark:text-sky-400 mb-2 flex items-center gap-1.5">
                            <x-icon.calendar class="w-3.5 h-3.5" />
                            <span>{{ __('Rutinitas Mingguan Direkomendasikan') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($ctxSummary['weekly_routine'] as $wr)
                                <div class="p-2.5 bg-white/90 dark:bg-gray-800/90 rounded-xl border border-sky-50 dark:border-gray-700/60 text-xs flex items-center justify-between">
                                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ $wr['days'] ?? '' }}</span>
                                    <span class="text-sky-600 dark:text-sky-400 font-medium">{{ $wr['focus'] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- 30-Day Dot Matrix / Progress Grid (Heatmap) -->
    <div class="mb-6 bg-white dark:bg-gray-800/90 rounded-3xl p-4 sm:p-5 shadow-sm border border-gray-100 dark:border-gray-800">
        <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
            <div class="flex items-center gap-2">
                <span class="text-xs font-black text-gray-800 dark:text-gray-200 uppercase tracking-wider flex items-center gap-1.5">
                    <x-icon.target class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan" />
                    <span>{{ __('Roadmap Matrix') }}</span>
                </span>
                <span class="text-[11px] font-bold text-gray-400 dark:text-gray-500">
                    ({{ $completedTargets }}/{{ $skill->target_days }} {{ __('Hari') }})
                </span>
            </div>
            
            <!-- Legend (Mobile-First Wrap) -->
            <div class="flex items-center gap-2.5 text-[10px] font-bold text-gray-500 dark:text-gray-400 flex-wrap">
                <span class="inline-flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> {{ __('Selesai') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-atoma-blue animate-pulse"></span> {{ __('Aktif') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span> {{ __('Rest') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> {{ __('Skip') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-gray-200 dark:bg-gray-700"></span> {{ __('Belum') }}
                </span>
            </div>
        </div>

        <!-- Matrix Cells Grid -->
        <div class="grid grid-cols-7 sm:grid-cols-10 md:grid-cols-15 gap-1.5 sm:gap-2">
            @for($d = 1; $d <= $skill->target_days; $d++)
                @php
                    $t = $skill->targets->firstWhere('day_number', $d);
                    $tDate = (clone $baseDate)->addDays($d - 1);
                    $isTCompleted = $t && $t->status === 'completed';
                    $isTSkipped = $t && $t->status === 'skipped';
                    $isTRest = $t && str_contains($t->title, 'Rest & Recovery');
                    $isTCurrent = ($skill->current_day === $d || $tDate->isToday()) && $skill->status !== 'completed';
                    
                    if ($isTCompleted) {
                        $cellClass = 'bg-emerald-500 hover:bg-emerald-600 text-white shadow-xs shadow-emerald-500/20';
                        $dotStatus = __('Selesai');
                    } elseif ($isTCurrent) {
                        $cellClass = 'bg-atoma-blue hover:bg-[#3455dc] text-white ring-2 ring-atoma-blue ring-offset-2 dark:ring-offset-gray-800 animate-pulse font-black shadow-md shadow-atoma-blue/30';
                        $dotStatus = __('Hari Ini (Aktif)');
                    } elseif ($isTRest) {
                        $cellClass = 'bg-sky-500 hover:bg-sky-600 text-white shadow-xs shadow-sky-500/20';
                        $dotStatus = __('Rest Day');
                    } elseif ($isTSkipped) {
                        $cellClass = 'bg-rose-500 hover:bg-rose-600 text-white shadow-xs shadow-rose-500/20';
                        $dotStatus = __('Terlewati');
                    } else {
                        $cellClass = 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-750 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 border border-gray-200/60 dark:border-gray-700/60';
                        $dotStatus = __('Belum Dibuka');
                    }
                @endphp
                <button type="button" 
                        @click="
                            const targetEl = document.getElementById('target-day-{{ $d }}') || document.getElementById('target-card-{{ $d }}');
                            if (targetEl) {
                                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                targetEl.classList.add('ring-2', 'ring-atoma-blue', 'transition-all');
                                setTimeout(() => targetEl.classList.remove('ring-2', 'ring-atoma-blue'), 2000);
                            }
                        "
                        class="aspect-square rounded-xl {{ $cellClass }} text-[11px] sm:text-xs font-black flex items-center justify-center transition-all cursor-pointer transform active:scale-90 hover:scale-105"
                        title="{{ __('Hari Ke-:d', ['d' => $d]) }}: {{ $t ? $t->title : __('Locked') }} ({{ $dotStatus }})">
                    {{ $d }}
                </button>
            @endfor
        </div>
    </div>

    <!-- Dynamic Weekly & Monthly AI Evaluation Banner with Guaranteed Contrast -->
    @php
        $wStatus = $this->selectedWeekStatus;
    @endphp

    @if($wStatus)
        <div x-data="{ openWeekDropdown: false }"
             :class="openWeekDropdown ? 'relative z-40' : 'relative z-10'"
             class="mb-8 rounded-3xl p-5 sm:p-7 shadow-xl shadow-atoma-blue/20 dark:shadow-none bg-gradient-to-r from-[#1b3cb8] via-atoma-blue to-[#284fd6] dark:from-gray-850 dark:via-indigo-950/60 dark:to-gray-900 border border-white/20 dark:border-indigo-500/30 text-white dark:text-gray-100 transition-all">
            <!-- Background Glow Isolated Container (Prevents clipping the dropdown menu) -->
            <div class="absolute inset-0 rounded-3xl overflow-hidden pointer-events-none">
                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/10 dark:bg-indigo-500/10 rounded-full blur-3xl"></div>
            </div>
            
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 text-[10px] sm:text-xs font-black px-3.5 py-1 rounded-full bg-white text-[#3E64FF] dark:bg-indigo-900/60 dark:text-indigo-300 dark:border dark:border-indigo-500/40 shadow-sm">
                            <x-icon.bot class="w-3.5 h-3.5" /> {{ __('Checkpoint Evaluasi AI') }}
                        </span>

                        <!-- Dropdown Select Week -->
                        <div class="relative inline-block" 
                             @click.outside="openWeekDropdown = false" 
                             @touchstart.outside="openWeekDropdown = false"
                             @keydown.escape.window="openWeekDropdown = false">
                            <button type="button" 
                                    @click="openWeekDropdown = !openWeekDropdown" 
                                    class="font-extrabold text-[11px] sm:text-xs px-3.5 py-1.5 rounded-full bg-white/20 hover:bg-white/30 active:scale-95 text-white border border-white/30 shadow-sm flex items-center gap-2 cursor-pointer backdrop-blur-md transition-all">
                                <span>{{ $this->weekCycles[$selectedWeek]['label'] ?? __('Pilih Minggu') }}</span>
                                <svg class="w-3.5 h-3.5 text-white/90 transition-transform duration-200" :class="openWeekDropdown ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </button>

                            <!-- MOBILE BOTTOM SHEET BACKDROP (Mobile only) -->
                            <div x-show="openWeekDropdown" 
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 @click="openWeekDropdown = false"
                                 class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 sm:hidden"
                                 style="display: none;"></div>

                            <!-- MOBILE BOTTOM SHEET (Mobile only: < sm) -->
                            <div x-show="openWeekDropdown" 
                                 x-transition:enter="transition ease-out duration-300 transform"
                                 x-transition:enter-start="translate-y-full opacity-0"
                                 x-transition:enter-end="translate-y-0 opacity-100"
                                 x-transition:leave="transition ease-in duration-200 transform"
                                 x-transition:leave-start="translate-y-0 opacity-100"
                                 x-transition:leave-end="translate-y-full opacity-0"
                                 style="display: none;" 
                                 class="fixed inset-x-0 bottom-0 z-50 bg-white dark:bg-gray-850 rounded-t-3xl p-5 pb-8 shadow-2xl border-t border-gray-100 dark:border-gray-750 max-h-[85vh] overflow-y-auto sm:hidden text-gray-800 dark:text-white">
                                
                                <!-- Drag pill handle -->
                                <div class="w-12 h-1.5 bg-gray-300 dark:bg-gray-600 rounded-full mx-auto mb-4"></div>

                                <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100 dark:border-gray-750">
                                    <div>
                                        <h4 class="text-sm font-black text-gray-900 dark:text-white">{{ __('Pilih Checkpoint Minggu') }}</h4>
                                        <p class="text-[11px] font-bold text-gray-400 dark:text-gray-500 mt-0.5">{{ __('Roadmap :days Hari Target', ['days' => $skill->target_days]) }}</p>
                                    </div>
                                    <button type="button" 
                                            @click="openWeekDropdown = false" 
                                            class="p-1.5 rounded-full bg-gray-100 dark:bg-gray-750 text-gray-400 hover:text-gray-700 dark:hover:text-white">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>

                                <div class="space-y-2">
                                    @foreach($this->weekCycles as $wNum => $wInfo)
                                        @php
                                            $tEnd = $skill->targets->firstWhere('day_number', $wInfo['end_day']);
                                            $itemHasResult = $tEnd && !empty($tEnd->evaluation_ai_suggestion);
                                            $itemIsLocked = $realtimeDay < $wInfo['end_day'] && !$itemHasResult;
                                            $isSelected = (int)$selectedWeek === (int)$wNum;
                                        @endphp
                                        <button type="button" 
                                                @click="$wire.set('selectedWeek', {{ $wNum }}); openWeekDropdown = false;" 
                                                class="w-full text-left p-3.5 rounded-2xl text-xs font-bold flex items-center justify-between gap-3 transition-all cursor-pointer active:scale-98 {{ $isSelected ? 'bg-atoma-blue text-white shadow-md shadow-atoma-blue/20' : 'bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-750' }}">
                                            
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 {{ $isSelected ? 'bg-white/20 text-white' : ($itemHasResult ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400' : ($itemIsLocked ? 'bg-gray-200 dark:bg-gray-700 text-gray-400' : 'bg-blue-100 dark:bg-blue-950 text-atoma-blue dark:text-atoma-cyan')) }}">
                                                    @if($itemHasResult)
                                                        <x-icon.check class="w-4 h-4" />
                                                    @elseif($itemIsLocked)
                                                        <x-icon.lock class="w-4 h-4" />
                                                    @else
                                                        <x-icon.bot class="w-4 h-4" />
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-extrabold text-sm truncate">{{ $wInfo['label'] }}</div>
                                                    <div class="text-xs {{ $isSelected ? 'text-white/80' : 'text-gray-400 dark:text-gray-500' }}">
                                                        {{ __('Hari :start - :end', ['start' => $wInfo['start_day'], 'end' => $wInfo['end_day']]) }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="shrink-0 flex items-center gap-2">
                                                @if($itemHasResult)
                                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-extrabold {{ $isSelected ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">{{ __('Selesai') }}</span>
                                                @elseif($itemIsLocked)
                                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-extrabold {{ $isSelected ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-750 dark:text-gray-400' }}">{{ __('Terkunci') }}</span>
                                                @else
                                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-extrabold {{ $isSelected ? 'bg-white/20 text-white' : 'bg-blue-100 text-atoma-blue dark:bg-blue-950 dark:text-blue-300' }}">{{ __('Siap') }}</span>
                                                @endif

                                                @if($isSelected)
                                                    <x-icon.check class="w-4 h-4 text-white shrink-0 ml-1" />
                                                @endif
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- DESKTOP DROPDOWN (Desktop only: >= sm) -->
                            <div x-show="openWeekDropdown" 
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                                 style="display: none;" 
                                 class="hidden sm:block absolute left-0 top-full mt-2.5 z-50 w-80 rounded-2xl shadow-2xl shadow-indigo-950/40 border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-850 p-2 space-y-1 max-h-72 overflow-y-auto hide-scrollbar text-gray-800 dark:text-white ring-1 ring-black/5">
                                
                                <div class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-750 mb-1 flex items-center justify-between">
                                    <span>{{ __('Pilih Checkpoint Minggu') }}</span>
                                    <span>{{ __('Hari :days Target', ['days' => $skill->target_days]) }}</span>
                                </div>

                                @foreach($this->weekCycles as $wNum => $wInfo)
                                    @php
                                        $tEnd = $skill->targets->firstWhere('day_number', $wInfo['end_day']);
                                        $itemHasResult = $tEnd && !empty($tEnd->evaluation_ai_suggestion);
                                        $itemIsLocked = $realtimeDay < $wInfo['end_day'] && !$itemHasResult;
                                        $isSelected = (int)$selectedWeek === (int)$wNum;
                                    @endphp
                                    <button type="button" 
                                            @click="$wire.set('selectedWeek', {{ $wNum }}); openWeekDropdown = false;" 
                                            class="w-full text-left px-3 py-2.5 rounded-xl text-xs font-bold flex items-center justify-between gap-2 transition-all cursor-pointer {{ $isSelected ? 'bg-atoma-blue text-white shadow-sm' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750' }}">
                                        
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0 {{ $isSelected ? 'bg-white/20 text-white' : ($itemHasResult ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400' : ($itemIsLocked ? 'bg-gray-100 dark:bg-gray-800 text-gray-400' : 'bg-blue-100 dark:bg-blue-950 text-atoma-blue dark:text-atoma-cyan')) }}">
                                                @if($itemHasResult)
                                                    <x-icon.check class="w-3.5 h-3.5" />
                                                @elseif($itemIsLocked)
                                                    <x-icon.lock class="w-3.5 h-3.5" />
                                                @else
                                                    <x-icon.bot class="w-3.5 h-3.5" />
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-extrabold truncate">{{ $wInfo['label'] }}</div>
                                                <div class="text-[10px] {{ $isSelected ? 'text-white/80' : 'text-gray-400 dark:text-gray-500' }}">
                                                    {{ __('Hari :start - :end', ['start' => $wInfo['start_day'], 'end' => $wInfo['end_day']]) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="shrink-0 flex items-center gap-1.5">
                                            @if($itemHasResult)
                                                <span class="text-[9px] px-1.5 py-0.5 rounded-md font-extrabold {{ $isSelected ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">{{ __('Selesai') }}</span>
                                            @elseif($itemIsLocked)
                                                <span class="text-[9px] px-1.5 py-0.5 rounded-md font-extrabold {{ $isSelected ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">{{ __('Terkunci') }}</span>
                                            @else
                                                <span class="text-[9px] px-1.5 py-0.5 rounded-md font-extrabold {{ $isSelected ? 'bg-white/20 text-white' : 'bg-blue-100 text-atoma-blue dark:bg-blue-950 dark:text-blue-300' }}">{{ __('Siap') }}</span>
                                            @endif

                                            @if($isSelected)
                                                <x-icon.check class="w-4 h-4 text-white shrink-0 ml-1" />
                                            @endif
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <h3 class="text-lg sm:text-2xl font-black tracking-tight leading-snug text-white dark:text-gray-100">
                        @if($wStatus['has_result'])
                            <span class="inline-flex items-center gap-2">
                                <x-icon.check-circle class="w-5 h-5 text-emerald-300 inline shrink-0" />
                                <span>{{ __('Evaluasi AI Week #:w Tersimpan!', ['w' => $wStatus['week_number']]) }}</span>
                            </span>
                        @elseif($wStatus['is_locked'])
                            <span class="inline-flex items-center gap-2">
                                <x-icon.lock class="w-5 h-5 text-white/70 inline shrink-0" />
                                <span>{{ __('Evaluasi Week #:w Terkunci', ['w' => $wStatus['week_number']]) }}</span>
                            </span>
                        @else
                            {{ __('Evaluasi AI Week #:w (Hari :start - :end) Siap Dijalankan!', ['w' => $wStatus['week_number'], 'start' => $wStatus['start_day'], 'end' => $wStatus['end_day']]) }}
                        @endif
                    </h3>
                    <p class="text-xs sm:text-sm font-medium max-w-xl mt-1 leading-relaxed text-white/90 dark:text-gray-300">
                        @if($wStatus['has_result'])
                            {{ __('Laporan analisis AI Coach untuk Hari :start - :end sudah selesai dan tersimpan sebagai pedoman bulanan.', ['start' => $wStatus['start_day'], 'end' => $wStatus['end_day']]) }}
                        @elseif($wStatus['is_locked'])
                            {{ __('Checkpoint minggu ini baru tersedia setelah Anda mencapai Hari Ke-:day (Selesaikan latihan harian terlebih dahulu!).', ['day' => $wStatus['end_day']]) }}
                        @else
                            {{ __('Dapatkan analisa mendalam dari AI Coach berdasarkan seluruh refleksi harianmu (Hari :start - :end). Hasil akan disimpan sebagai pedoman evaluasi bulanan!', ['start' => $wStatus['start_day'], 'end' => $wStatus['end_day']]) }}
                        @endif
                    </p>
                </div>

                <!-- Action Button -->
                <div class="shrink-0 w-full md:w-auto">
                    @if($wStatus['has_result'])
                        <button type="button" wire:click="viewWeeklyEvalForWeek({{ $wStatus['week_number'] }})" class="w-full md:w-auto px-6 py-3 bg-white text-[#3E64FF] hover:bg-atoma-ice dark:bg-indigo-600 dark:hover:bg-indigo-500 dark:text-white font-black text-xs sm:text-sm rounded-2xl shadow-xl transition-all cursor-pointer flex items-center justify-center gap-2 border border-white/30 dark:border-indigo-400/30 transform active:scale-95">
                            <x-icon.file-text class="w-4 h-4" />
                            <span>{{ __('Lihat Hasil Evaluasi Week #:w', ['w' => $wStatus['week_number']]) }}</span>
                        </button>
                    @elseif($wStatus['is_locked'])
                        <button type="button" disabled class="w-full md:w-auto px-5 py-3 bg-white/10 text-white/60 dark:bg-gray-800/60 dark:text-gray-400 border border-white/20 dark:border-gray-700 font-bold text-xs rounded-2xl shadow-none cursor-not-allowed flex items-center justify-center gap-2">
                            <x-icon.lock class="w-4 h-4 text-white/50" />
                            <span>{{ __('Terkunci (Tersedia Hari Ke-:day)', ['day' => $wStatus['end_day']]) }}</span>
                        </button>
                    @else
                        <button type="button" wire:click="runWeeklyEvaluation({{ $wStatus['end_day'] }})" class="w-full md:w-auto px-6 py-3 bg-white text-[#3E64FF] hover:bg-atoma-ice dark:bg-indigo-600 dark:hover:bg-indigo-500 dark:text-white font-black text-xs sm:text-sm rounded-2xl shadow-xl hover:shadow-2xl transition-all cursor-pointer flex items-center justify-center gap-2 border border-white/30 dark:border-indigo-400/30 transform active:scale-95">
                            <span wire:loading.remove wire:target="runWeeklyEvaluation({{ $wStatus['end_day'] }})" class="inline-flex items-center gap-1.5">
                                <x-icon.bot class="w-4 h-4" />
                                <span>{{ __('Mulai Evaluasi AI Week #:w', ['w' => $wStatus['week_number']]) }}</span>
                            </span>
                            <span wire:loading wire:target="runWeeklyEvaluation({{ $wStatus['end_day'] }})" class="flex items-center justify-center text-[#3E64FF] dark:text-white">
                                <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                {{ __('Menganalisis...') }}
                            </span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @php
        $targetWithSuggestion = $skill->targets->first(fn($t) => !empty($t->evaluation_ai_suggestion) && empty($t->evaluation_feedback));
    @endphp

    @if($targetWithSuggestion)
        @php $sug = $targetWithSuggestion->evaluation_ai_suggestion; @endphp
        <div class="mb-8 rounded-3xl p-5 sm:p-7 shadow-xl shadow-atoma-blue/20 dark:shadow-none bg-gradient-to-r from-[#1b3cb8] via-atoma-blue to-[#284fd6] dark:from-gray-850 dark:via-indigo-950/60 dark:to-gray-900 border border-white/20 dark:border-indigo-500/30 relative overflow-hidden text-white dark:text-gray-100">
            <div class="flex items-start justify-between relative z-10">
                <div>
                    <div class="inline-flex items-center gap-1.5 text-[10px] sm:text-xs font-black px-3.5 py-1 rounded-full mb-3 bg-white text-[#3E64FF] dark:bg-indigo-900/60 dark:text-indigo-300 dark:border dark:border-indigo-500/40 shadow-sm tracking-wide uppercase">
                        <x-icon.bot class="w-3.5 h-3.5" /> <span>{{ __('HASIL EVALUASI AI SUDAH SIAP!') }}</span>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black mb-1 text-white dark:text-gray-100">{{ __('Rekomendasi Adaptif Hari Ke-:day', ['day' => $targetWithSuggestion->day_number + 1]) }}</h3>
                    <p class="text-xs sm:text-sm font-medium mb-3 text-white/90 dark:text-gray-300">{{ __('AI Coach telah mengevaluasi progres mingguanmu dan menyiapkan rekomendasi target:') }}</p>
                    
                    <div class="rounded-2xl p-4 bg-black/20 dark:bg-black/40 border border-white/15 dark:border-white/10 mb-4 text-xs sm:text-sm space-y-1.5 shadow-sm">
                        <p class="text-white dark:text-gray-100 font-bold"><span class="text-atoma-ice dark:text-indigo-300 underline font-extrabold">{{ __('Target AI Baru:') }}</span> {{ !empty($sug['recommendations']) ? \Illuminate\Support\Str::limit($sug['recommendations'], 60) : ($sug['title'] ?? 'Target Adaptif AI Coach') }}</p>
                        <p class="leading-relaxed font-medium text-white/90 dark:text-gray-300">{{ $sug['summary'] ?? ($sug['recommendations'] ?? '') }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2.5">
                        <button type="button" wire:click="resolveAiEvaluationChoice({{ $targetWithSuggestion->id }}, 'use_ai')" class="px-5 py-2.5 bg-white text-[#3E64FF] hover:bg-atoma-ice dark:bg-indigo-600 dark:hover:bg-indigo-500 dark:text-white font-black text-xs sm:text-sm rounded-xl shadow-md transition-all flex items-center gap-2 cursor-pointer active:scale-95">
                            <x-icon.bot class="w-4 h-4" />
                            <span>{{ __('Gunakan Hasil Evaluasi AI') }}</span>
                        </button>
                        <button type="button" wire:click="resolveAiEvaluationChoice({{ $targetWithSuggestion->id }}, 'keep_template')" class="px-5 py-2.5 bg-white/15 hover:bg-white/25 dark:bg-white/10 dark:hover:bg-white/15 text-white dark:text-gray-200 border border-white/30 dark:border-white/20 font-bold text-xs sm:text-sm rounded-xl transition-all cursor-pointer flex items-center gap-2">
                            <x-icon.file-text class="w-4 h-4" />
                            <span>{{ __('Tetap Template Awal') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($viewMode === 'timeline')
    <!-- Roadmap / Pathway UI (Timeline View) -->
    <div class="relative">
        <!-- Connecting Spine Line -->
        <div class="absolute top-4 bottom-4 left-4 md:left-1/2 w-1 bg-atoma-aqua/40 dark:bg-gray-700/60 rounded-full transform md:-translate-x-1/2"></div>
        
        <div class="space-y-6 md:space-y-10 relative z-10">
            @for($i = 1; $i <= $skill->target_days; $i++)
                @php
                    $target = $skill->targets->where('day_number', $i)->first();
                    $index = $i - 1;
                    $isLeft = $index % 2 === 0;
                    
                    $targetDate = (clone $baseDate)->addDays($i - 1);
                    $formattedDate = $targetDate->translatedFormat('D, d M Y');
                    $isPastDate = $targetDate->lt($today);
                    $isToday = $targetDate->isToday();

                    if ($target) {
                        $isCompleted = $target->status === 'completed';
                        $isSkipped = $target->status === 'skipped';
                        $isCurrent = ($skill->current_day === $target->day_number || $isToday) && $skill->status !== 'completed';
                        $isLocked = $target->day_number > $skill->current_day && $targetDate->gt($today) && !$isCompleted && !$isSkipped;
                        
                        $isRest = str_contains($target->title, 'Rest & Recovery');
                        $nodeColor = $isCompleted ? 'bg-emerald-500 dark:bg-emerald-600' : ($isSkipped ? 'bg-rose-500 dark:bg-rose-600/80' : ($isRest ? 'bg-sky-500 dark:bg-sky-600' : ($isCurrent ? 'bg-atoma-blue dark:bg-indigo-600 ring-4 ring-atoma-blue/20 dark:ring-indigo-500/20' : 'bg-gray-400 dark:bg-gray-600')));
                    } else {
                        $isCompleted = false;
                        $isSkipped = false;
                        $isCurrent = $isToday;
                        $isLocked = $targetDate->gt($today);
                        $isRest = false;
                        $nodeColor = 'bg-gray-300 dark:bg-gray-700';
                    }
                @endphp
                
                <div id="target-day-{{ $i }}" class="flex items-start md:items-center md:justify-between w-full relative scroll-mt-28 {{ $isLocked ? 'opacity-70' : '' }}">
                    
                    <!-- Left Slot (Desktop only) -->
                    <div class="hidden md:block w-5/12">
                        @if($isLeft)
                            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm hover:shadow-md border transition-all relative group {{ $isCurrent ? 'border-2 border-atoma-blue shadow-lg shadow-atoma-blue/10' : 'border-gray-100 dark:border-gray-700 hover:border-atoma-aqua/50' }}">
                                @if(!$target)
                                    <div class="flex flex-col items-end text-right">
                                        <div class="inline-flex items-center gap-1.5 bg-gray-100 dark:bg-gray-700 text-gray-500 text-xs font-bold px-3 py-1 rounded-full mb-2">
                                            <x-icon.calendar class="w-3.5 h-3.5" />
                                            <span>{{ $formattedDate }} • {{ __('Hari Ke-:i', ['i' => $i]) }}</span>
                                        </div>
                                        <h4 class="text-base sm:text-lg font-bold text-gray-400 dark:text-gray-500 mb-1">{{ __('Locked Target') }}</h4>
                                        <p class="text-gray-400 dark:text-gray-600 text-xs font-medium">{{ __('Selesaikan hari sebelumnya untuk membuka') }}</p>
                                    </div>
                                @else
                                    <div class="flex items-center justify-between gap-2 mb-3">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center space-x-1 bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan text-[11px] font-black px-2.5 py-0.5 rounded-full border border-atoma-aqua/50 dark:border-atoma-blue/40">
                                                <span class="inline-flex items-center gap-1"><x-icon.calendar class="w-3 h-3" /> {{ $formattedDate }}</span>
                                                <span class="opacity-40">•</span>
                                                <span>H-{{ $i }}</span>
                                            </span>
                                            @if($target->is_ai_generated)
                                                <span class="bg-gradient-to-r from-atoma-blue to-atoma-cyan text-white text-[9px] font-extrabold px-2 py-0.5 rounded-full shadow-sm">AI Generated</span>
                                            @endif
                                        </div>

                                        @php
                                            $tTask = $target->task;
                                            $isSys = $tTask ? ($tTask->mode === 'sistematis') : ($skill->mode === 'systematic' || $skill->mode === 'sistematis');
                                        @endphp
                                        <button type="button" wire:click="toggleTargetMode({{ $target->id }})" class="inline-flex items-center gap-1 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full transition-all border cursor-pointer {{ $isSys ? 'bg-atoma-ice text-atoma-blue border-atoma-aqua/50 dark:bg-atoma-blue/20 dark:text-atoma-cyan dark:border-atoma-blue/40 hover:bg-atoma-aqua/20' : 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/50 dark:text-purple-300 dark:border-purple-800 hover:bg-indigo-50' }}" title="{{ __('Klik untuk ganti mode') }}">
                                            @if($isSys)
                                                <x-icon.target class="w-3 h-3" /> <span>Systematic</span>
                                            @else
                                                <x-icon.sparkles class="w-3 h-3" /> <span>Zen</span>
                                            @endif
                                        </button>
                                    </div>

                                    <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white mb-1.5">{{ $target->title }}</h4>
                                    @if($target->description)
                                        <div class="text-gray-600 dark:text-gray-300 text-xs sm:text-sm font-medium mb-3 leading-relaxed space-y-1">{!! nl2br(e($target->description)) !!}</div>
                                    @endif

                                    <!-- Sub-tasks Checklist & Bulky Tasks -->
                                    <x-milestone-subtasks-checklist :target="$target" />
                                    
                                    <!-- Reflection View & Warning Display -->
                                    @if(!empty($target->evaluation_feedback) && !str_starts_with($target->evaluation_feedback, 'Gagal') && !str_contains($target->evaluation_feedback, '[EVALUASI_MINGGUAN_SELESAI]'))
                                        <div class="mt-2.5 p-3 bg-atoma-ice/80 dark:bg-atoma-blue/20 rounded-2xl border border-atoma-aqua/60 dark:border-atoma-blue/40 text-left">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs font-bold text-atoma-blue dark:text-atoma-cyan inline-flex items-center gap-1">
                                                    <x-icon.message-square class="w-3.5 h-3.5" />
                                                    <span>{{ __('Refleksi Harian:') }}</span>
                                                </span>
                                                <button type="button" wire:click="openEvalModal({{ $target->id }})" class="text-[10px] text-atoma-blue dark:text-atoma-cyan font-bold hover:underline cursor-pointer inline-flex items-center gap-0.5">
                                                    <x-icon.pencil class="w-2.5 h-2.5" />
                                                    <span>{{ __('Edit') }}</span>
                                                </button>
                                            </div>
                                            <p class="text-xs text-atoma-blue dark:text-atoma-cyan font-medium italic">"{{ $target->evaluation_feedback }}"</p>
                                        </div>
                                    @elseif(($isCompleted || $isPastDate) && !$isRest && empty($target->evaluation_feedback))
                                        <div class="mt-2.5 p-2.5 bg-amber-50 dark:bg-amber-950/50 rounded-2xl border border-amber-200 dark:border-amber-800 flex items-center justify-between">
                                            <span class="text-[11px] font-bold text-amber-800 dark:text-amber-300 flex items-center gap-1">
                                                <x-icon.alert-triangle class="w-3.5 h-3.5" />
                                                <span>{{ __('Refleksi belum diisi') }}</span>
                                            </span>
                                            <button type="button" wire:click="openEvalModal({{ $target->id }})" class="px-2 py-1 bg-amber-600 hover:bg-amber-500 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all cursor-pointer inline-flex items-center gap-1">
                                                <x-icon.pencil class="w-2.5 h-2.5" />
                                                <span>{{ __('Isi') }}</span>
                                            </button>
                                        </div>
                                    @endif
                                    
                                    @php
                                        $ytUrl = $target->effective_youtube_url;
                                        $aiUrl = $target->effective_ai_chat_url;
                                    @endphp
                                    @if($ytUrl || $aiUrl)
                                        <div class="mt-2.5 flex items-center justify-end flex-wrap gap-1.5">
                                            @if($ytUrl)
                                                <a href="{{ $ytUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-[11px] font-bold text-red-600 hover:text-red-500 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 px-2.5 py-1 rounded-xl transition-colors shrink-0">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                                    Video YouTube
                                                </a>
                                            @endif
                                            @if($aiUrl)
                                                <x-ai-chat-badge :url="$aiUrl" />
                                            @endif
                                        </div>
                                    @endif
                                    
                                    <div class="mt-3.5 pt-2.5 border-t border-gray-100 dark:border-gray-700/50 flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            @if(!$isCompleted && !$isSkipped && !$isLocked)
                                                <button type="button" 
                                                        @click.stop="$dispatch('start-pomodoro-task', { taskId: '{{ $target->task ? $target->task->id : 'm-'.$target->id }}', taskTitle: '[Milestone] {{ addslashes($skill->title) }}: {{ addslashes($target->title) }}' })" 
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-600 active:scale-95 text-white shadow-xs transition-all cursor-pointer shrink-0"
                                                        title="{{ __('Mulai Sesi Fokus Pomodoro 25 Menit') }}">
                                                    <x-icon.clock class="w-3.5 h-3.5 text-white" />
                                                    <span>{{ __('Fokus') }}</span>
                                                </button>
                                            @endif

                                            @if($isCompleted)
                                                <div class="inline-flex items-center space-x-1.5">
                                                    <span class="inline-flex items-center text-emerald-600 font-extrabold text-[11px] bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                                        <svg class="w-3 h-3 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                        {{ __('Selesai') }}
                                                    </span>
                                                    @if(!$isRest)
                                                        <button type="button" wire:click="openEvalModal({{ $target->id }})" class="inline-flex items-center gap-1 text-[11px] font-bold text-atoma-blue dark:text-atoma-cyan bg-atoma-ice hover:bg-atoma-aqua/40 dark:bg-atoma-blue/20 px-2 py-0.5 rounded-lg transition-colors border border-atoma-aqua/50 dark:border-atoma-blue/40 cursor-pointer">
                                                            <x-icon.pencil class="w-3 h-3" />
                                                            <span>{{ __('Refleksi') }}</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            @elseif($isSkipped)
                                                <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-extrabold text-[11px] bg-rose-50 dark:bg-rose-950/60 px-2.5 py-0.5 rounded-lg border border-rose-200 dark:border-rose-800">
                                                    <x-icon.x class="w-3 h-3" />
                                                    <span>{{ __('Terlewati') }}</span>
                                                </span>
                                            @elseif($isCurrent)
                                                <button type="button" wire:click="{{ $isRest ? 'openRestModal('.$target->id.')' : 'openEvalModal('.$target->id.')' }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black shadow-sm transition-all text-white cursor-pointer {{ $isRest ? 'bg-sky-600 hover:bg-sky-500' : 'bg-atoma-blue hover:bg-[#3455dc] shadow-sm shadow-atoma-blue/30' }}">
                                                    @if($isRest)
                                                        <x-icon.coffee class="w-3.5 h-3.5" />
                                                        <span>{{ __('Rest Day Selesai') }}</span>
                                                    @else
                                                        <x-icon.check class="w-3.5 h-3.5" />
                                                        <span>{{ __('Selesaikan Hari Ini') }}</span>
                                                    @endif
                                                </button>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-gray-400 dark:text-gray-500 font-bold text-[11px] bg-gray-100 dark:bg-gray-700/60 px-2 py-0.5 rounded-lg">
                                                    <x-icon.lock class="w-3 h-3" />
                                                    <span>{{ __('Locked') }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        <div class="flex items-center space-x-1">
                                            <button type="button" wire:click="openEditModal({{ $target->id }})" class="inline-flex items-center text-[11px] font-bold text-gray-400 hover:text-atoma-blue dark:hover:text-atoma-cyan p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors cursor-pointer" title="{{ __('Edit Target') }}">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            </button>
                                            <button type="button" wire:click="deleteTarget({{ $target->id }})" wire:confirm="{{ __('Yakin hapus target ini?') }}" class="inline-flex items-center text-[11px] font-bold text-gray-400 hover:text-rose-500 p-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors cursor-pointer" title="{{ __('Hapus Target') }}">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    
                    <!-- Center Timeline Node (Alinged to card top) -->
                    <div class="absolute left-4 md:left-1/2 transform -translate-x-1/2 top-4 z-20 flex items-center justify-center">
                        <div class="w-8 h-8 md:w-10 md:h-10 {{ $nodeColor }} text-white font-black text-xs md:text-sm rounded-full flex items-center justify-center shadow-md border-2 md:border-3 border-white dark:border-gray-900 transition-transform duration-300">
                            @if($isRest)
                                <x-icon.coffee class="w-4 h-4 text-white" />
                            @elseif($isSkipped)
                                <x-icon.x class="w-4 h-4 text-white" />
                            @elseif($isCompleted)
                                <x-icon.check class="w-4 h-4 text-white" />
                            @else
                                {{ $i }}
                            @endif
                        </div>
                    </div>
                    
                    <!-- Right Slot (Mobile always visible, Desktop for even items) -->
                    <div class="w-full pl-11 md:pl-0 md:w-5/12 {{ $isLeft ? 'md:hidden' : '' }}">
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-6 shadow-sm hover:shadow-md border transition-all relative group {{ $isCurrent ? 'border-2 border-atoma-blue shadow-lg shadow-atoma-blue/10' : 'border-gray-100 dark:border-gray-700 hover:border-atoma-aqua/50' }}">
                            @if(!$target)
                                <div class="flex flex-col">
                                    <div class="inline-flex items-center gap-1.5 bg-gray-100 dark:bg-gray-700 text-gray-500 text-xs font-bold px-2.5 py-0.5 rounded-full mb-2 w-max">
                                        <x-icon.calendar class="w-3.5 h-3.5" />
                                        <span>{{ $formattedDate }} • {{ __('Hari Ke-:i', ['i' => $i]) }}</span>
                                    </div>
                                    <h4 class="text-base sm:text-lg font-bold text-gray-400 dark:text-gray-500 mb-1">{{ __('Locked Target') }}</h4>
                                    <p class="text-gray-400 dark:text-gray-600 text-xs font-medium">{{ __('Selesaikan hari sebelumnya untuk membuka') }}</p>
                                </div>
                            @else
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center space-x-1 bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan text-[11px] font-black px-2.5 py-0.5 rounded-full border border-atoma-aqua/50 dark:border-atoma-blue/40">
                                            <span class="inline-flex items-center gap-1"><x-icon.calendar class="w-3 h-3" /> {{ $formattedDate }}</span>
                                            <span class="opacity-40">•</span>
                                            <span>H-{{ $i }}</span>
                                        </span>
                                        @if($target->is_ai_generated)
                                            <span class="bg-gradient-to-r from-atoma-blue to-atoma-cyan text-white text-[9px] font-extrabold px-2 py-0.5 rounded-full shadow-sm">AI Generated</span>
                                        @endif
                                    </div>

                                    @php
                                        $tTask = $target->task;
                                        $isSys = $tTask ? ($tTask->mode === 'sistematis') : ($skill->mode === 'systematic' || $skill->mode === 'sistematis');
                                    @endphp
                                    <button type="button" wire:click="toggleTargetMode({{ $target->id }})" class="inline-flex items-center gap-1 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full transition-all border cursor-pointer {{ $isSys ? 'bg-atoma-ice text-atoma-blue border-atoma-aqua/50 dark:bg-atoma-blue/20 dark:text-atoma-cyan dark:border-atoma-blue/40 hover:bg-atoma-aqua/20' : 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/50 dark:text-purple-300 dark:border-purple-800 hover:bg-indigo-50' }}" title="{{ __('Klik untuk ganti mode') }}">
                                        @if($isSys)
                                            <x-icon.target class="w-3 h-3" /> <span>Systematic</span>
                                        @else
                                            <x-icon.sparkles class="w-3 h-3" /> <span>Zen</span>
                                        @endif
                                    </button>
                                </div>

                                <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white mb-1.5">{{ $target->title }}</h4>
                                @if($target->description)
                                    <div class="text-gray-600 dark:text-gray-300 text-xs sm:text-sm font-medium mb-3 leading-relaxed space-y-1">{!! nl2br(e($target->description)) !!}</div>
                                @endif

                                    <!-- Sub-tasks Checklist & Bulky Tasks -->
                                    <x-milestone-subtasks-checklist :target="$target" />

                                <!-- Reflection View & Warning Display -->
                                @if(!empty($target->evaluation_feedback) && !str_starts_with($target->evaluation_feedback, 'Gagal') && !str_contains($target->evaluation_feedback, '[EVALUASI_MINGGUAN_SELESAI]'))
                                    <div class="mt-2.5 p-3 bg-atoma-ice/80 dark:bg-atoma-blue/20 rounded-2xl border border-atoma-aqua/60 dark:border-atoma-blue/40 text-left">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-bold text-atoma-blue dark:text-atoma-cyan inline-flex items-center gap-1">
                                                <x-icon.message-square class="w-3.5 h-3.5" />
                                                <span>{{ __('Refleksi Harian:') }}</span>
                                            </span>
                                            <button type="button" wire:click="openEvalModal({{ $target->id }})" class="text-[10px] text-atoma-blue dark:text-atoma-cyan font-bold hover:underline cursor-pointer inline-flex items-center gap-0.5">
                                                <x-icon.pencil class="w-2.5 h-2.5" />
                                                <span>{{ __('Edit') }}</span>
                                            </button>
                                        </div>
                                        <p class="text-xs text-atoma-blue dark:text-atoma-cyan font-medium italic">"{{ $target->evaluation_feedback }}"</p>
                                    </div>
                                @elseif(($isCompleted || $isPastDate) && !$isRest && empty($target->evaluation_feedback))
                                    <div class="mt-2.5 p-2.5 bg-amber-50 dark:bg-amber-950/50 rounded-2xl border border-amber-200 dark:border-amber-800 flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-amber-800 dark:text-amber-300 flex items-center gap-1">
                                            <x-icon.alert-triangle class="w-3.5 h-3.5" />
                                            <span>{{ __('Refleksi belum diisi') }}</span>
                                        </span>
                                        <button type="button" wire:click="openEvalModal({{ $target->id }})" class="px-2 py-1 bg-amber-600 hover:bg-amber-500 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all cursor-pointer inline-flex items-center gap-1">
                                            <x-icon.pencil class="w-2.5 h-2.5" />
                                            <span>{{ __('Isi') }}</span>
                                        </button>
                                    </div>
                                @endif

                                @php
                                    $ytUrl = $target->effective_youtube_url;
                                    $aiUrl = $target->effective_ai_chat_url;
                                @endphp
                                @if($ytUrl || $aiUrl)
                                    <div class="mt-2.5 flex items-center flex-wrap gap-1.5">
                                        @if($ytUrl)
                                            <a href="{{ $ytUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-[11px] font-bold text-red-600 hover:text-red-500 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 px-2.5 py-1 rounded-xl transition-colors shrink-0 w-max">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                                Video YouTube
                                            </a>
                                        @endif
                                        @if($aiUrl)
                                            <x-ai-chat-badge :url="$aiUrl" />
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-3.5 pt-2.5 border-t border-gray-100 dark:border-gray-700/50 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        @if(!$isCompleted && !$isSkipped && !$isLocked)
                                            <button type="button" 
                                                    @click.stop="$dispatch('start-pomodoro-task', { taskId: '{{ $target->task ? $target->task->id : 'm-'.$target->id }}', taskTitle: '[Milestone] {{ addslashes($skill->title) }}: {{ addslashes($target->title) }}' })" 
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-600 active:scale-95 text-white shadow-xs transition-all cursor-pointer shrink-0"
                                                    title="{{ __('Mulai Sesi Fokus Pomodoro 25 Menit') }}">
                                                <x-icon.clock class="w-3.5 h-3.5 text-white" />
                                                <span>{{ __('Fokus') }}</span>
                                            </button>
                                        @endif

                                        @if($isCompleted)
                                            <div class="inline-flex items-center space-x-1.5">
                                                <span class="inline-flex items-center text-emerald-600 font-extrabold text-[11px] bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                                    <svg class="w-3 h-3 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                    {{ __('Selesai') }}
                                                </span>
                                                @if(!$isRest)
                                                    <button type="button" wire:click="openEvalModal({{ $target->id }})" class="inline-flex items-center gap-1 text-[11px] font-bold text-atoma-blue dark:text-atoma-cyan bg-atoma-ice hover:bg-atoma-aqua/40 dark:bg-atoma-blue/20 px-2 py-0.5 rounded-lg transition-colors border border-atoma-aqua/50 dark:border-atoma-blue/40 cursor-pointer">
                                                        <x-icon.pencil class="w-3 h-3" />
                                                        <span>{{ __('Refleksi') }}</span>
                                                    </button>
                                                @endif
                                            </div>
                                        @elseif($isSkipped)
                                            <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-extrabold text-[11px] bg-rose-50 dark:bg-rose-950/60 px-2.5 py-0.5 rounded-lg border border-rose-200 dark:border-rose-800">
                                                <x-icon.x class="w-3 h-3" />
                                                <span>{{ __('Terlewati') }}</span>
                                            </span>
                                        @elseif($isCurrent)
                                            <button type="button" wire:click="{{ $isRest ? 'openRestModal('.$target->id.')' : 'openEvalModal('.$target->id.')' }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-black shadow-sm transition-all text-white cursor-pointer {{ $isRest ? 'bg-sky-600 hover:bg-sky-500' : 'bg-atoma-blue hover:bg-[#3455dc] shadow-sm shadow-atoma-blue/30' }}">
                                                @if($isRest)
                                                    <x-icon.coffee class="w-3.5 h-3.5" />
                                                    <span>{{ __('Rest Day Selesai') }}</span>
                                                @else
                                                    <x-icon.check class="w-3.5 h-3.5" />
                                                    <span>{{ __('Selesaikan Hari Ini') }}</span>
                                                @endif
                                            </button>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-gray-400 dark:text-gray-500 font-bold text-[11px] bg-gray-100 dark:bg-gray-700/60 px-2 py-0.5 rounded-lg">
                                                <x-icon.lock class="w-3 h-3" />
                                                <span>{{ __('Locked') }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center space-x-1">
                                        <button type="button" wire:click="openEditModal({{ $target->id }})" class="inline-flex items-center text-[11px] font-bold text-gray-400 hover:text-atoma-blue dark:hover:text-atoma-cyan p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors cursor-pointer" title="{{ __('Edit Target') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button type="button" wire:click="deleteTarget({{ $target->id }})" wire:confirm="{{ __('Yakin hapus target ini?') }}" class="inline-flex items-center text-[11px] font-bold text-gray-400 hover:text-rose-500 p-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors cursor-pointer" title="{{ __('Hapus Target') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endfor
        </div>
    </div>
    @endif

    @if($viewMode === 'cards')
    <!-- Weekly Cards / Kanban Pathway View (Mobile-First) -->
    <div class="space-y-6">
        @foreach($this->weekCycles as $wNum => $wInfo)
            @php
                $wStart = $wInfo['start_day'];
                $wEnd = $wInfo['end_day'];
                $wTargets = $skill->targets->whereBetween('day_number', [$wStart, $wEnd]);
                $wDone = $wTargets->where('status', 'completed')->count();
                $wTotal = ($wEnd - $wStart + 1);
                $wPct = $wTotal > 0 ? round(($wDone / $wTotal) * 100) : 0;
            @endphp
            <div class="bg-white dark:bg-gray-800/90 rounded-3xl p-4 sm:p-6 border border-gray-100 dark:border-gray-800 shadow-sm" x-data="{ expanded: true }">
                <!-- Week Header -->
                <div class="flex items-center justify-between gap-3 cursor-pointer select-none" @click="expanded = !expanded">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-black text-xs sm:text-sm {{ $wDone === $wTotal && $wTotal > 0 ? 'bg-emerald-500 text-white shadow-xs shadow-emerald-500/30' : 'bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan border border-atoma-aqua/40 dark:border-atoma-blue/40' }}">
                            W{{ $wNum }}
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                                <span>{{ $wInfo['label'] }}</span>
                            </h3>
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-medium">
                                <span>{{ __(':done/:total Target Selesai (:pct%)', ['done' => $wDone, 'total' => $wTotal, 'pct' => $wPct]) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <div class="w-20 sm:w-28 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden hidden xs:block">
                            <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-2 rounded-full transition-all duration-300" style="width: {{ $wPct }}%"></div>
                        </div>
                        <button type="button" class="p-1 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer">
                            <svg class="w-5 h-5 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Week Targets Grid -->
                <div x-show="expanded" x-collapse class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/60 grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    @for($i = $wStart; $i <= $wEnd; $i++)
                        @php
                            $target = $skill->targets->where('day_number', $i)->first();
                            $targetDate = (clone $baseDate)->addDays($i - 1);
                            $formattedDate = $targetDate->translatedFormat('D, d M Y');
                            $isPastDate = $targetDate->lt($today);
                            $isToday = $targetDate->isToday();

                            if ($target) {
                                $isCompleted = $target->status === 'completed';
                                $isSkipped = $target->status === 'skipped';
                                $isCurrent = ($skill->current_day === $target->day_number || $isToday) && $skill->status !== 'completed';
                                $isLocked = $target->day_number > $skill->current_day && $targetDate->gt($today) && !$isCompleted && !$isSkipped;
                                $isRest = str_contains($target->title, 'Rest & Recovery');
                            } else {
                                $isCompleted = false;
                                $isSkipped = false;
                                $isCurrent = $isToday;
                                $isLocked = $targetDate->gt($today);
                                $isRest = false;
                            }
                        @endphp

                        <div id="target-card-{{ $i }}" class="rounded-2xl p-4 border transition-all relative flex flex-col justify-between scroll-mt-28 {{ $isCurrent ? 'bg-atoma-ice/40 dark:bg-atoma-blue/10 border-atoma-blue dark:border-atoma-cyan/60 shadow-md ring-1 ring-atoma-blue/30' : ($isCompleted ? 'bg-emerald-50/30 dark:bg-emerald-950/20 border-emerald-200/70 dark:border-emerald-800/50' : ($isRest ? 'bg-sky-50/40 dark:bg-sky-950/20 border-sky-200/70 dark:border-sky-800/50' : 'bg-gray-50/60 dark:bg-gray-900/40 border-gray-200/70 dark:border-gray-700/50')) }} {{ $isLocked ? 'opacity-65' : '' }}">
                            <div>
                                <!-- Card Header -->
                                <div class="flex items-center justify-between gap-1.5 mb-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-black px-2 py-0.5 rounded-lg {{ $isCompleted ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' : ($isCurrent ? 'bg-atoma-blue text-white' : ($isRest ? 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300')) }}">
                                            {{ __('Hari :i', ['i' => $i]) }}
                                        </span>
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400 font-bold">
                                            {{ $formattedDate }}
                                        </span>
                                    </div>

                                    @if($target)
                                        @php
                                            $tTask = $target->task;
                                            $isSys = $tTask ? ($tTask->mode === 'sistematis') : ($skill->mode === 'systematic' || $skill->mode === 'sistematis');
                                        @endphp
                                        <button type="button" wire:click="toggleTargetMode({{ $target->id }})" class="inline-flex items-center gap-1 text-[9px] font-extrabold px-2 py-0.5 rounded-full transition-all border cursor-pointer {{ $isSys ? 'bg-atoma-ice text-atoma-blue border-atoma-aqua/50 dark:bg-atoma-blue/20 dark:text-atoma-cyan dark:border-atoma-blue/40' : 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/50 dark:text-purple-300 dark:border-purple-800' }}">
                                            @if($isSys)
                                                <x-icon.target class="w-2.5 h-2.5" /> <span>Systematic</span>
                                            @else
                                                <x-icon.sparkles class="w-2.5 h-2.5" /> <span>Zen</span>
                                            @endif
                                        </button>
                                    @endif
                                </div>

                                <!-- Card Title -->
                                <h4 class="text-sm font-black text-gray-900 dark:text-white mb-1.5 leading-snug">
                                    {{ $target ? $target->title : __('Target Belum Dibuka') }}
                                </h4>

                                <!-- Card Description -->
                                @if($target && $target->description)
                                    <div class="text-gray-600 dark:text-gray-300 text-xs font-medium mb-2.5 line-clamp-3 leading-relaxed">
                                        {!! nl2br(e($target->description)) !!}
                                    </div>
                                @endif

                                <!-- Subtasks Preview & Bulky Tasks in Card -->
                                @if($target)
                                    @php
                                        $tSubsTotal = $target->subTasks ? $target->subTasks->count() : 0;
                                        $tSubsDone = $target->subTasks ? $target->subTasks->where('is_completed', true)->count() : 0;
                                    @endphp
                                    <div class="mb-2.5 p-2.5 bg-white/90 dark:bg-gray-800/90 rounded-xl border border-gray-100 dark:border-gray-700/60 text-[11px] space-y-1.5">
                                        <div class="flex items-center justify-between font-bold text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center gap-1">
                                                <x-icon.check-circle class="w-3 h-3 text-atoma-blue" />
                                                <span>{{ __('Subtask:') }}</span>
                                            </span>
                                            @if($tSubsTotal > 0)
                                                <span class="text-atoma-blue dark:text-atoma-cyan font-mono font-black">{{ $tSubsDone }}/{{ $tSubsTotal }}</span>
                                            @endif
                                        </div>
                                        @if($tSubsTotal > 0)
                                            <div class="space-y-1">
                                                @foreach($target->subTasks as $st)
                                                    <label class="flex items-center gap-1.5 cursor-pointer select-none text-[11px]">
                                                        <input type="checkbox" 
                                                               wire:click="toggleTargetSubTask({{ $target->id }}, {{ $st->id }})" 
                                                               {{ $st->is_completed ? 'checked' : '' }} 
                                                               class="rounded border-gray-300 dark:border-gray-600 text-atoma-blue focus:ring-atoma-blue w-3 h-3 cursor-pointer">
                                                        <span class="truncate {{ $st->is_completed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-200' }}">
                                                            {{ $st->title }}
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                        <div class="pt-1 flex items-center gap-1.5" x-data="{ newSub: '' }">
                                            <input type="text" x-model="newSub" @keydown.enter.prevent="if (newSub.trim()) { $wire.quickAddSubTask({{ $target->id }}, newSub); newSub = ''; }" placeholder="{{ __('+ Tambah subtask...') }}" class="w-full text-[11px] px-2 py-0.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-atoma-blue">
                                            <button type="button" @click="if (newSub.trim()) { $wire.quickAddSubTask({{ $target->id }}, newSub); newSub = ''; }" class="px-1.5 py-0.5 bg-atoma-blue text-white rounded-md text-[11px] font-bold shrink-0 hover:bg-[#3455dc] cursor-pointer" title="{{ __('Tambah Subtask') }}">+</button>
                                        </div>
                                    </div>
                                @endif

                                @php
                                    $ytUrl = $target ? $target->effective_youtube_url : null;
                                    $aiUrl = $target ? $target->effective_ai_chat_url : null;
                                @endphp
                                @if($ytUrl || $aiUrl)
                                    <div class="mb-2.5 flex items-center flex-wrap gap-1.5">
                                        @if($ytUrl)
                                            <a href="{{ $ytUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-[10px] font-bold text-red-600 hover:text-red-500 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 px-2 py-0.5 rounded-lg transition-colors shrink-0">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                                YouTube
                                            </a>
                                        @endif
                                        @if($aiUrl)
                                            <x-ai-chat-badge :url="$aiUrl" size="small" />
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Card Actions Footer -->
                            @if($target)
                                <div class="pt-2.5 mt-2 border-t border-gray-100 dark:border-gray-700/50 flex flex-wrap items-center justify-between gap-1.5">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        @if(!$isCompleted && !$isSkipped && !$isLocked)
                                            <button type="button" 
                                                    @click.stop="$dispatch('start-pomodoro-task', { taskId: '{{ $target->task ? $target->task->id : 'm-'.$target->id }}', taskTitle: '[Milestone] {{ addslashes($skill->title) }}: {{ addslashes($target->title) }}' })" 
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-500 hover:bg-amber-600 active:scale-95 text-white shadow-xs transition-all cursor-pointer shrink-0"
                                                    title="{{ __('Mulai Sesi Fokus Pomodoro 25 Menit') }}">
                                                <x-icon.clock class="w-3 h-3 text-white" />
                                                <span>{{ __('Fokus') }}</span>
                                            </button>
                                        @endif

                                        @if($isCompleted)
                                            <span class="inline-flex items-center text-emerald-600 dark:text-emerald-400 font-extrabold text-[10px] bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                {{ __('Selesai') }}
                                            </span>
                                            @if(!$isRest)
                                                <button type="button" wire:click="openEvalModal({{ $target->id }})" class="inline-flex items-center gap-1 text-[10px] font-bold text-atoma-blue dark:text-atoma-cyan bg-atoma-ice hover:bg-atoma-aqua/40 dark:bg-atoma-blue/20 px-2 py-0.5 rounded-lg transition-colors border border-atoma-aqua/50 dark:border-atoma-blue/40 cursor-pointer">
                                                    <x-icon.pencil class="w-2.5 h-2.5" />
                                                    <span>{{ __('Refleksi') }}</span>
                                                </button>
                                            @endif
                                        @elseif($isSkipped)
                                            <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-extrabold text-[10px] bg-rose-50 dark:bg-rose-950/60 px-2.5 py-0.5 rounded-lg border border-rose-200 dark:border-rose-800">
                                                <x-icon.x class="w-3 h-3" />
                                                <span>{{ __('Terlewati') }}</span>
                                            </span>
                                        @elseif($isCurrent)
                                            <button type="button" 
                                                    wire:click="{{ $isRest ? 'openRestModal('.$target->id.')' : 'openEvalModal('.$target->id.')' }}" 
                                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-black shadow-xs transition-all text-white cursor-pointer {{ $isRest ? 'bg-sky-600 hover:bg-sky-500' : 'bg-atoma-blue hover:bg-[#3455dc]' }}">
                                                @if($isRest)
                                                    <x-icon.coffee class="w-3 h-3" />
                                                    <span>{{ __('Rest Selesai') }}</span>
                                                @else
                                                    <x-icon.check class="w-3 h-3" />
                                                    <span>{{ __('Selesaikan') }}</span>
                                                @endif
                                            </button>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-gray-400 dark:text-gray-500 font-bold text-[10px] bg-gray-100 dark:bg-gray-700/60 px-2 py-0.5 rounded-lg">
                                                <x-icon.lock class="w-3 h-3" />
                                                <span>{{ __('Locked') }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center space-x-0.5">
                                        <button type="button" wire:click="openEditModal({{ $target->id }})" class="p-1 text-gray-400 hover:text-atoma-blue dark:hover:text-atoma-cyan rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors cursor-pointer" title="{{ __('Edit Target') }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button type="button" wire:click="deleteTarget({{ $target->id }})" wire:confirm="{{ __('Yakin hapus target ini?') }}" class="p-1 text-gray-400 hover:text-rose-500 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors cursor-pointer" title="{{ __('Hapus Target') }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endfor
                </div>
            </div>
        @endforeach
    </div>
    @endif
    @endif

    <!-- Daily Reflection Modal -->
    @if($showEvalModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="$set('showEvalModal', false)"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <x-icon.message-square class="w-6 h-6 text-atoma-blue" />
                            <span>{{ __('Refleksi Harian') }}</span>
                        </h3>
                        <button type="button" wire:click="$set('showEvalModal', false)" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-full p-2 transition-colors cursor-pointer">
                            <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <form wire:submit="submitEval">
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">{{ __('Bagaimana proses latihan kamu hari ini?') }}</label>
                            <textarea wire:model="userFeedback" rows="4" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="{{ __('Contoh: Berhasil menyelesaikan latihan editing 15 menit, sudah memahami teknik manual sequence...') }}"></textarea>
                            @error('userFeedback') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="w-full py-4 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-2xl font-bold text-base shadow-lg shadow-atoma-blue/25 dark:shadow-none hover:-translate-y-0.5 transition-all flex items-center justify-center cursor-pointer">
                            <span class="inline-flex items-center gap-2">
                                <span>{{ __('Simpan Refleksi Harian') }}</span>
                                <x-icon.save class="w-5 h-5" />
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Edit Target Modal -->
    @if($showEditModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="$set('showEditModal', false)"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <x-icon.pencil class="w-6 h-6 text-atoma-blue" />
                            <span>{{ __('Edit Target Harian') }}</span>
                        </h3>
                        <button type="button" wire:click="$set('showEditModal', false)" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-full p-2 transition-colors cursor-pointer">
                            <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <form wire:submit="updateTarget">
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Judul Target') }}</label>
                            <input type="text" wire:model="editTitle" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue">
                            @error('editTitle') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Mode Tugas Target') }}</label>
                            <select wire:model="editMode" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue">
                                <option value="sistematis">{{ __('System Task (Masuk ke Task List)') }}</option>
                                <option value="zen">{{ __('Zen Mode (Hanya di Pathway)') }}</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Deskripsi / Rincian Tugas') }}</label>
                            <textarea wire:model="editDescription" rows="4" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="{{ __("• Langkah 1...\n• Langkah 2...") }}"></textarea>
                            @error('editDescription') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Sub-tasks Manager -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                    <x-icon.check-circle class="w-4 h-4 text-atoma-blue" />
                                    <span>{{ __('Sub-task / Langkah Detail') }}</span>
                                </label>
                                <button type="button" wire:click="addEditSubTask" class="text-xs font-bold text-atoma-blue dark:text-atoma-cyan hover:underline cursor-pointer">+ {{ __('Tambah Sub-task') }}</button>
                            </div>
                            @if(!empty($editSubTasksList))
                                <div class="space-y-2 max-h-36 overflow-y-auto pr-1">
                                    @foreach($editSubTasksList as $index => $st)
                                        <div class="flex items-center gap-2" wire:key="subtask-edit-{{ $index }}">
                                            <input type="text" wire:model="editSubTasksList.{{ $index }}.title" placeholder="{{ __('Nama sub-task / langkah...') }}" class="flex-1 px-3 py-2 bg-gray-50 dark:bg-gray-800 border-0 rounded-xl text-xs sm:text-sm text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue">
                                            <button type="button" wire:click="removeEditSubTask({{ $index }})" class="text-red-400 hover:text-red-600 p-1.5 cursor-pointer">
                                                <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                <span>{{ __('Link Referensi YouTube (Opsional)') }}</span>
                            </label>
                            <input type="url" wire:model="editReferenceUrl" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="https://youtube.com/watch?v=...">
                            @error('editReferenceUrl') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1 flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon.sparkles class="w-4 h-4 text-indigo-500 shrink-0" />
                                    <span>{{ __('Link Referensi Chat AI (Opsional)') }}</span>
                                </span>
                                <span class="text-[11px] font-medium text-indigo-500 dark:text-indigo-400">Gemini, GPT, Claude, dll.</span>
                            </label>
                            <input type="url" wire:model="editAiChatUrl" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="e.g. https://chatgpt.com/share/... atau https://gemini.google.com/share/...">
                            @error('editAiChatUrl') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="w-full py-3.5 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-2xl font-bold text-base shadow-lg shadow-atoma-blue/25 transition-all cursor-pointer">
                            <span class="inline-flex items-center gap-2">
                                <span>{{ __('Simpan Perubahan') }}</span>
                                <x-icon.save class="w-4 h-4" />
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Add Target Modal -->
    @if($showAddModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="$set('showAddModal', false)"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <x-icon.plus class="w-6 h-6 text-atoma-blue" />
                            <span>{{ __('Tambah Target Harian') }}</span>
                        </h3>
                        <button type="button" wire:click="$set('showAddModal', false)" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-full p-2 transition-colors cursor-pointer">
                            <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <form wire:submit="createTarget">
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Target Hari Ke-') }}</label>
                            <input type="number" wire:model="newDayNumber" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue">
                            @error('newDayNumber') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Judul Target') }}</label>
                            <input type="text" wire:model="newTitle" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="{{ __('Contoh: Hari 2: Membuat Component Livewire') }}">
                            @error('newTitle') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Mode Tugas Target') }}</label>
                            <select wire:model="newMode" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue">
                                <option value="sistematis">{{ __('System Task (Masuk ke Task List)') }}</option>
                                <option value="zen">{{ __('Zen Mode (Hanya di Pathway)') }}</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Deskripsi / Rincian Tugas') }}</label>
                            <textarea wire:model="newDescription" rows="4" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="{{ __('• Rincian materi...') }}"></textarea>
                            @error('newDescription') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Sub-tasks Manager -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                    <x-icon.check-circle class="w-4 h-4 text-atoma-blue" />
                                    <span>{{ __('Sub-task / Langkah Detail') }}</span>
                                </label>
                                <button type="button" wire:click="addNewSubTask" class="text-xs font-bold text-atoma-blue dark:text-atoma-cyan hover:underline cursor-pointer">+ {{ __('Tambah Sub-task') }}</button>
                            </div>
                            @if(!empty($newSubTasksList))
                                <div class="space-y-2 max-h-36 overflow-y-auto pr-1">
                                    @foreach($newSubTasksList as $index => $st)
                                        <div class="flex items-center gap-2" wire:key="subtask-new-{{ $index }}">
                                            <input type="text" wire:model="newSubTasksList.{{ $index }}.title" placeholder="{{ __('Nama sub-task / langkah...') }}" class="flex-1 px-3 py-2 bg-gray-50 dark:bg-gray-800 border-0 rounded-xl text-xs sm:text-sm text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue">
                                            <button type="button" wire:click="removeNewSubTask({{ $index }})" class="text-red-400 hover:text-red-600 p-1.5 cursor-pointer">
                                                <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                <span>{{ __('Link Referensi YouTube (Opsional)') }}</span>
                            </label>
                            <input type="url" wire:model="newReferenceUrl" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="https://youtube.com/watch?v=...">
                            @error('newReferenceUrl') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1 flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon.sparkles class="w-4 h-4 text-indigo-500 shrink-0" />
                                    <span>{{ __('Link Referensi Chat AI (Opsional)') }}</span>
                                </span>
                                <span class="text-[11px] font-medium text-indigo-500 dark:text-indigo-400">Gemini, GPT, Claude, dll.</span>
                            </label>
                            <input type="url" wire:model="newAiChatUrl" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="e.g. https://chatgpt.com/share/... atau https://gemini.google.com/share/...">
                            @error('newAiChatUrl') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="w-full py-3.5 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-2xl font-bold text-base shadow-lg shadow-atoma-blue/25 transition-all cursor-pointer">
                            <span class="inline-flex items-center gap-2">
                                <span>{{ __('Tambah Target Harian') }}</span>
                                <x-icon.rocket class="w-4 h-4" />
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Weekly & Monthly Evaluation Result Modal -->
    @if($showWeeklyEvalModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="$set('showWeeklyEvalModal', false)"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <span class="bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 text-xs font-bold px-3 py-1 rounded-full inline-flex items-center gap-1.5">
                                <x-icon.trophy class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                <span>{{ __('Checkpoint Evaluasi AI') }}</span>
                            </span>
                            <h3 class="text-2xl font-black text-gray-900 dark:text-white mt-1">{{ __('Laporan Evaluasi AI Coach') }}</h3>
                        </div>
                        <button type="button" wire:click="$set('showWeeklyEvalModal', false)" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-full p-2 transition-colors cursor-pointer">
                            <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    @if($weeklyEvalData)
                        <div class="space-y-4 text-sm">
                            <div class="p-4 bg-atoma-ice/80 dark:bg-atoma-blue/20 rounded-2xl border border-atoma-aqua/60 dark:border-atoma-blue/40 text-center">
                                <div class="text-4xl font-black text-atoma-blue dark:text-atoma-cyan mb-1">{{ $weeklyEvalData['score'] ?? 88 }}/100</div>
                                <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('Performa Evaluasi') }}</div>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-800/60 p-4 rounded-2xl">
                                <h4 class="font-bold text-gray-900 dark:text-white mb-1 flex items-center gap-1.5">
                                    <x-icon.file-text class="w-4 h-4 text-atoma-blue" />
                                    <span>{{ __('Ringkasan Evaluasi:') }}</span>
                                </h4>
                                <p class="text-gray-600 dark:text-gray-300 leading-relaxed">{{ $weeklyEvalData['summary'] ?? '' }}</p>
                            </div>

                            <div class="bg-emerald-50 dark:bg-emerald-950/30 p-4 rounded-2xl border border-emerald-100 dark:border-emerald-800">
                                <h4 class="font-bold text-emerald-900 dark:text-emerald-300 mb-1 flex items-center gap-1.5">
                                    <x-icon.sparkles class="w-4 h-4 text-emerald-600" />
                                    <span>{{ __('Keunggulan Kamu:') }}</span>
                                </h4>
                                <p class="text-emerald-800 dark:text-emerald-200">{{ $weeklyEvalData['strengths'] ?? '' }}</p>
                            </div>

                            <div class="bg-purple-50 dark:bg-purple-950/30 p-4 rounded-2xl border border-purple-100 dark:border-purple-800">
                                <h4 class="font-bold text-purple-900 dark:text-purple-300 mb-1 flex items-center gap-1.5">
                                    <x-icon.target class="w-4 h-4 text-purple-600" />
                                    <span>{{ __('Rekomendasi / Pedoman Siklus Berikutnya:') }}</span>
                                </h4>
                                <p class="text-purple-800 dark:text-purple-200">{{ $weeklyEvalData['recommendations'] ?? '' }}</p>
                            </div>
                        </div>
                    @endif

                    <button type="button" wire:click="$set('showWeeklyEvalModal', false)" class="w-full mt-6 py-3.5 bg-atoma-blue hover:bg-[#3455dc] text-white font-bold rounded-2xl shadow-lg shadow-atoma-blue/25 transition-all cursor-pointer">
                        <span class="inline-flex items-center justify-center gap-2">
                            <span>{{ __('Tutup & Lanjutkan Latihan') }}</span>
                            <x-icon.rocket class="w-4 h-4" />
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Mindful Rest Day Modal -->
    @if($showRestModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="$set('showRestModal', false)"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden border border-sky-100 dark:border-sky-900/50 relative z-10">
                <!-- Header Banner -->
                <div class="bg-gradient-to-br from-sky-500 to-indigo-600 p-6 text-white text-center relative overflow-hidden">
                    <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-white/20 backdrop-blur-xs flex items-center justify-center shadow-inner">
                        <x-icon.coffee class="w-7 h-7 text-white" />
                    </div>
                    <span class="text-[11px] font-black uppercase tracking-wider bg-white/20 px-3 py-1 rounded-full inline-block mb-1">
                        Mindful Recovery & Rest
                    </span>
                    <h3 class="text-xl font-black">{{ __('Waktunya Pemulihan Diri') }}</h3>
                </div>

                <div class="p-6 sm:p-7 space-y-4">
                    <div class="p-4 bg-sky-50 dark:bg-sky-950/40 rounded-2xl border border-sky-100 dark:border-sky-800 text-sky-900 dark:text-sky-200 text-xs sm:text-sm leading-relaxed">
                        <p class="font-medium italic text-center">
                            "{{ __('Istirahat bukanlah tanda berhenti, melainkan proses penting di mana otak mengkonsolidasikan keahlian dan memulihkan fokus mental.') }}"
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                            {{ __('Catatan Relaksasi (Opsional)') }}
                        </label>
                        <textarea wire:model="restNotes" rows="3" class="block w-full px-3.5 py-2.5 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-xs sm:text-sm text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-sky-500" placeholder="{{ __('Contoh: Tidur cukup, meditasi 10 menit, dan jalan sore santai...') }}"></textarea>
                    </div>

                    <div class="space-y-2 pt-2">
                        <button type="button" 
                                wire:click="confirmRestDay"
                                class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-600 hover:to-indigo-700 text-white font-bold rounded-2xl shadow-lg shadow-sky-500/25 transition-all flex items-center justify-center gap-2 cursor-pointer active:scale-95">
                            <x-icon.coffee class="w-4 h-4 text-white" />
                            <span>{{ __('Konfirmasi Istirahat (+20 XP)') }}</span>
                        </button>
                        <button type="button" 
                                wire:click="$set('showRestModal', false)"
                                class="w-full py-2.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 font-bold text-xs rounded-xl transition-colors cursor-pointer">
                            {{ __('Kembali') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Digital Mastery Certificate Modal -->
    @if($showCertificateModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" x-data="{
        shareCertificate() {
            const shareText = 'Saya telah berhasil menyelesaikan tantangan penguasaan {{ addslashes($skill->title) }} selama {{ $skill->target_days }} hari di ATOMA!';
            if (navigator.share) {
                navigator.share({
                    title: 'ATOMA Digital Mastery Certificate - {{ addslashes($skill->title) }}',
                    text: shareText,
                    url: window.location.href
                }).catch(() => {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(shareText + ' ' + window.location.href);
                $dispatch('toast', { message: '{{ __('Teks prestasi dan link disalin ke clipboard!') }}', type: 'success' });
            }
        }
    }">
        <div class="fixed inset-0 bg-gray-950/75 backdrop-blur-md transition-opacity" wire:click="$set('showCertificateModal', false)"></div>
        <div class="flex min-h-full items-center justify-center p-3 sm:p-6">
            <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] shadow-2xl w-full max-w-2xl overflow-hidden border-2 border-amber-400/60 dark:border-amber-500/40 relative z-10 flex flex-col">
                <!-- Certificate Outer Frame with Gold Gradient Accent -->
                <div class="p-6 sm:p-10 relative bg-radial from-amber-50/40 via-white to-sky-50/30 dark:from-gray-900 dark:via-gray-850 dark:to-indigo-950/40 text-center">
                    
                    <!-- Decorative Certificate Border Corner Accents -->
                    <div class="absolute top-4 left-4 w-8 h-8 border-t-2 border-l-2 border-amber-500/60 rounded-tl-xl pointer-events-none"></div>
                    <div class="absolute top-4 right-4 w-8 h-8 border-t-2 border-r-2 border-amber-500/60 rounded-tr-xl pointer-events-none"></div>
                    <div class="absolute bottom-4 left-4 w-8 h-8 border-b-2 border-l-2 border-amber-500/60 rounded-bl-xl pointer-events-none"></div>
                    <div class="absolute bottom-4 right-4 w-8 h-8 border-b-2 border-r-2 border-amber-500/60 rounded-br-xl pointer-events-none"></div>

                    <!-- Close Button -->
                    <button type="button" wire:click="$set('showCertificateModal', false)" class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-2 rounded-full bg-gray-100/80 dark:bg-gray-800 transition-colors cursor-pointer">
                        <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>

                    <!-- Certificate Header -->
                    <div class="mb-4">
                        <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-gradient-to-r from-amber-500 to-yellow-500 text-white font-black text-[10px] sm:text-xs uppercase tracking-widest shadow-sm shadow-amber-500/20">
                            <x-icon.trophy class="w-3.5 h-3.5 text-white" />
                            <span>ATOMA DIGITAL MASTERY CERTIFICATE</span>
                        </div>
                    </div>

                    <h2 class="text-2xl sm:text-4xl font-black text-gray-900 dark:text-white tracking-tight font-serif mb-2">
                        {{ __('Sertifikat Penguasaan') }}
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium tracking-wide">
                        {{ __('Dengan bangga dipersembahkan kepada:') }}
                    </p>

                    <!-- Recipient Name -->
                    <div class="my-4 sm:my-6 py-2 border-b-2 border-amber-300 dark:border-amber-600/60 inline-block px-8 sm:px-14">
                        <h3 class="text-xl sm:text-3xl font-black text-atoma-blue dark:text-atoma-cyan tracking-wide">
                            {{ auth()->user()->name }}
                        </h3>
                    </div>

                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-300 font-medium max-w-md mx-auto leading-relaxed">
                        {{ __('Telah berhasil menuntaskan seluruh kurikulum dan tantangan harian pada modul keahlian:') }}
                    </p>

                    <!-- Skill Title Card -->
                    <div class="my-4 p-4 rounded-2xl bg-white/90 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 shadow-sm inline-block max-w-lg w-full">
                        <h4 class="text-lg sm:text-2xl font-black text-gray-900 dark:text-white mb-1">
                            {{ $skill->title }}
                        </h4>
                        <div class="flex items-center justify-center gap-3 text-xs text-gray-500 dark:text-gray-400 font-bold flex-wrap">
                            <span class="inline-flex items-center gap-1">
                                <x-icon.calendar class="w-3.5 h-3.5 text-atoma-blue" />
                                <span>{{ __(':days Hari Disiplin', ['days' => $skill->target_days]) }}</span>
                            </span>
                            <span>•</span>
                            <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                <x-icon.check-circle class="w-3.5 h-3.5" />
                                <span>100% {{ __('Selesai') }}</span>
                            </span>
                            <span>•</span>
                            <span>{{ $skill->updated_at ? $skill->updated_at->translatedFormat('d F Y') : date('d F Y') }}</span>
                        </div>
                    </div>

                    <!-- AI Coach Endorsement Seal -->
                    <div class="my-5 flex items-center justify-center gap-2">
                        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200/80 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold shadow-xs">
                            <x-icon.bot class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                            <span>{{ __('Terverifikasi oleh ATOMA AI Coach Engine') }}</span>
                        </div>
                    </div>

                    <!-- Action Buttons (Mobile First) -->
                    <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <button type="button" 
                                @click="shareCertificate()"
                                class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-atoma-blue to-[#284fd6] hover:bg-[#3455dc] text-white font-black text-xs sm:text-sm rounded-2xl shadow-lg shadow-atoma-blue/25 transition-all flex items-center justify-center gap-2 cursor-pointer active:scale-95">
                            <x-icon.share-2 class="w-4 h-4 text-white" />
                            <span>{{ __('Bagikan Prestasi') }}</span>
                        </button>
                        <button type="button" 
                                @click="window.print()"
                                class="w-full sm:w-auto px-6 py-3 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs sm:text-sm rounded-2xl transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <x-icon.download class="w-4 h-4" />
                            <span>{{ __('Cetak / Simpan PDF') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Milestone Task Modal (Add/Edit Task directly in Milestone) -->
    @if($showTaskModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeTaskModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10 animate-in fade-in zoom-in-95 duration-200">
                <form wire:submit.prevent="{{ $taskModalTab === 'bulk' && !$editingTaskId ? 'saveBulkMilestoneTasks' : 'saveMilestoneTask' }}">
                    <div class="p-6 sm:p-7">
                        <div class="flex justify-between items-center mb-5">
                            <div>
                                <h3 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">
                                    @if($editingTaskId)
                                        {{ __('Edit Tugas Milestone') }}
                                    @elseif($taskModalTab === 'bulk')
                                        {{ __('Tambah Banyak Tugas Sekaligus') }}
                                    @else
                                        {{ __('Tambah Tugas Baru') }}
                                    @endif
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ __('Milestone') }}: <span class="font-bold text-atoma-blue dark:text-atoma-cyan">{{ $skill->title }}</span>
                                </p>
                            </div>
                            <button type="button" wire:click="closeTaskModal" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-full p-2 transition-colors cursor-pointer">
                                <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        @if(!$editingTaskId)
                            <!-- Tab Switcher (Single vs Bulk) -->
                            <div class="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-800/80 rounded-2xl mb-5">
                                <button type="button" 
                                    wire:click="$set('taskModalTab', 'single')" 
                                    class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer {{ $taskModalTab === 'single' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    {{ __('Tugas Tunggal') }}
                                </button>
                                <button type="button" 
                                    wire:click="$set('taskModalTab', 'bulk')" 
                                    class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer {{ $taskModalTab === 'bulk' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-atoma-cyan shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    <span>{{ __('Tambah Banyak (Bulk)') }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 bg-atoma-blue/10 dark:bg-atoma-blue/30 text-atoma-blue dark:text-atoma-cyan rounded-md font-black uppercase">Cepat</span>
                                </button>
                            </div>
                        @endif

                        @if($taskModalTab === 'bulk' && !$editingTaskId)
                                <div class="p-3.5 bg-gradient-to-br from-indigo-50/90 via-white to-sky-50/60 dark:from-indigo-950/40 dark:via-gray-850 dark:to-sky-950/30 border border-indigo-100 dark:border-indigo-900/40 rounded-2xl space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                                                <x-icon.sparkles class="w-3.5 h-3.5" />
                                            </div>
                                            <span class="text-xs font-black text-gray-900 dark:text-white">{{ __('AI Coach Bulk Task Generator') }}</span>
                                        </div>
                                        <span class="text-[10px] font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-100/70 dark:bg-indigo-900/50 px-2 py-0.5 rounded-full">{{ __('Dinamis & Responsif') }}</span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <input type="text" 
                                               wire:model="bulkTaskAiFocus" 
                                               placeholder="{{ __('Tulis fokus/materi (cth: Knee Armor, Mobilitas, Lari Base...)') }}" 
                                               class="flex-1 px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-medium text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500">
                                        <button type="button" 
                                                wire:click="generateAiBulkTasks" 
                                                wire:loading.attr="disabled"
                                                class="px-3.5 py-2 bg-gradient-to-r from-indigo-600 to-sky-600 hover:from-indigo-700 hover:to-sky-700 text-white rounded-xl text-xs font-bold shadow-xs transition-all cursor-pointer inline-flex items-center gap-1.5 shrink-0 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="generateAiBulkTasks" class="inline-flex items-center gap-1">
                                                <x-icon.sparkles class="w-3.5 h-3.5" />
                                                <span>{{ __('Susun Tasks') }}</span>
                                            </span>
                                            <span wire:loading wire:target="generateAiBulkTasks" class="inline-flex items-center gap-1">
                                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                <span>{{ __('Menyusun...') }}</span>
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Quick Preset Chips -->
                                    <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar pt-0.5">
                                        <span class="text-[10px] font-bold text-gray-400 shrink-0">{{ __('Preset:') }}</span>
                                        <button type="button" wire:click="generateAiBulkTasks(null, 'Target Hari Ini')" class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white/90 dark:bg-gray-800 border border-indigo-100 dark:border-gray-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors whitespace-nowrap cursor-pointer">
                                            🎯 Target Hari Ini
                                        </button>
                                        <button type="button" wire:click="generateAiBulkTasks(null, 'Knee Armor & Mobilitas Sendi')" class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white/90 dark:bg-gray-800 border border-indigo-100 dark:border-gray-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors whitespace-nowrap cursor-pointer">
                                            🦵 Knee Armor & Mobilitas
                                        </button>
                                        <button type="button" wire:click="generateAiBulkTasks(null, 'Lari Base & Kardio')" class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white/90 dark:bg-gray-800 border border-indigo-100 dark:border-gray-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors whitespace-nowrap cursor-pointer">
                                            🏃 Lari & Kardio
                                        </button>
                                        <button type="button" wire:click="generateAiBulkTasks(null, 'Active Recovery & Stretching')" class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white/90 dark:bg-gray-800 border border-indigo-100 dark:border-gray-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors whitespace-nowrap cursor-pointer">
                                            🧘 Active Recovery
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ __('Daftar Judul Tugas (1 Baris = 1 Tugas)') }} <span class="text-red-500">*</span>
                                        </label>
                                        <span class="text-[10px] text-gray-400 font-medium">{{ __('Bisa diedit manual atau digenerate AI di atas') }}</span>
                                    </div>
                                    <textarea wire:model="bulkTasksInput" rows="5" class="block w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all placeholder:text-gray-400 font-mono leading-relaxed" placeholder="1. Riset referensi bab 1&#10;2. Ringkas poin penting&#10;3. Latihan mandiri 30 menit&#10;4. Review dan evaluasi" autofocus></textarea>
                                    @error('bulkTasksInput') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                            {{ __('Jadwal Tanggal') }} <span class="text-red-500">*</span>
                                        </label>
                                        <select wire:model="bulkTaskDateOption" class="block w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                            <option value="same_day">{{ __('Semua di Hari yang Sama') }}</option>
                                            <option value="sequential">{{ __('Berurutan (+1 Hari Tiap Baris)') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                            {{ __('Tanggal Mulai') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" wire:model="bulkTaskDate" class="block w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                    </div>
                                </div>
                                @error('bulkTaskDate') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                            {{ __('Prioritas') }}
                                        </label>
                                        <select wire:model="bulkTaskPriority" class="block w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                            <option value="normal">{{ __('Normal') }}</option>
                                            <option value="medium">{{ __('Sedang (Medium)') }}</option>
                                            <option value="high">{{ __('Tinggi (High)') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                            {{ __('Estimasi / Tugas (Menit)') }}
                                        </label>
                                        <input type="number" wire:model="bulkTaskEstimatedMinutes" min="5" max="480" step="5" class="block w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- Single Task Mode Form -->
                            <div class="space-y-4">
                                <!-- Task Title -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                        {{ __('Judul Tugas') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" wire:model="taskTitle" class="block w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all" placeholder="{{ __('Misal: Riset referensi bab 1') }}" autofocus>
                                    @error('taskTitle') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <!-- Task Description -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                        {{ __('Deskripsi / Catatan (Opsional)') }}
                                    </label>
                                    <textarea wire:model="taskDescription" rows="2" class="block w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all" placeholder="{{ __('Tulis detail atau instruksi tugas...') }}"></textarea>
                                    @error('taskDescription') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <!-- Scheduled Date (Dynamic Day Selection) -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                        {{ __('Jadwalkan Hari / Tanggal') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" wire:model="taskDate" class="block w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">
                                        {{ __('Pilih bebas hari/tanggal kapan tugas ini ingin kamu kerjakan.') }}
                                    </p>
                                    @error('taskDate') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <!-- Time & Deadline -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                            {{ __('Jam Mulai') }}
                                        </label>
                                        <input type="time" wire:model="taskStartTime" class="block w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                            {{ __('Deadline') }}
                                        </label>
                                        <input type="time" wire:model="taskDeadline" class="block w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                    </div>
                                </div>

                                <!-- Priority & Estimated Duration -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                            {{ __('Prioritas') }}
                                        </label>
                                        <select wire:model="taskPriority" class="block w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                            <option value="normal">{{ __('Normal') }}</option>
                                            <option value="medium">{{ __('Sedang (Medium)') }}</option>
                                            <option value="high">{{ __('Tinggi (High)') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                                            {{ __('Estimasi (Menit)') }}
                                        </label>
                                        <input type="number" wire:model="taskEstimatedMinutes" min="5" max="480" step="5" class="block w-full px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-sm font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all">
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Modal Actions -->
                        <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                            <button type="button" wire:click="closeTaskModal" class="px-4 py-2.5 rounded-xl font-bold text-xs text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                                {{ __('Batal') }}
                            </button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl font-bold text-xs bg-atoma-blue hover:bg-[#3455dc] text-white shadow-md shadow-atoma-blue/25 transition-all cursor-pointer inline-flex items-center gap-1.5">
                                @if($editingTaskId)
                                    <span>{{ __('Simpan Perubahan') }}</span>
                                @elseif($taskModalTab === 'bulk')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    <span>{{ __('Tambah Semua Tugas Sekaligus') }}</span>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    <span>{{ __('Tambahkan ke Milestone') }}</span>
                                @endif
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- AI Coach Chat Modal (Curhat & Konsultasi) -->
    @if($showCoachChatModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeCoachChat"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-xl overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10 flex flex-col max-h-[85vh]">
                <!-- Modal Header -->
                <div class="p-5 sm:p-6 bg-gradient-to-r from-indigo-600 via-atoma-blue to-[#284fd6] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white shadow-xs">
                            <x-icon.bot class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-black text-white leading-tight flex items-center gap-2">
                                <span>{{ __('AI Coach & Konsultasi Milestone') }}</span>
                            </h3>
                            <p class="text-xs text-white/80 font-medium">
                                {{ $skill->title }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeCoachChat" class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Chat History Window -->
                <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-3.5 bg-gray-50/50 dark:bg-gray-950/40 custom-scrollbar" id="coach-chat-container" x-data x-init="$watch('$wire.coachChatHistory', () => { setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50); })">
                    @forelse($coachChatHistory as $chat)
                        @php $isUser = ($chat['role'] ?? '') === 'user'; @endphp
                        <div class="flex {{ $isUser ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[90%] sm:max-w-[85%] rounded-2xl p-3.5 text-xs sm:text-sm {{ $isUser ? 'bg-atoma-blue text-white rounded-br-none shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-100 dark:border-gray-700/60 rounded-bl-none shadow-xs' }}">
                                <div class="font-extrabold text-[10px] mb-1 opacity-70">
                                    {{ $isUser ? __('Kamu') : __('AI Coach') }}
                                </div>
                                <div class="leading-relaxed whitespace-pre-wrap">{{ $chat['text'] ?? ($chat['content'] ?? '') }}</div>

                                @if(!$isUser && !empty($chat['adjustment']))
                                    @php
                                        $adj = $chat['adjustment'];
                                        $isApplied = !empty($adj['applied']);
                                        $isDismissed = !empty($adj['dismissed']);
                                        $changes = $this->computeScheduleChanges($adj);
                                        $prevTrainingDays = !empty($this->skill->training_days) ? (array)$this->skill->training_days : ['1', '2', '3', '4', '5', '6', '7'];
                                        $newTrainingDays = !empty($adj['training_days']) ? (array)$adj['training_days'] : $prevTrainingDays;
                                        $newCount = count($newTrainingDays);
                                    @endphp
                                    <div class="mt-3 p-4 sm:p-5 bg-gradient-to-br from-indigo-50/90 via-white to-atoma-blue/10 dark:from-indigo-950/60 dark:via-gray-900 dark:to-gray-850 rounded-2xl border-2 border-indigo-200/80 dark:border-indigo-800/80 text-left space-y-3.5 shadow-sm">
                                        <!-- Header & Status Badge -->
                                        <div class="flex items-start justify-between gap-2 flex-wrap border-b border-indigo-100 dark:border-indigo-900/60 pb-3">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <x-icon.sparkles class="w-4 h-4 text-atoma-blue dark:text-atoma-cyan" />
                                                    <h4 class="text-sm sm:text-base font-black text-gray-900 dark:text-white">
                                                        {{ __('Preview Penyesuaian Jadwal & Roadmap') }}
                                                    </h4>
                                                </div>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {{ __('Review rekomendasi adaptasi rutinitas & kartu target dari AI Coach') }}
                                                </p>
                                            </div>
                                            <div>
                                                @if($isApplied)
                                                    <span class="text-[10px] font-black px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 flex items-center gap-1 shadow-xs">
                                                        <x-icon.check class="w-3.5 h-3.5" />
                                                        <span>{{ __('Telah Diterapkan ke Milestone') }}</span>
                                                    </span>
                                                @elseif($isDismissed)
                                                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                                        {{ __('Diabaikan') }}
                                                    </span>
                                                @else
                                                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-900/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 flex items-center gap-1 animate-pulse">
                                                        <x-icon.sparkles class="w-3 h-3 text-indigo-600" />
                                                        <span>{{ __('Menunggu Persetujuan Anda') }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Quick Meta Parameter Pills (mirip modal create milestone) -->
                                        <div class="flex items-center gap-1.5 flex-wrap text-[11px]">
                                            <span class="px-2.5 py-1 rounded-xl bg-atoma-blue/10 dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-black border border-atoma-blue/20">
                                                🎯 Hari ke-{{ $this->skill->current_day }} dari {{ $this->skill->target_days }} Hari
                                            </span>
                                            <span class="px-2.5 py-1 rounded-xl font-bold border {{ count($prevTrainingDays) !== $newCount ? 'bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-700' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700' }}">
                                                ⚡ {{ $newCount }} Hari Latihan / Mgg
                                                @if(count($prevTrainingDays) !== $newCount)
                                                    <span class="text-[9px] uppercase font-black ml-0.5 text-amber-600 dark:text-amber-400">({{ $newCount > count($prevTrainingDays) ? '+' : '' }}{{ $newCount - count($prevTrainingDays) }})</span>
                                                @endif
                                            </span>
                                            <span class="px-2.5 py-1 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold border border-gray-200 dark:border-gray-700">
                                                📅 Mulai: {{ \Carbon\Carbon::parse($this->skill->start_date ?: $this->skill->created_at)->format('d M Y') }}
                                            </span>
                                            <span class="px-2.5 py-1 rounded-xl {{ $this->skill->mode === 'systematic' ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800' : 'bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800' }} font-bold">
                                                {{ $this->skill->mode === 'systematic' ? 'Sistematis' : 'Zen' }}
                                            </span>
                                        </div>

                                        <!-- Summary Box -->
                                        @if(!empty($adj['summary']))
                                            <div class="bg-white/80 dark:bg-gray-900/60 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900/50 shadow-2xs">
                                                <div class="text-[10px] uppercase font-black text-indigo-600 dark:text-indigo-400 tracking-wider mb-1 flex items-center gap-1">
                                                    <span>💡 Ringkasan Rekomendasi Coach</span>
                                                </div>
                                                <p class="text-xs text-gray-700 dark:text-gray-200 font-medium leading-relaxed">
                                                    {{ $adj['summary'] }}
                                                </p>
                                            </div>
                                        @endif

                                        <!-- 7-Day Routine Matrix (with DIUBAH highlights) -->
                                        @if(!empty($adj['weekly_routine']))
                                            <div class="bg-white dark:bg-gray-850 p-3 rounded-xl border border-gray-200 dark:border-gray-700/80 shadow-2xs">
                                                <div class="flex items-center justify-between gap-2 mb-2">
                                                    <span class="text-xs font-black uppercase tracking-wider text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                                                        <x-icon.calendar class="w-3.5 h-3.5 text-atoma-blue" />
                                                        <span>{{ __('Matriks Rutinitas 7 Hari') }}</span>
                                                    </span>
                                                    <span class="text-[10px] text-gray-400 font-bold">{{ __('Berulang Mingguan') }}</span>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-7 gap-1.5 text-center">
                                                    @foreach($adj['weekly_routine'] as $rDay)
                                                        @php
                                                            $iso = (int) ($rDay['iso'] ?? 0);
                                                            $isRest = ($rDay['type'] ?? '') === 'rest';
                                                            $wasTraining = in_array((string)$iso, $prevTrainingDays) || in_array($iso, $prevTrainingDays);
                                                            $isTraining = in_array((string)$iso, $newTrainingDays) || in_array($iso, $newTrainingDays);
                                                            $dayChanged = ($wasTraining !== $isTraining);
                                                        @endphp
                                                        <div class="relative p-2 rounded-xl border transition-all flex flex-col justify-between {{ $dayChanged ? 'bg-amber-50/90 dark:bg-amber-950/40 border-amber-300 dark:border-amber-700 ring-1 ring-amber-400/40 shadow-xs' : ($isRest ? 'bg-gray-50 dark:bg-gray-900/50 border-gray-200 dark:border-gray-800 text-gray-500' : 'bg-atoma-ice/60 dark:bg-atoma-blue/20 border-atoma-aqua/50 dark:border-atoma-blue/40 text-atoma-blue dark:text-atoma-cyan') }}">
                                                            @if($dayChanged)
                                                                <span class="absolute -top-2 -right-1 px-1.5 py-0.2 rounded-full text-[8px] font-black uppercase tracking-wider bg-amber-500 text-white shadow-xs">
                                                                    Diubah
                                                                </span>
                                                            @endif
                                                            <div class="text-[10px] uppercase font-black {{ $dayChanged ? 'text-amber-900 dark:text-amber-200' : 'text-gray-700 dark:text-gray-300' }}">
                                                                {{ $rDay['day_name'] }}
                                                            </div>
                                                            <div class="my-1">
                                                                <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md inline-block {{ $isRest ? 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300' : 'bg-atoma-blue text-white' }}">
                                                                    {{ $isRest ? 'Rest' : 'Workout' }}
                                                                </span>
                                                            </div>
                                                            <div class="text-[9px] line-clamp-2 leading-tight font-medium {{ $dayChanged ? 'text-amber-950 dark:text-amber-100 font-bold' : '' }}" title="{{ $rDay['label'] ?? '' }}">
                                                                {{ $rDay['label'] ?? '' }}
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Highlight Perubahan (Changelog Section) -->
                                        @if(!empty($changes))
                                            <div class="bg-amber-50/70 dark:bg-amber-950/30 p-3 rounded-xl border border-amber-200 dark:border-amber-800/60 shadow-2xs">
                                                <div class="flex items-center gap-1.5 mb-2 text-amber-900 dark:text-amber-200">
                                                    <span class="text-xs">🔄</span>
                                                    <h5 class="text-xs font-black uppercase tracking-wider">
                                                        {{ __('Daftar Perubahan Jadwal yang Dilakukan Coach:') }}
                                                    </h5>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                                    @foreach($changes as $ch)
                                                        <div class="flex items-start gap-2 bg-white/80 dark:bg-gray-900/60 p-2 rounded-lg border border-amber-200/60 dark:border-amber-900/40">
                                                            <span class="text-sm mt-0.5">{{ $ch['icon'] ?? '•' }}</span>
                                                            <div>
                                                                <span class="font-bold text-gray-900 dark:text-white">{{ $ch['title'] }}:</span>
                                                                <span class="text-gray-600 dark:text-gray-300">{{ $ch['desc'] }}</span>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Sneak Peek Toggle Button -->
                                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-indigo-100 dark:border-indigo-900/60 flex-wrap">
                                            <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300 font-bold">
                                                <x-icon.calendar class="w-4 h-4 text-atoma-blue" />
                                                <span>{{ __('Lihat dampak jadwal baru ke kartu target milestone:') }}</span>
                                            </div>
                                            <button type="button" 
                                                    wire:click="toggleCoachTimelinePreview" 
                                                    class="px-3 py-1.5 rounded-xl bg-atoma-blue/10 hover:bg-atoma-blue/20 dark:bg-atoma-blue/20 dark:hover:bg-atoma-blue/30 text-atoma-blue dark:text-atoma-cyan font-bold text-xs flex items-center gap-1.5 cursor-pointer transition-all border border-atoma-blue/30">
                                                <x-icon.sparkles class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan" />
                                                <span>{{ $showCoachTimelinePreview ? __('Tutup Intip Card') : __('🔍 Intip Card Target Milestone') }}</span>
                                            </button>
                                        </div>

                                        <!-- Sneak Peek Cards Drawer (Mirip Create Milestone) -->
                                        @if($showCoachTimelinePreview)
                                            @php
                                                $previewCards = $this->getCoachPreviewRoadmap($adj);
                                                $filteredCards = collect($previewCards)->filter(function($card) {
                                                    if ($this->coachPreviewFilter === 'all') return true;
                                                    if ($this->coachPreviewFilter === 'workout') return !$card['is_rest'];
                                                    if ($this->coachPreviewFilter === 'rest') return $card['is_rest'];
                                                    if (str_starts_with($this->coachPreviewFilter, 'w')) {
                                                        $weekNum = (int) substr($this->coachPreviewFilter, 1);
                                                        $cardWeek = (int) ceil($card['day_number'] / 7);
                                                        return $cardWeek === $weekNum;
                                                    }
                                                    return true;
                                                });
                                                $totalPreviewCount = count($previewCards);
                                                $maxPreviewWeeks = (int) ceil($totalPreviewCount / 7);
                                            @endphp
                                            <div class="bg-gray-50/95 dark:bg-gray-850 p-3.5 rounded-2xl border-2 border-atoma-blue/40 space-y-3 animate-in fade-in duration-150 shadow-inner">
                                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                                    <div>
                                                        <h5 class="font-black text-xs sm:text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                                                            <x-icon.sparkles class="w-4 h-4 text-atoma-blue" />
                                                            <span>{{ __('Preview Seluruh Kartu Target (Hari 1 - :count)', ['count' => $totalPreviewCount]) }}</span>
                                                        </h5>
                                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                            {{ __('Target dengan tanda "Diubah oleh Coach" akan otomatis diperbarui saat Anda menekan tombol "Setuju & Terapkan".') }}
                                                        </p>
                                                    </div>
                                                    <button type="button" wire:click="toggleCoachTimelinePreview" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 cursor-pointer">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>

                                                <!-- Filter Bar -->
                                                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 hide-scrollbar text-xs">
                                                    <button type="button" 
                                                            wire:click="$set('coachPreviewFilter', 'all')" 
                                                            class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $coachPreviewFilter === 'all' ? 'bg-atoma-blue text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                                                        {{ __('Semua (:count)', ['count' => $totalPreviewCount]) }}
                                                    </button>
                                                    @for($w = 1; $w <= $maxPreviewWeeks; $w++)
                                                        <button type="button" 
                                                                wire:click="$set('coachPreviewFilter', 'w{{ $w }}')" 
                                                                class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $coachPreviewFilter === 'w'.$w ? 'bg-atoma-blue text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                                                            {{ __('Minggu :w', ['w' => $w]) }}
                                                        </button>
                                                    @endfor
                                                    <button type="button" 
                                                            wire:click="$set('coachPreviewFilter', 'workout')" 
                                                            class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $coachPreviewFilter === 'workout' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                                                        {{ __('Workout') }}
                                                    </button>
                                                    <button type="button" 
                                                            wire:click="$set('coachPreviewFilter', 'rest')" 
                                                            class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $coachPreviewFilter === 'rest' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                                                        {{ __('Rest') }}
                                                    </button>
                                                </div>

                                                <!-- Cards Scroll Grid -->
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-[38vh] overflow-y-auto pr-1 custom-scrollbar">
                                                    @foreach($filteredCards as $c)
                                                        <div class="p-3 rounded-xl border transition-all {{ $c['is_changed'] ? 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-300 dark:border-amber-700 ring-1 ring-amber-400/30' : ($c['is_rest'] ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' : 'bg-white dark:bg-gray-800 border-indigo-100 dark:border-indigo-900/60') }}">
                                                            <div class="flex items-center justify-between gap-1.5 mb-1.5 flex-wrap">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="text-xs font-black text-gray-900 dark:text-white">Hari {{ $c['day_number'] }}</span>
                                                                    <span class="text-[10px] text-gray-400">({{ $c['day_name'] }})</span>
                                                                </div>
                                                                <div class="flex items-center gap-1">
                                                                    @if($c['is_changed'])
                                                                        <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-amber-500 text-white shadow-xs">
                                                                            ✨ Diubah Coach
                                                                        </span>
                                                                    @endif
                                                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-md {{ $c['is_rest'] ? 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300' : 'bg-atoma-blue text-white' }}">
                                                                        {{ $c['is_rest'] ? 'Rest' : 'Workout' }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <h6 class="text-xs font-bold text-gray-800 dark:text-gray-100 leading-snug mb-2">
                                                                {{ $c['title'] }}
                                                            </h6>
                                                            @if(!empty($c['sub_tasks']))
                                                                <div class="space-y-1 bg-gray-50/80 dark:bg-gray-900/50 p-2 rounded-lg border border-gray-100 dark:border-gray-800 text-[11px]">
                                                                    @foreach($c['sub_tasks'] as $st)
                                                                        <div class="flex items-start gap-1.5 text-gray-600 dark:text-gray-300">
                                                                            <span class="text-atoma-blue mt-0.5">✓</span>
                                                                            <span class="leading-tight">{{ $st }}</span>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Interactive Revision Box (Drawer) -->
                                        @if($showCoachRevisionBox)
                                            <div class="bg-indigo-50/90 dark:bg-indigo-950/70 p-3.5 rounded-2xl border border-indigo-200 dark:border-indigo-800 space-y-2.5 animate-in fade-in duration-150">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-xs font-black text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                                                        <x-icon.pencil class="w-3.5 h-3.5 text-indigo-600" />
                                                        <span>{{ __('Minta Revisi Jadwal ke AI Coach') }}</span>
                                                    </span>
                                                    <button type="button" wire:click="toggleCoachRevisionBox" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>
                                                <div class="flex gap-2">
                                                    <input type="text" 
                                                           wire:model="coachRevisionText" 
                                                           wire:keydown.enter="sendCoachRevision" 
                                                           placeholder="Contoh: Pindahkan hari rest ke Kamis, atau kurangi beban latihan..." 
                                                           class="flex-1 px-3 py-2 bg-white dark:bg-gray-900 border border-indigo-200 dark:border-indigo-800 rounded-xl text-xs text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue">
                                                    <button type="button" 
                                                            wire:click="sendCoachRevision" 
                                                            class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-xs transition-all cursor-pointer">
                                                        {{ __('Kirim') }}
                                                    </button>
                                                </div>
                                                <!-- Quick Revision Suggestion Pills -->
                                                <div class="flex items-center gap-1.5 flex-wrap text-[10px]">
                                                    <span class="text-gray-500 font-bold">{{ __('Saran cepat:') }}</span>
                                                    <button type="button" wire:click="sendCoachRevision(null, 'Coach, tolong pindahkan hari istirahat ke hari lain')" class="px-2 py-0.5 rounded-lg bg-white dark:bg-gray-800 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-bold hover:bg-indigo-100 transition-colors cursor-pointer">
                                                        🔄 Geser hari rest
                                                    </button>
                                                    <button type="button" wire:click="sendCoachRevision(null, 'Coach, tolong kurangi hari latihan mingguan')" class="px-2 py-0.5 rounded-lg bg-white dark:bg-gray-800 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-bold hover:bg-indigo-100 transition-colors cursor-pointer">
                                                        ⚡ Kurangi hari latihan
                                                    </button>
                                                    <button type="button" wire:click="sendCoachRevision(null, 'Coach, tolong sesuaikan jam latihan pagi dan sore')" class="px-2 py-0.5 rounded-lg bg-white dark:bg-gray-800 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-bold hover:bg-indigo-100 transition-colors cursor-pointer">
                                                        🕒 Ubah jam latihan
                                                    </button>
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Action Buttons: Setuju, Revisi, Abaikan -->
                                        @if(!$isApplied && !$isDismissed)
                                            <div class="flex items-center gap-2 pt-2 border-t border-indigo-100 dark:border-indigo-900/60 flex-wrap">
                                                <button type="button" 
                                                        wire:click="applyCoachAdjustment({{ $loop->index }})" 
                                                        wire:loading.attr="disabled"
                                                        class="flex-1 min-w-[160px] py-2.5 px-4 bg-atoma-blue hover:bg-[#3455dc] text-white font-extrabold text-xs sm:text-sm rounded-xl shadow-md shadow-atoma-blue/20 hover:shadow-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer active:scale-98">
                                                    <x-icon.check class="w-4 h-4 text-atoma-cyan" />
                                                    <span>{{ __('✨ Setuju & Terapkan Perubahan ke Milestone') }}</span>
                                                </button>
                                                <button type="button" 
                                                        wire:click="toggleCoachRevisionBox" 
                                                        class="py-2.5 px-3.5 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-750 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 font-extrabold text-xs sm:text-sm rounded-xl transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs">
                                                    <x-icon.pencil class="w-3.5 h-3.5" />
                                                    <span>{{ __('✏️ Minta Revisi') }}</span>
                                                </button>
                                                <button type="button" 
                                                        wire:click="dismissCoachAdjustment({{ $loop->index }})" 
                                                        class="py-2.5 px-3 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-500 dark:text-gray-400 font-bold text-xs rounded-xl transition-all cursor-pointer">
                                                    {{ __('Abaikan') }}
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                            <div class="w-12 h-12 mx-auto mb-2 bg-indigo-50 dark:bg-indigo-950/40 rounded-2xl flex items-center justify-center text-2xl">
                                💬
                            </div>
                            <p class="text-xs font-bold">{{ __('Mulai curhat atau minta saran tentang milestone ini.') }}</p>
                            <p class="text-[11px] mt-1">{{ __('AI Coach siap membantu mengatur ulang jadwal, motivasi, atau trik belajar.') }}</p>
                        </div>
                    @endforelse

                    <div wire:loading wire:target="sendCoachMessage" class="flex justify-start">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-3 text-xs border border-gray-100 dark:border-gray-700/60 text-gray-500 flex items-center gap-2">
                            <svg class="animate-spin h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ __('AI Coach sedang berpikir...') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Chat Input Footer -->
                <div class="p-3.5 sm:p-4 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 shrink-0">
                    <!-- Quick Suggestions -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-2 mb-2 hide-scrollbar">
                        <button type="button" wire:click="$set('coachUserMessage', 'Tolong sesuaikan jadwal latihan dan rutinitas mingguan milestone ini dengan kesibukanku')" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 whitespace-nowrap cursor-pointer transition-colors border border-indigo-200/50 dark:border-indigo-800/50">
                            {{ __('🔄 Re-adjust Jadwal & Rutinitas') }}
                        </button>
                        <button type="button" wire:click="$set('coachUserMessage', 'Target hari ini terlalu berat, ada saran alternatif?')" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 whitespace-nowrap cursor-pointer transition-colors">
                            {{ __('🎯 Target terlalu berat') }}
                        </button>
                        <button type="button" wire:click="$set('coachUserMessage', 'Aku sedang kurang motivasi, berikan tips booster konsistensi')" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 whitespace-nowrap cursor-pointer transition-colors">
                            {{ __('⚡ Butuh booster motivasi') }}
                        </button>
                        <button type="button" wire:click="$set('coachUserMessage', 'Bagaimana cara membagi waktu agar target milestone ini tidak bentrok?')" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 whitespace-nowrap cursor-pointer transition-colors">
                            {{ __('⏱️ Cara bagi waktu') }}
                        </button>
                        <button type="button" wire:click="$set('coachUserMessage', 'Tolong susunkan 5 bulk task terstruktur untuk milestone ini yang siap saya masukkan ke daftar tugas.')" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 whitespace-nowrap cursor-pointer transition-colors border border-indigo-200/50 dark:border-indigo-800/50">
                            {{ __('✨ Buatkan 5 Bulk Task Latihan') }}
                        </button>
                    </div>

                    <form wire:submit.prevent="sendCoachMessage" class="relative"
                          x-data="{
                              resize() {
                                  if ($refs.coachInput) {
                                      $refs.coachInput.style.height = 'auto';
                                      $refs.coachInput.style.height = Math.min(Math.max($refs.coachInput.scrollHeight, 48), 140) + 'px';
                                  }
                              }
                          }"
                          x-init="
                              $nextTick(() => resize());
                              $watch('$wire.coachUserMessage', value => {
                                  if (!value) {
                                      $nextTick(() => {
                                          if ($refs.coachInput) $refs.coachInput.style.height = '48px';
                                      });
                                  } else {
                                      $nextTick(() => resize());
                                  }
                              });
                          ">
                        <div class="relative flex items-end bg-gray-50 dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-inner focus-within:ring-2 focus-within:ring-atoma-blue focus-within:border-transparent transition-all">
                            <textarea 
                                x-ref="coachInput"
                                wire:model="coachUserMessage" 
                                @input="resize()"
                                @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); if ($wire.coachUserMessage.trim().length >= 2) { $wire.sendCoachMessage(); } }"
                                rows="1"
                                style="min-height: 48px; max-height: 140px;"
                                placeholder="{{ __('Ketik curhatan atau pertanyaan untuk coach... (Shift+Enter untuk baris baru)') }}" 
                                class="block w-full pl-4 pr-14 py-3 bg-transparent border-0 resize-none text-gray-900 dark:text-white font-medium focus:ring-0 focus:outline-none text-xs sm:text-sm leading-relaxed custom-scrollbar placeholder:text-gray-400 dark:placeholder:text-gray-500"
                                wire:loading.attr="disabled"
                                wire:target="sendCoachMessage"></textarea>
                            <button type="submit" 
                                    wire:loading.attr="disabled"
                                    wire:target="sendCoachMessage"
                                    class="absolute right-2 bottom-2 w-9 h-9 bg-atoma-blue hover:bg-[#3455dc] disabled:opacity-50 text-white rounded-xl flex items-center justify-center transition-all shadow-md hover:shadow-lg cursor-pointer shrink-0"
                                    title="{{ __('Kirim pesan (Enter)') }}">
                                <span wire:loading.remove wire:target="sendCoachMessage">
                                    <svg class="w-4 h-4 translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                </span>
                                <span wire:loading wire:target="sendCoachMessage">
                                    <svg class="animate-spin h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Extend Milestone Modal -->
    @if($showExtendModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeExtendModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10 animate-in fade-in zoom-in-95 duration-200 p-6 sm:p-7">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <x-icon.calendar class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900 dark:text-white">{{ __('Perpanjang Durasi Milestone') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Durasi saat ini: :days Hari', ['days' => $skill->target_days]) }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeExtendModal" class="p-1.5 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <p class="text-xs text-gray-600 dark:text-gray-300 mb-4 leading-relaxed">
                    {{ __('Pilih berapa hari tambahan yang kamu inginkan. AI Coach akan otomatis menyusun target harian dan subtask tambahan agar rencana belajarmu berlanjut mulus.') }}
                </p>

                <div class="space-y-3 mb-6">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        {{ __('Tambahan Hari') }}
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach([3, 7, 14] as $presetDay)
                            <button type="button" 
                                    wire:click="$set('extendAdditionalDays', {{ $presetDay }})" 
                                    class="py-2.5 rounded-xl text-xs font-black border transition-all cursor-pointer {{ (int)$extendAdditionalDays === $presetDay ? 'bg-atoma-blue text-white border-atoma-blue shadow-xs' : 'bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-100' }}">
                                +{{ $presetDay }} {{ __('Hari') }}
                            </button>
                        @endforeach
                    </div>

                    <div class="pt-2">
                        <input type="number" 
                               wire:model="extendAdditionalDays" 
                               min="1" 
                               max="180" 
                               class="block w-full px-3.5 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-atoma-blue focus:border-transparent transition-all" 
                               placeholder="{{ __('Atau ketik jumlah hari...') }}">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" wire:click="closeExtendModal" class="px-4 py-2.5 rounded-xl font-bold text-xs text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                        {{ __('Batal') }}
                    </button>
                    <button type="button" 
                            wire:click="extendMilestone" 
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 rounded-xl font-bold text-xs bg-atoma-blue hover:bg-[#3455dc] text-white shadow-md shadow-atoma-blue/25 transition-all cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50">
                        <span wire:loading.remove wire:target="extendMilestone">{{ __('Perpanjang Sekarang') }}</span>
                        <span wire:loading wire:target="extendMilestone" class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ __('Menyusun...') }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
