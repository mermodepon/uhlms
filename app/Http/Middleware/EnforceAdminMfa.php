<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AdminMfa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (
            $user instanceof User
            && AdminMfa::isEnforced()
            && AdminMfa::isMandatory($user)
            && ! AdminMfa::isEnabled($user)
            && ! $request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout')
        ) {
            return redirect()->route('admin.mfa.setup');
        }

        return $next($request);
    }
}
