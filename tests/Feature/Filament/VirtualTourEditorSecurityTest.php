<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\VirtualTourResource\Pages\ManageTourHotspots;
use App\Models\TourHotspot;
use App\Models\TourWaypoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualTourEditorSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_safely_serializes_stored_closing_script_payloads(): void
    {
        $payload = '</script><script>window.__tourXss = true</script>';
        $admin = User::create([
            'name' => 'Tour Security Admin',
            'email' => 'tour-security@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'permissions' => null,
        ]);
        $waypoint = TourWaypoint::create([
            'name' => $payload,
            'slug' => 'security-test-waypoint',
            'type' => 'entrance',
            'panorama_image' => 'virtual-tour/panoramas/security-test.jpg',
            'position_order' => 1,
            'is_active' => true,
        ]);
        TourHotspot::create([
            'waypoint_id' => $waypoint->id,
            'title' => $payload,
            'description' => $payload,
            'media_type' => null,
            'media_url' => $payload,
            'icon' => $payload,
            'pitch' => 0,
            'yaw' => 0,
            'action_type' => 'external-link',
            'action_target' => 'https://example.test/?value='.urlencode($payload),
            'sort_order' => 0,
            'is_active' => true,
            'size' => 3,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(ManageTourHotspots::getUrl(['record' => $waypoint->id]));

        $response->assertOk();
        $response->assertDontSee('</script><script>window.__tourXss', false);
        $response->assertSee('waypoints: JSON.parse(', false);
        $response->assertSee('hotspots: JSON.parse(', false);

        $policy = (string) (
            $response->headers->get('Content-Security-Policy')
            ?? $response->headers->get('Content-Security-Policy-Report-Only')
        );
        preg_match("/'nonce-([^']+)'/", $policy, $policyNonce);
        preg_match('/<script[^>]+nonce="([^"]+)"[^>]*>/', $response->getContent(), $scriptNonce);

        $this->assertNotEmpty($policyNonce[1] ?? null);
        $this->assertSame($policyNonce[1], $scriptNonce[1] ?? null);

        $page = app(ManageTourHotspots::class);
        $page->mount($waypoint->id);

        $this->assertSame($payload, $page->waypointsData()[0]['name']);
        $this->assertSame($payload, $page->hotspotsData()[0]['title']);
        $this->assertSame($payload, $page->hotspotsData()[0]['description']);
    }
}
