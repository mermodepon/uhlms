@php
    /** @var \Illuminate\Support\Collection $occupants */
@endphp

<div class="px-1 py-2">
    @if ($occupants->isEmpty())
        <div class="flex flex-col items-center justify-center py-8 text-gray-400">
            <x-heroicon-o-users class="mb-2 h-10 w-10 opacity-50" />
            <p class="text-sm font-medium">No current occupants</p>
            <p class="text-xs">No guests are currently checked in to this room.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">#</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Guest Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Checked In</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Reservation</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Purpose</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach ($occupants as $index => $occupant)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ trim(($occupant->guest_first_name ?? '') . ' ' . ($occupant->guest_last_name ?? '')) ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $occupant->checked_in_at ? $occupant->checked_in_at->format('M d, Y h:i A') : ($occupant->detailed_checkin_datetime ? $occupant->detailed_checkin_datetime->format('M d, Y h:i A') : '—') }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($occupant->reservation)
                                    <a
                                        href="{{ \App\Filament\Resources\ReservationResource::getUrl('view', ['record' => $occupant->reservation_id]) }}"
                                        target="_blank"
                                        class="font-mono text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ $occupant->reservation->reference_number ?? '#' . $occupant->reservation_id }}
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $occupant->purpose_of_stay ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-right text-xs text-gray-400">
            {{ $occupants->count() }} {{ Str::plural('guest', $occupants->count()) }} currently checked in
        </p>
    @endif
</div>
