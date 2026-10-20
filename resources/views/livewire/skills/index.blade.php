<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Skill;
use App\Services\AiSkillPlannerService;

new #[Layout('layouts.app')] class extends Component {
    public $skills = [];
    public $showCreateModal = false;
    
    // Setup Form
    public $newSkillTitle = '';
    public $newSkillDays = 30;
    public $startDate = '';
    public $referenceUrl = '';
    public $aiChatUrl = '';
    public $aiSyllabusText = '';
    public $trainingDays = ['1', '2', '3', '4', '5', '6', '7'];
    public $skillMode = 'systematic'; // 'systematic' | 'zen'
    
    // Detail Modal State
    public $showDetailModal = false;
    public $selectedSkill = null;
    public $modalSelectedWeek = 1;

    // Interview State
    public $isInterviewing = false;
    public $isConfirming = false;
    public $confirmationData = [];
    public $finalPrompt = '';
    public $chatHistory = []; // {role: 'ai'|'user', text: ''}
    public $currentMessage = '';
    public $isChatLoading = false;

    // Sneak Peek Timeline & Cards State (Confirmation Step)
    public $showTimelinePreview = false;
    public $previewRoadmap = [];
    public $previewFilter = 'all'; // 'all', 'workout', 'rest', 'w1', 'w2', 'w3', ...
    public $previewEditingIndex = null;
    public $previewEditTitle = '';
    public $previewEditIsRest = false;
    public $previewEditSubtasks = [];
    public $previewNewSubtaskInput = '';

    public function openCreateModal()
    {
        if (auth()->check() && !auth()->user()->canCreateMilestone()) {
            $this->dispatch('show-toast', [
                'title' => 'Batas Akun Free: 1 Milestone Aktif',
                'message' => 'Akun Free dibatasi maksimal 1 Milestone aktif. Selesaikan target berjalan atau upgrade ke ATOMA PRO untuk membuat unlimited milestone!',
                'icon' => 'crown'
            ]);
            $this->dispatch('open-pro-modal');
            return;
        }

        $this->reset([
            'isInterviewing', 'isConfirming', 'confirmationData', 'finalPrompt',
            'chatHistory', 'currentMessage', 'newSkillTitle', 'referenceUrl', 'aiChatUrl', 'aiSyllabusText',
            'showTimelinePreview', 'previewRoadmap', 'previewEditingIndex', 'previewFilter'
        ]);
        $this->newSkillDays = 30;
        $this->startDate = now()->toDateString();
        $this->trainingDays = ['1', '2', '3', '4', '5', '6', '7'];
        $this->skillMode = 'systematic';
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
    }

    public function openDetailModal($skillId)
    {
        $this->selectedSkill = Auth::user()->skills()->with(['targets.subTasks'])->findOrFail($skillId);
        $currentDay = $this->selectedSkill->current_day ?: 1;
        $this->modalSelectedWeek = (int) ceil($currentDay / 7);
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedSkill = null;
        $this->modalSelectedWeek = 1;
    }

    public function getModalWeekCyclesProperty()
    {
        if (!$this->selectedSkill) return [];
        $targetDays = $this->selectedSkill->target_days;
        $weeks = [];
        $wNum = 1;
        
        for ($start = 1; $start <= $targetDays; $start += 7) {
            $end = min($start + 6, $targetDays);
            $weeks[$wNum] = [
                'week_number' => $wNum,
                'start_day' => $start,
                'end_day' => $end,
                'label' => "Week {$wNum} (Hari {$start} - {$end})"
            ];
            $wNum++;
        }
        
        return $weeks;
    }

    public function toggleDay($dayNum)
    {
        if (in_array((string)$dayNum, $this->trainingDays)) {
            $this->trainingDays = array_diff($this->trainingDays, [(string)$dayNum]);
        } else {
            $this->trainingDays[] = (string)$dayNum;
        }
        if ($this->isConfirming) {
            $this->syncPreviewRoadmap(app(AiSkillPlannerService::class));
        }
    }

    public function updatedNewSkillDays()
    {
        if ($this->isConfirming) {
            $this->syncPreviewRoadmap(app(AiSkillPlannerService::class));
        }
    }

    public function updatedStartDate()
    {
        if ($this->isConfirming) {
            $this->syncPreviewRoadmap(app(AiSkillPlannerService::class));
        }
    }

    public function syncPreviewRoadmap(AiSkillPlannerService $aiService)
    {
        $tempSkill = new \App\Models\Skill([
            'title' => $this->newSkillTitle,
            'prompt' => $this->finalPrompt,
            'target_days' => $this->newSkillDays,
            'start_date' => $this->startDate ?: now()->toDateString(),
            'mode' => $this->skillMode,
            'training_days' => $this->trainingDays,
            'interview_context' => [
                'chat_history' => $this->chatHistory,
                'summary' => $this->confirmationData,
                'syllabus_text' => $this->aiSyllabusText ?: null,
            ],
            'reference_url' => $this->referenceUrl ?: null,
            'ai_chat_url' => $this->aiChatUrl ?: null,
        ]);

        $this->previewRoadmap = $aiService->generateFullRoadmap($tempSkill);
    }

    public function toggleTimelinePreview(AiSkillPlannerService $aiService)
    {
        $this->showTimelinePreview = !$this->showTimelinePreview;
        if ($this->showTimelinePreview && empty($this->previewRoadmap)) {
            $this->syncPreviewRoadmap($aiService);
        }
    }

    public function editPreviewCard($index)
    {
        if (!isset($this->previewRoadmap[$index])) return;

        $this->previewEditingIndex = $index;
        $card = $this->previewRoadmap[$index];
        $this->previewEditTitle = $card['title'] ?? '';
        $this->previewEditIsRest = str_contains(strtolower($card['title'] ?? ''), 'rest & recovery');

        $subtasks = [];
        if (!empty($card['sub_tasks']) && is_array($card['sub_tasks'])) {
            foreach ($card['sub_tasks'] as $st) {
                $stTitle = is_array($st) ? ($st['title'] ?? '') : (string)$st;
                if (trim($stTitle)) $subtasks[] = trim($stTitle);
            }
        }
        $this->previewEditSubtasks = $subtasks;
        $this->previewNewSubtaskInput = '';
    }

    public function addPreviewSubtask()
    {
        $val = trim($this->previewNewSubtaskInput);
        if (strlen($val) >= 2) {
            $this->previewEditSubtasks[] = $val;
            $this->previewNewSubtaskInput = '';
        }
    }

    public function removePreviewSubtask($stIndex)
    {
        if (isset($this->previewEditSubtasks[$stIndex])) {
            unset($this->previewEditSubtasks[$stIndex]);
            $this->previewEditSubtasks = array_values($this->previewEditSubtasks);
        }
    }

    public function savePreviewCard()
    {
        if ($this->previewEditingIndex === null || !isset($this->previewRoadmap[$this->previewEditingIndex])) return;

        $idx = $this->previewEditingIndex;
        $dNum = $this->previewRoadmap[$idx]['day_number'] ?? ($idx + 1);

        $title = trim($this->previewEditTitle);
        if (empty($title)) {
            $title = $this->previewEditIsRest ? "Hari {$dNum}: Rest & Recovery" : "Hari {$dNum}: Sesi Latihan";
        }

        $this->previewRoadmap[$idx]['title'] = $title;
        $this->previewRoadmap[$idx]['is_customized'] = true;
        $this->previewRoadmap[$idx]['sub_tasks'] = array_map(fn($t) => ['title' => $t, 'is_completed' => false], $this->previewEditSubtasks);

        $this->previewEditingIndex = null;
        $this->dispatch('toast', message: "Kartu Hari Ke-{$dNum} berhasil disesuaikan!", type: 'success');
    }

    public function cancelPreviewCardEdit()
    {
        $this->previewEditingIndex = null;
    }

    public function resetPreviewRoadmap(AiSkillPlannerService $aiService)
    {
        $this->previewEditingIndex = null;
        $this->syncPreviewRoadmap($aiService);
        $this->dispatch('toast', message: 'Timeline dikembalikan ke rancangan standar AI Coach.', type: 'info');
    }

    public function mount()
    {
        $this->startDate = now()->toDateString();
        $this->loadSkills();
    }

    public function loadSkills()
    {
        $this->skills = Auth::user()->skills()->orderBy('created_at', 'desc')->get();
    }

    public function with(): array
    {
        return [
            'quotaResetTime' => \Illuminate\Support\Facades\Cache::get('gemini_quota_reset_time'),
        ];
    }

    public function createManualSkill()
    {
        if (auth()->check() && !auth()->user()->canCreateMilestone()) {
            $this->dispatch('show-toast', [
                'title' => 'Batas Akun Free: 1 Milestone Aktif',
                'message' => 'Akun Free dibatasi maksimal 1 Milestone aktif. Selesaikan target berjalan atau upgrade ke ATOMA PRO untuk membuat unlimited milestone!',
                'icon' => 'crown'
            ]);
            $this->dispatch('open-pro-modal');
            return;
        }

        $this->validate([
            'newSkillTitle' => 'required|string|min:3',
            'newSkillDays' => 'required|integer|min:1|max:365',
            'startDate' => 'required|date',
        ]);

        $skill = auth()->user()->skills()->create([
            'title' => $this->newSkillTitle,
            'prompt' => 'Manual Setup',
            'target_days' => $this->newSkillDays,
            'start_date' => $this->startDate,
            'mode' => $this->skillMode,
            'training_days' => $this->trainingDays,
            'interview_context' => [],
            'setup_status' => 'active',
            'current_day' => 1,
            'reference_url' => $this->referenceUrl ?: null,
            'ai_chat_url' => $this->aiChatUrl ?: null,
        ]);

        $firstTarget = null;
        for ($i = 1; $i <= min(7, $skill->target_days); $i++) {
            $date = \Carbon\Carbon::parse($skill->start_date)->addDays($i - 1);
            $dayOfWeekIso = (string) $date->dayOfWeekIso;
            $isGoDay = in_array($dayOfWeekIso, $skill->training_days ?? []);

            if ($isGoDay) {
                $target = $skill->targets()->create([
                    'day_number' => $i,
                    'title' => ($i === 1) ? "Hari 1: Target Pertama " . $skill->title : "Hari {$i}: Pendalaman " . $skill->title,
                    'description' => "• Tuliskan rincian latihan / materi hari ini.\n• Selesaikan 1 sesi fokus Pomodoro 25 menit.",
                    'is_ai_generated' => false,
                    'reference_url' => $skill->reference_url,
                    'ai_chat_url' => $skill->ai_chat_url,
                    'status' => 'pending'
                ]);
            } else {
                $target = $skill->targets()->create([
                    'day_number' => $i,
                    'title' => "Hari {$i}: Rest & Recovery",
                    'description' => "• Hari istirahat terjadwal (" . $date->translatedFormat('l') . ").\n• Manfaatkan hari ini untuk pemulihan fisik & mental.",
                    'is_ai_generated' => false,
                    'status' => 'pending'
                ]);
            }

            if ($i === 1) {
                $firstTarget = $target;
            }
        }

        if ($skill->mode === 'systematic' && $firstTarget && !str_contains($firstTarget->title, 'Rest & Recovery')) {
            $this->createTaskForTarget($skill, $firstTarget);
        }

        $this->reset(['showCreateModal', 'isInterviewing', 'isConfirming', 'confirmationData', 'finalPrompt', 'chatHistory', 'currentMessage', 'newSkillTitle', 'newSkillDays', 'referenceUrl', 'aiChatUrl', 'aiSyllabusText', 'trainingDays', 'skillMode']);
        $this->trainingDays = ['1', '2', '3', '4', '5', '6', '7'];
        $this->startDate = now()->toDateString();
        $this->loadSkills();
        $this->dispatch('toast', message: 'Milestone berhasil dibuat secara manual!', type: 'success');
        
        return redirect()->route('skills.show', $skill->id);
    }

    public function startSetup(AiSkillPlannerService $aiService)
    {
        if (auth()->check() && !auth()->user()->canCreateMilestone()) {
            $this->dispatch('show-toast', [
                'title' => 'Batas Akun Free: 1 Milestone Aktif',
                'message' => 'Akun Free dibatasi maksimal 1 Milestone aktif. Selesaikan target berjalan atau upgrade ke ATOMA PRO untuk membuat unlimited milestone!',
                'icon' => 'crown'
            ]);
            $this->dispatch('open-pro-modal');
            return;
        }

        \Illuminate\Support\Facades\Log::info('startSetup was triggered!', [
            'title' => $this->newSkillTitle,
            'days' => $this->newSkillDays,
            'url' => $this->referenceUrl,
            'ai_chat_url' => $this->aiChatUrl,
            'has_syllabus' => !empty($this->aiSyllabusText),
        ]);
        $this->validate([
            'newSkillTitle' => 'required|string|min:3',
            'newSkillDays' => 'required|integer|min:1|max:365',
        ]);

        $this->isInterviewing = true;
        
        // Initial AI prompt
        $response = $aiService->chatSetup(
            $this->newSkillTitle, 
            $this->newSkillDays, 
            [], 
            "Halo Coach, bantu aku menyusun rencana ini.", 
            $this->referenceUrl, 
            $this->aiChatUrl,
            $this->aiSyllabusText
        );
        
        if (isset($response['error'])) {
            $this->isInterviewing = false;
            if ($response['error'] === 'quota_exceeded') {
                $this->dispatch('toast', message: 'Token harian AI habis! Silakan coba lagi besok.', type: 'error');
            } else {
                $this->dispatch('toast', message: 'Gagal terhubung ke AI. Silakan coba lagi.', type: 'error');
            }
            return;
        }

        $this->chatHistory[] = ['role' => 'ai', 'text' => $response['reply']];
        
        if ($response['is_ready']) {
            $this->isConfirming = true;
            $this->confirmationData = $response['summary'] ?? [];
            $this->finalPrompt = $response['final_prompt'] ?? '';

            if (!empty($response['plan_parameters'])) {
                $params = $response['plan_parameters'];
                if (!empty($params['title'])) {
                    $this->newSkillTitle = $params['title'];
                }
                if (!empty($params['target_days'])) {
                    $this->newSkillDays = (int) $params['target_days'];
                }
                if (!empty($params['start_date'])) {
                    $this->startDate = $params['start_date'];
                }
                if (!empty($params['training_days'])) {
                    $this->trainingDays = array_map('strval', (array) $params['training_days']);
                }
                if (!empty($params['mode']) && in_array($params['mode'], ['systematic', 'zen'])) {
                    $this->skillMode = $params['mode'];
                }
            }

            $this->syncPreviewRoadmap($aiService);
        }
        
        \Illuminate\Support\Facades\Log::info('startSetup FINISHED successfully!', [
            'is_ready' => $response['is_ready'] ?? false,
            'reply' => $response['reply'] ?? ''
        ]);
    }

    public function sendMessage(AiSkillPlannerService $aiService)
    {
        $this->validate(['currentMessage' => 'required|string|min:2']);
        $msg = $this->currentMessage;
        $this->currentMessage = '';
        $this->isChatLoading = true;

        if (empty($this->aiSyllabusText)) {
            if (str_contains($msg, 'Matriks Mingguan') || str_contains($msg, 'Rutinitas Harian') || str_contains($msg, 'Tabel Urutan') || str_contains($msg, 'Dopamine Detox') || str_contains($msg, 'BAGIAN 1') || str_contains($msg, 'BAGIAN 2')) {
                $this->aiSyllabusText = $msg;
            }
        }

        $this->chatHistory[] = ['role' => 'user', 'text' => $msg];
        
        $response = $aiService->chatSetup(
            $this->newSkillTitle, 
            $this->newSkillDays, 
            $this->chatHistory, 
            $msg, 
            $this->referenceUrl, 
            $this->aiChatUrl,
            $this->aiSyllabusText
        );
        
        if (isset($response['error'])) {
            $this->isChatLoading = false;
            // Hapus pesan user terakhir karena gagal terkirim (opsional)
            array_pop($this->chatHistory);
            
            if ($response['error'] === 'quota_exceeded') {
                $this->dispatch('toast', message: 'Token harian AI habis! Silakan coba lagi besok.', type: 'error');
            } else {
                $this->dispatch('toast', message: 'Gagal terhubung ke AI. Silakan coba lagi.', type: 'error');
            }
            return;
        }

        $this->chatHistory[] = ['role' => 'ai', 'text' => $response['reply']];
        
        if ($response['is_ready']) {
            $this->isConfirming = true;
            $this->confirmationData = $response['summary'] ?? [];
            $this->finalPrompt = $response['final_prompt'] ?? '';

            if (!empty($response['plan_parameters'])) {
                $params = $response['plan_parameters'];
                if (!empty($params['title'])) {
                    $this->newSkillTitle = $params['title'];
                }
                if (!empty($params['target_days'])) {
                    $this->newSkillDays = (int) $params['target_days'];
                }
                if (!empty($params['start_date'])) {
                    $this->startDate = $params['start_date'];
                }
                if (!empty($params['training_days'])) {
                    $this->trainingDays = array_map('strval', (array) $params['training_days']);
                }
                if (!empty($params['mode']) && in_array($params['mode'], ['systematic', 'zen'])) {
                    $this->skillMode = $params['mode'];
                }
            }

            $this->syncPreviewRoadmap($aiService);
        }

        $this->isChatLoading = false;
    }

    public function cancelConfirm()
    {
        $this->isConfirming = false;
        // Biarkan user ngetik feedback
    }

    public function confirmSetup(AiSkillPlannerService $aiService)
    {
        if (auth()->check() && !auth()->user()->canCreateMilestone()) {
            $this->dispatch('show-toast', [
                'title' => 'Batas Akun Free: 1 Milestone Aktif',
                'message' => 'Akun Free dibatasi maksimal 1 Milestone aktif. Selesaikan target berjalan atau upgrade ke ATOMA PRO untuk membuat unlimited milestone!',
                'icon' => 'crown'
            ]);
            $this->dispatch('open-pro-modal');
            return;
        }

        $this->finalizeSkill($this->finalPrompt, $aiService);
    }

    private function finalizeSkill($prompt, AiSkillPlannerService $aiService)
    {
        @set_time_limit(120);

        $skill = auth()->user()->skills()->create([
            'title' => $this->newSkillTitle,
            'prompt' => $prompt,
            'target_days' => $this->newSkillDays,
            'start_date' => $this->startDate ?: now()->toDateString(),
            'mode' => $this->skillMode,
            'training_days' => $this->trainingDays,
            'interview_context' => [
                'chat_history' => $this->chatHistory,
                'summary' => $this->confirmationData,
                'syllabus_text' => $this->aiSyllabusText ?: null,
            ],
            'setup_status' => 'active',
            'current_day' => 1,
            'reference_url' => $this->referenceUrl ?: null,
            'ai_chat_url' => $this->aiChatUrl ?: null,
        ]);

        // Generate Full Roadmap in One-Shot, or use customized Preview Roadmap
        if (!empty($this->previewRoadmap)) {
            $roadmapTargets = $this->previewRoadmap;
        } else {
            $roadmapTargets = $aiService->generateFullRoadmap($skill);
        }
        $day1Target = null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($skill, $roadmapTargets, &$day1Target) {
            foreach ($roadmapTargets as $rt) {
                $created = $skill->targets()->create([
                    'day_number' => $rt['day_number'] ?? 1,
                    'title' => $rt['title'],
                    'description' => $rt['description'] ?? '',
                    'is_ai_generated' => $rt['is_ai_generated'] ?? true,
                    'reference_url' => $rt['reference_url'] ?? null,
                    'ai_chat_url' => $rt['ai_chat_url'] ?? null,
                    'status' => 'pending'
                ]);

                // Populate SubTasks for bulky task card support
                if (!empty($rt['sub_tasks']) && is_array($rt['sub_tasks'])) {
                    foreach ($rt['sub_tasks'] as $st) {
                        $stTitle = is_array($st) ? ($st['title'] ?? 'Tugas') : (string)$st;
                        if (trim($stTitle) !== '') {
                            $created->subTasks()->create([
                                'title' => trim($stTitle),
                                'is_completed' => false,
                            ]);
                        }
                    }
                } elseif (!empty($rt['description'])) {
                    $lines = explode("\n", $rt['description']);
                    foreach ($lines as $line) {
                        $cleanLine = trim(ltrim(trim($line), '•-*0123456789. '));
                        if (strlen($cleanLine) >= 4) {
                            $created->subTasks()->create([
                                'title' => $cleanLine,
                                'is_completed' => false,
                            ]);
                        }
                    }
                }

                if ($created->day_number === 1) {
                    $day1Target = $created;
                }
            }
        });

        if ($skill->mode === 'systematic' && $day1Target) {
            $this->createTaskForTarget($skill, $day1Target);
        }

        $this->reset([
            'showCreateModal', 'isInterviewing', 'isConfirming', 'confirmationData',
            'finalPrompt', 'chatHistory', 'currentMessage', 'newSkillTitle',
            'newSkillDays', 'referenceUrl', 'aiChatUrl', 'aiSyllabusText',
            'trainingDays', 'skillMode', 'showTimelinePreview', 'previewRoadmap',
            'previewEditingIndex', 'previewFilter'
        ]);
        $this->trainingDays = ['1', '2', '3', '4', '5', '6', '7'];
        $this->loadSkills();
        $this->dispatch('toast', message: 'Skill Milestone created!', type: 'success');
        
        $this->openDetailModal($skill->id);
    }

    private function createTaskForTarget($skill, $target)
    {
        return \App\Services\AiSkillPlannerService::createBulkTasksForTarget($skill, $target, auth()->user());
    }
    
    public function deleteSkill($id)
    {
        auth()->user()->skills()->findOrFail($id)->delete();
        $this->loadSkills();
        $this->dispatch('toast', message: 'Skill deleted', type: 'nudge');
    }
}; ?>

