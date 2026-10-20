@props([
    'type' => 'auto', // 'league', 'prestige', 'milestone', 'achievement', 'auto'
    'tier' => null,
    'league' => null,
    'prestige' => null,
    'milestone' => null, // 30, 90, 180, 365 or '30d', '90d', '180d', '365d'
    'achievement' => null, // code string e.g. 'task_master', 'streak_titan', etc.
    'size' => 'md',
])

@php
    $assetPath = '';
    $title = '';

    if ($milestone !== null || $type === 'milestone') {
        $mKey = strtolower(str_replace('d', '', (string)($milestone ?? '30')));
        $mFile = match($mKey) {
            '30' => 'milestone_30d.svg',
            '90' => 'milestone_90d.svg',
            '180' => 'milestone_180d.svg',
            '365' => 'milestone_365d.svg',
            default => 'milestone_30d.svg',
        };
        $assetPath = "images/badges/milestones/{$mFile}";
        $title = "Milestone {$mKey} Hari (>80% Selesai)";
    } elseif ($achievement !== null || $type === 'achievement') {
        $code = strtolower((string)$achievement);
        $aFile = match($code) {
            'task_master', 'first_task', 'tasks_50' => 'achieve_task_master.svg',
            'streak_titan', 'streak_7' => 'achieve_streak_titan.svg',
            'deep_focus', 'pomodoro_5h' => 'achieve_deep_focus.svg',
            'flowtime', 'flowstate' => 'achieve_flowtime.svg',
            'reflective_pen', 'journals_10' => 'achieve_reflective_pen.svg',
            'shield_defense', 'kebal_bacok' => 'achieve_shield_defense.svg',
            'duel_king', 'raja_duel', 'senggol_10' => 'achieve_duel_king.svg',
            'workout_pr', 'iron_will' => 'achieve_workout_pr.svg',
            default => 'achieve_task_master.svg',
        };
        $assetPath = "images/badges/achievements/{$aFile}";
        $title = "Pencapaian: " . ucwords(str_replace('_', ' ', $code));
    } elseif ($league !== null || ($type === 'league' && $tier !== null)) {
        $lKey = $league ?? match((int)$tier) {
            1 => 'bronze_3',
            2 => 'silver_3',
            3 => 'gold_3',
            4 => 'diamond_1',
            5 => 'master',
            default => 'bronze_3',
        };

        $leagueFile = match(true) {
            str_starts_with($lKey, 'bronze') => 'league_bronze.svg',
            str_starts_with($lKey, 'silver') => 'league_silver.svg',
            str_starts_with($lKey, 'gold') => 'league_gold.svg',
            str_starts_with($lKey, 'diamond') => 'league_diamond.svg',
            $lKey === 'master' => 'league_master.svg',
            default => 'league_bronze.svg',
        };

        $assetPath = "images/badges/league/{$leagueFile}";
        $title = "Liga: " . ucwords(str_replace('_', ' ', $lKey));
    } elseif ($prestige !== null || $type === 'prestige') {
        $pTier = max(1, min(5, (int) ($prestige ?? $tier ?? 1)));
        $assetPath = "images/badges/prestige/prestige_tier_{$pTier}.svg";
        
        $pNames = [
            1 => '🌱 Novice (Tier 1)',
            2 => '⚔️ Practitioner (Tier 2)',
            3 => '🔥 Master (Tier 3)',
            4 => '💎 Grandmaster (Tier 4)',
            5 => '👑 Ascended (Tier 5)',
        ];
        $title = "Prestige: " . ($pNames[$pTier] ?? "Tier {$pTier}");
    } else {
        $assetPath = "images/badges/league/league_bronze.svg";
        $title = "ATOMA Badge";
    }

    $sizeClasses = match($size) {
        'xs' => 'w-5 h-5',
        'sm' => 'w-6 h-6',
        'md' => 'w-8 h-8',
        'lg' => 'w-12 h-12',
        'xl' => 'w-16 h-16',
        '2xl' => 'w-24 h-24',
        '3xl' => 'w-32 h-32',
        'full' => 'w-full h-full',
        default => 'w-8 h-8',
    };
@endphp

<div {{ $attributes->merge(['class' => "inline-flex items-center justify-center shrink-0 $sizeClasses transition-transform hover:scale-105 select-none"]) }}
     title="{{ $title }}">
    <img src="{{ asset($assetPath) }}" 
         alt="{{ $title }}" 
         class="w-full h-full object-contain pointer-events-none">
</div>
