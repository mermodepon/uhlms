<?php

namespace App\Filament\Pages;

use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationPayment;
use App\Models\ReservationFeedback;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page
{
    protected const REPORT_TYPE = 'monthly_or_report';

    protected const REPORT_PERMISSION = User::REPORT_MONTHLY_VIEW;

    protected const EXPORT_PERMISSION = User::REPORT_MONTHLY_EXPORT;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Monthly Report';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reports';

    public string $reportType = 'monthly_or_report';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $reservationStatus = null;

    public ?string $monthPeriod = null;

    public int $monthlyReportPage = 1;

    public int $monthlyReportPerPage = 10;

    public int $reservationListPage = 1;

    public int $reservationListPerPage = 25;

    public int $occupancyPage = 1;

    public int $occupancyPerPage = 10;

    public int $roomUtilizationPage = 1;

    public int $roomUtilizationPerPage = 10;

    public int $stayLogsPage = 1;

    public int $stayLogsPerPage = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission(static::REPORT_PERMISSION) ?? false;
    }

    public function mount(): void
    {
        $this->reportType = static::REPORT_TYPE;
        $this->dateFrom = Carbon::today()->subDays(30)->format('Y-m-d');
        $this->dateTo = Carbon::today()->format('Y-m-d');
        $this->reservationStatus = null; // All statuses
        $this->monthPeriod = Carbon::today()->format('Y-m');
    }

    public function getTitle(): string
    {
        return static::$navigationLabel ?? 'Reports';
    }

    public function updatedMonthPeriod(?string $value): void
    {
        $this->monthlyReportPage = 1;

        if (! $value) {
            return;
        }

        $monthStart = Carbon::createFromFormat('Y-m', $value)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Keep the date range in sync so print headers and period metadata stay consistent.
        $this->dateFrom = $monthStart->format('Y-m-d');
        $this->dateTo = $monthEnd->format('Y-m-d');
    }

    public function updatedReportType(): void
    {
        $this->monthlyReportPage = 1;
        $this->reservationListPage = 1;
        $this->occupancyPage = 1;
        $this->roomUtilizationPage = 1;
        $this->stayLogsPage = 1;
    }

    public function updatedDateFrom(): void
    {
        $this->reservationListPage = 1;
        $this->occupancyPage = 1;
        $this->roomUtilizationPage = 1;
        $this->stayLogsPage = 1;
    }

    public function updatedDateTo(): void
    {
        $this->reservationListPage = 1;
        $this->occupancyPage = 1;
        $this->roomUtilizationPage = 1;
        $this->stayLogsPage = 1;
    }

    public function updatedReservationStatus(): void
    {
        $this->reservationListPage = 1;
    }

    public function previousMonthlyReportPage(): void
    {
        $this->monthlyReportPage = max(1, $this->monthlyReportPage - 1);
    }

    public function nextMonthlyReportPage(): void
    {
        $this->monthlyReportPage++;
    }

    public function previousReservationListPage(): void
    {
        $this->reservationListPage = max(1, $this->reservationListPage - 1);
    }

    public function nextReservationListPage(): void
    {
        $this->reservationListPage++;
    }

    public function previousOccupancyPage(): void
    {
        $this->occupancyPage = max(1, $this->occupancyPage - 1);
    }

    public function nextOccupancyPage(): void
    {
        $this->occupancyPage++;
    }

    public function previousRoomUtilizationPage(): void
    {
        $this->roomUtilizationPage = max(1, $this->roomUtilizationPage - 1);
    }

    public function nextRoomUtilizationPage(): void
    {
        $this->roomUtilizationPage++;
    }

    public function previousStayLogsPage(): void
    {
        $this->stayLogsPage = max(1, $this->stayLogsPage - 1);
    }

    public function nextStayLogsPage(): void
    {
        $this->stayLogsPage++;
    }

    public function downloadMonthlyReportExcel(): StreamedResponse
    {
        abort_unless(
            auth()->user()?->hasPermission(static::REPORT_PERMISSION)
                && auth()->user()?->hasPermission(static::EXPORT_PERMISSION),
            403,
        );

        $data = $this->getMonthlyOrReport();
        $month = $this->monthPeriod
            ? Carbon::createFromFormat('Y-m', $this->monthPeriod)
            : Carbon::today();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monthly Report');

        $logoPath = public_path('images/cmu_logo.png');
        if (is_file($logoPath)) {
            $logo = new Drawing();
            $logo->setName('CMU Logo');
            $logo->setPath($logoPath);
            $logo->setCoordinates('A1');
            $logo->setHeight(58);
            $logo->setOffsetX(8);
            $logo->setOffsetY(4);
            $logo->setWorksheet($sheet);
        }

        $sheet->mergeCells('B1:K1');
        $sheet->setCellValue('B1', 'Republic of the Philippines');
        $sheet->mergeCells('B2:K2');
        $sheet->setCellValue('B2', 'CENTRAL MINDANAO UNIVERSITY');
        $sheet->mergeCells('B3:K3');
        $sheet->setCellValue('B3', 'Musuan, Maramag, Bukidnon');
        $sheet->mergeCells('A4:K4');
        $sheet->getStyle('A4:K4')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        $sheet->mergeCells('A6:K6');
        $sheet->setCellValue('A6', 'UNIVERSITY HOMESTAY');
        $sheet->mergeCells('A7:K7');
        $sheet->setCellValue('A7', 'LODGING MONTHLY REPORT');
        $sheet->mergeCells('A8:K8');
        $sheet->setCellValue('A8', 'FOR THE MONTH OF '.strtoupper($data['month_label']));

        $sheet->getStyle('B1:B3')->getFont()->setSize(10);
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A6:A8')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A6:A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(18);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(18);
        $sheet->getRowDimension(4)->setRowHeight(8);

        $headers = [
            'Date',
            'Guest Names',
            'No. of Nights / Qty',
            'Room No. / Particulars',
            'Rates',
            'No. of Pax (M/F)',
            'R.F. Number',
            'Amount',
            'O.R. Number',
            'O.R. Date',
            'Total',
        ];

        $headerRow = 10;
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$headerRow, $header);
        }

        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM],
            ],
        ]);
        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->getAlignment()->setWrapText(true);

        $rowNumber = $headerRow + 1;
        foreach ($data['rows_by_date'] ?? [] as $dateGroup) {
            $firstRowForDate = $rowNumber;
            $dateGroupRowCount = count($dateGroup['rows']);

            foreach ($dateGroup['rows'] as $row) {
                $sheet->setCellValue("A{$rowNumber}", $rowNumber === $firstRowForDate ? $dateGroup['date'] : '');
                $guestName = $row['guest_name'];
                if (! empty($row['guest_id_number'])) {
                    $guestName .= "\nID: ".$row['guest_id_number'];
                }
                $sheet->setCellValue("B{$rowNumber}", $guestName);
                $sheet->setCellValue("C{$rowNumber}", $row['nights']);
                $sheet->setCellValue("D{$rowNumber}", $row['room_particulars']);
                if ($row['rate'] === '-') {
                    $sheet->setCellValue("E{$rowNumber}", '-');
                } else {
                    $sheet->setCellValue("E{$rowNumber}", (float) str_replace(',', '', $row['rate']));
                }
                $sheet->setCellValue("F{$rowNumber}", $row['male_count'] !== null ? "{$row['male_count']}/{$row['female_count']}" : '');
                $sheet->setCellValueExplicit("G{$rowNumber}", (string) $row['rf_number'], DataType::TYPE_STRING);
                $sheet->setCellValue("H{$rowNumber}", (float) $row['amount']);
                $sheet->setCellValueExplicit("I{$rowNumber}", (string) $row['or_number'], DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("J{$rowNumber}", (string) $row['or_date'], DataType::TYPE_STRING);
                if ($row['show_total']) {
                    $sheet->setCellValue("K{$rowNumber}", (float) $row['total']);
                } else {
                    $sheet->setCellValue("K{$rowNumber}", '**');
                }
                $rowNumber++;
            }

            if ($dateGroupRowCount > 1) {
                $sheet->mergeCells('A'.$firstRowForDate.':A'.($rowNumber - 1));
                $sheet->getStyle('A'.$firstRowForDate.':A'.($rowNumber - 1))->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            $sheet->setCellValue("A{$rowNumber}", '');
            $sheet->setCellValue("B{$rowNumber}", '');
            $sheet->setCellValue("C{$rowNumber}", '');
            $sheet->setCellValue("D{$rowNumber}", '');
            $sheet->setCellValue("E{$rowNumber}", 'Total Pax:');
            $sheet->setCellValue("F{$rowNumber}", "{$dateGroup['total_male']}/{$dateGroup['total_female']}");
            $sheet->setCellValue("G{$rowNumber}", 'Total Amount:');
            $sheet->setCellValue("H{$rowNumber}", (float) $dateGroup['total_amount']);
            $sheet->mergeCells("H{$rowNumber}:K{$rowNumber}");
            $sheet->getStyle("A{$rowNumber}:K{$rowNumber}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'],
                ],
            ]);
            $rowNumber++;
        }

        if (empty($data['rows_by_date'])) {
            $sheet->mergeCells("A{$rowNumber}:K{$rowNumber}");
            $sheet->setCellValue("A{$rowNumber}", 'No entries found for this month.');
            $sheet->getStyle("A{$rowNumber}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $rowNumber++;
        }

        $summaryStartRow = $rowNumber + 1;
        $sheet->setCellValue("A{$summaryStartRow}", 'Total Pax Accommodated for the month of '.$data['month_label'].':');
        $sheet->mergeCells("A{$summaryStartRow}:E{$summaryStartRow}");
        $sheet->setCellValue("F{$summaryStartRow}", (int) $data['total_pax']);
        $sheet->setCellValue("I{$summaryStartRow}", 'Check-in Payments');
        $sheet->setCellValue("K{$summaryStartRow}", (float) $data['grand_total']);
        $sheet->getStyle("A{$summaryStartRow}:K{$summaryStartRow}")->getFont()->setBold(true);

        foreach ([
            ['In-Stay Add-Ons Billed', (float) $data['in_stay_addons_billed']],
            ['In-Stay Extensions Billed', (float) $data['in_stay_extensions_billed']],
            ['Payments Collected', (float) $data['payments_collected']],
            ['Outstanding Balance (Affected Stays)', (float) $data['outstanding_balance']],
        ] as $index => [$label, $amount]) {
            $financialRow = $summaryStartRow + $index + 1;
            $sheet->setCellValue("I{$financialRow}", $label);
            $sheet->setCellValue("K{$financialRow}", $amount);
            $sheet->getStyle("I{$financialRow}:K{$financialRow}")->getFont()->setBold(true);
        }

        $summaryRows = [
            ['*Domestic Male', $data['total_domestic_male']],
            ['*Domestic Female', $data['total_domestic_female']],
            ['*International Male', $data['total_international_male']],
            ['*International Female', $data['total_international_female']],
        ];

        foreach ($summaryRows as $index => [$label, $value]) {
            $summaryRow = $summaryStartRow + $index + 1;
            $sheet->setCellValue("E{$summaryRow}", $label);
            $sheet->setCellValue("F{$summaryRow}", (int) $value);
            $sheet->getStyle("E{$summaryRow}:F{$summaryRow}")->getFont()->setBold(true);
        }

        $signatoryRow = $summaryStartRow + count($summaryRows) + 3;
        $sheet->setCellValue("A{$signatoryRow}", 'Prepared by:');
        $sheet->setCellValue("I{$signatoryRow}", 'Approved by:');
        $sheet->setCellValue('A'.($signatoryRow + 2), Setting::get('signatory_prepared_name', 'GENELYN ABARQUEZ - ENSOMO'));
        $sheet->setCellValue('A'.($signatoryRow + 3), Setting::get('signatory_prepared_title', 'LODGING SUPERVISOR'));
        $sheet->setCellValue('I'.($signatoryRow + 2), Setting::get('signatory_approved_name', 'RUBIE ANDOY - ARROYO'));
        $sheet->setCellValue('I'.($signatoryRow + 3), Setting::get('signatory_approved_title', 'Director, University Homestay'));
        $sheet->getStyle('A'.($signatoryRow + 2).':K'.($signatoryRow + 2))->getFont()->setBold(true);

        $metadataRow = $signatoryRow + 6;
        $sheet->setCellValue("A{$metadataRow}", 'CMU-F-5-OUH-028');
        $sheet->mergeCells("A{$metadataRow}:C{$metadataRow}");
        $sheet->setCellValue("E{$metadataRow}", '17-Nov-21');
        $sheet->mergeCells("E{$metadataRow}:G{$metadataRow}");
        $sheet->setCellValue("K{$metadataRow}", 'Rev. 0');
        $sheet->getStyle("A{$metadataRow}:K{$metadataRow}")->applyFromArray([
            'font' => ['size' => 8],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
        $sheet->getStyle("E{$metadataRow}:G{$metadataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("K{$metadataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $lastDataRow = max($headerRow, $rowNumber - 1);
        $sheet->getStyle("A{$headerRow}:K{$lastDataRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_HAIR],
            ],
        ]);
        $sheet->getStyle("E".($headerRow + 1).":E{$lastDataRow}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
        $sheet->getStyle("H".($headerRow + 1).":H{$lastDataRow}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
        $sheet->getStyle("K".($headerRow + 1).":K{$lastDataRow}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
        $sheet->getStyle("K{$summaryStartRow}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
        $sheet->getStyle("K".($summaryStartRow + 1).":K".($summaryStartRow + 3))->getNumberFormat()->setFormatCode('"₱"#,##0.00');
        $sheet->getStyle('A1:K'.$metadataRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$headerRow}:K{$lastDataRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("C".($headerRow + 1).":C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F".($headerRow + 1).":F{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E".($headerRow + 1).":E{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("H".($headerRow + 1).":H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("K".($headerRow + 1).":K{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A1:K'.$metadataRow)->getFont()->setName('Arial')->setSize(10);

        $columnWidths = [
            'A' => 12,
            'B' => 24,
            'C' => 14,
            'D' => 32,
            'E' => 12,
            'F' => 14,
            'G' => 18,
            'H' => 14,
            'I' => 20,
            'J' => 12,
            'K' => 14,
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LEGAL)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setRight(0.55)
            ->setBottom(0.4)
            ->setLeft(0.55);

        $writer = new Xlsx($spreadsheet);
        $filename = 'monthly-report-'.$month->format('Y-m').'.xlsx';

        return response()->streamDownload(function () use ($writer, $spreadsheet): void {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function getReportDataProperty(): array
    {
        return match ($this->reportType) {
            'reservation_summary' => $this->getReservationSummary(),
            'gender_statistics' => $this->getGenderStatistics(),
            'feedback_analytics' => $this->getFeedbackAnalytics(),
            'occupancy' => $this->getOccupancyReport(),
            'room_utilization' => $this->getRoomUtilization(),
            'stay_logs' => $this->getStayLogs(),
            'reservation_list' => $this->getReservationList(),
            'monthly_or_report' => $this->getMonthlyOrReport(),
            default => [],
        };
    }

    protected function getMonthlyOrReport(): array
    {
        $month = $this->monthPeriod
            ? Carbon::createFromFormat('Y-m', $this->monthPeriod)
            : Carbon::today();

        $from = $month->copy()->startOfMonth()->startOfDay();
        $to = $month->copy()->endOfMonth()->endOfDay();

        // Keep shared period values aligned with this report's selected month.
        $this->dateFrom = $from->format('Y-m-d');
        $this->dateTo = $to->format('Y-m-d');

        // Get all reservations with check-ins in this month
        $reservations = Reservation::query()
            ->with([
                'roomAssignments' => function ($q) use ($from, $to) {
                    $q->whereNotNull('checked_in_at')
                        ->whereBetween('checked_in_at', [$from, $to])
                        ->with('room.roomType');
                },
                'payments',
                'guests',
                'checkInSnapshots',
                'charges',
            ])
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereHas('roomAssignments', function ($q) use ($from, $to) {
                $q->whereNotNull('checked_in_at')
                    ->whereBetween('checked_in_at', [$from, $to]);
            })
            ->get();

        $rowsByDate = [];
        $totalDomesticMale = 0;
        $totalDomesticFemale = 0;
        $totalInternationalMale = 0;
        $totalInternationalFemale = 0;
        $grandTotal = 0;

        foreach ($reservations as $reservation) {
            $assignments = $reservation->roomAssignments
                ->filter(function ($assignment) use ($from, $to) {
                    if (! $assignment->checked_in_at) {
                        return false;
                    }

                    return Carbon::parse($assignment->checked_in_at)->betweenIncluded($from, $to);
                })
                ->values();

            if ($assignments->isEmpty()) {
                continue;
            }

            $firstAssignment = $assignments->first();
            $postedPayments = $reservation->payments
                ->where('status', 'posted')
                ->values();
            $onlinePayment = $postedPayments
                ->where('gateway', 'paymongo')
                ->sortByDesc('id')
                ->first();
            $officialOrNumber = $this->cleanOfficialReceiptNumber($firstAssignment->payment_or_number ?? null);
            $onlineReference = $onlinePayment?->reference_no
                ?: ($onlinePayment?->gateway_payment_id ? 'PM-'.$onlinePayment->gateway_payment_id : null);
            $orDate = $firstAssignment->or_date
                ? Carbon::parse($firstAssignment->or_date)
                : ($onlinePayment?->or_date
                    ? Carbon::parse($onlinePayment->or_date)
                    : ($onlinePayment?->received_at
                        ? Carbon::parse($onlinePayment->received_at)
                        : Carbon::parse($firstAssignment->checked_in_at)));

            $dateKey = $orDate->toDateString();

            // Calculate number of nights (by calendar date, not exact hours)
            $checkInDate = Carbon::parse($firstAssignment->checked_in_at ?? $reservation->check_in_date)->startOfDay();
            $checkOutDate = $firstAssignment->checked_out_at
                ? Carbon::parse($firstAssignment->checked_out_at)->startOfDay()
                : Carbon::parse($reservation->check_out_date)->startOfDay();
            $nights = max(1, (int) $checkInDate->diffInDays($checkOutDate));

            // Guest name (lastname first)
            $guestName = trim(($reservation->guest_last_name ?? '').', '.($reservation->guest_first_name ?? ''));
            if (empty(trim($guestName, ', '))) {
                $guestName = $reservation->guest_name ?? 'Unknown Guest';
            }

            // Discount / ID info
            $snapshot = $reservation->checkInSnapshots->sortByDesc('id')->first();
            $discountCharge = $reservation->charges->where('charge_type', 'discount')->sortByDesc('id')->first();
            $hasDiscount = $discountCharge !== null;
            $guestIdNumber = $hasDiscount ? ($snapshot?->id_number ?? '') : '';

            // Common payment reference for all lines of this reservation.
            // Official OR remains preferred; PayMongo IDs are shown as online references.
            $orNumber = $officialOrNumber
                ?: ($onlineReference ? 'Online Ref: '.$onlineReference : '-');
            $orDateFormatted = $orDate->format('m/d/Y');
            $rfNumber = $reservation->reference_number ?? '-';

            // Count overall pax (for date-group subtotals and report footer)
            $domesticMale = 0;
            $domesticFemale = 0;
            $internationalMale = 0;
            $internationalFemale = 0;
            foreach ($assignments as $assignment) {
                $gender = $assignment->guest_gender ?? $reservation->guest_gender ?? 'Other';
                $nationality = $assignment->nationality ?? 'Filipino';
                $isDomestic = stripos($nationality, 'filipino') !== false || stripos($nationality, 'philippine') !== false;
                if ($isDomestic && strtolower($gender) === 'male') {
                    $domesticMale++;
                } elseif ($isDomestic && strtolower($gender) === 'female') {
                    $domesticFemale++;
                } elseif (! $isDomestic && strtolower($gender) === 'male') {
                    $internationalMale++;
                } elseif (! $isDomestic && strtolower($gender) === 'female') {
                    $internationalFemale++;
                }
            }
            $totalDomesticMale += $domesticMale;
            $totalDomesticFemale += $domesticFemale;
            $totalInternationalMale += $internationalMale;
            $totalInternationalFemale += $internationalFemale;
            $maleCount = $domesticMale + $internationalMale;
            $femaleCount = $domesticFemale + $internationalFemale;

            // Amount actually paid. For fully online balance payments, the assignment
            // has no manual collection amount, so use posted payment records.
            $assignmentPaymentAmount = (float) ($firstAssignment->payment_amount ?? 0);
            $postedPaymentTotal = (float) $postedPayments->sum(fn ($payment) => (float) $payment->amount);
            $amountPaid = $assignmentPaymentAmount > 0 ? $assignmentPaymentAmount : $postedPaymentTotal;
            $grandTotal += $amountPaid;

            // ---- Build per-line sub-rows ----
            $reservationLines = [];
            $isFirstLine = true;

            // One line per unique room
            foreach ($assignments->unique('room_id') as $asgmt) {
                $room = $asgmt->room;
                $roomType = $room?->roomType ?? null;
                if (! $room || ! $roomType) {
                    continue;
                }

                $rate = (float) $roomType->base_rate;

                // Pax in this specific room
                $roomAsgmts = $assignments->where('room_id', $room->id);
                $roomMale = 0;
                $roomFemale = 0;
                foreach ($roomAsgmts as $ra) {
                    $g = strtolower($ra->guest_gender ?? $reservation->guest_gender ?? '');
                    if ($g === 'male') {
                        $roomMale++;
                    } elseif ($g === 'female') {
                        $roomFemale++;
                    }
                }

                // Line amount
                if ($roomType->pricing_type === 'per_person') {
                    $guestCount = max(1, $roomAsgmts->count());
                    $lineAmount = $rate * $guestCount * $nights;
                } else {
                    $lineAmount = $rate * $nights;
                }

                $reservationLines[] = [
                    'guest_name' => $isFirstLine ? $guestName : '***',
                    'guest_id_number' => $isFirstLine ? $guestIdNumber : '',
                    'nights' => $nights,
                    'room_particulars' => $roomType->name.' #'.$room->room_number,
                    'rate' => number_format($rate, 2),
                    'male_count' => $roomMale,
                    'female_count' => $roomFemale,
                    'rf_number' => $rfNumber,
                    'amount' => $lineAmount,
                    'or_number' => $orNumber,
                    'or_date' => $orDateFormatted,
                    'total' => null,
                    'show_total' => false,
                ];
                $isFirstLine = false;
            }

            // One line per add-on charge
            foreach ($reservation->charges->where('charge_type', 'addon')->filter(
                fn ($charge) => ! str_starts_with((string) data_get($charge->meta, 'source', ''), 'in_stay_addon')
            ) as $charge) {
                $qty = (int) max(1, $charge->qty ?? 1);
                // Strip leading multiplier prefix (e.g. "3x ") so the Particulars column isn't redundant with the Qty column
                $particulars = preg_replace('/^\d+x\s+/', '', $charge->description);
                $reservationLines[] = [
                    'guest_name' => '***',
                    'guest_id_number' => '',
                    'nights' => $qty,
                    'room_particulars' => $particulars,
                    'rate' => number_format((float) $charge->unit_price, 2),
                    'male_count' => null,
                    'female_count' => null,
                    'rf_number' => $rfNumber,
                    'amount' => (float) $charge->amount,
                    'or_number' => $orNumber,
                    'or_date' => $orDateFormatted,
                    'total' => null,
                    'show_total' => false,
                ];
            }

            // If no lines were built (no room data), add a fallback line
            if (empty($reservationLines)) {
                $reservationLines[] = [
                    'guest_name' => $guestName,
                    'guest_id_number' => $guestIdNumber,
                    'nights' => $nights,
                    'room_particulars' => '-',
                    'rate' => '-',
                    'male_count' => $maleCount,
                    'female_count' => $femaleCount,
                    'rf_number' => $rfNumber,
                    'amount' => $amountPaid,
                    'or_number' => $orNumber,
                    'or_date' => $orDateFormatted,
                    'total' => null,
                    'show_total' => false,
                ];
            }

            // Total (payment amount) shown only on the first line
            $reservationLines[0]['total'] = $amountPaid;
            $reservationLines[0]['show_total'] = true;

            // Add all lines to the date group
            if (! isset($rowsByDate[$dateKey])) {
                $rowsByDate[$dateKey] = [
                    'date' => $orDate->format('m/d/Y'),
                    'date_sort' => $dateKey,
                    'rows' => [],
                    'total_male' => 0,
                    'total_female' => 0,
                    'total_amount' => 0,
                    'total_addons_billed' => 0,
                    'total_extensions_billed' => 0,
                ];
            }

            foreach ($reservationLines as $line) {
                $rowsByDate[$dateKey]['rows'][] = $line;
            }

            $rowsByDate[$dateKey]['total_male'] += $maleCount;
            $rowsByDate[$dateKey]['total_female'] += $femaleCount;
            $rowsByDate[$dateKey]['total_amount'] += $amountPaid;
        }

        // In-stay add-ons and extensions belong to their posting month, not
        // the reservation's original check-in month. Collection is separate.
        $inStayCharges = ReservationCharge::query()
            ->with('reservation.payments')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('charge_type', ['addon', 'room_rate', 'discount'])
            ->get()
            ->filter(fn (ReservationCharge $charge): bool => str_starts_with((string) data_get($charge->meta, 'source', ''), 'in_stay_addon') || str_starts_with((string) data_get($charge->meta, 'source', ''), 'in_stay_extension'));

        $inStayAddonsBilled = 0.0;
        $inStayExtensionsBilled = 0.0;
        foreach ($inStayCharges as $charge) {
            $reservation = $charge->reservation;
            if (! $reservation) {
                continue;
            }

            $postedAt = Carbon::parse($charge->created_at);
            $dateKey = $postedAt->toDateString();
            if (! isset($rowsByDate[$dateKey])) {
                $rowsByDate[$dateKey] = [
                    'date' => $postedAt->format('m/d/Y'),
                    'date_sort' => $dateKey,
                    'rows' => [],
                    'total_male' => 0,
                    'total_female' => 0,
                    'total_amount' => 0,
                    'total_addons_billed' => 0,
                    'total_extensions_billed' => 0,
                ];
            }

            $qty = (float) $charge->qty;
            [$orNumber, $orDate] = $this->settlementReceiptForDeferredCharge($reservation, $charge);
            $rowsByDate[$dateKey]['rows'][] = [
                'guest_name' => trim(($reservation->guest_last_name ?? '').', '.($reservation->guest_first_name ?? '')) ?: ($reservation->guest_name ?? 'Unknown Guest'),
                'guest_id_number' => '',
                'nights' => $qty > 0 ? $qty : 1,
                'room_particulars' => $charge->description,
                'rate' => number_format((float) $charge->unit_price, 2),
                'male_count' => null,
                'female_count' => null,
                'rf_number' => $reservation->reference_number ?? '-',
                'amount' => (float) $charge->amount,
                'or_number' => $orNumber,
                'or_date' => $orDate,
                'total' => null,
                'show_total' => false,
            ];
            $source = (string) data_get($charge->meta, 'source', '');
            if (str_starts_with($source, 'in_stay_extension')) {
                $rowsByDate[$dateKey]['total_extensions_billed'] += (float) $charge->amount;
                $inStayExtensionsBilled += (float) $charge->amount;
            } else {
                $rowsByDate[$dateKey]['total_addons_billed'] += (float) $charge->amount;
                $inStayAddonsBilled += (float) $charge->amount;
            }
        }

        $paymentsCollected = (float) ReservationPayment::query()
            ->where('status', 'posted')
            ->whereBetween(DB::raw('COALESCE(received_at, created_at)'), [$from, $to])
            ->sum('amount');

        $outstandingBalance = (float) $inStayCharges
            ->pluck('reservation')
            ->filter()
            ->unique('id')
            ->sum(fn (Reservation $reservation) => (float) $reservation->balance_due);

        // Sort by date
        $rowsByDate = collect($rowsByDate)
            ->sortBy('date_sort')
            ->values()
            ->toArray();

        $totalPax = $totalDomesticMale + $totalDomesticFemale + $totalInternationalMale + $totalInternationalFemale;

        return [
            'type' => 'monthly_or_report',
            'month_label' => $month->format('F Y'),
            'rows_by_date' => $rowsByDate,
            'grand_total' => $grandTotal,
            'in_stay_addons_billed' => $inStayAddonsBilled,
            'in_stay_extensions_billed' => $inStayExtensionsBilled,
            'payments_collected' => $paymentsCollected,
            'outstanding_balance' => $outstandingBalance,
            'total_domestic_male' => $totalDomesticMale,
            'total_domestic_female' => $totalDomesticFemale,
            'total_international_male' => $totalInternationalMale,
            'total_international_female' => $totalInternationalFemale,
            'total_male' => $totalDomesticMale + $totalInternationalMale,
            'total_female' => $totalDomesticFemale + $totalInternationalFemale,
            'total_pax' => $totalPax,
        ];
    }

    protected function cleanOfficialReceiptNumber(?string $orNumber): ?string
    {
        $orNumber = trim((string) $orNumber);

        if ($orNumber === '' || strtoupper($orNumber) === 'N/A') {
            return null;
        }

        return $orNumber;
    }

    /** @return array{0:string,1:string} */
    private function settlementReceiptForDeferredCharge(Reservation $reservation, ReservationCharge $charge): array
    {
        if ((float) $reservation->balance_due > 0.01) {
            return ['—', '—'];
        }

        $effectiveCharge = $charge;
        if (str_contains((string) data_get($charge->meta, 'source', ''), '_void')) {
            $originalId = (int) data_get($charge->meta, 'voids_charge_id', 0);
            if ($originalId) {
                $effectiveCharge = $reservation->charges()->find($originalId) ?? $charge;
            }
        }

        $settlement = $reservation->payments
            ->filter(fn (ReservationPayment $payment): bool => $payment->status === 'posted'
                && data_get($payment->meta, 'source') === 'checkout_settlement'
                && filled($payment->reference_no)
                && Carbon::parse($payment->received_at ?? $payment->created_at)->gte(Carbon::parse($effectiveCharge->created_at)))
            ->sortBy(fn (ReservationPayment $payment) => $payment->received_at ?? $payment->created_at)
            ->first();

        if (! $settlement) {
            return ['—', '—'];
        }

        return [
            (string) $settlement->reference_no,
            Carbon::parse($settlement->or_date ?? $settlement->received_at ?? $settlement->created_at)->format('m/d/Y'),
        ];
    }


    protected function getReservationSummary(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $reservations = Reservation::with('preferredRoomType')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in_date', [$from, $to])
                    ->orWhereBetween('check_out_date', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('check_in_date', '<=', $from)
                            ->where('check_out_date', '>=', $to);
                    });
            })
            ->get();

        $byStatus = $reservations->groupBy('status')->map->count();
        $byPurpose = $reservations->groupBy('purpose')->map->count();
        $byRoomType = $reservations
            ->map(fn ($reservation) => $reservation->preferredRoomType?->name)
            ->filter()
            ->countBy()
            ->toArray();

        $totalNights = $reservations->whereIn('status', ['checked_in', 'checked_out'])->sum(function ($r) {
            $nights = Carbon::parse($r->check_in_date)->diffInDays(Carbon::parse($r->check_out_date));

            return $nights * max(1, (int) $r->number_of_occupants);
        });

        return [
            'type' => 'reservation_summary',
            'total' => $reservations->count(),
            'by_status' => $byStatus->toArray(),
            'by_purpose' => $byPurpose->toArray(),
            'by_room_type' => $byRoomType,
            'total_guest_nights' => $totalNights,
            'avg_occupants' => round($reservations->avg('number_of_occupants') ?? 0, 1),
        ];
    }

    protected function getGenderStatistics(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $assignments = RoomAssignment::query()
            ->whereNotNull('checked_in_at')
            ->whereBetween('checked_in_at', [$from, $to])
            ->get(['guest_gender', 'nationality', 'checked_in_at']);

        $classificationRows = [
            'domestic' => $this->emptyGenderStatRow('Domestic'),
            'foreign' => $this->emptyGenderStatRow('Foreign'),
            'unknown' => $this->emptyGenderStatRow('Unknown Nationality'),
        ];

        $nationalityRows = [];

        foreach ($assignments as $assignment) {
            $classification = $this->classifyGuestNationality($assignment->nationality);
            $gender = $this->normalizeGuestGender($assignment->guest_gender);
            $nationality = trim((string) $assignment->nationality);
            $nationalityKey = $nationality !== '' ? mb_strtolower($nationality) : 'unspecified';
            $nationalityLabel = $nationality !== '' ? $nationality : 'Unspecified';

            $classificationRows[$classification][$gender]++;
            $classificationRows[$classification]['total']++;

            if (! isset($nationalityRows[$nationalityKey])) {
                $nationalityRows[$nationalityKey] = [
                    'nationality' => $nationalityLabel,
                    'classification' => $classification,
                    'classification_label' => $classificationRows[$classification]['label'],
                    'male' => 0,
                    'female' => 0,
                    'other' => 0,
                    'unspecified' => 0,
                    'total' => 0,
                ];
            }

            $nationalityRows[$nationalityKey][$gender]++;
            $nationalityRows[$nationalityKey]['total']++;
        }

        $totalGuests = $assignments->count();
        $classificationRows = collect($classificationRows)
            ->map(function (array $row) use ($totalGuests) {
                $row['percentage'] = $totalGuests > 0 ? round(($row['total'] / $totalGuests) * 100, 1) : 0;

                return $row;
            })
            ->values()
            ->toArray();

        $nationalityRows = collect($nationalityRows)
            ->sortByDesc('total')
            ->values()
            ->toArray();

        $domestic = collect($classificationRows)->firstWhere('key', 'domestic') ?? $this->emptyGenderStatRow('Domestic');
        $foreign = collect($classificationRows)->firstWhere('key', 'foreign') ?? $this->emptyGenderStatRow('Foreign');
        $unknown = collect($classificationRows)->firstWhere('key', 'unknown') ?? $this->emptyGenderStatRow('Unknown Nationality');

        return [
            'type' => 'gender_statistics',
            'total_guests' => $totalGuests,
            'domestic_total' => $domestic['total'],
            'foreign_total' => $foreign['total'],
            'unknown_nationality_total' => $unknown['total'],
            'male_total' => collect($classificationRows)->sum('male'),
            'female_total' => collect($classificationRows)->sum('female'),
            'other_total' => collect($classificationRows)->sum('other'),
            'unspecified_total' => collect($classificationRows)->sum('unspecified'),
            'classification_rows' => $classificationRows,
            'nationality_rows' => $nationalityRows,
            'domestic_foreign_chart' => [
                'labels' => ['Domestic', 'Foreign'],
                'male' => [$domestic['male'], $foreign['male']],
                'female' => [$domestic['female'], $foreign['female']],
                'other_unspecified' => [
                    $domestic['other'] + $domestic['unspecified'],
                    $foreign['other'] + $foreign['unspecified'],
                ],
            ],
            'origin_share_chart' => [
                'labels' => ['Domestic', 'Foreign', 'Unknown'],
                'data' => [$domestic['total'], $foreign['total'], $unknown['total']],
            ],
        ];
    }

    protected function emptyGenderStatRow(string $label): array
    {
        return [
            'key' => match ($label) {
                'Domestic' => 'domestic',
                'Foreign' => 'foreign',
                default => 'unknown',
            },
            'label' => $label,
            'male' => 0,
            'female' => 0,
            'other' => 0,
            'unspecified' => 0,
            'total' => 0,
            'percentage' => 0,
        ];
    }

    protected function classifyGuestNationality(?string $nationality): string
    {
        $nationality = trim((string) $nationality);

        if ($nationality === '') {
            return 'unknown';
        }

        return preg_match('/filipino|philippine/i', $nationality) ? 'domestic' : 'foreign';
    }

    protected function normalizeGuestGender(?string $gender): string
    {
        return match (mb_strtolower(trim((string) $gender))) {
            'male' => 'male',
            'female' => 'female',
            'other' => 'other',
            default => 'unspecified',
        };
    }

    protected function getFeedbackAnalytics(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $feedback = ReservationFeedback::query()
            ->with(['reservation.preferredRoomType', 'guestAccount'])
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$from, $to])
            ->get();

        $total = $feedback->count();
        $averageOverall = $total > 0 ? round((float) $feedback->avg('overall_rating'), 2) : 0;
        $stayAgainYes = $feedback->filter(fn (ReservationFeedback $item) => $item->would_stay_again === true)->count();
        $stayAgainNo = $feedback->filter(fn (ReservationFeedback $item) => $item->would_stay_again === false)->count();
        $stayAgainUnknown = $feedback->filter(fn (ReservationFeedback $item) => is_null($item->would_stay_again))->count();
        $answeredStayAgain = $stayAgainYes + $stayAgainNo;
        $stayAgainPercent = $answeredStayAgain > 0 ? round(($stayAgainYes / $answeredStayAgain) * 100, 1) : 0;
        $lowRatings = $feedback->where('overall_rating', '<=', 2);

        $ratingDistribution = collect(range(1, 5))
            ->mapWithKeys(fn (int $rating) => [(string) $rating => $feedback->where('overall_rating', $rating)->count()])
            ->toArray();

        $categoryDefinitions = [
            'cleanliness_rating' => 'Cleanliness',
            'comfort_rating' => 'Comfort',
            'service_rating' => 'Staff / Service',
            'value_rating' => 'Value',
            'booking_experience_rating' => 'Booking Experience',
        ];

        $categoryRows = collect($categoryDefinitions)
            ->map(function (string $label, string $field) use ($feedback): array {
                $ratings = $feedback->pluck($field)->filter(fn ($rating) => filled($rating))->map(fn ($rating) => (int) $rating);

                return [
                    'field' => $field,
                    'label' => $label,
                    'responses' => $ratings->count(),
                    'average' => $ratings->isNotEmpty() ? round((float) $ratings->avg(), 2) : 0,
                    'lowest' => $ratings->isNotEmpty() ? (int) $ratings->min() : null,
                ];
            })
            ->values()
            ->toArray();

        $periodDays = max(1, (int) $from->diffInDays($to) + 1);
        $trendFormat = $periodDays > 62 ? 'Y-m' : 'Y-m-d';
        $trendLabelFormat = $periodDays > 62 ? 'M Y' : 'M d';
        $trendRows = $feedback
            ->groupBy(fn (ReservationFeedback $item) => $item->submitted_at?->format($trendFormat) ?? 'unknown')
            ->map(function ($items, string $key) use ($trendLabelFormat): array {
                return [
                    'key' => $key,
                    'label' => $key === 'unknown' ? 'Unknown' : Carbon::parse($key.'-01')->format($trendLabelFormat),
                    'average' => round((float) $items->avg('overall_rating'), 2),
                    'count' => $items->count(),
                ];
            })
            ->sortBy('key')
            ->values()
            ->toArray();

        if ($periodDays <= 62) {
            $trendRows = $feedback
                ->groupBy(fn (ReservationFeedback $item) => $item->submitted_at?->toDateString() ?? 'unknown')
                ->map(function ($items, string $key): array {
                    return [
                        'key' => $key,
                        'label' => $key === 'unknown' ? 'Unknown' : Carbon::parse($key)->format('M d'),
                        'average' => round((float) $items->avg('overall_rating'), 2),
                        'count' => $items->count(),
                    ];
                })
                ->sortBy('key')
                ->values()
                ->toArray();
        }

        $roomTypeRows = $feedback
            ->groupBy(fn (ReservationFeedback $item) => $item->reservation?->preferredRoomType?->name ?? 'Unspecified')
            ->map(function ($items, string $roomType): array {
                return [
                    'room_type' => $roomType,
                    'feedback_count' => $items->count(),
                    'average_rating' => round((float) $items->avg('overall_rating'), 2),
                    'low_ratings' => $items->where('overall_rating', '<=', 2)->count(),
                ];
            })
            ->sortByDesc('feedback_count')
            ->values()
            ->toArray();

        $lowRatingRows = $lowRatings
            ->sortByDesc('submitted_at')
            ->take(10)
            ->map(function (ReservationFeedback $item) use ($categoryDefinitions): array {
                $categoryLows = collect($categoryDefinitions)
                    ->filter(fn (string $label, string $field) => filled($item->{$field}) && (int) $item->{$field} <= 2)
                    ->values()
                    ->implode(', ');

                return [
                    'reservation' => $item->reservation?->reference_number ?? '-',
                    'guest' => $item->guestAccount?->name ?? $item->reservation?->guest_name ?? '-',
                    'overall_rating' => $item->overall_rating,
                    'category_lows' => $categoryLows !== '' ? $categoryLows : '-',
                    'submitted_at' => $item->submitted_at?->format('M d, Y g:i A') ?? '-',
                    'comment' => filled($item->comments) ? str($item->comments)->limit(120)->toString() : '-',
                ];
            })
            ->values()
            ->toArray();

        return [
            'type' => 'feedback_analytics',
            'total_feedback' => $total,
            'average_overall' => $averageOverall,
            'stay_again_percent' => $stayAgainPercent,
            'low_rating_count' => $lowRatings->count(),
            'unreviewed_count' => $feedback->where('status', 'new')->count(),
            'rating_distribution' => $ratingDistribution,
            'category_rows' => $categoryRows,
            'stay_again_chart' => [
                'labels' => ['Yes', 'No', 'Not Answered'],
                'data' => [$stayAgainYes, $stayAgainNo, $stayAgainUnknown],
            ],
            'trend_rows' => $trendRows,
            'room_type_rows' => $roomTypeRows,
            'low_rating_rows' => $lowRatingRows,
        ];
    }

    protected function getOccupancyReport(): array
    {
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);
        $roomsByStatus = Room::where('is_active', true)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalRooms = (int) $roomsByStatus->sum();
        $occupiedNow = (int) ($roomsByStatus['occupied'] ?? 0);
        $maintenanceNow = (int) ($roomsByStatus['maintenance'] ?? 0);

        $assignments = RoomAssignment::query()
            ->whereNotNull('checked_in_at')
            ->where('checked_in_at', '<=', $to->copy()->endOfDay())
            ->where(function ($q) use ($from) {
                $q->whereNull('checked_out_at')
                    ->orWhere('checked_out_at', '>=', $from->copy()->startOfDay());
            })
            ->get(['checked_in_at', 'checked_out_at']);

        // Daily occupancy for chart (last 30 days)
        $dailyOccupancy = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dateStart = $date->copy()->startOfDay();
            $dateEnd = $date->copy()->endOfDay();
            $occupied = $assignments->filter(function ($assignment) use ($dateStart, $dateEnd) {
                $checkedIn = Carbon::parse($assignment->checked_in_at);
                $checkedOut = $assignment->checked_out_at ? Carbon::parse($assignment->checked_out_at) : null;

                return $checkedIn->lte($dateEnd) && ($checkedOut === null || $checkedOut->gte($dateStart));
            })->count();

            $dailyOccupancy[] = [
                'date' => $date->format('M d'),
                'occupied' => $occupied,
                'rate' => $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0,
            ];
        }

        return [
            'type' => 'occupancy',
            'total_rooms' => $totalRooms,
            'occupied_now' => $occupiedNow,
            'maintenance_now' => $maintenanceNow,
            'available_now' => $totalRooms - $occupiedNow - $maintenanceNow,
            'current_rate' => $totalRooms > 0 ? round(($occupiedNow / $totalRooms) * 100, 1) : 0,
            'daily' => $dailyOccupancy,
        ];
    }

    protected function getRoomUtilization(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();
        $totalDays = max(1, $from->diffInDays($to->copy()->startOfDay()) + 1); // inclusive day count

        $rooms = Room::with(['roomType', 'roomAssignments' => function ($q) use ($from, $to) {
            // Only load assignments that overlap with the selected period
            $q->whereNotNull('checked_in_at')
                ->where(function ($q) use ($from, $to) {
                    $q->where('checked_in_at', '<=', $to)
                        ->where(function ($q) use ($from) {
                            $q->whereNull('checked_out_at')
                                ->orWhere('checked_out_at', '>=', $from);
                        });
                });
        }])->where('is_active', true)->get();

        $utilization = $rooms->map(function ($room) use ($from, $to, $totalDays) {
            $daysOccupied = $room->roomAssignments->sum(function ($assign) use ($from, $to) {
                $checkIn = Carbon::parse($assign->checked_in_at)->startOfDay();
                // Still checked in → count through end of today
                $checkOut = $assign->checked_out_at
                    ? Carbon::parse($assign->checked_out_at)->startOfDay()
                    : Carbon::today()->addDay(); // include today as an occupied day

                // Clamp to the report period
                $start = $checkIn->greaterThan($from) ? $checkIn : $from->copy();
                $end = $checkOut->lessThan($to) ? $checkOut : $to->copy()->startOfDay()->addDay();

                // No overlap → 0
                if ($start->greaterThanOrEqualTo($end)) {
                    return 0;
                }

                return (int) $start->diffInDays($end);
            });

            return [
                'room' => $room->room_number,
                'type' => $room->roomType->name ?? 'N/A',
                'status' => $room->status,
                'days_occupied' => $daysOccupied,
                'utilization_rate' => round(($daysOccupied / $totalDays) * 100, 1),
            ];
        })->sortByDesc('utilization_rate')->values()->toArray();

        // By room type
        $stayCountsByRoomType = RoomAssignment::query()
            ->join('rooms', 'room_assignments.room_id', '=', 'rooms.id')
            ->whereBetween('room_assignments.checked_in_at', [$from, $to])
            ->select('rooms.room_type_id as room_type_id', DB::raw('count(*) as total'))
            ->groupBy('rooms.room_type_id')
            ->pluck('total', 'room_type_id');

        $byType = RoomType::withCount(['rooms' => function ($q) {
            $q->where('is_active', true);
        }])->get()->map(function ($type) use ($stayCountsByRoomType) {
            $stayCount = (int) ($stayCountsByRoomType[$type->id] ?? 0);

            return [
                'name' => $type->name,
                'room_count' => $type->rooms_count,
                'total_stays' => $stayCount,
            ];
        })->toArray();

        return [
            'type' => 'room_utilization',
            'rooms' => $utilization,
            'by_type' => $byType,
        ];
    }

    protected function getStayLogs(): array
    {
        // still named getStayLogs for compatibility with the resource, but data
        // now comes from RoomAssignment so that we can eventually remove the
        // stay_logs table altogether.
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $logs = RoomAssignment::with(['reservation', 'room.roomType', 'assignedByUser', 'checkedOutByUser'])
            ->whereBetween('checked_in_at', [$from, $to])
            ->orderByDesc('checked_in_at')
            ->get()
            ->map(function ($assign) {
                return [
                    'guest' => $assign->reservation->guest_name ?? 'N/A',
                    'reference' => $assign->reservation->reference_number ?? 'N/A',
                    'room' => $assign->room->room_number ?? 'N/A',
                    'room_type' => $assign->room->roomType->name ?? 'N/A',
                    'checked_in' => $assign->checked_in_at ? Carbon::parse($assign->checked_in_at)->format('M d, Y') : '-',
                    'checked_out' => $assign->checked_out_at ? Carbon::parse($assign->checked_out_at)->format('M d, Y') : 'Still checked in',
                    'checked_in_by' => $assign->assignedByUser->name ?? '-',
                    'checked_out_by' => optional($assign->checkedOutByUser)->name ?? '-',
                    // Ensure nights is always an integer (date-only diff)
                    'nights' => $assign->checked_out_at
                        ? (int) Carbon::parse($assign->checked_in_at)->startOfDay()->diffInDays(Carbon::parse($assign->checked_out_at)->startOfDay())
                        : (int) Carbon::parse($assign->checked_in_at)->startOfDay()->diffInDays(Carbon::now()->startOfDay()),
                    'remarks' => $assign->remarks ?? '-',
                ];
            });

        return [
            'type' => 'stay_logs',
            'logs' => $logs->toArray(),
            'total_stays' => count($logs),
            'completed' => $logs->where('checked_out', '!=', 'Still checked in')->count(),
            'ongoing' => $logs->where('checked_out', 'Still checked in')->count(),
        ];
    }

    protected function getReservationList(): array
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $query = Reservation::with(['preferredRoomType', 'roomAssignments.room.roomType'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in_date', [$from, $to])
                    ->orWhereBetween('check_out_date', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('check_in_date', '<=', $from)
                            ->where('check_out_date', '>=', $to);
                    });
            });

        // Apply status filter if specified
        if ($this->reservationStatus) {
            $query->where('status', $this->reservationStatus);
        }

        $reservations = $query->orderBy('check_in_date', 'desc')
            ->get()
            ->map(function ($reservation) {
                $assignedRooms = $reservation->roomAssignments->map(fn ($assignment) => $assignment->room->room_number)->join(', ');
                $nights = Carbon::parse($reservation->check_in_date)->diffInDays(Carbon::parse($reservation->check_out_date));

                return [
                    'reference' => $reservation->reference_number,
                    'guest_name' => $reservation->guest_name,
                    'guest_email' => $reservation->guest_email,
                    'guest_phone' => $reservation->guest_phone,
                    'check_in_date' => Carbon::parse($reservation->check_in_date)->format('M d, Y'),
                    'check_out_date' => Carbon::parse($reservation->check_out_date)->format('M d, Y'),
                    'nights' => $nights,
                    'occupants' => $reservation->number_of_occupants,
                    'preferred_room_type' => $reservation->preferredRoomType->name ?? 'N/A',
                    'assigned_rooms' => $assignedRooms ?: 'Not assigned',
                    'purpose' => $reservation->purpose,
                    'status' => $reservation->status,
                    'created_at' => Carbon::parse($reservation->created_at)->format('M d, Y'),
                ];
            })->toArray();

        $byStatus = collect($reservations)->groupBy('status')->map->count()->toArray();

        return [
            'type' => 'reservation_list',
            'reservations' => $reservations,
            'total' => count($reservations),
            'by_status' => $byStatus,
        ];
    }
}
