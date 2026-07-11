<p>Dear {{ $offer->reservation->guest_name }},</p>
<p>Your requested room type is unavailable, but we can offer <strong>{{ $offer->offeredRoomType->name }}</strong> for your stay from {{ $offer->reservation->check_in_date->format('M d, Y') }} to {{ $offer->reservation->check_out_date->format('M d, Y') }}.</p>
<p><strong>Quoted total:</strong> PHP {{ number_format($offer->quoted_total, 2) }}@if($offer->quoted_total != $offer->original_total) ({{ $offer->quoted_total > $offer->original_total ? '+' : '-' }}PHP {{ number_format(abs($offer->quoted_total - $offer->original_total), 2) }} from your original request)@endif.</p>
@if($offer->message)<p>{{ $offer->message }}</p>@endif
<p>This offer is held until <strong>{{ $offer->expires_at->format('M d, Y g:i A') }}</strong>.</p>
<p><a href="{{ $offerUrl }}">Review and respond to this alternative offer</a></p>
