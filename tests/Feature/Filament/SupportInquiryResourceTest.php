<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SupportInbox;
use App\Filament\Resources\SupportInquiryResource;
use App\Filament\Resources\SupportInquiryResource\Pages\EditSupportInquiry;
use App\Filament\Resources\SupportInquiryResource\Pages\ListSupportInquiries;
use App\Filament\Resources\SupportInquiryResource\Pages\ViewSupportInquiry;
use App\Models\GuestAccount;
use App\Models\SupportInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SupportInquiryResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $restrictedStaff;

    private User $viewOnlyStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'permissions' => null,
        ]);

        $this->restrictedStaff = User::create([
            'name' => 'Restricted',
            'email' => 'restricted@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'permissions' => [
                'support_inquiries_view' => false,
                'support_inquiries_edit' => false,
            ],
        ]);

        $this->viewOnlyStaff = User::create([
            'name' => 'View Only',
            'email' => 'view-only@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'permissions' => [
                'support_inquiries_view' => true,
                'support_inquiries_edit' => false,
            ],
        ]);
    }

    public function test_staff_can_render_support_inquiry_list(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(ListSupportInquiries::class)->assertSuccessful();
    }

    public function test_permission_controls_resource_access(): void
    {
        $this->actingAs($this->staff);
        $this->assertTrue(SupportInquiryResource::canAccess());

        $this->actingAs($this->restrictedStaff);
        $this->assertFalse(SupportInquiryResource::canAccess());
    }

    public function test_staff_can_update_triage_fields(): void
    {
        $this->actingAs($this->staff);

        $inquiry = $this->createInquiry();

        Livewire::test(EditSupportInquiry::class, ['record' => $inquiry->getRouteKey()])
            ->fillForm([
                'status' => SupportInquiry::STATUS_RESOLVED,
                'priority' => SupportInquiry::PRIORITY_HIGH,
                'internal_notes' => 'Responded by phone.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $inquiry->refresh();

        $this->assertSame(SupportInquiry::STATUS_RESOLVED, $inquiry->status);
        $this->assertSame(SupportInquiry::PRIORITY_HIGH, $inquiry->priority);
        $this->assertSame('Responded by phone.', $inquiry->internal_notes);
        $this->assertSame($this->staff->id, $inquiry->handled_by);
        $this->assertNotNull($inquiry->handled_at);
        $this->assertNotNull($inquiry->resolved_at);
    }

    public function test_mark_status_helper_sets_handler(): void
    {
        $inquiry = $this->createInquiry();

        $inquiry->markStatus(SupportInquiry::STATUS_IN_PROGRESS, $this->staff);

        $this->assertSame(SupportInquiry::STATUS_IN_PROGRESS, $inquiry->fresh()->status);
        $this->assertSame($this->staff->id, $inquiry->fresh()->handled_by);
    }

    public function test_view_only_staff_cannot_send_support_replies(): void
    {
        $this->actingAs($this->viewOnlyStaff);
        $inquiry = $this->createInquiry();

        Livewire::test(SupportInbox::class)
            ->set('selectedId', $inquiry->id)
            ->set('replyText', 'This reply must not be sent.')
            ->call('sendReply')
            ->assertForbidden();

        $this->assertDatabaseCount('support_inquiry_replies', 0);
    }

    public function test_view_only_staff_can_read_inquiries_but_resource_reply_actions_are_hidden(): void
    {
        $this->actingAs($this->viewOnlyStaff);
        $inquiry = $this->createAccountInquiry();

        Livewire::test(ListSupportInquiries::class)
            ->assertSuccessful()
            ->assertTableActionHidden('reply', $inquiry);

        Livewire::test(ViewSupportInquiry::class, ['record' => $inquiry->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('reply');
    }

    public function test_table_reply_action_authorizes_its_execution_callback(): void
    {
        $this->actingAs($this->viewOnlyStaff);
        $inquiry = $this->createAccountInquiry();
        $component = Livewire::test(ListSupportInquiries::class);
        $action = $component->instance()->getTable()->getAction('reply');

        $action
            ->record($inquiry)
            ->formData(['message' => 'This direct table reply must be rejected.']);

        $this->assertForbiddenCallback(fn () => $action->call());
        $this->assertDatabaseCount('support_inquiry_replies', 0);
    }

    public function test_view_reply_action_authorizes_its_execution_callback(): void
    {
        $this->actingAs($this->viewOnlyStaff);
        $inquiry = $this->createAccountInquiry();
        $component = Livewire::test(ViewSupportInquiry::class, ['record' => $inquiry->getRouteKey()]);
        $action = $component->instance()->getAction('reply');

        $action
            ->record($inquiry)
            ->formData(['message' => 'This direct detail reply must be rejected.']);

        $this->assertForbiddenCallback(fn () => $action->call());
        $this->assertDatabaseCount('support_inquiry_replies', 0);
    }

    public function test_authorized_staff_can_reply_from_table_and_view_actions(): void
    {
        $this->actingAs($this->staff);
        $inquiry = $this->createAccountInquiry();

        Livewire::test(ListSupportInquiries::class)
            ->assertTableActionVisible('reply', $inquiry)
            ->callTableAction('reply', $inquiry, [
                'message' => 'Reply sent from the inquiry list.',
            ])
            ->assertHasNoTableActionErrors();

        Livewire::test(ViewSupportInquiry::class, ['record' => $inquiry->getRouteKey()])
            ->assertActionVisible('reply')
            ->callAction('reply', [
                'message' => 'Reply sent from the inquiry detail page.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('support_inquiry_replies', [
            'support_inquiry_id' => $inquiry->id,
            'user_id' => $this->staff->id,
            'guest_account_id' => null,
            'message' => 'Reply sent from the inquiry list.',
        ]);
        $this->assertDatabaseHas('support_inquiry_replies', [
            'support_inquiry_id' => $inquiry->id,
            'user_id' => $this->staff->id,
            'guest_account_id' => null,
            'message' => 'Reply sent from the inquiry detail page.',
        ]);
    }

    public function test_reply_actions_reject_inquiries_without_a_guest_account(): void
    {
        $this->actingAs($this->staff);
        $inquiry = $this->createInquiry();

        Livewire::test(ListSupportInquiries::class)
            ->assertTableActionHidden('reply', $inquiry);

        Livewire::test(ViewSupportInquiry::class, ['record' => $inquiry->getRouteKey()])
            ->assertActionHidden('reply');

        $this->assertFalse(SupportInquiryResource::canReply($inquiry));
        $this->assertForbiddenCallback(fn () => SupportInquiryResource::authorizeReply($inquiry));
        $this->assertDatabaseCount('support_inquiry_replies', 0);
    }

    private function assertForbiddenCallback(callable $callback): void
    {
        try {
            $callback();
            $this->fail('The reply callback did not reject the unauthorized request.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function createAccountInquiry(): SupportInquiry
    {
        $account = GuestAccount::create([
            'last_name' => 'Guest',
            'first_name' => 'Support',
            'email' => 'linked-guest@example.com',
            'password' => 'password',
            'phone' => '09171234567',
        ]);

        return $this->createInquiry([
            'guest_account_id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'source' => SupportInquiry::SOURCE_GUEST_ACCOUNT,
        ]);
    }

    private function createInquiry(array $overrides = []): SupportInquiry
    {
        return SupportInquiry::create(array_merge([
            'name' => 'Public Guest',
            'email' => 'guest@example.com',
            'category' => SupportInquiry::CATEGORY_GENERAL,
            'subject' => 'Question',
            'message' => 'I need help with my reservation.',
            'status' => SupportInquiry::STATUS_NEW,
            'priority' => SupportInquiry::PRIORITY_NORMAL,
            'source' => SupportInquiry::SOURCE_PUBLIC,
        ], $overrides));
    }
}
