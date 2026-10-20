@props(['class' => 'w-4 h-4', 'title' => null])

@php
    $id = 'goalFlameGrad_' . md5($attributes->get('id', uniqid()));
@endphp

<span class="inline-flex items-center shrink-0" @if($title) title="{{ $title }}" @endif>
    <svg {{ $attributes->merge(['class' => $class . ' shrink-0 transition-transform group-hover:scale-110 drop-shadow-[0_1px_3px_rgba(249,115,22,0.45)]']) }} viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <!-- Outer Burning Flame -->
        <path d="M12.0001 1.75C12.0001 1.75 13.6501 5.8 15.8501 8.2C18.0501 10.6 20.0001 12.8 20.0001 15.8C20.0001 19.8 16.4001 22.25 12.0001 22.25C7.60012 22.25 4.00012 19.8 4.00012 15.8C4.00012 11.8 7.50012 7.8 12.0001 1.75Z" 
              fill="url(#{{ $id }}_outer)" />
        
        <!-- Mid Vibrant Core -->
        <path d="M12.0001 8.5C12.0001 8.5 13.5001 11.2 15.0001 13C16.5001 14.8 17.2001 16.2 17.2001 17.8C17.2001 20.5 14.8001 21.8 12.0001 21.8C9.20012 21.8 6.80012 20.5 6.80012 17.8C6.80012 15.2 8.50012 12.8 12.0001 8.5Z" 
              fill="url(#{{ $id }}_mid)" />

        <!-- Inner White-Hot Flame Spark -->
        <path d="M12.0001 13.5C12.0001 13.5 12.8001 15 13.8001 16.2C14.6001 17.2 14.9001 18.2 14.9001 19.2C14.9001 20.8 13.6001 21.6 12.0001 21.6C10.4001 21.6 9.10012 20.8 9.10012 19.2C9.10012 17.8 10.2001 16.2 12.0001 13.5Z" 
              fill="#FFFBEB" 
              class="animate-pulse" />

        <!-- Emitting Spark Top -->
        <circle cx="16.5" cy="5.5" r="1" fill="#FBBF24" opacity="0.9" />
        <circle cx="7.8" cy="8.2" r="0.75" fill="#F97316" opacity="0.8" />

        <defs>
            <linearGradient id="{{ $id }}_outer" x1="12" y1="1.75" x2="12" y2="22.25" gradientUnits="userSpaceOnUse">
                <stop stop-color="#F59E0B" />
                <stop offset="0.45" stop-color="#EA580C" />
                <stop offset="1" stop-color="#DC2626" />
            </linearGradient>
            <linearGradient id="{{ $id }}_mid" x1="12" y1="8.5" x2="12" y2="21.8" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FEF08A" />
                <stop offset="0.5" stop-color="#F59E0B" />
                <stop offset="1" stop-color="#EA580C" />
            </linearGradient>
        </defs>
    </svg>
</span>
