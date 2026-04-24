<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public string $context = 'status_changed',
        public ?string $previousStatus = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->resolveSubject(),
        );
    }

    public function content(): Content
    {
        $onlinePaymentsEnabled = Setting::isOnlinePaymentsEnabled();
        $paymentLink = $onlinePaymentsEnabled && $this->reservation->isPaymentLinkValid()
            ? $this->reservation->generatePaymentLink()
            : null;

        return new Content(
            view: 'emails.reservation-status',
            with: [
                'reservation' => $this->reservation,
                'context' => $this->context,
                'previousStatus' => $this->previousStatus,
                'trackingUrl' => $this->reservation->generateGuestTrackingUrl(),
                'statusLabel' => $this->formatStatus($this->reservation->status),
                'previousStatusLabel' => $this->previousStatus ? $this->formatStatus($this->previousStatus) : null,
                'paymentLink' => $paymentLink,
                'onlinePaymentsEnabled' => $onlinePaymentsEnabled,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function resolveSubject(): string
    {
        if ($this->context === 'submitted') {
            return "Reservation Received - {$this->reservation->reference_number}";
        }

        return "Reservation {$this->formatStatus($this->reservation->status)} - {$this->reservation->reference_number}";
    }

    private function formatStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }
}
