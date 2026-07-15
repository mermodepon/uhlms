<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTrustedHost
{
    /**
     * Force Symfony to validate the request host against Laravel's trusted-host patterns.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->getHost();

        return $next($request);
    }
}
