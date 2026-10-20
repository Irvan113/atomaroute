@props(['url', 'size' => 'normal'])

@php
    $info = \App\Services\AiChatReferenceService::parseAiChat($url);
@endphp

@if($info)
    @php
        $sizeClasses = $size === 'small' 
            ? 'text-[10px] px-2 py-0.5 rounded-lg' 
            : 'text-[11px] px-2.5 py-1 rounded-xl';
        $iconSize = $size === 'small' ? 'w-3 h-3 mr-1' : 'w-3.5 h-3.5 mr-1';
    @endphp
    <a href="{{ $info['url'] }}" 
       target="_blank" 
       rel="noopener noreferrer"
       title="{{ __('Buka sesi chat :provider di tab baru', ['provider' => $info['name']]) }}: {{ $info['url'] }}"
       class="inline-flex items-center font-bold transition-all shadow-xs active:scale-95 shrink-0 {{ $info['badge_class'] }} {{ $sizeClasses }}">
        @if($info['icon'] === 'gemini')
            <svg class="{{ $iconSize }} shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 24C12 17.373 6.627 12 0 12C6.627 12 12 6.627 12 0C12 6.627 17.373 12 24 12C17.373 12 12 17.373 12 24Z"/>
            </svg>
        @elseif($info['icon'] === 'chatgpt')
            <svg class="{{ $iconSize }} shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.981 4.18a5.985 5.985 0 0 0-3.998 2.9 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 13.26 24a6.056 6.056 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM13.26 22.43a4.476 4.476 0 0 1-2.876-1.04l.141-.081 4.779-2.758a.795.795 0 0 0 .392-.681v-6.737l2.02 1.168a.071.071 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.494 4.494zM3.6 18.304a4.47 4.47 0 0 1-.535-3.014l.142.085 4.783 2.759a.771.771 0 0 0 .78 0l5.843-3.369v2.332a.08.08 0 0 1-.033.064l-4.83 2.79a4.5 4.5 0 0 1-6.15-1.647zm-1.63-9.87a4.512 4.512 0 0 1 2.34-1.976v5.679a.79.79 0 0 0 .392.68l5.822 3.36-2.02 1.168a.076.076 0 0 1-.067 0L3.6 14.55a4.498 4.498 0 0 1-1.63-6.116zM17.47 13.06l-5.843-3.37 2.02-1.164a.082.082 0 0 1 .068 0l4.839 2.792a4.496 4.496 0 0 1-.692 8.084v-5.661a.79.79 0 0 0-.392-.681zm2.93-3.342l-.142-.085-4.782-2.759a.775.775 0 0 0-.78 0L8.853 10.244V7.912a.08.08 0 0 1 .033-.064l4.83-2.79a4.499 4.499 0 0 1 6.684 4.66zM8.332 15.584l-2.02-1.168a.077.077 0 0 1-.037-.052V8.78a4.5 4.5 0 0 1 7.37-3.453l-.14.08-4.78 2.759a.794.794 0 0 0-.393.681v6.737zm1.141-2.924l2.527-1.458 2.527 1.458v2.917l-2.527 1.459-2.527-1.459z"/>
            </svg>
        @elseif($info['icon'] === 'claude')
            <svg class="{{ $iconSize }} shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h-2v-6h2v6zm4 0h-2V7h2v10z"/>
            </svg>
        @elseif($info['icon'] === 'perplexity')
            <svg class="{{ $iconSize }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="2" x2="12" y2="22"></line>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                <line x1="19.07" y1="4.93" x2="4.93" y2="19.07"></line>
            </svg>
        @elseif($info['icon'] === 'deepseek')
            <svg class="{{ $iconSize }} shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/>
            </svg>
        @else
            <x-icon.sparkles class="{{ $iconSize }} shrink-0" />
        @endif
        <span>{{ $info['label'] }}</span>
    </a>
@endif
