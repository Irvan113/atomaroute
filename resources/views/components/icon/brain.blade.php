@props(['class' => 'w-4 h-4'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/>
    <path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/>
    <path d="M12 5v13"/>
    <path d="M9.5 8.5a3 3 0 0 1 2.5 1.5 3 3 0 0 1 2.5-1.5"/>
    <path d="M9 13.5a3 3 0 0 1 3 1.5 3 3 0 0 1 3-1.5"/>
</svg>
