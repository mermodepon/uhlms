@if($guests && $guests->count() > 0)
    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                        #
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                        Full Name
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                        Gender
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($guests as $index => $guest)
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                            {{ $index + 1 }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                            {{ $guest->full_name }}
                        </td>
                        <td class="px-4 py-2 text-sm">
                            @if($guest->gender)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $guest->gender === 'Male' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                       ($guest->gender === 'Female' ? 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200' : 
                                       'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300') }}">
                                    {{ $guest->gender }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
        Total: {{ $guests->count() }} guests 
        ({{ $guests->where('gender', 'Male')->count() }} male, 
        {{ $guests->where('gender', 'Female')->count() }} female)
    </p>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No guests added yet.</p>
@endif
