<?php

namespace App\Observers;

use App\Models\Message;
use App\Models\Notification;
use App\Models\User;

class MessageObserver
{
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        // Eager load relationships to avoid lazy loading violations
        $message->loadMissing(['sender', 'reservation']);

        // Get reservation reference for context
        $reservationRef = $message->reservation 
            ? "Reservation #{$message->reservation->reference_number}" 
            : "Message";

        // If message is from a guest, notify all staff/admin users
        if ($message->sender_type === 'guest') {
            $staffUsers = User::whereIn('role', ['admin', 'staff'])->get();
            
            $messagePreview = strlen($message->message) > 60 
                ? substr($message->message, 0, 60) . '...' 
                : $message->message;

            foreach ($staffUsers as $user) {
                Notification::createNotification(
                    notifiable: $user,
                    title: "New Message from Guest",
                    message: "{$reservationRef}: {$messagePreview}",
                    type: 'info',
                    category: 'message',
                    actionUrl: $message->reservation_id 
                        ? "/admin/conversations/{$message->reservation_id}" 
                        : null,
                    createdBy: $message->sender_id
                );
            }
        } 
        // If message is from staff/admin, notify the guest (if authenticated)
        elseif (in_array($message->sender_type, ['staff', 'admin']) && $message->reservation) {
            // Try to find authenticated guest user
            $guestUser = User::where('role', 'guest')
                ->where('email', $message->reservation->guest_email)
                ->first();

            if ($guestUser) {
                $senderName = $message->sender ? $message->sender->name : 'Staff';
                $messagePreview = strlen($message->message) > 60 
                    ? substr($message->message, 0, 60) . '...' 
                    : $message->message;

                Notification::createNotification(
                    notifiable: $guestUser,
                    title: "New Reply from {$senderName}",
                    message: "{$reservationRef}: {$messagePreview}",
                    type: 'info',
                    category: 'message',
                    actionUrl: "/guest/messages",
                    createdBy: $message->sender_id
                );
            }
        }
    }

    /**
     * Handle the Message "updated" event.
     */
    public function updated(Message $message): void
    {
        // Eager load relationships
        $message->loadMissing(['sender', 'reservation']);

        // If message is marked as read, notify the sender (if guest is authenticated)
        if ($message->isDirty('is_read') && $message->is_read) {
            if ($message->sender_type === 'guest' && $message->sender) {
                $reservationRef = $message->reservation 
                    ? "Reservation #{$message->reservation->reference_number}" 
                    : "Your message";

                Notification::createNotification(
                    notifiable: $message->sender,
                    title: "Message Read",
                    message: "{$reservationRef} has been read by staff",
                    type: 'success',
                    category: 'message',
                    actionUrl: $message->reservation_id 
                        ? "/admin/conversations/{$message->reservation_id}" 
                        : null,
                    createdBy: auth()->id()
                );
            }
        }
    }

    /**
     * Handle the Message "deleted" event.
     */
    public function deleted(Message $message): void
    {
        // Optional: Clean up related notifications when message is deleted
        // This prevents orphaned notifications pointing to deleted messages
        Notification::where('category', 'message')
            ->where('action_url', 'like', "%/conversations/{$message->reservation_id}%")
            ->delete();
    }

    /**
     * Handle the Message "restored" event.
     */
    public function restored(Message $message): void
    {
        //
    }

    /**
     * Handle the Message "force deleted" event.
     */
    public function forceDeleted(Message $message): void
    {
        //
    }
}
