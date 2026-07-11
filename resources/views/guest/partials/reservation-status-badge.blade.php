@php
    $status = (string) ($status ?? '');
    $label = $label ?? str($status)->replace('_', ' ')->title()->toString();
    $palette = [
        'pending' => ['background' => '#fbbf24', 'color' => '#422006'],
        'awaiting_alternative_confirmation' => ['background' => '#f59e0b', 'color' => '#422006'],
        'approved' => ['background' => '#919F02', 'color' => '#ffffff'],
        'confirmed' => ['background' => '#10B981', 'color' => '#ffffff'],
        'declined' => ['background' => '#EF4444', 'color' => '#ffffff'],
        'cancelled' => ['background' => '#6B7280', 'color' => '#ffffff'],
        'checked_in' => ['background' => '#16a34a', 'color' => '#ffffff'],
        'checked_out' => ['background' => '#94a3b8', 'color' => '#0f172a'],
    ];
    $colors = $palette[$status] ?? ['background' => '#6B7280', 'color' => '#ffffff'];
@endphp

<span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-sm font-semibold shadow-sm"
      style="background-color: {{ $colors['background'] }}; color: {{ $colors['color'] }};">
    {{ $label }}
</span>
