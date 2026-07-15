<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Support\AdminMfa;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class AdminLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if ($request->session()->has(AdminMfa::PENDING_SESSION_KEY)) {
            return redirect()->route('admin.mfa.challenge');
        }

        $user = auth()->user();
        if (
            $user instanceof User
            && AdminMfa::isEnforced()
            && AdminMfa::isMandatory($user)
            && ! AdminMfa::isEnabled($user)
        ) {
            return redirect()->route('admin.mfa.setup');
        }

        return redirect()->intended(Filament::getUrl());
    }
}
