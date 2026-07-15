<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    private const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), hid=(), display-capture=()';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('security.transport.enforce_https', true);
        config()->set('security.transport.canonical_host', 'app.uhlms.uk');
        config()->set('security.transport.hsts_max_age', 2592000);
        config()->set('security.transport.hsts_include_subdomains', false);
        config()->set('security.transport.hsts_preload', false);
        config()->set('security.content_security_policy.mode', 'report-only');
    }

    public function test_public_http_requests_redirect_to_the_same_https_path_and_query(): void
    {
        $response = $this->get('http://app.uhlms.uk/rooms?arrival=2026-08-01&nights=2');

        $response->assertStatus(301);
        $response->assertRedirect('https://app.uhlms.uk/rooms?arrival=2026-08-01&nights=2');
        $response->assertHeaderMissing('Strict-Transport-Security');
        $this->assertBaselineHeaders($response);
    }

    public function test_public_https_responses_receive_staged_hsts_without_subdomains_or_preload(): void
    {
        $response = $this->get('https://app.uhlms.uk/');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=2592000');

        $header = (string) $response->headers->get('Strict-Transport-Security');
        $this->assertStringNotContainsString('includeSubDomains', $header);
        $this->assertStringNotContainsString('preload', $header);
    }

    public function test_local_http_remains_available_when_public_https_is_enabled(): void
    {
        $response = $this->get('http://localhost/');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_html_responses_receive_a_unique_nonce_based_report_only_policy(): void
    {
        $first = $this->get('http://localhost/');
        $second = $this->get('http://localhost/');

        $firstPolicy = (string) $first->headers->get('Content-Security-Policy-Report-Only');
        $secondPolicy = (string) $second->headers->get('Content-Security-Policy-Report-Only');

        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\\/=]+'/", $firstPolicy);
        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\\/=]+'/", $secondPolicy);
        $this->assertNotSame($firstPolicy, $secondPolicy);
        $first->assertHeaderMissing('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $firstPolicy);
        $this->assertStringContainsString("script-src-attr 'unsafe-inline'", $firstPolicy);
        $this->assertExecutableInlineScriptsUsePolicyNonce($first);
    }

    public function test_non_html_responses_do_not_receive_a_content_security_policy(): void
    {
        Route::get('/_security-json', fn () => response()->json(['ok' => true]));

        $response = $this->get('http://localhost/_security-json');

        $response->assertOk();
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $this->assertBaselineHeaders($response);
    }

    public function test_baseline_headers_apply_to_html_json_redirects_and_handled_errors(): void
    {
        Route::get('/_security-json-headers', fn () => response()->json(['ok' => true]));
        Route::get('/_security-forbidden', fn () => abort(403));

        $responses = [
            $this->get('http://localhost/'),
            $this->get('http://localhost/_security-json-headers'),
            $this->get('http://app.uhlms.uk/rooms'),
            $this->get('http://localhost/_security-forbidden'),
            $this->get('http://localhost/_security-not-found'),
        ];

        foreach ($responses as $response) {
            $this->assertBaselineHeaders($response);
        }
    }

    public function test_php_disclosure_header_is_removed_from_application_responses(): void
    {
        Route::get('/_security-powered-by', fn () => response('ok')->header('X-Powered-By', 'PHP/8.2.30'));

        $response = $this->get('http://localhost/_security-powered-by');

        $response->assertOk();
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_csp_can_be_enforced_or_disabled_explicitly(): void
    {
        config()->set('security.content_security_policy.mode', 'enforce');

        $enforced = $this->get('http://localhost/');
        $enforced->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $this->assertNotEmpty($enforced->headers->get('Content-Security-Policy'));
        $this->assertExecutableInlineScriptsUsePolicyNonce($enforced, 'Content-Security-Policy');

        config()->set('security.content_security_policy.mode', 'off');

        $disabled = $this->get('http://localhost/');
        $disabled->assertHeaderMissing('Content-Security-Policy');
        $disabled->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_filament_inline_scripts_use_the_policy_nonce(): void
    {
        config()->set('security.content_security_policy.mode', 'enforce');

        $response = $this->get('http://localhost/admin/login');

        $response->assertOk();
        $this->assertExecutableInlineScriptsUsePolicyNonce($response, 'Content-Security-Policy');
    }

    public function test_livewire_fragments_can_reuse_only_a_well_formed_document_nonce(): void
    {
        config()->set('security.content_security_policy.mode', 'enforce');
        Route::get('/_security-livewire-nonce', fn () => response('<html><body>fragment</body></html>'));

        $documentNonce = base64_encode(random_bytes(18));
        $valid = $this->withHeaders([
            'X-Livewire' => 'true',
            'X-CSP-Nonce' => $documentNonce,
        ])->get('http://localhost/_security-livewire-nonce');

        $this->assertStringContainsString(
            "'nonce-{$documentNonce}'",
            (string) $valid->headers->get('Content-Security-Policy'),
        );

        $invalid = $this->withHeaders([
            'X-Livewire' => 'true',
            'X-CSP-Nonce' => "bad' 'unsafe-inline",
        ])->get('http://localhost/_security-livewire-nonce');

        $invalidPolicy = (string) $invalid->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString("bad' 'unsafe-inline", $invalidPolicy);
        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\\/=]+'/", $invalidPolicy);
    }

    private function assertBaselineHeaders($response): void
    {
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-XSS-Protection', '0');
        $response->assertHeader('Permissions-Policy', self::PERMISSIONS_POLICY);
        $response->assertHeaderMissing('X-Powered-By');
    }

    private function assertExecutableInlineScriptsUsePolicyNonce($response, string $header = 'Content-Security-Policy-Report-Only'): void
    {
        $policy = (string) $response->headers->get($header);
        preg_match("/'nonce-([^']+)'/", $policy, $policyNonce);
        $this->assertNotEmpty($policyNonce[1] ?? null, 'The CSP header did not contain a nonce.');

        preg_match_all('/<script\\b([^>]*)>/i', (string) $response->getContent(), $scriptTags);

        foreach ($scriptTags[1] as $attributes) {
            if (preg_match('/\\bsrc\\s*=/i', $attributes)) {
                continue;
            }

            if (preg_match('/\\btype=["\']application\\/json["\']/i', $attributes)) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/\\bnonce=["\']'.preg_quote($policyNonce[1], '/').'["\']/',
                $attributes,
                'An executable inline script did not use the response CSP nonce.',
            );
            preg_match_all('/\\bnonce\\s*=/i', $attributes, $nonceAttributes);
            $this->assertCount(1, $nonceAttributes[0], 'An executable inline script had duplicate nonce attributes.');
        }
    }
}
