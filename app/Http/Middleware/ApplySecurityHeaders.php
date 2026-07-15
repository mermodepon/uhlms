<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->removePhpDisclosureHeader();

        if ($this->shouldEnforcePublicHttps($request) && ! $request->isSecure()) {
            $response = redirect()->to(
                'https://'.config('security.transport.canonical_host').$request->getRequestUri(),
                301,
            );

            return $this->applyBaselineHeaders($response);
        }

        $cspMode = config('security.content_security_policy.mode', 'off');
        $cspEnabled = in_array($cspMode, ['report-only', 'enforce'], true);

        if ($cspEnabled) {
            $request->attributes->set('csp_nonce', $this->nonceForRequest($request));
        }

        $response = $next($request);

        if ($this->shouldEnforcePublicHttps($request) && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        if ($cspEnabled && $this->isHtmlResponse($response)) {
            $header = $cspMode === 'enforce'
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';

            $response->headers->set($header, $this->contentSecurityPolicy(
                (string) $request->attributes->get('csp_nonce'),
            ));
        }

        return $this->applyBaselineHeaders($response);
    }

    private function applyBaselineHeaders(Response $response): Response
    {
        foreach (config('security.browser_headers', []) as $name => $value) {
            $response->headers->set($name, $value);
        }

        $response->headers->remove('X-Powered-By');
        $this->removePhpDisclosureHeader();

        return $response;
    }

    private function removePhpDisclosureHeader(): void
    {
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }
    }

    private function nonceForRequest(Request $request): string
    {
        $livewireNonce = (string) $request->header('X-CSP-Nonce', '');

        if (
            $request->headers->has('X-Livewire')
            && preg_match('~\A[A-Za-z0-9+/]{24}\z~D', $livewireNonce) === 1
        ) {
            return $livewireNonce;
        }

        return base64_encode(random_bytes(18));
    }

    private function shouldEnforcePublicHttps(Request $request): bool
    {
        if (! config('security.transport.enforce_https', false)) {
            return false;
        }

        $canonicalHost = strtolower((string) config('security.transport.canonical_host'));

        return $canonicalHost !== '' && hash_equals($canonicalHost, strtolower($request->getHost()));
    }

    private function hstsValue(): string
    {
        $value = 'max-age='.(int) config('security.transport.hsts_max_age', 2592000);

        if (config('security.transport.hsts_include_subdomains', false)) {
            $value .= '; includeSubDomains';
        }

        if (config('security.transport.hsts_preload', false)) {
            $value .= '; preload';
        }

        return $value;
    }

    private function isHtmlResponse(Response $response): bool
    {
        return str_contains(
            strtolower((string) $response->headers->get('Content-Type')),
            'text/html',
        );
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $directives = config('security.content_security_policy.directives', []);
        $directives['script-src'] = [
            ...($directives['script-src'] ?? ["'self'"]),
            "'nonce-{$nonce}'",
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
