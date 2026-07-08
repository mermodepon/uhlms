<x-filament-panels::page>
    @php
        $data = $this->calendarData;
        $summary = $data['summary'];
        $statusLabels = [
            '' => 'All statuses',
            'available' => 'Available',
            'reserved' => 'Reserved',
            'occupied' => 'Occupied',
            'maintenance' => 'Under Maintenance',
            'inactive' => 'Inactive',
        ];
        $typeLabels = [
            'holds' => 'Held rooms',
            'assignments' => 'Checked-in/out',
            'room_states' => 'Maintenance/inactive',
            'unassigned' => 'Unassigned requests',
        ];
    @endphp

    <style>
        .util-calendar-shell {
            border: 1px solid rgb(229 231 235);
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }
        .dark .util-calendar-shell {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
        }
        .util-calendar-scroll {
            overflow: auto;
            max-height: 72vh;
        }
        .util-calendar-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
        }
        .util-calendar-table th,
        .util-calendar-table td {
            border-right: 1px solid rgb(229 231 235);
            border-bottom: 1px solid rgb(229 231 235);
            vertical-align: top;
        }
        .dark .util-calendar-table th,
        .dark .util-calendar-table td {
            border-color: rgb(55 65 81);
        }
        .util-room-head,
        .util-room-cell {
            position: sticky;
            left: 0;
            z-index: 2;
            width: 230px;
            min-width: 230px;
            max-width: 230px;
            background: white;
        }
        .dark .util-room-head,
        .dark .util-room-cell {
            background: rgb(31 41 55);
        }
        .util-date-head {
            position: sticky;
            top: 0;
            z-index: 1;
            width: 128px;
            min-width: 128px;
            background: rgb(249 250 251);
        }
        .dark .util-date-head {
            background: rgb(17 24 39);
        }
        .util-room-head {
            top: 0;
            z-index: 3;
            background: rgb(249 250 251);
        }
        .dark .util-room-head {
            background: rgb(17 24 39);
        }
        .util-cell {
            width: 128px;
            min-width: 128px;
            height: 86px;
            padding: 6px;
            background: white;
        }
        .dark .util-cell {
            background: rgb(31 41 55);
        }
        .util-cell-today {
            background: rgb(239 246 255);
        }
        .dark .util-cell-today {
            background: rgb(30 58 95);
        }
        .util-event {
            display: block;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border-radius: 6px;
            padding: 3px 6px;
            color: white;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
        }
    </style>

    <div class="space-y-4">
        <div class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 lg:grid-cols-6">
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">From</span>
                <input type="date" wire:model.live="dateFrom" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
            </label>
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">To</span>
                <input type="date" wire:model.live="dateTo" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
            </label>
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Floor</span>
                <select wire:model.live="floorId" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">All floors</option>
                    @foreach($this->floorOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Room Type</span>
                <select wire:model.live="roomTypeId" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">All room types</option>
                    @foreach($this->roomTypeOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Status</span>
                <select wire:model.live="roomStatus" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button type="button" wire:click="previousRange" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Prev
                </button>
                <button type="button" wire:click="nextRange" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Next
                </button>
                <button type="button" wire:click="resetFilters" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-primary-700">
                    Reset
                </button>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-4">
                @foreach($typeLabels as $key => $label)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model.live="visibleTypes" value="{{ $key }}" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['label' => 'Rooms', 'value' => $summary['rooms']],
                ['label' => 'Capacity', 'value' => $summary['capacity']],
                ['label' => 'Held Slots', 'value' => $summary['held_slots']],
                ['label' => 'Occupied Slots', 'value' => $summary['occupied_slots']],
                ['label' => 'Maintenance', 'value' => $summary['maintenance_rooms']],
                ['label' => 'Unassigned', 'value' => $summary['unassigned_reservations']],
            ] as $item)
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $item['label'] }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="util-calendar-shell">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">
                {{ $summary['period_label'] }}
            </div>
            <div class="util-calendar-scroll">
                <table class="util-calendar-table">
                    <thead>
                        <tr>
                            <th class="util-room-head px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Room</th>
                            @foreach($data['dates'] as $date)
                                <th class="util-date-head px-2 py-2 text-center {{ $date['is_today'] ? 'text-primary-700 dark:text-primary-200' : 'text-gray-600 dark:text-gray-300' }}">
                                    <div class="font-semibold">{{ $date['label'] }}</div>
                                    <div class="text-[11px]">{{ $date['weekday'] }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['rows'] as $row)
                            <tr>
                                <td class="util-room-cell px-3 py-3">
                                    <a href="{{ $row['url'] }}" class="font-semibold text-primary-700 hover:underline dark:text-primary-300">
                                        Room {{ $row['room_number'] }}
                                    </a>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['room_type'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['floor'] }} &bull; {{ $row['capacity'] }} slots</div>
                                    <div class="mt-2 inline-flex rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                        {{ ucfirst(str_replace('_', ' ', $row['status'])) }}
                                    </div>
                                </td>
                                @foreach($data['dates'] as $date)
                                    @php $cell = $row['cells'][$date['key']]; @endphp
                                    <td class="util-cell {{ $date['is_today'] ? 'util-cell-today' : '' }}">
                                        @if($cell['slot_summary']['label'])
                                            <div class="mb-1 text-[11px] font-medium text-gray-500 dark:text-gray-300">{{ $cell['slot_summary']['label'] }}</div>
                                        @endif
                                        <div class="space-y-1">
                                            @forelse($cell['events'] as $event)
                                                @if($event['url'])
                                                    <a href="{{ $event['url'] }}" class="util-event" style="background-color: {{ $event['color'] }}" title="{{ $event['label'] }}">
                                                        {{ $event['label'] }}
                                                    </a>
                                                @else
                                                    <span class="util-event" style="background-color: {{ $event['color'] }}" title="{{ $event['label'] }}">
                                                        {{ $event['label'] }}
                                                    </span>
                                                @endif
                                            @empty
                                                <span class="text-[11px] text-gray-300 dark:text-gray-600">Open</span>
                                            @endforelse
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($data['dates']) + 1 }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No rooms match the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(in_array('unassigned', $this->visibleTypes, true))
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Unassigned Requests</h3>
                </div>
                <div class="p-4">
                    {{ $this->table }}
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
