<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(to right, #00491E, #02681E);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 20px -30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .reservation-details {
            background: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #6b7280;
            font-weight: 600;
        }
        .detail-value {
            color: #111827;
        }
        .payment-button {
            display: inline-block;
            background: linear-gradient(to right, #00491E, #02681E);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            font-size: 16px;
        }
        .button-container {
            text-align: center;
        }
        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏠 Complete Your Payment</h1>
        </div>

        <p>Dear <strong>{{ $reservation->guest_name }}</strong>,</p>

        <p>Thank you for your reservation at our University Homestay! To confirm your booking, please complete your deposit payment using the secure link below.</p>

        <div class="reservation-details">
            <h3 style="margin-top: 0; color: #00491E;">Reservation Details</h3>
            <div class="detail-row">
                <span class="detail-label">Reservation Number:</span>
                <span class="detail-value"><strong>{{ $reservation->reference_number }}</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-in Date:</span>
                <span class="detail-value">{{ $reservation->check_in_date->format('F j, Y') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-out Date:</span>
                <span class="detail-value">{{ $reservation->check_out_date->format('F j, Y') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Number of Guests:</span>
                <span class="detail-value">{{ $reservation->number_of_occupants }}</span>
            </div>
            @if($reservation->preferredRoomType)
            <div class="detail-row">
                <span class="detail-label">Room Type:</span>
                <span class="detail-value">{{ $reservation->preferredRoomType->name }}</span>
            </div>
            @endif
        </div>

        <div class="info-box">
            <strong>📋 What You'll Pay:</strong><br>
            You'll pay a deposit now to secure your reservation. The remaining balance will be collected when you check in at the homestay.
        </div>

        <div class="button-container">
            <a href="{{ $paymentLink }}" class="payment-button">
                🔒 Pay Deposit Now
            </a>
        </div>

        <div class="warning-box">
            <strong>⏰ Important:</strong> This payment link will expire on <strong>{{ $expiresAt ? $expiresAt->format('F j, Y \a\t g:i A') : 'N/A' }}</strong>. 
            Please complete your payment before this time to secure your reservation.
        </div>

        <p><strong>Payment Methods Available:</strong></p>
        <ul>
            <li>GCash</li>
            <li>Maya (PayMaya)</li>
            <li>GrabPay</li>
            <li>Credit/Debit Card</li>
        </ul>

        <p><strong>What Happens Next?</strong></p>
        <ol>
            <li>Click the "Pay Deposit Now" button above</li>
            <li>Choose your preferred payment method</li>
            <li>Complete the secure payment</li>
            <li>You'll receive a confirmation email</li>
            <li>Our staff will review and finalize your reservation</li>
        </ol>

        <div class="info-box">
            <strong>💡 Need Help?</strong><br>
            If you have any questions or need assistance, please contact us at:<br>
            📧 Email: support@uhlms.edu.ph<br>
            📞 Phone: (123) 456-7890
        </div>

        <div class="footer">
            <p>This is an automated message from the University Homestay Management System.</p>
            <p>&copy; {{ date('Y') }} University Homestay. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
