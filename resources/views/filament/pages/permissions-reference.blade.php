<x-filament-panels::page>

    {{-- Role Hierarchy Cards --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 p-6">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Role Overview</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            {{-- Super Admin --}}
            <div class="rounded-xl bg-red-50 p-5 dark:bg-red-950/30">
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-red-600 text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-red-800 dark:text-red-300">Super Administrator</p>
                        <span class="inline-block text-xs bg-red-600 text-white rounded px-1.5 py-0.5">super_admin</span>
                    </div>
                </div>
                <p class="text-sm text-red-700 dark:text-red-400">
                    Unrestricted access to all features. Can manage all users including admins, assign any role,
                    and set custom permissions for other users. Cannot be restricted by custom permissions.
                </p>
            </div>

            {{-- Admin --}}
            <div class="rounded-xl bg-amber-50 p-5 dark:bg-amber-950/30">
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-amber-500 text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Administrator</p>
                        <span class="inline-block text-xs bg-amber-500 text-white rounded px-1.5 py-0.5">admin</span>
                    </div>
                </div>
                <p class="text-sm text-amber-700 dark:text-amber-400">
                    Full CRUD access to all resources. Can create and manage staff accounts.
                    Cannot view or edit other admin accounts. Cannot set custom permissions.
                </p>
            </div>

            {{-- Staff --}}
            <div class="rounded-xl bg-blue-50 p-5 dark:bg-blue-950/30">
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-blue-600 text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">Staff</p>
                        <span class="inline-block text-xs bg-blue-600 text-white rounded px-1.5 py-0.5">staff</span>
                    </div>
                </div>
                <p class="text-sm text-blue-700 dark:text-blue-400">
                    View-only access to room management resources. Can create and edit reservations
                    but cannot delete them. No access to user management or administrative configuration pages.
                </p>
            </div>

        </div>{{-- /grid --}}
    </div>{{-- /role overview container --}}

    {{-- Permission Matrix --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Default Permission Matrix</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                These are the defaults applied when a user has no custom permissions set.
                Super Admins can override these per-user from the Users management page.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800 text-left">
                        <th class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300 w-40">Resource</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300 w-28">Action</th>
                        <th class="px-4 py-3 text-center font-semibold text-red-700 dark:text-red-400 w-32">
                            <div class="flex items-center justify-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                                Super Admin
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center font-semibold text-amber-700 dark:text-amber-400 w-32">
                            <div class="flex items-center justify-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                                Admin
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center font-semibold text-blue-700 dark:text-blue-400 w-32">
                            <div class="flex items-center justify-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>
                                Staff
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                    @php
                    $yes = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-200 text-green-700 dark:bg-green-900/60 dark:text-green-400 shadow"><svg class="w-5 h-5" fill="none" stroke="currentColor" style="color:inherit" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>';
                    $no  = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-200 text-red-700 dark:bg-red-900/60 dark:text-red-400 shadow"><svg class="w-5 h-5" fill="none" stroke="currentColor" style="color:inherit" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></span>';

                    $matrix = [
                        'Reservations' => [
                            'View'   => [true,  true,  true],
                            'Create' => [true,  true,  true],
                            'Edit'   => [true,  true,  true],
                            'Delete' => [true,  true,  false],
                        ],
                        'Rooms' => [
                            'View'   => [true,  true,  true],
                            'Create' => [true,  true,  false],
                            'Edit'   => [true,  true,  false],
                            'Delete' => [true,  true,  false],
                        ],
                        'Room Types' => [
                            'View'   => [true,  true,  true],
                            'Create' => [true,  true,  false],
                            'Edit'   => [true,  true,  false],
                            'Delete' => [true,  true,  false],
                        ],
                        'Floors' => [
                            'View'   => [true,  true,  true],
                            'Create' => [true,  true,  false],
                            'Edit'   => [true,  true,  false],
                            'Delete' => [true,  true,  false],
                        ],
                        'Amenities' => [
                            'View'   => [true,  true,  true],
                            'Create' => [true,  true,  false],
                            'Edit'   => [true,  true,  false],
                            'Delete' => [true,  true,  false],
                        ],
                        'Add-Ons' => [
                            'View'   => [true,  true,  true],
                            'Create' => [true,  true,  false],
                            'Edit'   => [true,  true,  false],
                            'Delete' => [true,  true,  false],
                        ],
                        'Users' => [
                            'View'   => [true,  true,  false],
                            'Create' => [true,  true,  false],
                            'Edit'   => [true,  true,  false],
                            'Delete' => [true,  true,  false],
                        ],
                        'Stay Logs' => [
                            'View'   => [true,  true,  true],
                        ],
                    ];

                    $rowIndex = 0;
                    @endphp

                    @foreach ($matrix as $resource => $actions)
                        @php $actionCount = count($actions); $first = true; @endphp
                        @foreach ($actions as $action => $perms)
                            @php
                            $bg = $rowIndex % 2 === 0 ? '' : 'bg-gray-50/60 dark:bg-gray-800/30';
                            $rowIndex++;
                            @endphp
                            <tr class="{{ $bg }}">
                                @if ($first)
                                    <td class="px-6 py-2.5 font-medium text-gray-800 dark:text-gray-200 align-top" rowspan="{{ $actionCount }}">
                                        {{ $resource }}
                                    </td>
                                    @php $first = false; @endphp
                                @endif
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $action }}</td>
                                <td class="px-4 py-2.5 text-center">{!! $perms[0] ? $yes : $no !!}</td>
                                <td class="px-4 py-2.5 text-center">{!! $perms[1] ? $yes : $no !!}</td>
                                <td class="px-4 py-2.5 text-center">{!! $perms[2] ? $yes : $no !!}</td>
                            </tr>
                        @endforeach
                    @endforeach

                </tbody>
            </table>
        </div>
    </div>

    {{-- Notes --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 p-6 space-y-3">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Notes</h2>
        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400 list-none">
            <li class="flex gap-2">
                <svg class="w-4 h-4 mt-0.5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
                </svg>
                <span><strong class="text-gray-800 dark:text-gray-200">Admins managing Users:</strong>
                    Admins can only view and manage <em>Staff</em> accounts. They cannot see, edit, or delete
                    other Admin or Super Admin accounts — even if they are granted full user permissions.</span>
            </li>
            <li class="flex gap-2">
                <svg class="w-4 h-4 mt-0.5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
                </svg>
                <span><strong class="text-gray-800 dark:text-gray-200">Custom Permissions:</strong>
                    Super Admins can override the defaults above on a per-user basis via
                    <em>Configuration → Users → Edit User → Custom Permissions</em>.
                    Enabling custom permissions for a user completely replaces their role-based defaults.</span>
            </li>
            <li class="flex gap-2">
                <svg class="w-4 h-4 mt-0.5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
                </svg>
                <span><strong class="text-gray-800 dark:text-gray-200">Self-deletion prevention:</strong>
                    No user — regardless of role or permissions — can delete their own account.</span>
            </li>
            <li class="flex gap-2">
                <svg class="w-4 h-4 mt-0.5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
                </svg>
                <span><strong class="text-gray-800 dark:text-gray-200">Linked users:</strong>
                    Users linked to existing room assignments or reviewed reservations cannot be deleted
                    to preserve data integrity.</span>
            </li>
            <li class="flex gap-2">
                <svg class="w-4 h-4 mt-0.5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-9a3 3 0 100 6 3 3 0 000-6z"/>
                </svg>
                <span><strong class="text-gray-800 dark:text-gray-200">Super Admin is always unrestricted:</strong>
                    Custom permissions have no effect on Super Admin accounts. They always retain full access.</span>
            </li>
        </ul>
    </div>

</x-filament-panels::page>
