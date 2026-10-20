@props(['class' => 'w-4 h-4'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="11" cy="11" r="8"/>
    <path d="m21 21-4.3-4.3"/>
</svg>
