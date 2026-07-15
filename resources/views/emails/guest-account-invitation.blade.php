<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#f5f5f5;font-family:Arial,sans-serif;color:#1f2937;">
    <main style="max-width:600px;margin:24px auto;background:#ffffff;padding:32px;border-radius:12px;">
        <h1 style="margin:0 0 20px;color:#00491E;font-size:24px;">Online payment received</h1>
        <p>Hello {{ $reservation->guest_name }},</p>
        <p>Your online check-in payment of <strong>PHP {{ number_format((float) $payment->amount, 2) }}</strong> for reservation <strong>{{ $reservation->reference_number }}</strong> has been recorded.</p>
        <p>Creating a guest account is optional. If you use this same email address and verify it, this reservation and its payment details will appear in your account.</p>
        <p style="margin:28px 0;text-align:center;">
            <a href="{{ $registerUrl }}" style="display:inline-block;background:#00491E;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">Create Guest Account</a>
        </p>
        <p style="font-size:13px;color:#6b7280;">You may ignore this email if you do not wish to create an account. Your payment and stay are unaffected.</p>
    </main>
</body>
</html>
