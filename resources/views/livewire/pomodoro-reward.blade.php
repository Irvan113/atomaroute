<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use App\Models\FocusSession;
use App\Models\Task;

new class extends Component {
    
    #[On('pomodoro-completed')]
    public function rewardPomodoro($taskId = null, $minutes = 25, $ambientSound = null, $mode = 'work')
    {
        $user = auth()->user();
        if (!$user) return;

        $duration = max(1, (int) $minutes);
        $userTz = $user->timezone ?? config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($userTz);

        // Count previous sessions completed today
        $prevSessionsToday = FocusSession::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->whereIn('mode', ['work', 'flowtime'])
            ->count();
        $sessionNumber = $prevSessionsToday + 1;

        // Calculate rewards: 1 hour (60 minutes) = 150 XP max cap (2.5 XP/min)
        $calculatedXp = (int) round($duration * 2.5);
        $coinsToGain = ($sessionNumber >= 4 || $duration >= 50) ? 1 : 0;

        // Daily cap: max 150 Pending XP per day from pomodoro
        $todayXpSoFar = (int) FocusSession::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->sum('xp_earned');
        $availableXp = max(0, 150 - $todayXpSoFar);
        $xpToGain = min($calculatedXp, $availableXp);

        // Record focus session to database
        FocusSession::create([
            'user_id' => $user->id,
            'task_id' => $taskId ? (int) $taskId : null,
            'minutes' => $duration,
            'mode' => $mode ?: 'work',
            'ambient_sound' => $ambientSound,
            'xp_earned' => $xpToGain,
            'coins_earned' => $coinsToGain,
        ]);

        if ($taskId) {
            $task = $user->tasks()->find($taskId);
            if ($task) {
                $task->increment('pomodoro_sessions');
                $task->increment('actual_minutes', $duration);
            }
        }

        if ($xpToGain > 0) {
            $taskTitle = (isset($task) && $task) ? " ({$task->title})" : "";
            $user->addPendingXp($xpToGain, 'pomodoro', "Fokus Pomodoro ({$duration}m){$taskTitle}");
        }
        if ($coinsToGain > 0) {
            $user->coins += $coinsToGain;
        }

        $user->last_pomodoro_xp_at = $today;
        $user->save();

        $rewardText = "+{$xpToGain} Pending XP";
        if ($coinsToGain > 0) {
            $rewardText .= " & +{$coinsToGain} Koin 🪙";
        }

        $modeLabel = $mode === 'flowtime' ? 'Flowtime' : 'Focus Session';
        $this->dispatch('toast', message: "{$modeLabel} #{$sessionNumber} Selesai ({$duration}m)! {$rewardText} 🍅", type: 'success');
        $this->dispatch('profile-updated');
        $this->dispatch('pomodoro-session-logged');

        // Check Hidden Mission (Overachiever: 250 Task XP + 60 mins Pomodoro in 1 day)
        $hiddenReward = \App\Services\GamificationService::checkOverachieverHiddenMission($user);
        if ($hiddenReward) {
            $this->dispatch('toast', message: $hiddenReward['message'], type: 'success');
            $this->dispatch('play-sound', type: 'level_up');
            $this->dispatch('trigger-confetti');
        }

        // Check & Unlock Achievements
        $unlockedAchievements = (new \App\Services\AchievementService())->checkAndUnlock($user);
        foreach ($unlockedAchievements as $unlockedBadge) {
            $this->dispatch('achievement-unlocked', badge: $unlockedBadge);
        }
    }

    #[On('complete-task-from-pomodoro')]
    public function completeTaskFromPomodoro($taskId)
    {
        $user = auth()->user();
        if (!$user || !$taskId) return;

        $task = $user->tasks()->find($taskId);
        if (!$task) return;

        if (!$task->is_completed) {
            foreach ($task->subTasks as $st) {
                if (!$st->is_completed) {
                    $st->update(['is_completed' => true]);
                    \App\Services\GamificationService::awardSubTaskXp($user, $st);
                }
            }

            $task->is_completed = true;
            $task->save();

            // Calculate task earned XP using central TaskXpService
            $evaluation = \App\Services\TaskXpService::evaluateTaskCompletion($task, $user);
            $taskXp = $evaluation['xp'];

            $this->dispatch('toast', message: $evaluation['message'], type: $evaluation['type']);
            $this->dispatch('play-sound', type: 'task_complete');
            $this->dispatch('task-completed', taskId: $task->id);
            $this->dispatch('profile-updated');

            // Check Hidden Mission (Overachiever: 250 Task XP + 60 mins Pomodoro in 1 day)
            $hiddenReward = \App\Services\GamificationService::checkOverachieverHiddenMission($user);
            if ($hiddenReward) {
                $this->dispatch('toast', message: $hiddenReward['message'], type: 'success');
                $this->dispatch('play-sound', type: 'level_up');
                $this->dispatch('trigger-confetti');
            }

            // Check & Unlock Achievements
            $unlockedAchievements = (new \App\Services\AchievementService())->checkAndUnlock($user);
            foreach ($unlockedAchievements as $unlockedBadge) {
                $this->dispatch('achievement-unlocked', badge: $unlockedBadge);
            }
        }
    }

    #[On('toggle-subtask-from-pomodoro')]
    public function toggleSubtaskFromPomodoro($taskId, $subTaskId)
    {
        $user = auth()->user();
        if (!$user || !$taskId || !$subTaskId) return;

        $task = $user->tasks()->find($taskId);
        if (!$task) return;

        $subTask = $task->subTasks()->find($subTaskId);
        if (!$subTask) return;

        $subTask->is_completed = !$subTask->is_completed;
        $subTask->save();

        if ($subTask->is_completed) {
            $awarded = \App\Services\GamificationService::awardSubTaskXp($user, $subTask);
            if ($awarded > 0) {
                $this->dispatch('profile-updated');
            }
            $hiddenReward = \App\Services\GamificationService::checkDailyHiddenMission($user);
            if ($hiddenReward) {
                $this->dispatch('toast', message: $hiddenReward['message'], type: 'success');
                $this->dispatch('play-sound', preset: 'fanfare');
                $this->dispatch('trigger-confetti');
            }
        } else {
            \App\Services\GamificationService::revertSubTaskXp($user, $subTask);
            $this->dispatch('profile-updated');
        }

        $this->dispatch('subtask-toggled-global', taskId: $task->id, subTaskId: $subTask->id, isCompleted: $subTask->is_completed);
    }
}; ?>

<div>
    <!-- Headless component for listening to events -->
</div>
