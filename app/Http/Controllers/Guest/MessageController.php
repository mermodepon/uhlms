<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $referenceNumber = $request->query('reference');
        
        $reservation = null;
        $messages = collect();
        
        if ($referenceNumber) {
            $reservation = Reservation::where('reference_number', $referenceNumber)->first();
            
            if ($reservation) {
                $messages = Message::where('reservation_id', $reservation->id)
                    ->orderBy('created_at', 'asc')
                    ->get();
                    
                // Mark staff/admin replies as read when guest views them
                Message::where('reservation_id', $reservation->id)
                    ->whereIn('sender_type', ['staff', 'admin'])
                    ->where('is_read', false)
                    ->each(function ($message) {
                        $message->markAsRead();
                    });
            }
        }
        
        return view('guest.messages', compact('reservation', 'messages', 'referenceNumber'));
    }
    
    /**
     * Store a message tied to a reservation (requires reference number).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference_number' => 'required|exists:reservations,reference_number',
            'sender_name'      => 'required|string|max:255',
            'sender_email'     => 'required|email|max:255',
            'message'          => 'required|string|max:5000',
        ]);
        
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        $reservation = Reservation::where('reference_number', $request->reference_number)->firstOrFail();
        
        Message::create([
            'reservation_id' => $reservation->id,
            'sender_name'    => $request->sender_name,
            'sender_email'   => $request->sender_email,
            'sender_type'    => 'guest',
            'message'        => $request->message,
        ]);
        
        return back()->with('success', 'Your message has been sent successfully!');
    }

    /**
     * Store a general inquiry (no reservation reference needed).
     */
    public function storeInquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sender_name'  => 'required|string|max:255',
            'sender_email' => 'required|email|max:255',
            'subject'      => 'required|string|max:150',
            'message'      => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('active_tab', 'inquiry');
        }

        Message::create([
            'reservation_id' => null,
            'sender_name'    => $request->sender_name,
            'sender_email'   => $request->sender_email,
            'subject'        => $request->subject,
            'sender_type'    => 'guest',
            'message'        => $request->message,
        ]);

        return back()->with('inquiry_success', 'Your inquiry has been submitted! Our staff will get back to you via email.')->with('active_tab', 'inquiry');
    }
}

