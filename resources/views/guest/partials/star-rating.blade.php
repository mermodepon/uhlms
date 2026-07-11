@php
    $rating = max(0, min(5, (int) ($rating ?? 0)));
    $label = $label ?? "{$rating} out of 5";
@endphp

<span class="inline-flex items-center gap-1" aria-label="{{ $label }}">
    @for($star = 1; $star <= 5; $star++)
        <span class="{{ $star <= $rating ? 'text-[#FFC600]' : 'text-gray-300' }}" aria-hidden="true">&#9733;</span>
    @endfor
    <span class="ml-1 text-sm font-semibold text-gray-700">{{ $rating }} / 5</span>
</span>
