@php
    $plugin = \Saade\FilamentFullCalendar\FilamentFullCalendarPlugin::get();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        {{-- Toggleable Legend --}}
        <div class="flex flex-wrap items-center gap-x-1 gap-y-2 mb-4 px-1">
            @foreach([
                ['key' => 'pending', 'color' => '#fbbf24', 'label' => 'Pending'],
                ['key' => 'approved', 'color' => '#3b82f6', 'label' => 'Approved'],
                ['key' => 'pending_payment', 'color' => '#8b5cf6', 'label' => 'Pending Payment'],
                ['key' => 'checked_in', 'color' => '#16a34a', 'label' => 'Checked In'],
                ['key' => 'checked_out', 'color' => '#94a3b8', 'label' => 'Checked Out'],
            ] as $item)
                @php $isActive = in_array($item['key'], $this->activeStatuses); @endphp
                <button
                    type="button"
                    wire:click="toggleStatus('{{ $item['key'] }}')"
                    class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border transition-all duration-150 cursor-pointer select-none
                        {{ $isActive
                            ? 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800'
                            : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 opacity-40' }}"
                    title="{{ $isActive ? 'Hide' : 'Show' }} {{ $item['label'] }} reservations"
                >
                    <span style="width:12px;height:12px;border-radius:3px;background-color:{{ $item['color'] }};display:inline-block;flex-shrink:0;"></span>
                    <span class="text-xs font-medium {{ $isActive ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-500' }}">{{ $item['label'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- FullCalendar --}}
        <div class="filament-fullcalendar" wire:ignore x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('filament-fullcalendar-alpine', 'saade/filament-fullcalendar') }}"
            ax-load-css="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('filament-fullcalendar-styles', 'saade/filament-fullcalendar') }}"
            x-ignore x-data="fullcalendar({
                locale: @js($plugin->getLocale()),
                plugins: @js($plugin->getPlugins()),
                schedulerLicenseKey: @js($plugin->getSchedulerLicenseKey()),
                timeZone: @js($plugin->getTimezone()),
                config: @js($this->getConfig()),
                editable: @json($plugin->isEditable()),
                selectable: @json($plugin->isSelectable()),
                eventClassNames: {!! htmlspecialchars($this->eventClassNames(), ENT_COMPAT) !!},
                eventContent: {!! htmlspecialchars($this->eventContent(), ENT_COMPAT) !!},
                eventDidMount: {!! htmlspecialchars($this->eventDidMount(), ENT_COMPAT) !!},
                eventWillUnmount: {!! htmlspecialchars($this->eventWillUnmount(), ENT_COMPAT) !!},
            })">
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
