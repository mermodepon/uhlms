@php
    $status = (string) ($status ?? '');
    $presentation = \App\Models\Reservation::statusPresentation($status);
    $label = $label ?? $presentation['label'];
@endphp

<span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-sm font-semibold shadow-sm"
      style="background-color: {{ $presentation['hex'] }}; color: {{ $presentation['badge_text'] }};">
    {{ $label }}
</span>
