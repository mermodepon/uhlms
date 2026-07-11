<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function create(Reservation $reservation)
    {
        $account = Auth::guard('guest')->user();
        abort_unless((int) $reservation->guest_account_id === (int) $account->id, 403);

        $reservation->load('feedback', 'preferredRoomType');

        if (! $reservation->canReceiveFeedbackFrom($account)) {
            return redirect()
                ->route('guest.account.reservations.show', $reservation)
                ->with('success', $reservation->feedback ? 'Feedback has already been submitted for this stay.' : 'Feedback is available after a completed stay from a verified guest account.');
        }

        return view('guest.account.feedback', compact('account', 'reservation'));
    }

    public function store(Request $request, Reservation $reservation)
    {
        $account = Auth::guard('guest')->user();
        abort_unless((int) $reservation->guest_account_id === (int) $account->id, 403);

        $reservation->load('feedback');

        if (! $reservation->canReceiveFeedbackFrom($account)) {
            return redirect()
                ->route('guest.account.reservations.show', $reservation)
                ->withErrors(['feedback' => $reservation->feedback ? 'Feedback has already been submitted for this stay.' : 'Feedback is available after a completed stay from a verified guest account.']);
        }

        $ratingRule = ['nullable', 'integer', 'min:1', 'max:5'];
        $data = $request->validate([
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'cleanliness_rating' => $ratingRule,
            'comfort_rating' => $ratingRule,
            'service_rating' => $ratingRule,
            'value_rating' => $ratingRule,
            'booking_experience_rating' => $ratingRule,
            'would_stay_again' => ['nullable', Rule::in(['1', '0'])],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $reservation->feedback()->create([
            ...$data,
            'guest_account_id' => $account->id,
            'would_stay_again' => array_key_exists('would_stay_again', $data) ? (bool) $data['would_stay_again'] : null,
            'status' => 'new',
            'visibility_status' => 'internal',
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('guest.account.reservations.show', $reservation)
            ->with('success', 'Thank you. Your feedback has been submitted and will be reviewed internally.');
    }
}
