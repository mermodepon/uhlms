<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('guest.account.profile', ['account' => Auth::guard('guest')->user()]);
    }

    public function update(Request $request)
    {
        $account = Auth::guard('guest')->user();

        $data = $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^(09\d{9}|\+639\d{9}|639\d{9})$/'],
            'gender' => 'nullable|in:Male,Female,Other',
            'age' => 'nullable|integer|min:18|max:120',
            'address' => 'nullable|string|max:1000',
        ]);

        $account->update($data);

        return back()->with('success', 'Profile updated. Future booking forms will use these details.');
    }
}
