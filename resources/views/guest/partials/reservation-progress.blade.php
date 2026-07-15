@props([
    'reservation',
    'heading' => 'Reservation Progress',
    'showGuidance' => true,
])

@php
    $statusPresentation = \App\Models\Reservation::statusPresentation($reservation->status);
    $statusLabels = \App\Models\Reservation::statusOptions(false);
    $steps = ['pending', 'approved', 'confirmed', 'checked_in', 'checked_out'];
    $currentIndex = array_search($reservation->status, $steps);
    $currentIndex = in_array($reservation->status, ['declined', 'cancelled'], true) ? -1 : $currentIndex;
    $guidanceTone = in_array($reservation->status, ['declined', 'cancelled'], true)
        ? 'border-red-200 bg-red-50 text-red-800'
        : 'border-blue-200 bg-blue-50 text-blue-800';
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-xl font-bold text-[#00491E]">{{ $heading }}</h2>
        @include('guest.partials.reservation-status-badge', ['status' => $reservation->status, 'label' => $statusPresentation['label']])
    </div>

    @if(! in_array($reservation->status, ['declined', 'cancelled'], true))
        <div class="mt-6 flex items-start justify-between">
            @foreach($steps as $index => $step)
                <div class="flex min-w-0 flex-1 flex-col items-center {{ $index < count($steps) - 1 ? 'relative' : '' }}">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $index <= $currentIndex ? 'bg-[#00491E] text-white' : 'bg-gray-200 text-gray-500' }}">
                        {{ $index < $currentIndex ? '✓' : $index + 1 }}
                    </div>
                    <span class="mt-2 text-center text-xs {{ $index <= $currentIndex ? 'font-medium text-[#00491E]' : 'text-gray-400' }}">
                        {{ $statusLabels[$step] ?? ucfirst(str_replace('_', ' ', $step)) }}
                    </span>
                    @if($index < count($steps) - 1)
                        <div class="absolute left-1/2 top-4 h-0.5 w-full {{ $index < $currentIndex ? 'bg-[#00491E]' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($showGuidance)
        <div class="mt-6 rounded-lg border p-4 text-sm {{ $guidanceTone }}">{{ $statusPresentation['guidance'] }}</div>
    @endif
</div>
