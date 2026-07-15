<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\GuestAccount;
use App\Support\CanonicalAppUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function showRequest()
    {
        return view('guest.account.password-request');
    }

    public function send(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        $account = GuestAccount::where('email', $data['email'])->first();

        if ($account) {
            $token = Str::random(64);
            DB::table('guest_password_reset_tokens')->updateOrInsert(
                ['email' => $account->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $url = CanonicalAppUrl::fromRelative(route('guest.account.password.reset', [
                'token' => $token,
                'email' => $account->email,
            ], false));
            Mail::raw(
                "Reset your University Homestay guest account password using this link:\n{$url}\n\nThis link expires in 60 minutes.",
                fn ($message) => $message->to($account->email)->subject('Reset your guest account password')
            );
        }

        return back()->with('success', 'If an account exists for that email, a reset link has been sent.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('guest.account.password-reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('guest_password_reset_tokens')->where('email', $data['email'])->first();

        if (! $record || Carbon::parse($record->created_at)->addMinutes(60)->isPast() || ! Hash::check($data['token'], $record->token)) {
            return back()->withErrors(['email' => 'This reset link is invalid or expired.']);
        }

        $account = GuestAccount::where('email', $data['email'])->firstOrFail();
        $account->update(['password' => $data['password']]);
        DB::table('guest_password_reset_tokens')->where('email', $data['email'])->delete();

        return redirect()->route('guest.account.login')->with('success', 'Password reset. You can now log in.');
    }
}