<div class="relative pb-24 max-w-4xl mx-auto px-4 sm:px-6">
    <div class="flex justify-between items-center gap-3 mb-6 sm:mb-8 sticky top-[60px] sm:top-[72px] bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-md z-10 py-3 sm:py-4 border-b border-transparent">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-gray-100 tracking-tight">Milestone Skills</h2>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium">Master new skills day by day</p>
        </div>
        <button wire:click="openCreateModal" class="bg-atoma-blue hover:bg-[#3455dc] text-white rounded-2xl h-10 sm:h-12 px-3.5 sm:px-6 flex items-center justify-center gap-1.5 sm:gap-2 shadow-lg shadow-atoma-blue/25 dark:shadow-none hover:shadow-xl hover:-translate-y-0.5 transition-all font-bold text-xs sm:text-sm shrink-0 cursor-pointer">
            <x-icon.sparkles class="w-4 h-4 text-white" />
            <span>New Skill</span>
        </button>
    </div>

    @if($skills->isEmpty())
        <div class="text-center py-20 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan flex items-center justify-center border border-atoma-aqua/50 dark:border-atoma-blue/40 shadow-sm">
                <x-icon.target class="w-8 h-8" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">No active skills</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">Challenge yourself and start mastering something new today.</p>
            <button wire:click="openCreateModal" class="inline-flex items-center text-atoma-blue font-bold hover:underline cursor-pointer">
                Create your first milestone &rarr;
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            @foreach($skills as $skill)
                <div class="group bg-white dark:bg-gray-800 rounded-2xl p-5 sm:p-6 shadow-sm hover:shadow-xl shadow-atoma-blue/10 dark:shadow-none border border-gray-100 dark:border-gray-700 hover:border-atoma-aqua/60 dark:hover:border-atoma-blue/40 transition-all transform hover:-translate-y-0.5 relative overflow-hidden flex flex-col h-full">
                    
                    <!-- Background glow -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-atoma-blue/10 rounded-full blur-3xl -mr-10 -mt-10 group-hover:bg-atoma-cyan/20 transition-all z-0 pointer-events-none"></div>
                    
                    <!-- Full Card Click -->
                    <button type="button" wire:click="openDetailModal({{ $skill->id }})" class="absolute inset-0 z-10 w-full h-full text-left focus:outline-none cursor-pointer"></button>
                    
                    <!-- Content -->
                    <div class="relative z-20 flex-1 flex flex-col pointer-events-none">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="flex-1 min-w-0 pr-1">
                                <h3 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white group-hover:text-atoma-blue dark:group-hover:text-atoma-cyan transition-colors truncate">{{ $skill->title }}</h3>
                            </div>
                            
                            <div class="flex items-center gap-2 shrink-0 pointer-events-auto">
                                <span class="inline-flex items-center gap-1 bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan text-[10px] font-black uppercase px-2.5 py-1 rounded-full border border-atoma-aqua/60 dark:border-atoma-blue/40">
                                    @if($skill->mode === 'zen')
                                        <x-icon.sparkles class="w-3 h-3" /> Zen
                                    @else
                                        <x-icon.target class="w-3 h-3" /> Systematic
                                    @endif
                                </span>
                                
                                <button wire:click.prevent="deleteSkill({{ $skill->id }})" wire:confirm="Are you sure you want to delete this milestone?" class="text-gray-400 hover:text-red-500 bg-gray-50 hover:bg-red-50 dark:bg-gray-700/50 dark:hover:bg-red-900/30 p-1.5 sm:p-2 rounded-xl transition-colors border border-gray-100 dark:border-gray-700 hover:border-red-100 dark:hover:border-red-800 shadow-sm flex items-center justify-center cursor-pointer" title="Delete Milestone">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                        
                        @php
                            $calcProg = $skill->calculated_progress;
                            $tSubs = $skill->total_sub_tasks_count;
                            $dSubs = $skill->completed_sub_tasks_count;
                        @endphp
                        <div class="flex items-center justify-between text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium mb-3 mt-auto">
                            <span class="bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 rounded-lg text-xs font-bold text-gray-700 dark:text-gray-300">
                                @if($tSubs > 0)
                                    {{ $dSubs }}/{{ $tSubs }} Subtask
                                @else
                                    Hari {{ $skill->current_day }} / {{ $skill->target_days }}
                                @endif
                            </span>
                            <span class="font-bold text-atoma-blue dark:text-atoma-cyan font-mono">{{ $calcProg }}% Selesai</span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5 mb-1 overflow-hidden">
                            <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan h-2.5 rounded-full transition-all duration-1000" style="width: {{ $calcProg }}%"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Create Modal -->
    @if($showCreateModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeCreateModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl w-full {{ $isConfirming ? ($showTimelinePreview ? 'max-w-4xl' : 'max-w-2xl') : 'max-w-md' }} overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10 transition-all duration-200">
                <div class="p-6 sm:p-8">
                    @if(!$isInterviewing && !$isConfirming)
                        <form wire:submit="startSetup" wire:key="setup-state">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-2xl font-black text-gray-900 dark:text-white">New Milestone</h3>
                                <button type="button" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-full p-2 transition-colors cursor-pointer">
                                    <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <div class="mt-4">
                                <div class="mb-5">
                                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Goal Title</label>
                                    <input type="text" wire:model="newSkillTitle" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="e.g. Belajar Gitar">
                                    @error('newSkillTitle') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                    <div class="grid grid-cols-2 gap-3 mb-5">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Target Days</label>
                                            <input type="number" wire:model="newSkillDays" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="30">
                                            @error('newSkillDays') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai</label>
                                            <input type="date" wire:model="startDate" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue">
                                            @error('startDate') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="mb-5">
                                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Milestone Mode</label>
                                        <div class="grid grid-cols-2 gap-3">
                                             <button type="button" wire:click="$set('skillMode', 'systematic')" 
                                                 class="p-3 rounded-2xl border text-left transition-all flex flex-col justify-between {{ $skillMode === 'systematic' ? 'border-atoma-blue bg-atoma-ice/60 dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan ring-2 ring-atoma-blue' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                                                 <div class="font-bold text-xs flex items-center gap-1.5 mb-1"><x-icon.target class="w-3.5 h-3.5 shrink-0" /> <span>Systematic</span></div>
                                                 <div class="text-[10px] opacity-75 leading-tight">Terhubung ke Task Harian & Timer Pomodoro.</div>
                                             </button>
                                             <button type="button" wire:click="$set('skillMode', 'zen')" 
                                                 class="p-3 rounded-2xl border text-left transition-all flex flex-col justify-between {{ $skillMode === 'zen' ? 'border-atoma-cyan bg-atoma-ice/40 dark:bg-atoma-cyan/20 text-atoma-blue dark:text-atoma-cyan ring-2 ring-atoma-cyan' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                                                 <div class="font-bold text-xs flex items-center gap-1.5 mb-1"><x-icon.sparkles class="w-3.5 h-3.5 shrink-0" /> <span>Zen Mode</span></div>
                                                 <div class="text-[10px] opacity-75 leading-tight">Checklist santai tanpa integrasi Task.</div>
                                             </button>
                                         </div>
                                     </div>
                                     
                                     <div class="mb-5">
                                         <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                             <svg class="w-4 h-4 mr-1 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                             YouTube Reference Link <span class="text-xs text-gray-400 font-normal ml-2">(Optional)</span>
                                         </label>
                                         <input type="url" wire:model="referenceUrl" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border-0 rounded-2xl text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-atoma-blue" placeholder="e.g. https://youtube.com/watch?v=...">
                                         @error('referenceUrl') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                     </div>

                                     <div class="mb-5" x-data="{ openAiImporter: false }">
                                         <div class="flex items-center justify-between mb-2">
                                             <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 flex items-center">
                                                 <x-icon.sparkles class="w-4 h-4 mr-1.5 text-indigo-500" />
                                                 <span>Impor Silabus AI / Catatan</span>
                                                 <span class="text-xs text-gray-400 font-normal ml-2">(Opsional)</span>
                                             </label>
                                             <button type="button" 
                                                     @click="openAiImporter = !openAiImporter" 
                                                     class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 flex items-center gap-1 cursor-pointer transition-colors">
                                                 <span x-text="openAiImporter ? '✕ Tutup Importer' : '⚡ Buka Importer AI'"></span>
                                                 <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="openAiImporter ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                             </button>
                                         </div>

                                         <!-- Smart AI Importer Collapsible Box -->
                                         <div x-show="openAiImporter" x-collapse class="space-y-3 p-3.5 bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200/80 dark:border-indigo-800/60 rounded-2xl mb-2">
                                             <div>
                                                 <div class="flex items-center justify-between mb-1.5">
                                                     <span class="text-xs font-bold text-indigo-900 dark:text-indigo-300 flex items-center gap-1.5">
                                                         <span>📋</span> Tempel Teks Silabus (ChatGPT / Gemini / Claude)
                                                     </span>
                                                     <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium">Auto-Diserap AI Coach</span>
                                                 </div>
                                                 <textarea wire:model="aiSyllabusText" 
                                                           rows="4" 
                                                           class="block w-full px-3.5 py-2.5 bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-800 rounded-xl text-xs text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none" 
                                                           placeholder="Punya jadwal/silabus dari obrolan AI lain? Copy dan paste teksnya di sini! AI Coach ATOMA akan langsung membedah dan memetakannya ke dalam roadmap harian otomatis."></textarea>
                                                 @error('aiSyllabusText') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                             </div>

                                             <div>
                                                 <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1 flex items-center justify-between">
                                                     <span>Tautan Asli Chat AI (Untuk Bookmark Pribadi)</span>
                                                     <span class="text-[10px] text-gray-400 font-normal">Opsional</span>
                                                 </label>
                                                 <input type="url" wire:model="aiChatUrl" class="block w-full px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-xs text-gray-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="e.g. https://chatgpt.com/share/... atau https://share.gemini.google/...">
                                                 @error('aiChatUrl') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <div class="mb-8">
                                         <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Training Days (Go vs Rest)</label>
                                         <div class="flex space-x-2">
                                             @foreach([1 => 'M', 2 => 'T', 3 => 'W', 4 => 'T', 5 => 'F', 6 => 'S', 7 => 'S'] as $num => $label)
                                                 <button type="button" wire:click="toggleDay('{{ $num }}')" 
                                                     class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors border {{ in_array((string)$num, $trainingDays) ? 'bg-atoma-blue border-atoma-blue text-white shadow-md shadow-atoma-blue/25' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500 hover:border-atoma-aqua' }}">
                                                     {{ $label }}
                                                 </button>
                                             @endforeach
                                         </div>
                                     </div>

                                     <div class="space-y-3 pt-2">
                                         <!-- Opsi 1: Manual Setup -->
                                         <button type="button" wire:click="createManualSkill" class="w-full py-3.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-white rounded-2xl font-bold text-base transition-all flex items-center justify-center border border-gray-200 dark:border-gray-700">
                                             <span wire:loading.remove wire:target="createManualSkill" class="inline-flex items-center gap-2">
                                                 <x-icon.pencil class="w-4 h-4 text-gray-600 dark:text-gray-300" />
                                                 <span>Manual Setup (Bebas Buat & Edit Target)</span>
                                             </span>
                                             <span wire:loading wire:target="createManualSkill">Membuat Milestone...</span>
                                         </button>

                                         <!-- Opsi 2: AI Coach Setup -->
                                         @if($quotaResetTime)
                                             @php
                                                 $resetDate = \Carbon\Carbon::createFromTimestamp($quotaResetTime);
                                                 $hoursLeft = max(1, ceil(now()->diffInMinutes($resetDate) / 60));
                                             @endphp
                                             <button type="button" @click="window.dispatchEvent(new CustomEvent('show-toast', { detail: { title: 'Token AI harian sedang habis (~' + {{ $hoursLeft }} + 'j lagi). Gunakan Manual Setup!', icon: 'warning' } }))" class="w-full py-3.5 bg-gray-100 dark:bg-gray-800/60 text-gray-400 dark:text-gray-500 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 border border-dashed border-gray-300 dark:border-gray-700 cursor-pointer hover:border-amber-400 transition-colors">
                                                 <x-icon.lock class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                                 <span>AI Coach (Token Habis ~{{ $hoursLeft }}j Lagi)</span>
                                             </button>
                                         @else
                                             <button type="submit" class="w-full py-3.5 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-2xl font-bold text-base shadow-lg shadow-atoma-blue/25 dark:shadow-none hover:shadow-xl hover:-translate-y-0.5 transition-all flex items-center justify-center">
                                                 <span wire:loading.remove wire:target="startSetup" class="inline-flex items-center gap-2">
                                                     <x-icon.sparkles class="w-4 h-4 text-white" />
                                                     <span>AI Coach Setup (Otomatis)</span>
                                                 </span>
                                                 <span wire:loading wire:target="startSetup">Connecting to Coach...</span>
                                             </button>
                                         @endif
                                     </div>
                                 </form>
                             </div>
                         @elseif($isConfirming)
                             <div wire:key="confirm-state" x-data="{ showParamEdit: false }">
                                 <div class="flex justify-between items-center mb-4 border-b border-gray-100 dark:border-gray-800 pb-3">
                                     <div>
                                         <h3 class="text-xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                                             <x-icon.sparkles class="w-5 h-5 text-atoma-blue" />
                                             <span>Konfirmasi Rencana Milestone</span>
                                         </h3>
                                         <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Review strategi komprehensif dari AI Coach sebelum memulai</p>
                                     </div>
                                     <button wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer p-1">
                                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                     </button>
                                 </div>

                                 <!-- Parameter Quick Bar & Inline Adjuster -->
                                 <div class="mb-4 bg-gray-50 dark:bg-gray-800/80 rounded-2xl p-3 border border-gray-200/80 dark:border-gray-700">
                                     <div class="flex items-center justify-between gap-2 flex-wrap">
                                         <div class="flex items-center gap-1.5 flex-wrap text-xs">
                                             <span class="px-2.5 py-1 rounded-xl bg-atoma-blue/10 dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan font-black border border-atoma-blue/20">
                                                 🎯 {{ $newSkillDays }} Hari
                                             </span>
                                             <span class="px-2.5 py-1 rounded-xl bg-gray-200/70 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold">
                                                 📅 Mulai: {{ \Carbon\Carbon::parse($startDate ?: now())->format('d M Y') }}
                                             </span>
                                             <span class="px-2.5 py-1 rounded-xl bg-gray-200/70 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold">
                                                 ⚡ {{ count($trainingDays) }} Hari Latihan / Mgg
                                             </span>
                                             <span class="px-2.5 py-1 rounded-xl {{ $skillMode === 'systematic' ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800' : 'bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800' }} font-bold">
                                                 {{ $skillMode === 'systematic' ? 'Sistematis' : 'Zen' }}
                                             </span>
                                         </div>
                                         <button type="button" @click="showParamEdit = !showParamEdit" class="text-xs font-bold text-atoma-blue dark:text-atoma-cyan hover:underline flex items-center gap-1 cursor-pointer">
                                             <x-icon.pencil class="w-3.5 h-3.5" />
                                             <span x-text="showParamEdit ? 'Tutup Edit' : 'Sesuaikan Parameter'"></span>
                                         </button>
                                     </div>

                                     <!-- Parameter Edit Drawer -->
                                     <div x-show="showParamEdit" x-collapse class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs space-y-3" style="display: none;">
                                         <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                             <div>
                                                 <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Target Durasi (Hari)</label>
                                                 <input type="number" wire:model.live="newSkillDays" min="1" max="365" class="w-full px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-atoma-blue">
                                             </div>
                                             <div>
                                                 <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai</label>
                                                 <input type="date" wire:model.live="startDate" class="w-full px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-atoma-blue">
                                             </div>
                                             <div>
                                                 <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Mode Eksekusi</label>
                                                 <select wire:model.live="skillMode" class="w-full px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-atoma-blue">
                                                     <option value="systematic">Sistematis (Task + Pomodoro)</option>
                                                     <option value="zen">Zen (Checklist Santai)</option>
                                                 </select>
                                             </div>
                                         </div>
                                         <div>
                                             <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1.5">Pilih Hari Latihan Aktif (Klik untuk toggle On/Off):</label>
                                             <div class="flex items-center gap-1.5 flex-wrap">
                                                 @foreach([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'] as $num => $dayName)
                                                     <button type="button" wire:click="toggleDay('{{ $num }}')" class="px-2.5 py-1.5 rounded-xl font-bold text-xs transition-all border {{ in_array((string)$num, $trainingDays) ? 'bg-atoma-blue text-white border-atoma-blue shadow-xs' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-400' }}">
                                                         {{ $dayName }}
                                                     </button>
                                                 @endforeach
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <!-- Scrollable Rich Plan Cards -->
                                 <div class="space-y-4 mb-5 max-h-[58vh] overflow-y-auto pr-1 custom-scrollbar text-sm">
                                     <!-- Card 1: Hero Meta Card -->
                                     <div class="bg-gradient-to-br from-atoma-blue/10 via-white to-atoma-cyan/10 dark:from-atoma-blue/20 dark:via-gray-850 dark:to-gray-900 rounded-2xl p-4 sm:p-5 border border-atoma-aqua/50 dark:border-atoma-blue/40 shadow-xs">
                                         <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                             <div>
                                                 <span class="text-[10px] uppercase font-black tracking-wider text-atoma-blue dark:text-atoma-cyan">Milestone Curriculum</span>
                                                 <h4 class="text-base sm:text-lg font-black text-gray-900 dark:text-white leading-tight mt-0.5">{{ $newSkillTitle }}</h4>
                                             </div>
                                             <div class="text-right">
                                                 <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-black bg-atoma-blue text-white shadow-xs">
                                                     {{ $newSkillDays }} Hari • ~{{ ceil($newSkillDays / 7) }} Minggu
                                                 </span>
                                             </div>
                                         </div>
                                         
                                         @php
                                             $parsedStart = \Carbon\Carbon::parse($startDate ?: now());
                                             $parsedEnd = (clone $parsedStart)->addDays(max(0, $newSkillDays - 1));
                                         @endphp
                                         <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3 pt-3 border-t border-atoma-aqua/30 dark:border-gray-700/60 text-xs">
                                             <div class="text-gray-600 dark:text-gray-300">
                                                 <span class="font-bold text-gray-800 dark:text-gray-200">Rentang Kalender:</span><br>
                                                 {{ $parsedStart->translatedFormat('d M Y') }} ➔ {{ $parsedEnd->translatedFormat('d M Y') }}
                                             </div>
                                             <div class="text-gray-600 dark:text-gray-300">
                                                 <span class="font-bold text-gray-800 dark:text-gray-200">Rasio Ritme Mingguan:</span><br>
                                                 {{ count($trainingDays) }} Hari Aktif : {{ max(0, 7 - count($trainingDays)) }} Hari Rest & Recovery
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Card 2: Rutinitas Mingguan (Interactive 7-Day Grid) -->
                                     <div class="bg-white dark:bg-gray-800/90 rounded-2xl p-4 border border-gray-200 dark:border-gray-700/80 shadow-xs">
                                         <div class="flex items-center justify-between gap-2 mb-2.5">
                                             <h4 class="font-black text-xs sm:text-sm text-gray-900 dark:text-white flex items-center gap-1.5 uppercase tracking-wider">
                                                 <x-icon.calendar class="w-4 h-4 text-atoma-blue" />
                                                 <span>Jadwal & Rutinitas Mingguan</span>
                                             </h4>
                                             <span class="text-[11px] font-bold text-gray-400">7 Hari Berulang</span>
                                         </div>
                                         @if(!empty($confirmationData['training_schedule']))
                                             <p class="text-xs text-gray-600 dark:text-gray-300 mb-3 bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-xl border border-gray-100 dark:border-gray-800 leading-relaxed">
                                                 {{ $confirmationData['training_schedule'] }}
                                             </p>
                                         @endif

                                         <!-- 7-Day Grid Pills -->
                                         @php
                                             $weeklyRoutine = $confirmationData['weekly_routine'] ?? [];
                                             if (empty($weeklyRoutine)) {
                                                 $dayMap = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
                                                 $weeklyRoutine = [];
                                                 foreach ($dayMap as $iso => $name) {
                                                     $isGo = in_array((string)$iso, $trainingDays) || in_array($iso, $trainingDays);
                                                     $weeklyRoutine[] = [
                                                         'day_name' => $name,
                                                         'iso' => $iso,
                                                         'type' => $isGo ? 'workout' : 'rest',
                                                         'label' => $isGo ? 'Sesi Latihan / Fokus' : 'Rest & Recovery'
                                                     ];
                                                 }
                                             }
                                         @endphp
                                         <div class="grid grid-cols-2 sm:grid-cols-7 gap-2">
                                             @foreach($weeklyRoutine as $routineDay)
                                                 @php
                                                     $isRest = ($routineDay['type'] ?? '') === 'rest';
                                                     $isActiveRecovery = ($routineDay['type'] ?? '') === 'active_recovery';
                                                 @endphp
                                                 <div class="p-2 rounded-xl border text-center flex flex-col justify-between {{ $isRest ? 'bg-gray-50 dark:bg-gray-900/40 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' : ($isActiveRecovery ? 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300' : 'bg-atoma-ice/60 dark:bg-atoma-blue/20 border-atoma-aqua/50 dark:border-atoma-blue/40 text-atoma-blue dark:text-atoma-cyan') }}">
                                                     <span class="text-[11px] font-black uppercase">{{ $routineDay['day_name'] }}</span>
                                                     <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-md my-1 inline-block {{ $isRest ? 'bg-gray-200/80 dark:bg-gray-800 text-gray-700 dark:text-gray-300' : ($isActiveRecovery ? 'bg-amber-200 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200' : 'bg-atoma-blue text-white') }}">
                                                         {{ $isRest ? 'Rest' : ($isActiveRecovery ? 'Recovery' : 'Workout') }}
                                                     </span>
                                                     <span class="text-[10px] font-medium leading-tight line-clamp-2" title="{{ $routineDay['label'] ?? '' }}">{{ $routineDay['label'] ?? '' }}</span>
                                                 </div>
                                             @endforeach
                                         </div>

                                        <!-- Sneak Peek Timeline & Cards Bar -->
                                        <div class="mt-3.5 pt-3 border-t border-gray-100 dark:border-gray-700/80 flex items-center justify-between gap-2 flex-wrap">
                                            <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300 font-bold">
                                                <x-icon.calendar class="w-4 h-4 text-atoma-blue dark:text-atoma-cyan" />
                                                <span>{{ __('Cek kartu harian & sesuaikan jika ada yang miss:') }}</span>
                                            </div>
                                            <button type="button" 
                                                    wire:click="toggleTimelinePreview" 
                                                    class="px-3.5 py-1.5 rounded-xl bg-atoma-blue hover:bg-[#3455dc] text-white font-extrabold text-xs flex items-center gap-1.5 cursor-pointer transition-all shadow-xs active:scale-95">
                                                <x-icon.sparkles class="w-3.5 h-3.5 text-atoma-cyan" />
                                                <span>{{ $showTimelinePreview ? __('Tutup Intip Timeline') : __('🔍 Intip Timeline & Card (:count Hari)', ['count' => count($previewRoadmap) ?: $newSkillDays]) }}</span>
                                            </button>
                                        </div>
                                     </div>

                                     @if($showTimelinePreview)
                                         <!-- Timeline & Card Customizer Preview Card -->
                                         <div class="bg-gray-50/90 dark:bg-gray-850 rounded-2xl p-4 sm:p-5 border-2 border-atoma-blue/40 shadow-sm space-y-4 animate-in fade-in zoom-in-98 duration-150">
                                             <div class="flex items-center justify-between gap-2 flex-wrap">
                                                 <div>
                                                     <h4 class="font-black text-sm text-gray-900 dark:text-white flex items-center gap-2">
                                                         <x-icon.sparkles class="w-4 h-4 text-atoma-blue" />
                                                         <span>{{ __('Intip & Sesuaikan Kartu Target (Hari 1 - :count)', ['count' => count($previewRoadmap) ?: $newSkillDays]) }}</span>
                                                     </h4>
                                                     <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                         {{ __('Klik "✏️ Adjust Card" pada hari mana pun jika ingin menyesuaikan judul, materi, atau daftar tugas sebelum milestone dimulai.') }}
                                                     </p>
                                                 </div>
                                                 <div class="flex items-center gap-2">
                                                     <button type="button" 
                                                             wire:click="resetPreviewRoadmap" 
                                                             class="text-[10px] font-bold px-2.5 py-1 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-650 text-gray-700 dark:text-gray-300 transition-colors cursor-pointer">
                                                         {{ __('🔄 Reset Standar AI') }}
                                                     </button>
                                                     <button type="button" 
                                                             wire:click="toggleTimelinePreview" 
                                                             class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 cursor-pointer">
                                                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                     </button>
                                                 </div>
                                             </div>

                                             <!-- Filter Bar -->
                                             <div class="flex items-center gap-1.5 overflow-x-auto pb-1 hide-scrollbar text-xs">
                                                 <button type="button" 
                                                         wire:click="$set('previewFilter', 'all')" 
                                                         class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $previewFilter === 'all' ? 'bg-atoma-blue text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                                                     {{ __('Semua (:count)', ['count' => count($previewRoadmap)]) }}
                                                 </button>
                                                 @php $maxWeeks = (int) ceil((count($previewRoadmap) ?: $newSkillDays) / 7); @endphp
                                                 @for($w = 1; $w <= $maxWeeks; $w++)
                                                     <button type="button" 
                                                             wire:click="$set('previewFilter', 'w{{ $w }}')" 
                                                             class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $previewFilter === 'w'.$w ? 'bg-atoma-blue text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                                                         {{ __('Minggu :w', ['w' => $w]) }}
                                                     </button>
                                                 @endfor
                                                 <button type="button" 
                                                         wire:click="$set('previewFilter', 'workout')" 
                                                         class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $previewFilter === 'workout' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                                                     {{ __('Khusus Workout') }}
                                                 </button>
                                                 <button type="button" 
                                                         wire:click="$set('previewFilter', 'rest')" 
                                                         class="px-2.5 py-1 rounded-lg font-bold transition-all cursor-pointer {{ $previewFilter === 'rest' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                                                     {{ __('Khusus Rest') }}
                                                 </button>
                                             </div>

                                             <!-- Preview Cards Grid -->
                                             <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[46vh] overflow-y-auto pr-1 custom-scrollbar">
                                                 @php
                                                     $baseStart = \Carbon\Carbon::parse($startDate ?: now());
                                                 @endphp
                                                 @foreach($previewRoadmap as $idx => $target)
                                                     @php
                                                         $dNum = $target['day_number'] ?? ($idx + 1);
                                                         $targetDate = (clone $baseStart)->addDays($dNum - 1);
                                                         $isRest = str_contains(strtolower($target['title'] ?? ''), 'rest & recovery');
                                                         $weekNum = ceil($dNum / 7);

                                                         if ($previewFilter === 'workout' && $isRest) continue;
                                                         if ($previewFilter === 'rest' && !$isRest) continue;
                                                         if (str_starts_with($previewFilter, 'w') && $previewFilter !== 'workout' && $previewFilter !== 'all') {
                                                             $fWeek = (int) substr($previewFilter, 1);
                                                             if ($weekNum !== $fWeek) continue;
                                                         }
                                                         $isEditing = $previewEditingIndex === $idx;
                                                     @endphp
                                                     <div class="bg-white dark:bg-gray-800 rounded-xl p-3.5 border transition-all {{ $isEditing ? 'border-2 border-atoma-blue ring-2 ring-atoma-blue/20 shadow-md' : 'border-gray-200 dark:border-gray-700/80 hover:border-atoma-blue/40 shadow-xs' }}">
                                                         @if($isEditing)
                                                             <!-- Inline Edit Form -->
                                                             <div class="space-y-3">
                                                                 <div class="flex items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-700 pb-2">
                                                                     <span class="text-xs font-black text-atoma-blue dark:text-atoma-cyan">
                                                                         {{ __('Edit Kartu Hari Ke-:d', ['d' => $dNum]) }}
                                                                     </span>
                                                                     <span class="text-[10px] text-gray-400">{{ $targetDate->translatedFormat('d M Y') }}</span>
                                                                 </div>

                                                                 <div>
                                                                     <label class="block text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Judul Kartu') }}</label>
                                                                     <input type="text" 
                                                                            wire:model="previewEditTitle" 
                                                                            class="w-full px-3 py-1.5 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue">
                                                                 </div>

                                                                 <div class="flex items-center gap-2">
                                                                     <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-gray-700 dark:text-gray-300">
                                                                         <input type="checkbox" wire:model="previewEditIsRest" class="rounded border-gray-300 text-atoma-blue focus:ring-atoma-blue">
                                                                         <span>{{ __('Jadikan Rest & Recovery') }}</span>
                                                                     </label>
                                                                 </div>

                                                                 <div>
                                                                     <label class="block text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">{{ __('Daftar Subtask / Tugas:') }}</label>
                                                                     <div class="space-y-1.5 mb-2">
                                                                         @forelse($previewEditSubtasks as $stIdx => $stItem)
                                                                             <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-gray-50 dark:bg-gray-900 text-xs border border-gray-100 dark:border-gray-700">
                                                                                 <span class="text-gray-800 dark:text-gray-200 font-medium truncate">• {{ $stItem }}</span>
                                                                                 <button type="button" wire:click="removePreviewSubtask({{ $stIdx }})" class="text-red-500 hover:text-red-700 text-xs font-black px-1.5 cursor-pointer">×</button>
                                                                             </div>
                                                                         @empty
                                                                             <p class="text-[10px] text-gray-400 italic">{{ __('Belum ada subtask untuk kartu ini.') }}</p>
                                                                         @endforelse
                                                                     </div>

                                                                     <div class="flex items-center gap-1.5">
                                                                         <input type="text" 
                                                                                wire:model="previewNewSubtaskInput" 
                                                                                wire:keydown.enter.prevent="addPreviewSubtask"
                                                                                placeholder="{{ __('Tambah subtask baru...') }}" 
                                                                                class="flex-1 px-2.5 py-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-xs font-medium focus:ring-1 focus:ring-atoma-blue text-gray-900 dark:text-white">
                                                                         <button type="button" 
                                                                                 wire:click="addPreviewSubtask" 
                                                                                 class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg font-bold text-xs cursor-pointer">
                                                                             + {{ __('Tambah') }}
                                                                         </button>
                                                                     </div>
                                                                 </div>

                                                                 <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                                                                     <button type="button" 
                                                                             wire:click="cancelPreviewCardEdit" 
                                                                             class="px-3 py-1.5 rounded-lg text-xs font-bold text-gray-500 hover:text-gray-700 dark:text-gray-400 cursor-pointer">
                                                                         {{ __('Batal') }}
                                                                     </button>
                                                                     <button type="button" 
                                                                             wire:click="savePreviewCard" 
                                                                             class="px-3.5 py-1.5 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-lg text-xs font-bold shadow-xs cursor-pointer">
                                                                         {{ __('Simpan Perubahan') }}
                                                                     </button>
                                                                 </div>
                                                             </div>
                                                         @else
                                                             <!-- Card View -->
                                                             <div class="flex items-center justify-between gap-1.5 mb-1.5">
                                                                 <div class="flex items-center gap-1.5">
                                                                     <span class="px-2 py-0.5 rounded-md font-black text-[10px] bg-atoma-blue/10 dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan border border-atoma-blue/20">
                                                                         H-{{ $dNum }}
                                                                     </span>
                                                                     <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400">
                                                                         {{ $targetDate->translatedFormat('D, d M') }}
                                                                     </span>
                                                                 </div>
                                                                 <div class="flex items-center gap-1">
                                                                     @if(!empty($target['is_customized']))
                                                                         <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/70 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                                                             {{ __('✏️ Disesuaikan') }}
                                                                         </span>
                                                                     @endif
                                                                     <span class="text-[9px] font-bold px-1.5 py-0.5 rounded {{ $isRest ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' : 'bg-atoma-blue text-white' }}">
                                                                         {{ $isRest ? 'Rest' : 'Workout' }}
                                                                     </span>
                                                                 </div>
                                                             </div>

                                                             <h5 class="text-xs font-bold text-gray-900 dark:text-white leading-snug mb-1.5">
                                                                 {{ $target['title'] ?? "Hari {$dNum}" }}
                                                             </h5>

                                                             <!-- Subtasks pills -->
                                                             @if(!empty($target['sub_tasks']) && is_array($target['sub_tasks']))
                                                                 <div class="space-y-1 mb-2.5">
                                                                     @foreach(array_slice($target['sub_tasks'], 0, 3) as $st)
                                                                         @php $stTitle = is_array($st) ? ($st['title'] ?? '') : (string)$st; @endphp
                                                                         <div class="text-[10px] text-gray-600 dark:text-gray-400 font-medium flex items-center gap-1">
                                                                             <span class="text-atoma-blue dark:text-atoma-cyan text-[8px]">▪</span>
                                                                             <span class="truncate">{{ $stTitle }}</span>
                                                                         </div>
                                                                     @endforeach
                                                                     @if(count($target['sub_tasks']) > 3)
                                                                         <span class="text-[9px] font-bold text-gray-400 block">+ {{ count($target['sub_tasks']) - 3 }} {{ __('tugas lainnya...') }}</span>
                                                                     @endif
                                                                 </div>
                                                             @endif

                                                             <div class="pt-1.5 border-t border-gray-100 dark:border-gray-750 flex items-center justify-end">
                                                                 <button type="button" 
                                                                         wire:click="editPreviewCard({{ $idx }})" 
                                                                         class="text-[10px] font-bold text-atoma-blue dark:text-atoma-cyan hover:underline flex items-center gap-1 cursor-pointer">
                                                                     <x-icon.pencil class="w-3 h-3" />
                                                                     <span>{{ __('Adjust Card') }}</span>
                                                                 </button>
                                                             </div>
                                                         @endif
                                                     </div>
                                                 @endforeach
                                             </div>
                                         </div>
                                     @endif

                                     <!-- Card 3: Roadmap 4 Fase Kurikulum -->
                                     @php
                                         $phases = $confirmationData['phases'] ?? [];
                                         if (empty($phases)) {
                                             $p1 = max(1, (int) round($newSkillDays * 0.25));
                                             $p2 = max($p1 + 1, (int) round($newSkillDays * 0.50));
                                             $p3 = max($p2 + 1, (int) round($newSkillDays * 0.75));
                                             $phases = [
                                                 ['phase' => 1, 'name' => 'Fondasi & Adaptasi', 'days' => "Hari 1 - {$p1}", 'focus' => 'Pembiasaan ritme awal & penyesuaian mobilitas fisik/akademik'],
                                                 ['phase' => 2, 'name' => 'Pembentukan Teknik & Konsistensi', 'days' => "Hari " . ($p1+1) . " - {$p2}", 'focus' => 'Peningkatan form, repetisi terpandu, dan disiplin jadwal harian'],
                                                 ['phase' => 3, 'name' => 'Latihan Terapan & Intensitas', 'days' => "Hari " . ($p2+1) . " - {$p3}", 'focus' => 'Penguatan kapasitas endurance & variasi tantangan beban'],
                                                 ['phase' => 4, 'name' => 'Uji Penguasaan & Kebiasaan Permanen', 'days' => "Hari " . ($p3+1) . " - {$newSkillDays}", 'focus' => 'Evaluasi hasil, rekor pribadi (PR), dan sertifikat penguasaan'],
                                             ];
                                         }
                                     @endphp
                                     <div class="bg-white dark:bg-gray-800/90 rounded-2xl p-4 border border-gray-200 dark:border-gray-700/80 shadow-xs">
                                         <div class="flex items-center justify-between gap-2 mb-3">
                                             <h4 class="font-black text-xs sm:text-sm text-gray-900 dark:text-white flex items-center gap-1.5 uppercase tracking-wider">
                                                 <x-icon.target class="w-4 h-4 text-atoma-blue" />
                                                 <span>Tahapan Kurikulum Roadmap (4 Fase)</span>
                                             </h4>
                                             <span class="text-[11px] font-bold text-atoma-blue dark:text-atoma-cyan">Tahapan Terstruktur</span>
                                         </div>
                                         <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                             @foreach($phases as $idx => $phase)
                                                 <div class="p-3 rounded-xl border border-gray-100 dark:border-gray-700/60 bg-gray-50/70 dark:bg-gray-900/40">
                                                     <div class="flex items-center justify-between gap-2 mb-1">
                                                         <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-atoma-blue/10 dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan">
                                                             Fase {{ $phase['phase'] ?? ($idx + 1) }}: {{ $phase['days'] ?? '' }}
                                                         </span>
                                                     </div>
                                                     <h5 class="text-xs font-bold text-gray-900 dark:text-white">{{ $phase['name'] }}</h5>
                                                     <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-0.5 leading-snug">{{ $phase['focus'] }}</p>
                                                 </div>
                                             @endforeach
                                         </div>
                                     </div>

                                     <!-- Card 4: Overview Strategi Dinamis -->
                                     <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-4 border border-gray-200 dark:border-gray-700">
                                         <h4 class="font-bold text-gray-900 dark:text-white mb-1.5 flex items-center text-xs sm:text-sm">
                                             <svg class="w-4 h-4 mr-1.5 text-atoma-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                             Overview Strategi {{ $newSkillDays }} Hari
                                         </h4>
                                         <p class="text-xs text-gray-700 dark:text-gray-300 italic leading-relaxed">
                                             "{{ $confirmationData['overview'] ?? 'Rencana komprehensif berhasil dirancang untuk memandu pencapaian targetmu.' }}"
                                         </p>
                                     </div>

                                     <!-- Card 5: Sumber Referensi & Integrasi Sistem ATOMA -->
                                     <div class="bg-white dark:bg-gray-800/90 rounded-2xl p-4 border border-gray-200 dark:border-gray-700/80 shadow-xs space-y-3">
                                         @if(!empty($confirmationData['youtube_insight']) && !str_contains(strtolower($confirmationData['youtube_insight']), 'tidak ada referensi khusus'))
                                             <div class="bg-red-50/70 dark:bg-red-950/30 rounded-xl p-3 border border-red-100 dark:border-red-900/40 text-xs">
                                                 <div class="font-bold text-red-700 dark:text-red-400 mb-0.5 flex items-center">
                                                     <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                                     Insight Referensi YouTube
                                                 </div>
                                                 <p class="text-gray-700 dark:text-gray-300">{{ $confirmationData['youtube_insight'] }}</p>
                                             </div>
                                         @endif

                                         @if(!empty($confirmationData['ai_chat_insight']) && !str_contains(strtolower($confirmationData['ai_chat_insight']), 'tidak ada referensi chat ai khusus'))
                                             <div class="bg-indigo-50/70 dark:bg-indigo-950/30 rounded-xl p-3 border border-indigo-100 dark:border-indigo-900/40 text-xs">
                                                 <div class="font-bold text-indigo-700 dark:text-indigo-400 mb-0.5 flex items-center">
                                                     <x-icon.sparkles class="w-3.5 h-3.5 mr-1" />
                                                     Insight Referensi Chat AI
                                                 </div>
                                                 <p class="text-gray-700 dark:text-gray-300">{{ $confirmationData['ai_chat_insight'] }}</p>
                                             </div>
                                         @endif

                                         <div class="pt-1">
                                             <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block mb-1.5">Fitur Terintegrasi Otomatis:</span>
                                             <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-bold text-gray-700 dark:text-gray-300">
                                                 <div class="p-2 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800 flex items-center gap-1.5">
                                                     <span class="text-emerald-500">✓</span> Task Harian
                                                 </div>
                                                 <div class="p-2 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800 flex items-center gap-1.5">
                                                     <span class="text-emerald-500">✓</span> Timer Pomodoro
                                                 </div>
                                                 <div class="p-2 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800 flex items-center gap-1.5">
                                                     <span class="text-emerald-500">✓</span> Evaluasi Berkala
                                                 </div>
                                                 <div class="p-2 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800 flex items-center gap-1.5">
                                                     <span class="text-amber-500">🏆</span> +100 EXP Kelulusan
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                 <div class="flex space-x-3 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                                     <button wire:click="cancelConfirm" type="button" class="flex-1 py-3 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-2xl font-bold transition-colors cursor-pointer text-xs sm:text-sm">
                                         Batal & Revisi Chat
                                     </button>
                                     <button wire:click="confirmSetup" type="button" class="flex-1 py-3 bg-atoma-blue hover:bg-[#3455dc] text-white rounded-2xl font-bold shadow-lg shadow-atoma-blue/25 dark:shadow-none transition-all flex justify-center items-center cursor-pointer text-xs sm:text-sm">
                                         <span wire:loading.remove wire:target="confirmSetup" class="inline-flex items-center gap-1.5">
                                             <span>Setuju & Mulai 🚀 ({{ $newSkillDays }} Hari)</span>
                                         </span>
                                         <span wire:loading wire:target="confirmSetup">Membangun Roadmap...</span>
                                     </button>
                                 </div>
                             </div>
                        @else
                            <div wire:key="chat-state" class="flex flex-col h-full">
                                <div class="flex justify-between items-center mb-6 border-b border-gray-100 dark:border-gray-800 pb-4">
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                                        <x-icon.bot class="w-5 h-5 text-atoma-blue" />
                                        <span>Coach Interview</span>
                                    </h3>
                                    <button wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>

                                <div class="h-96 overflow-y-auto mb-4 space-y-4 pr-2 custom-scrollbar" id="chat-container" x-data x-init="$watch('$wire.chatHistory', () => { setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50); })">
                                    @foreach($chatHistory as $msg)
                                        @if($msg['role'] === 'ai')
                                            <div class="flex items-start">
                                                <div class="w-8 h-8 rounded-full bg-atoma-ice dark:bg-atoma-blue/30 flex items-center justify-center mr-2 shrink-0 shadow-sm border border-atoma-aqua/50 dark:border-atoma-blue/50 text-atoma-blue dark:text-atoma-cyan">
                                                    <x-icon.bot class="w-4 h-4" />
                                                </div>
                                                <div class="bg-gray-100 dark:bg-gray-800 rounded-2xl rounded-tl-none px-4 py-3 text-sm text-gray-800 dark:text-gray-200 shadow-sm whitespace-pre-wrap leading-relaxed">
                                                    {{ $msg['text'] }}
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex items-start justify-end">
                                                <div class="bg-atoma-blue text-white rounded-2xl rounded-tr-none px-4 py-3 text-sm ml-8 shadow-xs whitespace-pre-wrap leading-relaxed">
                                                    {{ $msg['text'] }}
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                    
                                    <div class="flex items-start" wire:loading wire:target="sendMessage">
                                        <div class="w-8 h-8 rounded-full bg-atoma-ice dark:bg-atoma-blue/30 flex items-center justify-center mr-2 shrink-0 shadow-sm border border-atoma-aqua/50 dark:border-atoma-blue/50 animate-pulse text-atoma-blue dark:text-atoma-cyan">
                                            <x-icon.bot class="w-4 h-4" />
                                        </div>
                                        <div class="bg-gray-100 dark:bg-gray-800 rounded-2xl rounded-tl-none px-4 py-4 text-sm text-gray-800 dark:text-gray-200 shadow-sm flex space-x-1.5 items-center">
                                            <div class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                            <div class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                            <div class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                                        </div>
                                    </div>
                                </div>

                                @if($quotaResetTime)
                                    @php
                                        $resetDate = \Carbon\Carbon::createFromTimestamp($quotaResetTime);
                                        $hoursLeft = max(1, ceil(now()->diffInMinutes($resetDate) / 60));
                                    @endphp
                                    <div class="mt-auto relative bg-gray-100 dark:bg-gray-800 rounded-2xl p-4 text-center border border-gray-200 dark:border-gray-700">
                                        <div class="text-gray-500 dark:text-gray-400 font-bold mb-1 flex items-center justify-center">
                                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                                            Sesi Terkunci
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            Token harian AI habis. Coba lagi dalam ~{{ $hoursLeft }} jam.
                                        </div>
                                    </div>
                                @else
                                    <form wire:submit="sendMessage" class="mt-auto relative" novalidate
                                          x-data="{
                                              resize() {
                                                  if ($refs.chatInput) {
                                                      $refs.chatInput.style.height = 'auto';
                                                      $refs.chatInput.style.height = Math.min(Math.max($refs.chatInput.scrollHeight, 48), 140) + 'px';
                                                  }
                                              }
                                          }"
                                          x-init="
                                              $nextTick(() => resize());
                                              $watch('$wire.currentMessage', value => {
                                                  if (!value) {
                                                      $nextTick(() => {
                                                          if ($refs.chatInput) $refs.chatInput.style.height = '48px';
                                                      });
                                                  } else {
                                                      $nextTick(() => resize());
                                                  }
                                              });
                                          ">
                                        <div class="relative flex items-end bg-gray-50 dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-inner focus-within:ring-2 focus-within:ring-atoma-blue focus-within:border-transparent transition-all">
                                            <textarea 
                                                x-ref="chatInput"
                                                wire:model="currentMessage" 
                                                @input="resize()"
                                                @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); if ($wire.currentMessage.trim().length >= 2) { $wire.sendMessage(); } }"
                                                rows="1"
                                                style="min-height: 48px; max-height: 140px;"
                                                class="block w-full pl-4 pr-14 py-3 bg-transparent border-0 resize-none text-gray-900 dark:text-white font-medium focus:ring-0 focus:outline-none text-sm leading-relaxed custom-scrollbar placeholder:text-gray-400 dark:placeholder:text-gray-500" 
                                                placeholder="Ketik balasan atau tempel jadwal di sini... (Shift+Enter untuk baris baru)" 
                                                {{ $isChatLoading ? 'disabled' : '' }}></textarea>
                                            
                                            <button type="submit" 
                                                    class="absolute right-2 bottom-2 w-9 h-9 bg-atoma-blue hover:bg-[#3455dc] disabled:opacity-50 text-white rounded-xl flex items-center justify-center transition-all shadow-md hover:shadow-lg cursor-pointer shrink-0" 
                                                    {{ $isChatLoading ? 'disabled' : '' }}
                                                    title="Kirim pesan (Enter)">
                                                <span wire:loading.remove wire:target="sendMessage">
                                                    <svg class="w-4 h-4 translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                                </span>
                                                <span wire:loading wire:target="sendMessage">
                                                    <svg class="animate-spin h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                </span>
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

      <!-- Detail Modal Pop-up -->
    @if($showDetailModal && $selectedSkill)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeDetailModal"></div>
        <div class="flex min-h-full items-center justify-center p-3 sm:p-6">
            <div class="bg-white dark:bg-gray-900 rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-100 dark:border-gray-800 relative z-10 flex flex-col max-h-[90vh] pointer-events-auto my-auto">
                
                <!-- Header Banner (Fixed Top) -->
                <div class="bg-gradient-to-r from-atoma-blue to-atoma-cyan dark:from-gray-850 dark:via-indigo-950/70 dark:to-gray-900 border-b border-transparent dark:border-indigo-500/30 p-5 sm:p-6 text-white relative shrink-0">
                    <button type="button" wire:click="closeDetailModal" class="absolute top-4 right-4 text-white/70 hover:text-white bg-white/10 hover:bg-white/20 dark:bg-gray-800/80 dark:hover:bg-gray-750 dark:text-gray-300 dark:hover:text-white rounded-full p-2 transition-colors cursor-pointer">
                        <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    
                    <div class="flex items-center space-x-2 mb-2">
                        <span class="bg-white/20 dark:bg-indigo-900/60 dark:text-indigo-200 dark:border dark:border-indigo-500/40 backdrop-blur-md text-white text-xs font-black uppercase px-3 py-1 rounded-full tracking-wider inline-flex items-center gap-1.5">
                            @if($selectedSkill->mode === 'zen')
                                <x-icon.sparkles class="w-3.5 h-3.5" /> <span>Zen Mode</span>
                            @else
                                <x-icon.target class="w-3.5 h-3.5" /> <span>Systematic Mode</span>
                            @endif
                        </span>
                        <span class="bg-white/20 dark:bg-indigo-900/60 dark:text-indigo-200 dark:border dark:border-indigo-500/40 backdrop-blur-md text-white text-xs font-bold px-3 py-1 rounded-full">
                            Day {{ $selectedSkill->current_day }} of {{ $selectedSkill->target_days }}
                        </span>
                    </div>
                    
                    <h2 class="text-xl sm:text-2xl font-black mb-1 leading-tight text-white dark:text-gray-100">{{ $selectedSkill->title }}</h2>
                    <p class="text-white/80 dark:text-gray-300 text-xs sm:text-sm font-medium line-clamp-2">{{ $selectedSkill->description ?? 'Milestone Challenge' }}</p>
                    
                    <!-- Progress Bar -->
                    @php $modalProg = $selectedSkill->calculated_progress; @endphp
                    <div class="mt-3 bg-white/20 dark:bg-gray-700/60 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-white dark:bg-indigo-500 h-2.5 rounded-full transition-all duration-700" style="width: {{ $modalProg }}%"></div>
                    </div>
                    <div class="flex justify-between items-center text-xs font-bold text-white/80 dark:text-gray-400 mt-1.5">
                        <span>{{ __('Progress Keseluruhan') }}</span>
                        <span class="text-white dark:text-indigo-300">{{ __(':prog% Selesai', ['prog' => $modalProg]) }}</span>
                    </div>
                </div>

                <!-- Scrollable Modal Content -->
                <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-5">
                    <!-- YouTube & AI Chat Reference Links & Strategy Insight -->
                    @php
                        $modalYtUrl = $selectedSkill->effective_youtube_url;
                        $modalAiUrl = $selectedSkill->effective_ai_chat_url;
                    @endphp
                    @if($modalYtUrl || $modalAiUrl)
                        <div class="flex items-center flex-wrap gap-2 p-2.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40">
                            @if($modalYtUrl)
                                <a href="{{ $modalYtUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 px-2.5 py-1.5 rounded-xl border border-red-100 dark:border-red-900/50 hover:underline shrink-0">
                                    <svg class="w-4 h-4 mr-1.5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                    <span>Video YouTube</span>
                                </a>
                            @endif
                            @if($modalAiUrl)
                                <x-ai-chat-badge :url="$modalAiUrl" />
                            @endif
                        </div>
                    @endif

                    @php
                        $aiSummary = null;
                        if (is_array($selectedSkill->interview_context)) {
                            foreach($selectedSkill->interview_context as $msg) {
                                if (isset($msg['text']) && str_contains($msg['text'], 'READY')) {
                                    $parsed = json_decode($msg['text'], true);
                                    if (isset($parsed['summary'])) {
                                        $aiSummary = $parsed['summary'];
                                    }
                                }
                            }
                        }
                    @endphp

                    @if($aiSummary)
                        <div class="text-xs space-y-2 text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 p-3.5 rounded-2xl border border-gray-100 dark:border-gray-700/60">
                            @if(isset($aiSummary['youtube_insight']))
                                <p><strong class="text-gray-900 dark:text-white">{{ __('Insight YouTube:') }}</strong> {{ $aiSummary['youtube_insight'] }}</p>
                            @endif
                            @if(isset($aiSummary['ai_chat_insight']))
                                <p><strong class="text-gray-900 dark:text-white">{{ __('Insight Chat AI:') }}</strong> {{ $aiSummary['ai_chat_insight'] }}</p>
                            @endif
                            @if(isset($aiSummary['training_schedule']))
                                <p><strong class="text-gray-900 dark:text-white">{{ __('Jadwal Latihan:') }}</strong> {{ $aiSummary['training_schedule'] }}</p>
                            @endif
                            @if(isset($aiSummary['overview']))
                                <p><strong class="text-gray-900 dark:text-white">{{ __('Overview Strategy:') }}</strong> {{ $aiSummary['overview'] }}</p>
                            @endif
                        </div>
                    @endif

                    <!-- Targets Roadmap List with Week Filter Dropdown -->
                    <div>
                        @php
                            $cycles = $this->modalWeekCycles;
                            $activeWNum = (int) $this->modalSelectedWeek;
                            $activeWeek = $cycles[$activeWNum] ?? ($cycles[1] ?? ['start_day' => 1, 'end_day' => 7, 'label' => 'Week 1 (Hari 1-7)']);
                            
                            $filteredTargets = $selectedSkill->targets
                                ->whereBetween('day_number', [$activeWeek['start_day'], $activeWeek['end_day']])
                                ->unique('day_number')
                                ->sortBy('day_number');
                        @endphp

                        <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                            <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-atoma-blue dark:text-atoma-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                {{ __('Target') }} ({{ $activeWeek['label'] }})
                            </h4>

                            <!-- Week Filter Custom Dropdown Selector -->
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">{{ __('Minggu:') }}</span>
                                <div x-data="{ open: false }" class="relative inline-block">
                                    <button type="button" 
                                            @click="open = !open" 
                                            class="text-xs font-extrabold px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 shadow-2xs flex items-center gap-1.5 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-750 transition-colors">
                                        <span>{{ $cycles[$modalSelectedWeek]['label'] ?? __('Pilih Minggu') }}</span>
                                        <svg class="w-3 h-3 text-atoma-blue dark:text-indigo-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>

                                    <div x-show="open" 
                                         @click.away="open = false" 
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                         style="display: none;" 
                                         class="absolute right-0 top-9 z-30 w-48 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1.5 space-y-0.5 max-h-56 overflow-y-auto hide-scrollbar">
                                        @foreach($cycles as $wNum => $wInfo)
                                            <button type="button" 
                                                    @click="$wire.set('modalSelectedWeek', {{ $wNum }}); open = false;" 
                                                    class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between transition-colors cursor-pointer {{ (int)$modalSelectedWeek === (int)$wNum ? 'bg-atoma-blue/10 dark:bg-indigo-950/70 text-atoma-blue dark:text-indigo-300 font-extrabold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                                <span>{{ $wInfo['label'] }}</span>
                                                @if((int)$modalSelectedWeek === (int)$wNum)
                                                    <x-icon.check class="w-3.5 h-3.5 text-atoma-blue dark:text-indigo-400" />
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($filteredTargets->count() > 0)
                            <div class="space-y-3">
                                @foreach($filteredTargets as $target)
                                    @php
                                        $isCompleted = $target->status === 'completed';
                                        $isSkipped = $target->status === 'skipped' || $target->status === 'failed';
                                        $feedback = $target->evaluation_feedback;
                                        $isWeeklyCheckpoint = $feedback && str_contains($feedback, '[EVALUASI_MINGGUAN_SELESAI]');
                                        $isUserReflection = $feedback && !str_starts_with($feedback, 'Gagal') && !$isWeeklyCheckpoint;
                                        $cleanFeedback = $isWeeklyCheckpoint ? str_replace('[EVALUASI_MINGGUAN_SELESAI]', '', $feedback) : $feedback;
                                    @endphp

                                    <div class="p-3.5 sm:p-4 rounded-2xl border transition-all flex items-start justify-between gap-3 shadow-sm {{ $isCompleted ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200/80 dark:border-emerald-800/60' : ($isSkipped ? 'bg-rose-50/40 dark:bg-rose-950/20 border-rose-200/60 dark:border-rose-800/40' : 'bg-gray-50 dark:bg-gray-800/60 border-gray-100 dark:border-gray-700/60') }}">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center flex-wrap gap-2 mb-1.5">
                                                <span class="text-xs font-black px-2.5 py-0.5 rounded-lg shrink-0 {{ $isCompleted ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : ($isSkipped ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300') }}">
                                                    Day {{ $target->day_number }}
                                                </span>
                                                <h5 class="font-bold text-xs sm:text-sm text-gray-900 dark:text-white break-words">{{ $target->title }}</h5>

                                                @if($isSkipped)
                                                    <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400 bg-rose-100/70 dark:bg-rose-950/60 px-2 py-0.5 rounded-md border border-rose-200 dark:border-rose-800">
                                                        {{ __('Melewati Batas Waktu') }}
                                                    </span>
                                                @endif
                                            </div>

                                            <p class="text-xs text-gray-600 dark:text-gray-400 whitespace-pre-line leading-relaxed mb-1">{{ $target->description }}</p>

                                            @if($target->subTasks && $target->subTasks->count() > 0)
                                                <div class="mt-2 text-[11px] bg-gray-100/80 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 p-2.5 rounded-xl border border-gray-200/60 dark:border-gray-700/60 space-y-1">
                                                    <div class="font-extrabold text-[10px] text-gray-500 uppercase tracking-wider mb-0.5 flex items-center gap-1">
                                                        <x-icon.check-circle class="w-3.5 h-3.5 text-gray-500 inline" />
                                                        <span>{{ __('Sub-task (:done/:total):', ['done' => $target->subTasks->where('is_completed', true)->count(), 'total' => $target->subTasks->count()]) }}</span>
                                                    </div>
                                                    @foreach($target->subTasks as $st)
                                                        <div class="flex items-center gap-1.5 font-medium {{ $st->is_completed ? 'line-through opacity-50' : '' }}">
                                                            @if($st->is_completed)
                                                                <x-icon.check-circle class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                                                            @else
                                                                <span class="w-3.5 h-3.5 rounded border border-gray-400 dark:border-gray-500 inline-block shrink-0"></span>
                                                            @endif
                                                            <span>{{ $st->title }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if($isWeeklyCheckpoint)
                                                <div class="mt-2 text-[11px] bg-purple-50 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200 p-2.5 rounded-xl border border-purple-200/70 dark:border-purple-800/60">
                                                    <div class="font-extrabold text-[10px] text-purple-700 dark:text-purple-300 uppercase tracking-wider mb-0.5 flex items-center gap-1">
                                                        <x-icon.bot class="w-3.5 h-3.5 text-purple-700 dark:text-purple-300 inline shrink-0" />
                                                        <span>{{ __('Analisis AI Coach (Checkpoint Minggu Ini):') }}</span>
                                                    </div>
                                                    <p class="leading-relaxed font-medium">{{ trim($cleanFeedback) }}</p>
                                                </div>
                                            @elseif($isUserReflection)
                                                <div class="mt-2 text-[11px] bg-atoma-ice dark:bg-atoma-blue/20 text-atoma-blue dark:text-atoma-cyan p-2.5 rounded-xl border border-atoma-aqua/60 dark:border-atoma-blue/40">
                                                    <div class="font-extrabold text-[10px] text-atoma-blue dark:text-atoma-cyan uppercase tracking-wider mb-0.5 flex items-center gap-1">
                                                        <x-icon.message-square class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan inline shrink-0" />
                                                        <span>{{ __('Refleksi Harian User:') }}</span>
                                                    </div>
                                                    <p class="italic font-medium">"{{ trim($cleanFeedback) }}"</p>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="shrink-0 pt-0.5">
                                            @if($isCompleted)
                                                <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                                    <x-icon.check class="w-3.5 h-3.5" />
                                                </span>
                                            @elseif($isSkipped)
                                                <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-rose-500 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                                    <x-icon.x class="w-3.5 h-3.5" />
                                                </span>
                                            @else
                                                <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-amber-400 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                                    <x-icon.clock class="w-3.5 h-3.5" />
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center p-6 bg-gray-50 dark:bg-gray-800/40 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700">
                                <p class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ __('Belum ada target yang disiapkan untuk :week.', ['week' => $activeWeek['label']]) }}</p>
                                <p class="text-[11px] text-gray-400 mt-1">{{ __('Target akan dibuat otomatis saat Anda memasuki minggu ini!') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Modal Footer / Navigation (Fixed Bottom) -->
                <div class="p-4 sm:p-5 bg-gray-50 dark:bg-gray-800/90 border-t border-gray-100 dark:border-gray-700/60 flex justify-between items-center shrink-0">
                    <button type="button" @click="$wire.closeDetailModal()" class="px-4 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold rounded-xl text-xs hover:bg-gray-300 transition-colors cursor-pointer">
                        {{ __('Tutup') }}
                    </button>

                    <a href="{{ route('skills.show', $selectedSkill->id) }}" wire:navigate class="px-5 py-2.5 bg-atoma-blue hover:bg-[#3455dc] dark:bg-indigo-600 dark:hover:bg-indigo-500 text-white font-bold rounded-xl text-xs shadow-md shadow-atoma-blue/25 dark:shadow-none hover:shadow-lg transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>{{ __('Buka Roadmap Lengkap') }}</span>
                        <x-icon.arrow-right class="w-4 h-4" />
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif
