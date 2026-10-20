@props(['class' => 'w-4 h-4'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <rect x="2" y="7" width="16" height="13" rx="2" ry="2"/>
    <path d="M6 3h12a2 2 0 0 1 2 2v12"/>
</svg>
