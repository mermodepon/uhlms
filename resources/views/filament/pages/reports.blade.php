<x-filament-panels::page>
    {{-- Print Styles --}}
    <style>
        @media print {
            /* Hide everything in the Filament shell */
            .fi-sidebar, .fi-topbar, .fi-header, .fi-breadcrumbs,
            .fi-sidebar-close-overlay, nav, header,
            [class*="fi-sidebar"], [class*="fi-topbar"],
            .fi-header-heading, .fi-page-header {
                display: none !important;
            }
            .fi-main {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .fi-main-ctn, .fi-page {
                padding: 0 !important;
                max-width: 100% !important;
            }

            /* Hide non-printable elements */
            .no-print, .no-print * {
                display: none !important;
            }

            /* Show print header */
            .print-header {
                display: block !important;
            }

            /* Remove all horizontal scrollbars */
            .overflow-x-auto {
                overflow-x: visible !important;
            }
            *, *::before, *::after {
                overflow-x: visible !important;
            }
            table {
                width: 100% !important;
                table-layout: auto !important;
            }

            /* Clean styling for print */
            body {
                background: white !important;
                color: black !important;
                font-size: 10pt !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .bg-white, [class*="dark:bg-gray"] {
                background: white !important;
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            .text-primary-600 { color: #00491E !important; }
            .text-green-600 { color: #02681E !important; }
            .text-blue-600 { color: #1d4ed8 !important; }
            .text-red-600 { color: #dc2626 !important; }
            .text-amber-600 { color: #d97706 !important; }

            /* Progress bars print with color */
            .bg-green-500, .bg-red-500, .bg-amber-500, .bg-primary-500 {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Badges print with color */
            [class*="bg-yellow-100"], [class*="bg-blue-100"], [class*="bg-red-100"],
            [class*="bg-gray-100"], [class*="bg-green-100"], [class*="bg-purple-100"],
            [class*="bg-amber-100"] {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Page breaks */
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }

            /* Charts: hide canvas, show generated images */
            canvas {
                display: none !important;
            }
            .chart-print-img {
                display: block !important;
                max-width: 100% !important;
                height: auto !important;
            }
            .chart-container {
                page-break-inside: avoid;
            }

            @page {
                size: A4 landscape;
                margin: 1.5cm;
            }
        }

        /* Print header hidden on screen */
        .print-header {
            display: none;
        }
        /* Chart print images hidden on screen */
        .chart-print-img {
            display: none;
        }
    </style>

    <div class="space-y-6">
        {{-- Print Header (only visible when printing) --}}
        <div class="print-header" style="text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #00491E;">
            <h1 style="font-size: 18pt; font-weight: bold; color: #00491E; margin: 0;">
                Central Mindanao University - University Homestay
            </h1>
            <h2 style="font-size: 14pt; font-weight: 600; color: #333; margin: 4px 0;">
                @switch($reportType)
                    @case('reservation_summary') Reservation Summary Report @break
                    @case('occupancy') Occupancy Report @break
                    @case('room_utilization') Room Utilization Report @break
                    @case('stay_logs') Stay Logs Report @break
                @endswitch
            </h2>
            <p style="font-size: 10pt; color: #666; margin: 2px 0;">
                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('F d, Y') }} &mdash; {{ \Carbon\Carbon::parse($dateTo)->format('F d, Y') }}
            </p>
            <p style="font-size: 9pt; color: #999; margin: 2px 0;">
                Generated: {{ now()->format('F d, Y h:i A') }}
            </p>
        </div>

        {{-- Filters & Actions --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 no-print">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Report Type</label>
                    <select wire:model.live="reportType" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-primary-500">
                        <option value="reservation_summary">Reservation Summary</option>
                        <option value="occupancy">Occupancy Report</option>
                        <option value="room_utilization">Room Utilization</option>
                        <option value="stay_logs">Stay Logs</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                    <input type="date" wire:model.live="dateTo" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <button onclick="(function(){ if(window.CMUCharts && window.CMUCharts.refreshAll){ window.CMUCharts.refreshAll(); } else if(window.location){ window.location.reload(); } })()" title="Refresh charts for the selected date range" style="background:#ffffff;color:#00491E;border:1px solid #00491E;border-radius:8px;padding:10px 18px;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,0.06);">
                        Refresh Charts
                    </button>
                    <button onclick="if(window.printReportNoCharts){window.printReportNoCharts()}else if(window.printReport){window.printReport()}else{window.print()}" style="background-color: #00491E; color: #FFC600; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 15px; display: inline-flex; align-items: center; gap: 10px; border: 2px solid #FFC600; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
                    <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print Report
                    </button>
                </div>
            </div>
        </div>

        @php
            $data = $this->reportData;
            $periodTitle = 'Period: ' . \Carbon\Carbon::parse($dateFrom)->format('M d, Y') . ' — ' . \Carbon\Carbon::parse($dateTo)->format('M d, Y');
        @endphp

        {{-- Reservation Summary Report --}}
        @if(($data['type'] ?? '') === 'reservation_summary')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-primary-600">{{ $data['total'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Reservations</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-green-600">{{ $data['total_guest_nights'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Guest-Nights</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-blue-600">{{ $data['avg_occupants'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Avg. Occupants per Reservation</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- By Status --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">By Status</h3>
                    <div class="space-y-3">
                        @foreach($data['by_status'] as $status => $count)
                            <div class="flex justify-between items-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($status)
                                        @case('pending') bg-yellow-100 text-yellow-800 @break
                                        @case('approved') bg-blue-100 text-blue-800 @break
                                        @case('declined') bg-red-100 text-red-800 @break
                                        @case('cancelled') bg-gray-100 text-gray-800 @break
                                        @case('checked_in') bg-green-100 text-green-800 @break
                                        @case('checked_out') bg-purple-100 text-purple-800 @break
                                    @endswitch">
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </span>
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- By Purpose --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">By Purpose</h3>
                    <div class="space-y-3">
                        @foreach($data['by_purpose'] as $purpose => $count)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400 capitalize">{{ $purpose }}</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- By Room Type --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">By Room Type</h3>
                    <div class="space-y-3">
                        @foreach($data['by_room_type'] as $type => $count)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400">{{ $type }}</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Charts Row --}}
            @php
                $statusColors = ['pending' => '#F59E0B', 'approved' => '#3B82F6', 'declined' => '#EF4444', 'cancelled' => '#6B7280', 'checked_in' => '#10B981', 'checked_out' => '#8B5CF6'];
                $statusLabels = array_values(array_map(fn($s) => ucwords(str_replace('_', ' ', $s)), array_keys($data['by_status'])));
                $statusBgColors = array_values(array_map(fn($s) => $statusColors[$s] ?? '#6B7280', array_keys($data['by_status'])));
                $purposeLabels = array_values(array_map(fn($s) => ucwords($s), array_keys($data['by_purpose'])));

                $chartStatusDoughnut = json_encode([
                    'type' => 'doughnut',
                    'data' => ['labels' => $statusLabels, 'datasets' => [['data' => array_values($data['by_status']), 'backgroundColor' => $statusBgColors, 'borderWidth' => 2, 'borderColor' => '#fff']]],
                    'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom'], 'title' => ['display' => true, 'text' => $periodTitle]]],
                ]);
                $chartPurposeBar = json_encode([
                    'type' => 'bar',
                    'data' => ['labels' => $purposeLabels, 'datasets' => [['label' => 'Reservations', 'data' => array_values($data['by_purpose']), 'backgroundColor' => ['#00491E', '#02681E', '#919F02', '#FFC600', '#3B82F6', '#8B5CF6'], 'borderRadius' => 6]]],
                    'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false], 'title' => ['display' => true, 'text' => $periodTitle]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]]]],
                ]);
                // dynamic colors for room types
                $roomTypeLabels = array_values(array_keys($data['by_room_type']));
                $palette = ['#00491E','#02681E','#919F02','#FFC600','#3B82F6','#EF4444','#A78BFA','#F97316','#06B6D4','#8B5CF6'];
                $roomTypeColors = [];
                foreach($roomTypeLabels as $i => $label) {
                    $roomTypeColors[] = $palette[$i % count($palette)];
                }

                $chartRoomTypeBar = json_encode([
                    'type' => 'bar',
                    'data' => ['labels' => $roomTypeLabels, 'datasets' => [['label' => 'Reservations', 'data' => array_values($data['by_room_type']), 'backgroundColor' => $roomTypeColors, 'borderRadius' => 6]]],
                    'options' => ['indexAxis' => 'y', 'responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false], 'title' => ['display' => true, 'text' => $periodTitle]], 'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]]]],
                ]);
            @endphp
            <div wire:key="res-charts-{{ md5(json_encode($data)) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 chart-container" x-data x-init="$nextTick(() => CMUCharts.init($el))">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reservations by Status</h3>
                            <button onclick="CMUCharts.print(this.closest('.chart-container'))" class="no-print" style="background:#00491E;color:#FFC600;border:1px solid #FFC600;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:12px;display:inline-flex;align-items:center;gap:4px;" title="Print this chart">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print
                            </button>
                        </div>
                        <div style="position: relative; height: 280px;"><canvas></canvas></div>
                        <script type="application/json" data-chart>{!! $chartStatusDoughnut !!}</script>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 chart-container" x-data x-init="$nextTick(() => CMUCharts.init($el))">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reservations by Purpose</h3>
                            <button onclick="CMUCharts.print(this.closest('.chart-container'))" class="no-print" style="background:#00491E;color:#FFC600;border:1px solid #FFC600;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:12px;display:inline-flex;align-items:center;gap:4px;" title="Print this chart">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print
                            </button>
                        </div>
                        <div style="position: relative; height: 280px;"><canvas></canvas></div>
                        <script type="application/json" data-chart>{!! $chartPurposeBar !!}</script>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 chart-container" x-data x-init="$nextTick(() => CMUCharts.init($el))">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reservations by Room Type</h3>
                        <button onclick="CMUCharts.print(this.closest('.chart-container'))" class="no-print" style="background:#00491E;color:#FFC600;border:1px solid #FFC600;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:12px;display:inline-flex;align-items:center;gap:4px;" title="Print this chart">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print
                        </button>
                    </div>
                    <div style="position: relative; height: 280px;"><canvas></canvas></div>
                    <script type="application/json" data-chart>{!! $chartRoomTypeBar !!}</script>
                </div>
            </div>
        @endif

        {{-- Occupancy Report --}}
        @if(($data['type'] ?? '') === 'occupancy')
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-primary-600">{{ $data['total_rooms'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Active Rooms</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-green-600">{{ $data['available_now'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Available Now</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-red-600">{{ $data['occupied_now'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Occupied Now</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-amber-600">{{ $data['current_rate'] }}%</div>
                    <div class="text-sm text-gray-500 mt-1">Current Occupancy Rate</div>
                </div>
            </div>

            {{-- Daily Occupancy Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Daily Occupancy Trend</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Date</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Rooms Occupied</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Rate</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Visual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['daily'] as $day)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300">{{ $day['date'] }}</td>
                                    <td class="py-2 px-3 text-center text-gray-700 dark:text-gray-300">{{ $day['occupied'] }}</td>
                                    <td class="py-2 px-3 text-center font-medium text-gray-700 dark:text-gray-300">{{ $day['rate'] }}%</td>
                                    <td class="py-2 px-3">
                                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-3">
                                            <div class="h-3 rounded-full {{ $day['rate'] > 80 ? 'bg-red-500' : ($day['rate'] > 50 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ min($day['rate'], 100) }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Occupancy Charts --}}
            @php
                $chartOccLine = json_encode([
                    'type' => 'line',
                    'data' => [
                        'labels' => array_column($data['daily'], 'date'),
                        'datasets' => [[
                            'label' => 'Occupancy Rate (%)',
                            'data' => array_column($data['daily'], 'rate'),
                            'borderColor' => '#00491E',
                            'backgroundColor' => 'rgba(0, 73, 30, 0.1)',
                            'fill' => true, 'tension' => 0.3,
                            'pointBackgroundColor' => '#FFC600',
                            'pointBorderColor' => '#00491E',
                            'pointRadius' => 3, 'borderWidth' => 2,
                        ]],
                    ],
                    'options' => [
                        'responsive' => true, 'maintainAspectRatio' => false,
                        '_yPercent' => true,
                        'scales' => ['y' => ['beginAtZero' => true, 'max' => 100]],
                        'plugins' => ['legend' => ['display' => false], 'title' => ['display' => true, 'text' => $periodTitle]],
                    ],
                ]);
                $chartRoomStatusDoughnut = json_encode([
                    'type' => 'doughnut',
                    'data' => [
                        'labels' => ['Available', 'Occupied', 'Maintenance'],
                        'datasets' => [[
                            'data' => [$data['available_now'], $data['occupied_now'], $data['maintenance_now']],
                            'backgroundColor' => ['#10B981', '#EF4444', '#F59E0B'],
                            'borderWidth' => 2, 'borderColor' => '#fff',
                        ]],
                    ],
                    'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom'], 'title' => ['display' => true, 'text' => $periodTitle]]],
                ]);
            @endphp
            <div wire:key="occ-charts-{{ md5(json_encode($data)) }}">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 chart-container" x-data x-init="$nextTick(() => CMUCharts.init($el))">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Daily Occupancy Rate Trend</h3>
                            <button onclick="CMUCharts.print(this.closest('.chart-container'))" class="no-print" style="background:#00491E;color:#FFC600;border:1px solid #FFC600;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:12px;display:inline-flex;align-items:center;gap:4px;" title="Print this chart">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print
                            </button>
                        </div>
                        <div style="position: relative; height: 300px;"><canvas></canvas></div>
                        <script type="application/json" data-chart>{!! $chartOccLine !!}</script>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 chart-container" x-data x-init="$nextTick(() => CMUCharts.init($el))">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Current Room Status</h3>
                            <button onclick="CMUCharts.print(this.closest('.chart-container'))" class="no-print" style="background:#00491E;color:#FFC600;border:1px solid #FFC600;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:12px;display:inline-flex;align-items:center;gap:4px;" title="Print this chart">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print
                            </button>
                        </div>
                        <div style="position: relative; height: 300px;"><canvas></canvas></div>
                        <script type="application/json" data-chart>{!! $chartRoomStatusDoughnut !!}</script>
                    </div>
                </div>
            </div>
        @endif

        {{-- Room Utilization Report --}}
        @if(($data['type'] ?? '') === 'room_utilization')
            {{-- By Room Type Summary --}}
            <div class="grid grid-cols-1 md:grid-cols-{{ count($data['by_type']) }} gap-4">
                @foreach($data['by_type'] as $type)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $type['name'] }}</div>
                        <div class="text-2xl font-bold text-primary-600 mt-2">{{ $type['total_stays'] }}</div>
                        <div class="text-sm text-gray-500">stays ({{ $type['room_count'] }} rooms)</div>
                    </div>
                @endforeach
            </div>

            {{-- Room Details --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Room-by-Room Utilization</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Room</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Type</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Status</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Total Stays</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Days Occupied</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Utilization</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['rooms'] as $room)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-2 px-3 font-medium text-gray-700 dark:text-gray-300">{{ $room['room'] }}</td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $room['type'] }}</td>
                                    <td class="py-2 px-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            @switch($room['status'])
                                                @case('available') bg-green-100 text-green-800 @break
                                                @case('occupied') bg-blue-100 text-blue-800 @break
                                                @case('maintenance') bg-amber-100 text-amber-800 @break
                                                @case('inactive') bg-gray-100 text-gray-800 @break
                                            @endswitch">
                                            {{ ucfirst($room['status']) }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-center text-gray-700 dark:text-gray-300">{{ $room['total_stays'] }}</td>
                                    <td class="py-2 px-3 text-center text-gray-700 dark:text-gray-300">{{ $room['days_occupied'] }}</td>
                                    <td class="py-2 px-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="w-16 bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                                <div class="h-2 rounded-full bg-primary-500" style="width: {{ min($room['utilization_rate'], 100) }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-500">{{ $room['utilization_rate'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Utilization Charts --}}
            @php
                $barColors = array_values(array_map(function($r) {
                    if ($r['utilization_rate'] >= 70) return '#00491E';
                    if ($r['utilization_rate'] >= 40) return '#02681E';
                    if ($r['utilization_rate'] > 0) return '#919F02';
                    return '#D1D5DB';
                }, $data['rooms']));

                $chartUtilBar = json_encode([
                    'type' => 'bar',
                    'data' => [
                        'labels' => array_column($data['rooms'], 'room'),
                        'datasets' => [[
                            'label' => 'Utilization %',
                            'data' => array_column($data['rooms'], 'utilization_rate'),
                            'backgroundColor' => $barColors,
                            'borderRadius' => 4,
                        ]],
                    ],
                    'options' => [
                        'responsive' => true, 'maintainAspectRatio' => false,
                        '_yPercent' => true,
                        'scales' => ['y' => ['beginAtZero' => true, 'max' => 100]],
                        'plugins' => ['legend' => ['display' => false], 'title' => ['display' => true, 'text' => $periodTitle]],
                    ],
                ]);
                // dynamic colors for stays-by-type pie
                $staysLabels = array_column($data['by_type'], 'name');
                $staysData = array_column($data['by_type'], 'total_stays');
                $palette = ['#00491E','#02681E','#919F02','#FFC600','#3B82F6','#EF4444','#A78BFA','#F97316','#06B6D4','#8B5CF6'];
                $staysColors = [];
                foreach($staysLabels as $i => $label) {
                    $staysColors[] = $palette[$i % count($palette)];
                }

                $chartStaysPie = json_encode([
                    'type' => 'pie',
                    'data' => [
                        'labels' => $staysLabels,
                        'datasets' => [[
                            'data' => $staysData,
                            'backgroundColor' => $staysColors,
                            'borderWidth' => 2, 'borderColor' => '#fff',
                        ]],
                    ],
                    'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom'], 'title' => ['display' => true, 'text' => $periodTitle]]],
                ]);
            @endphp
            <div wire:key="util-charts-{{ md5(json_encode($data)) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 chart-container" x-data x-init="$nextTick(() => CMUCharts.init($el))">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Utilization Rate by Room</h3>
                            <button onclick="CMUCharts.print(this.closest('.chart-container'))" class="no-print" style="background:#00491E;color:#FFC600;border:1px solid #FFC600;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:12px;display:inline-flex;align-items:center;gap:4px;" title="Print this chart">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print
                            </button>
                        </div>
                        <div style="position: relative; height: 300px;"><canvas></canvas></div>
                        <script type="application/json" data-chart>{!! $chartUtilBar !!}</script>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 chart-container" x-data x-init="$nextTick(() => CMUCharts.init($el))">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Stays by Room Type</h3>
                            <button onclick="CMUCharts.print(this.closest('.chart-container'))" class="no-print" style="background:#00491E;color:#FFC600;border:1px solid #FFC600;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:12px;display:inline-flex;align-items:center;gap:4px;" title="Print this chart">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                Print
                            </button>
                        </div>
                        <div style="position: relative; height: 300px;"><canvas></canvas></div>
                        <script type="application/json" data-chart>{!! $chartStaysPie !!}</script>
                    </div>
                </div>
            </div>
        @endif

        {{-- Stay Logs Report --}}
        @if(($data['type'] ?? '') === 'stay_logs')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-primary-600">{{ $data['total_stays'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Stays</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-green-600">{{ $data['completed'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Completed</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-blue-600">{{ $data['ongoing'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Still Checked In</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stay Log Details</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Reference</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Guest</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Room</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Checked In</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Checked Out</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Nights</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['logs'] as $log)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-2 px-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $log['reference'] }}</td>
                                    <td class="py-2 px-3 font-medium text-gray-700 dark:text-gray-300">{{ $log['guest'] }}</td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $log['room'] }} ({{ $log['room_type'] }})</td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                                        <div>{{ $log['checked_in'] }}</div>
                                        <div class="text-xs text-gray-400">by {{ $log['checked_in_by'] }}</div>
                                    </td>
                                    <td class="py-2 px-3">
                                        @if($log['checked_out'] === 'Still checked in')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Still checked in</span>
                                        @else
                                            <div class="text-gray-600 dark:text-gray-400">{{ $log['checked_out'] }}</div>
                                            <div class="text-xs text-gray-400">by {{ $log['checked_out_by'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-center font-medium text-gray-700 dark:text-gray-300">{{ $log['nights'] }}</td>
                                    <td class="py-2 px-3 text-gray-500 text-xs max-w-xs truncate">{{ $log['remarks'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-gray-500">No stay logs found for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

</x-filament-panels::page>
