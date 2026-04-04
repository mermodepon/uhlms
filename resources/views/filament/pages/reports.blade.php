<x-filament-panels::page>
    {{-- Print Styles --}}
    <style>
        @media print {
            /* Collapse the Filament layout shell so it produces no blank page */
            .fi-sidebar, .fi-topbar, .fi-header, .fi-breadcrumbs,
            .fi-sidebar-close-overlay, nav, header,
            [class*="fi-sidebar"], [class*="fi-topbar"],
            .fi-header-heading, .fi-page-header {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                overflow: hidden !important;
            }
            /* Flatten layout wrapper so it never generates a blank page */
            .fi-layout {
                display: block !important;
            }
            .fi-body {
                display: block !important;
            }
            .fi-main-ctn {
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
            .fi-main {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .fi-page {
                padding: 0 !important;
                max-width: 100% !important;
            }
            /* Collapse spacing so header and table start on the same page */
            #report-printable-area {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            #report-printable-area > * + * {
                margin-top: 0 !important;
            }
            #report-printable-area > div.bg-white {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            /* Override Tailwind space-y-* utilities in print */
            .space-y-6 > :not([hidden]) ~ :not([hidden]),
            .space-y-4 > :not([hidden]) ~ :not([hidden]) {
                --tw-space-y-reverse: 0;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
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
            /* Prevent tfoot from repeating on every page */
            tfoot { display: table-row-group; }

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
                size: long landscape;
                margin: 1cm 1.5cm;
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

    <div id="report-printable-area" class="space-y-6">
        {{-- Print Header (only visible when printing) --}}
        <div class="print-header" style="margin-bottom: 8px;">
            {{-- CMU Letterhead --}}
            <div style="display: flex; align-items: center; gap: 16px; padding-bottom: 10px; border-bottom: 2px solid #000;">
                <img src="{{ asset('images/cmu_logo.png') }}" alt="CMU Logo" style="height: 72px; width: auto; flex-shrink: 0;">
                <div style="line-height: 1.4;">
                    <div style="font-size: 10pt; color: #333;">Republic of the Philippines</div>
                    <div style="font-size: 13pt; font-weight: bold; color: #000; letter-spacing: 0.02em;">CENTRAL MINDANAO UNIVERSITY</div>
                    <div style="font-size: 10pt; color: #333;">Musuan, Maramag, Bukidnon</div>
                </div>
            </div>
            {{-- Secondary header (print) --}}
            <div style="text-align: center; margin-top: 14px; margin-bottom: 20px; line-height: 1.8;">
                <div style="font-size: 12pt; font-weight: bold; letter-spacing: 0.05em;">UNIVERSITY HOMESTAY</div>
                <div style="font-size: 12pt; font-weight: bold; letter-spacing: 0.05em;">
                    @switch($reportType)
                        @case('monthly_or_report') LODGING MONTHLY REPORT @break
                        @case('reservation_summary') RESERVATION SUMMARY REPORT @break
                        @case('occupancy') OCCUPANCY REPORT @break
                        @case('room_utilization') ROOM UTILIZATION REPORT @break
                        @case('stay_logs') STAY LOGS REPORT @break
                        @case('reservation_list') RESERVATION LIST @break
                        @default REPORT
                    @endswitch
                </div>
                <div style="font-size: 12pt; font-weight: bold; letter-spacing: 0.05em;">
                    @if($reportType === 'monthly_or_report')
                        FOR THE MONTH OF
                        @if($this->monthPeriod)
                            {{ strtoupper(\Carbon\Carbon::createFromFormat('Y-m', $this->monthPeriod)->format('F Y')) }}
                        @else
                            {{ strtoupper(\Carbon\Carbon::parse($dateFrom)->format('F Y')) }}
                        @endif
                    @else
                        {{ strtoupper(\Carbon\Carbon::parse($dateFrom)->format('M d, Y')) }} &mdash; {{ strtoupper(\Carbon\Carbon::parse($dateTo)->format('M d, Y')) }}
                    @endif
                </div>
            </div>
        </div>

        {{-- Filters & Actions --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 no-print">
            <div class="grid grid-cols-1 md:grid-cols-{{ $reportType === 'reservation_list' ? '4' : ($reportType === 'monthly_or_report' ? '2' : '3') }} gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Report Type</label>
                    <select wire:model.live="reportType" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-primary-500">
                        <option value="monthly_or_report">Monthly Report</option>
                        <option value="reservation_summary">Reservation Summary</option>
                        <option value="reservation_list">Reservation List</option>
                        <option value="occupancy">Occupancy Report</option>
                        <option value="room_utilization">Room Utilization</option>
                        <option value="stay_logs">Stay Logs</option>
                    </select>
                </div>
                @if($reportType === 'monthly_or_report')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Month</label>
                        <input type="month" wire:model.live="monthPeriod" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-primary-500">
                    </div>
                @endif
                @if($reportType !== 'monthly_or_report')
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                    <input type="date" wire:model.live="dateTo" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-primary-500">
                </div>
                @endif
                @if($reportType === 'reservation_list')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status Filter</label>
                        <select wire:model.live="reservationStatus" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-2 focus:ring-primary-500">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="checked_in">Checked In</option>
                            <option value="checked_out">Checked Out</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                @endif
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <button onclick="if(window.printReportNoCharts){window.printReportNoCharts()}else if(window.printReport){window.printReport()}else{window.print()}" style="background-color: #00491E; color: #FFC600; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 15px; display: inline-flex; align-items: center; gap: 10px; border: 2px solid #FFC600; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
                    <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print Report
                    </button>
                    <button onclick="(function(){ if(window.CMUCharts && window.CMUCharts.refreshAll){ window.CMUCharts.refreshAll(); } else if(window.location){ window.location.reload(); } })()" title="Refresh charts for the selected date range" style="background:#ffffff;color:#00491E;border:1px solid #00491E;border-radius:8px;padding:10px 18px;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,0.06);">
                        Refresh Charts
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
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Official Check-in</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Actual Check-out</th>
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
                                    <td class="py-2 px-3 text-center font-medium text-gray-700 dark:text-gray-300">{{ (int) $log['nights'] }}</td>
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

        {{-- Reservation List Report --}}
        @if(($data['type'] ?? '') === 'reservation_list')
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <div class="text-3xl font-bold text-primary-600">{{ $data['total'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Reservations</div>
                </div>
                @foreach($data['by_status'] ?? [] as $status => $count)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center">
                        <div class="text-2xl font-bold
                            @switch($status)
                                @case('pending') text-yellow-600 @break
                                @case('approved') text-blue-600 @break
                                @case('checked_in') text-green-600 @break
                                @case('checked_out') text-gray-600 @break
                                @case('cancelled') text-red-600 @break
                                @default text-gray-600
                            @endswitch
                        ">{{ $count }}</div>
                        <div class="text-sm text-gray-500 mt-1">{{ ucfirst(str_replace('_', ' ', $status)) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Reservation Details</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Reference</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Guest Name</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Contact</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Check-in</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Check-out</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Nights</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Occupants</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Room Type</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Assigned Rooms</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Purpose</th>
                                <th class="text-center py-2 px-3 text-gray-600 dark:text-gray-400">Status</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['reservations'] as $reservation)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-2 px-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $reservation['reference'] }}</td>
                                    <td class="py-2 px-3 font-medium text-gray-700 dark:text-gray-300">{{ $reservation['guest_name'] }}</td>
                                    <td class="py-2 px-3 text-xs text-gray-600 dark:text-gray-400">
                                        <div>{{ $reservation['guest_email'] }}</div>
                                        <div>{{ $reservation['guest_phone'] }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $reservation['check_in_date'] }}</td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $reservation['check_out_date'] }}</td>
                                    <td class="py-2 px-3 text-center font-medium text-gray-700 dark:text-gray-300">{{ $reservation['nights'] }}</td>
                                    <td class="py-2 px-3 text-center text-gray-600 dark:text-gray-400">{{ $reservation['occupants'] }}</td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $reservation['preferred_room_type'] }}</td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $reservation['assigned_rooms'] }}</td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $reservation['purpose'] }}</td>
                                    <td class="py-2 px-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            @switch($reservation['status'])
                                                @case('pending') bg-yellow-100 text-yellow-800 @break
                                                @case('approved') bg-blue-100 text-blue-800 @break
                                                @case('checked_in') bg-green-100 text-green-800 @break
                                                @case('checked_out') bg-gray-100 text-gray-800 @break
                                                @case('cancelled') bg-red-100 text-red-800 @break
                                                @default bg-gray-100 text-gray-800
                                            @endswitch
                                        ">{{ ucfirst(str_replace('_', ' ', $reservation['status'])) }}</span>
                                    </td>
                                    <td class="py-2 px-3 text-xs text-gray-600 dark:text-gray-400">{{ $reservation['created_at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="py-8 text-center text-gray-500">No reservations found for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Monthly OR Report --}}
        @if(($data['type'] ?? '') === 'monthly_or_report')
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                {{-- Secondary header visible on screen --}}
                <div class="no-print text-center mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="font-bold tracking-widest text-gray-800 dark:text-gray-100 text-sm uppercase">University Homestay</div>
                    <div class="font-bold tracking-widest text-gray-800 dark:text-gray-100 text-sm uppercase">Lodging Monthly Report</div>
                    <div class="font-bold tracking-widest text-gray-800 dark:text-gray-100 text-sm uppercase">
                        For the Month of
                        @if($this->monthPeriod)
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $this->monthPeriod)->format('F Y') }}
                        @else
                            {{ \Carbon\Carbon::parse($dateFrom)->format('F Y') }}
                        @endif
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="border-b-2 border-gray-800 dark:border-gray-600">
                                <th class="text-left py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">Date</th>
                                <th class="text-left py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">Guest Names</th>
                                <th class="text-center py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">No. of Nights / Qty</th>
                                <th class="text-left py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">Room No. / Particulars</th>
                                <th class="text-right py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">Rates</th>
                                <th class="text-center py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">No. of Pax (M/F)</th>
                                <th class="text-left py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">R.F. Number</th>
                                <th class="text-right py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">Amount</th>
                                <th class="text-left py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">O.R. Number</th>
                                <th class="text-center py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">O.R. Date</th>
                                <th class="text-right py-2 px-2 text-gray-700 dark:text-gray-300 font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['rows_by_date'] as $dateGroup)
                                @foreach($dateGroup['rows'] as $index => $row)
                                    <tr class="border-b border-gray-200 dark:border-gray-700/50">
                                        @if($index === 0)
                                            <td class="py-2 px-2 text-gray-700 dark:text-gray-300 font-medium" rowspan="{{ count($dateGroup['rows']) }}">
                                                {{ $dateGroup['date'] }}
                                            </td>
                                        @endif
                                        <td class="py-2 px-2 text-gray-700 dark:text-gray-300">
                                            {{ $row['guest_name'] }}
                                            @if(!empty($row['guest_id_number']))
                                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">ID: {{ $row['guest_id_number'] }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2 px-2 text-center text-gray-700 dark:text-gray-300">{{ $row['nights'] }}</td>
                                        <td class="py-2 px-2 text-gray-600 dark:text-gray-400 text-xs">{{ $row['room_particulars'] }}</td>
                                        <td class="py-2 px-2 text-right text-gray-700 dark:text-gray-300">₱{{ $row['rate'] }}</td>
                                        <td class="py-2 px-2 text-center text-gray-700 dark:text-gray-300">
                                            @if($row['male_count'] !== null)
                                                {{ $row['male_count'] }}/{{ $row['female_count'] }}
                                            @endif
                                        </td>
                                        <td class="py-2 px-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $row['rf_number'] }}</td>
                                        <td class="py-2 px-2 text-right font-medium text-gray-800 dark:text-gray-200">₱{{ number_format($row['amount'], 2) }}</td>
                                        <td class="py-2 px-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $row['or_number'] }}</td>
                                        <td class="py-2 px-2 text-center text-gray-600 dark:text-gray-400 text-xs">{{ $row['or_date'] }}</td>
                                        <td class="py-2 px-2 text-right font-medium text-gray-800 dark:text-gray-200">
                                            @if($row['show_total'])
                                                ₱{{ number_format($row['total'], 2) }}
                                            @else
                                                **
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                {{-- Date subtotal row --}}
                                <tr class="bg-gray-100 dark:bg-gray-700/30 border-b-2 border-gray-400 dark:border-gray-600">
                                    <td colspan="5" class="py-2 px-2 text-right font-semibold text-gray-800 dark:text-gray-200">Total Pax:</td>
                                    <td class="py-2 px-2 text-center font-bold text-primary-700 dark:text-primary-400">{{ $dateGroup['total_male'] }}/{{ $dateGroup['total_female'] }}</td>
                                    <td class="py-2 px-2 text-right font-semibold text-gray-800 dark:text-gray-200">Total Amount:</td>
                                    <td colspan="4" class="py-2 px-2 text-right font-bold text-primary-700 dark:text-primary-400">₱{{ number_format($dateGroup['total_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="py-8 text-center text-gray-500">No entries found for this month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(!empty($data['rows_by_date']))
                            <tfoot>
                                {{-- Total Pax Accommodated row --}}
                                <tr class="border-t-2 border-gray-400 dark:border-gray-600">
                                    <td colspan="5" class="py-3 px-2 text-right font-bold uppercase text-gray-900 dark:text-gray-100">
                                        Total Pax Accommodated for the month of {{ $data['month_label'] }}:
                                    </td>
                                    <td class="py-3 px-2 text-center font-bold text-gray-900 dark:text-gray-100">
                                        {{ $data['total_pax'] }}
                                    </td>
                                    <td class="py-3 px-2"></td>
                                    <td class="py-3 px-2"></td>
                                    <td class="py-3 px-2 font-bold uppercase text-right text-gray-900 dark:text-gray-100">Grand Total</td>
                                    <td class="py-3 px-2 font-bold text-right text-gray-900 dark:text-gray-100">₱</td>
                                    <td class="py-3 px-2 font-bold text-right text-gray-900 dark:text-gray-100">{{ number_format($data['grand_total'], 2) }}</td>
                                </tr>
                                {{-- Summary section --}}
                                <tr class="border-t border-gray-300 dark:border-gray-600">
                                    <td colspan="5" class="py-1 px-2 text-right font-semibold uppercase text-gray-800 dark:text-gray-200">*Domestic Male</td>
                                    <td class="py-1 px-2 text-center font-bold text-gray-900 dark:text-gray-100">{{ $data['total_domestic_male'] }}</td>
                                    <td colspan="5" class="py-1 px-2"></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="py-1 px-2 text-right font-semibold uppercase text-gray-800 dark:text-gray-200">*Domestic Female</td>
                                    <td class="py-1 px-2 text-center font-bold text-gray-900 dark:text-gray-100">{{ $data['total_domestic_female'] }}</td>
                                    <td colspan="5" class="py-1 px-2"></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="py-1 px-2 text-right font-semibold uppercase text-gray-800 dark:text-gray-200">*International Male</td>
                                    <td class="py-1 px-2 text-center font-bold text-gray-900 dark:text-gray-100">{{ $data['total_international_male'] }}</td>
                                    <td colspan="5" class="py-1 px-2"></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="py-1 px-2 text-right font-semibold uppercase text-gray-800 dark:text-gray-200">*International Female</td>
                                    <td class="py-1 px-2 text-center font-bold text-gray-900 dark:text-gray-100">{{ $data['total_international_female'] }}</td>
                                    <td colspan="5" class="py-1 px-2"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                {{-- Signatories footer (print only) --}}
                <div class="print-header" style="margin-top: 32px;">
                    @php
                        $preparedName  = \App\Models\Setting::get('signatory_prepared_name',  'GENELYN ABARQUEZ – ENSOMO');
                        $preparedTitle = \App\Models\Setting::get('signatory_prepared_title', 'LODGING SUPERVISOR');
                        $approvedName  = \App\Models\Setting::get('signatory_approved_name',  'RUBIE ANDOY - ARROYO');
                        $approvedTitle = \App\Models\Setting::get('signatory_approved_title', 'Director, University Homestay');
                    @endphp
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <div style="font-size: 9pt; margin-bottom: 24px;">Prepared by:</div>
                            <div style="font-size: 10pt; font-weight: bold;">{{ $preparedName }}</div>
                            <div style="font-size: 9pt;">{{ $preparedTitle }}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 9pt; margin-bottom: 24px;">Approved by:</div>
                            <div style="font-size: 10pt; font-weight: bold;">{{ $approvedName }}</div>
                            <div style="font-size: 9pt;">{{ $approvedTitle }}</div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 32px; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 6px;">
                        <span>CMU-F-5-OUH-028</span>
                        <span>17-Nov-21</span>
                        <span>Rev. 0</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

</x-filament-panels::page>
