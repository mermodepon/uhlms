<?php

namespace App\Http\Middleware;

use App\Support\SecurityMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonitorSecurityResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/webhooks/paymongo')) {
            app(SecurityMonitor::class)->response(
                $response->getStatusCode(),
                $request->route()?->getName(),
                $request->method(),
                $request->ip(),
            );
        }

        return $response;
    }
}
