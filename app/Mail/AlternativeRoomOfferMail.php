<?php

namespace App\Mail;

use App\Models\ReservationAlternativeOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlternativeRoomOfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReservationAlternativeOffer $offer, public string $offerUrl)
    {
        $this->offer->loadMissing(['reservation', 'requestLine.roomType', 'offeredRoomType']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Room Alternative Available - '.$this->offer->reservation->reference_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.alternative-room-offer');
    }

    public function attachments(): array { return []; }
}
