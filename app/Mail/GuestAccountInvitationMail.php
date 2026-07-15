<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Support\CanonicalAppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestAccountInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public ReservationPayment $payment,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Keep Your Reservation Details - {$this->reservation->reference_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest-account-invitation',
            with: [
                'reservation' => $this->reservation,
                'payment' => $this->payment,
                'registerUrl' => CanonicalAppUrl::fromRelative(route('guest.account.register', [], false)),
            ],
        );
    }
}
