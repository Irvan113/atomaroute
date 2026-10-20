@props(['class' => 'w-4 h-4'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <polygon points="5 4 15 12 5 20 5 4"/>
    <line x1="19" y1="5" x2="19" y2="19"/>
</svg>
