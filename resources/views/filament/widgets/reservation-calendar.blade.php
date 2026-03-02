<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <span class="text-base font-bold">Reservation Calendar</span>
                <div class="flex items-center gap-2">
                    <button wire:click="previousMonth"
                        class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <span class="text-sm font-bold text-gray-800 min-w-[120px] text-center">{{ $monthLabel }}</span>
                    <button wire:click="nextMonth"
                        class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </x-slot>

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mb-5 px-1">
            @foreach([
                ['color' => '#fbbf24', 'label' => 'Pending'],
                ['color' => '#3b82f6', 'label' => 'Approved'],
                ['color' => '#16a34a', 'label' => 'Checked In'],
                ['color' => '#94a3b8', 'label' => 'Checked Out'],
            ] as $item)
                <div class="flex items-center gap-2">
                    <span style="width:10px;height:10px;border-radius:50%;background-color:{{ $item['color'] }};display:inline-block;flex-shrink:0;"></span>
                    <span class="text-xs text-gray-500">{{ $item['label'] }}</span>
                </div>
            @endforeach
        </div>

        {{-- Calendar --}}
        <div class="overflow-x-auto overflow-y-visible -mx-1">
            <div class="min-w-[600px] px-1 overflow-visible">

                {{-- Day-of-week header --}}
                <div class="grid grid-cols-7 mb-2">
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $day)
                        <div @class([
                            'py-2 text-center text-xs font-semibold uppercase tracking-wider',
                            'text-gray-400' => in_array($i, [0, 6]),
                            'text-gray-500' => !in_array($i, [0, 6]),
                        ])>{{ $day }}</div>
                    @endforeach
                </div>

                {{-- Weeks --}}
                <div class="grid grid-rows-auto gap-1" style="overflow:visible;">
                    @foreach($weeks as $week)
                        <div class="grid grid-cols-7 gap-1" style="overflow:visible;">
                            @foreach($week as $colIndex => $day)
                                @php
                                    $dotColors = [
                                        'pending'     => '#fbbf24',
                                        'approved'    => '#3b82f6',
                                        'checked_in'  => '#16a34a',
                                        'checked_out' => '#94a3b8',
                                    ];
                                    $totalCount = count($day['reservations']);
                                    $isWeekend  = in_array($colIndex, [0, 6]);
                                    $tooltipRight = $colIndex >= 5;

                                    // Tooltip & dots use the same dataset: all active reservations on this day
                                    $statusCounts = [];
                                    foreach ($day['reservations'] as $res) {
                                        $statusCounts[$res['status']] = ($statusCounts[$res['status']] ?? 0) + 1;
                                    }

                                    // Dot rendering (check-in/check-out only for visual markers)
                                    $checkinReservations  = array_filter($day['reservations'], fn($r) => $r['is_checkin']);
                                    $checkoutReservations = array_filter($day['reservations'], fn($r) => $r['is_checkout'] && !$r['is_checkin']);
                                    $combinedCounts = [];
                                    foreach ($checkinReservations as $res) {
                                        $combinedCounts[$res['status']] = ($combinedCounts[$res['status']] ?? 0) + 1;
                                    }
                                    foreach ($checkoutReservations as $res) {
                                        $combinedCounts[$res['status']] = ($combinedCounts[$res['status']] ?? 0) + 1;
                                    }
                                    $checkinTotal = count($checkinReservations) + count($checkoutReservations);
                                @endphp
                                <div class="rounded-xl flex flex-col items-center py-3 px-1 min-h-[80px] transition relative"
                                    x-data="{ show: false }"
                                    @mouseenter="{{ $day['in_month'] && $checkinTotal > 0 ? 'show = true' : '' }}"
                                    @mouseleave="show = false"
                                    @if($day['is_today'])
                                        style="background-color:#ffffff;border:1px solid #f3f4f6;"
                                    @elseif(!$day['in_month'])
                                        style="background-color:rgba(249,250,251,0.6);border:1px solid rgba(243,244,246,0.6);"
                                    @elseif($isWeekend)
                                        style="background-color:#f9fafb;border:1px solid #f3f4f6;"
                                    @else
                                        style="background-color:#ffffff;border:1px solid #f3f4f6;"
                                    @endif
                                >
                                @if($day['in_month'] && $checkinTotal > 0)
                                <div x-show="show" x-transition
                                    style="position:absolute;{{ $tooltipRight ? 'right' : 'left' }}:0;top:calc(100% + 6px);width:210px;background:#1e293b;color:#f8fafc;border-radius:10px;padding:12px 14px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,0.22);pointer-events:none;">
                                    <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">
                                        {{ $day['date']->format('F j, Y') }}
                                    </div>
                                    <div style="font-size:10px;color:#64748b;margin-bottom:8px;">Check-ins &amp; Check-outs</div>
                                    <div style="display:flex;flex-direction:column;gap:5px;">
                                        @foreach([
                                            'pending'     => ['label' => 'Pending',     'color' => '#fbbf24'],
                                            'approved'    => ['label' => 'Approved',    'color' => '#3b82f6'],
                                            'checked_in'  => ['label' => 'Checked In',  'color' => '#16a34a'],
                                            'checked_out' => ['label' => 'Checked Out', 'color' => '#94a3b8'],
                                        ] as $st => $meta)
                                            @if(isset($combinedCounts[$st]))
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                                <div style="display:flex;align-items:center;gap:6px;">
                                                    <span style="width:9px;height:9px;border-radius:50%;background:{{ $meta['color'] }};flex-shrink:0;display:inline-block;"></span>
                                                    <span style="font-size:12px;color:#e2e8f0;">{{ $meta['label'] }}</span>
                                                </div>
                                                <span style="font-size:13px;font-weight:700;color:#ffffff;">{{ $combinedCounts[$st] }}</span>
                                            </div>
                                            @endif
                                        @endforeach
                                        <div style="margin-top:6px;padding-top:6px;border-top:1px solid rgba(148,163,184,0.25);display:flex;align-items:center;justify-content:space-between;">
                                            <span style="font-size:12px;color:#94a3b8;font-weight:600;">Total</span>
                                            <span style="font-size:13px;font-weight:800;color:#ffffff;">{{ $checkinTotal }}</span>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                    {{-- Day Number --}}
                                    <span class="text-sm font-bold w-7 h-7 flex items-center justify-center rounded-full mb-2"
                                        @if($day['is_today'])
                                            style="background-color:#2563eb;color:#ffffff;font-weight:900;font-size:1rem;"
                                        @elseif(!$day['in_month'])
                                            style="color:#d1d5db;"
                                        @elseif($isWeekend)
                                            style="color:#9ca3af;font-weight:400;"
                                        @else
                                            style="color:#374151;"
                                        @endif
                                    >
                                        {{ $day['date']->day }}
                                    </span>

                                    {{-- Dot indicators (check-in / check-out markers) --}}
                                    @if($checkinTotal > 0 && $day['in_month'])
                                        <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:3px;padding:0 2px;">
                                            @foreach($combinedCounts as $status => $count)
                                                @php $hexColor = $dotColors[$status] ?? '#d1d5db'; @endphp
                                                @for($d = 0; $d < min($count, 4); $d++)
                                                    <span title="{{ $count }} {{ ucfirst(str_replace('_', ' ', $status)) }}"
                                                        style="width:8px;height:8px;border-radius:50%;background-color:{{ $hexColor }};display:inline-block;flex-shrink:0;"></span>
                                                @endfor
                                            @endforeach
                                        </div>
                                        @if($checkinTotal > 4)
                                            <span class="mt-1 text-[10px] text-gray-400 font-medium leading-none">
                                                +{{ $checkinTotal - 4 }}
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
