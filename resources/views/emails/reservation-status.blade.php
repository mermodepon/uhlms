<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Status Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            max-width: 640px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f3f4f6;
        }
        .container {
            background: #ffffff;
            border-radius: 10px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }
        .header {
            background: linear-gradient(to right, #00491E, #02681E);
            color: #ffffff;
            border-radius: 10px 10px 0 0;
            padding: 24px;
            margin: -32px -32px 24px -32px;
            text-align: center;
        }
        .status-chip {
            display: inline-block;
            background: #ecfdf3;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
        }
        .details {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }
        .detail-row {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .label {
            color: #6b7280;
            font-weight: 700;
        }
        .button-wrap {
            text-align: center;
            margin: 28px 0;
        }
        .button {
            display: inline-block;
            background: linear-gradient(to right, #00491E, #02681E);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 700;
        }
        .notice {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 14px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0 0 8px;">University Homestay</h1>
            <p style="margin: 0;">Reservation update for {{ $reservation->reference_number }}</p>
        </div>

        <p>Dear <strong>{{ $reservation->guest_name }}</strong>,</p>

        @if($context === 'submitted')
            <p>We received your reservation request. You can use the secure link below to check updates without relying on a public reference-number search alone.</p>
        @else
            <p>Your reservation status has been updated.</p>
        @endif

        <p>
            Current status:
            <span class="status-chip">{{ $statusLabel }}</span>
        </p>

        @if($previousStatusLabel)
            <p style="margin-top: -4px; color: #4b5563;">Previous status: {{ $previousStatusLabel }}</p>
        @endif

        <div class="details">
            <div class="detail-row"><span class="label">Reference Number:</span> {{ $reservation->reference_number }}</div>
            <div class="detail-row"><span class="label">Guest Email:</span> {{ $reservation->guest_email }}</div>
            <div class="detail-row"><span class="label">Check-in Date:</span> {{ $reservation->check_in_date->format('F j, Y') }}</div>
            <div class="detail-row"><span class="label">Check-out Date:</span> {{ $reservation->check_out_date->format('F j, Y') }}</div>
            @if($reservation->preferredRoomType)
                <div class="detail-row"><span class="label">Room Type:</span> {{ $reservation->preferredRoomType->name }}</div>
            @endif
        </div>

        <div class="button-wrap">
            <a href="{{ $trackingUrl }}" class="button">Open Secure Tracking Link</a>
        </div>

        <div class="notice">
            For privacy, manual tracking in the app now requires both your reservation reference number and guest email address.
        </div>

        @if($paymentLink && in_array($reservation->status, ['approved', 'confirmed', 'pending_payment'], true))
            <div class="button-wrap">
                <a href="{{ $paymentLink }}" class="button">Open Payment Link</a>
            </div>
        @elseif(!($onlinePaymentsEnabled ?? true) && in_array($reservation->status, ['approved', 'confirmed', 'pending_payment'], true))
            <div class="notice">
                Online payment is currently unavailable. Please wait for staff instructions regarding payment.
            </div>
        @endif

        @if(in_array($reservation->status, ['declined', 'cancelled'], true))
            <div class="notice" style="background: #fef2f2; border-left-color: #dc2626;">
                Please contact the homestay staff if you need clarification about this reservation status.
            </div>
        @endif

        <div class="footer">
            This message was sent automatically by the University Homestay Management System.
        </div>
    </div>
</body>
</html>
