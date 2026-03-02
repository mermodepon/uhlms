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
                    
                // Mark guest messages from staff/admin as read
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
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference_number' => 'required|exists:reservations,reference_number',
            'sender_name' => 'required|string|max:255',
            'sender_email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
        ]);
        
        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $reservation = Reservation::where('reference_number', $request->reference_number)->firstOrFail();
        
        Message::create([
            'reservation_id' => $reservation->id,
            'sender_name' => $request->sender_name,
            'sender_email' => $request->sender_email,
            'sender_type' => 'guest',
            'message' => $request->message,
        ]);
        
        // Notify staff about new guest message
        $staffUsers = \App\Models\User::whereIn('role', ['admin', 'staff'])->get();
        foreach ($staffUsers as $user) {
            \App\Models\Notification::createNotification(
                $user,
                'New Guest Message',
                'Guest ' . $request->sender_name . ' sent a message regarding reservation ' . $request->reference_number,
                'info',
                'message',
                '/admin/messages'
            );
        }
        
        return back()->with('success', 'Your message has been sent successfully!');
    }
}

