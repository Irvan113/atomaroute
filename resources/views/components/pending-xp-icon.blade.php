@props(['source' => 'other', 'class' => 'w-4 h-4'])

@switch($source)
    @case('achievement')
        <x-icon.trophy :class="$class" />
        @break
    @case('task')
        <x-icon.check-circle :class="$class" />
        @break
    @case('subtask')
        <x-icon.layers :class="$class" />
        @break
    @case('pomodoro')
        <x-icon.clock :class="$class" />
        @break
    @case('workout')
        <x-icon.activity :class="$class" />
        @break
    @case('flashcard')
        <x-icon.cards :class="$class" />
        @break
    @case('journal')
        <x-icon.book-open :class="$class" />
        @break
    @case('duel')
        <x-icon.sword :class="$class" />
        @break
    @case('skill')
        <x-icon.target :class="$class" />
        @break
    @case('hidden_mission')
        <x-icon.sparkles :class="$class" />
        @break
    @default
        <x-icon.zap :class="$class" />
@endswitch
