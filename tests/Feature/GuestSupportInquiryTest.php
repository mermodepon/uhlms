<?php

namespace Tests\Feature;

use App\Models\GuestAccount;
use App\Models\SupportInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestSupportInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_support_page_explains_account_requirement_and_guest_reservations(): void
    {
        $this->get(route('guest.support'))
            ->assertOk()
            ->assertSee('Support is available to verified guest accounts')
            ->assertSee('Create a Guest Account')
            ->assertSee('You do not need an account to request a stay.');
    }

    public function test_authenticated_guest_is_sent_from_public_support_to_their_threads(): void
    {
        $account = $this->account(['email_verified_at' => now()]);

        $this->actingAs($account, 'guest')
            ->get(route('guest.support'))
            ->assertRedirect(route('guest.account.support.index'));
    }

    public function test_unverified_guest_can_read_support_but_cannot_create_or_reply(): void
    {
        $account = $this->account();
        $inquiry = $this->inquiryFor($account);

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.support.index'))
            ->assertOk()
            ->assertSee('Verify your email before sending support messages.')
            ->assertSee('Resend Verification');

        $this->actingAs($account, 'guest')
            ->get(route('guest.account.support.show', $inquiry))
            ->assertOk()
            ->assertSee('Verify your email before replying.');

        $this->actingAs($account, 'guest')
            ->post(route('guest.account.support.submit'), $this->payload())
            ->assertRedirect(route('guest.account.support.index'))
            ->assertSessionHasErrors('support');

        $this->assertDatabaseCount('support_inquiries', 1);

        $this->actingAs($account, 'guest')
            ->post(route('guest.account.support.reply', $inquiry), ['message' => 'Can you please help me?'])
            ->assertRedirect(route('guest.account.support.index'))
            ->assertSessionHasErrors('support');

        $this->assertDatabaseCount('support_inquiry_replies', 0);
    }

    public function test_verified_guest_can_create_and_reply_with_actual_verification_timestamp(): void
    {
        $verifiedAt = now()->startOfSecond()->subMinute();
        $account = $this->account(['email_verified_at' => $verifiedAt]);

        $this->actingAs($account, 'guest')
            ->post(route('guest.account.support.submit'), $this->payload())
            ->assertRedirect();

        $inquiry = SupportInquiry::sole();
        $this->assertTrue($inquiry->email_verified_at->equalTo($verifiedAt));
        $this->assertSame($account->id, $inquiry->guest_account_id);

        $this->actingAs($account, 'guest')
            ->post(route('guest.account.support.reply', $inquiry), ['message' => 'Can you please help me?'])
            ->assertRedirect(route('guest.account.support.show', $inquiry));

        $this->assertDatabaseHas('support_inquiry_replies', [
            'support_inquiry_id' => $inquiry->id,
            'guest_account_id' => $account->id,
            'message' => 'Can you please help me?',
        ]);
    }

    public function test_guest_cannot_access_another_guests_thread(): void
    {
        $owner = $this->account(['email' => 'owner@example.com', 'email_verified_at' => now()]);
        $otherGuest = $this->account(['email' => 'other@example.com', 'email_verified_at' => now()]);
        $inquiry = $this->inquiryFor($owner);

        $this->actingAs($otherGuest, 'guest')
            ->get(route('guest.account.support.show', $inquiry))
            ->assertForbidden();

        $this->actingAs($otherGuest, 'guest')
            ->get(route('guest.account.support.messages', $inquiry))
            ->assertForbidden();

        $this->actingAs($otherGuest, 'guest')
            ->post(route('guest.account.support.reply', $inquiry), ['message' => 'Not my thread.'])
            ->assertForbidden();
    }

    private function account(array $overrides = []): GuestAccount
    {
        static $sequence = 1;

        return GuestAccount::create(array_merge([
            'last_name' => 'Guest',
            'first_name' => 'Support',
            'email' => 'guest'.($sequence++).'@example.com',
            'password' => 'password',
            'phone' => '09171234567',
        ], $overrides));
    }

    private function inquiryFor(GuestAccount $account): SupportInquiry
    {
        return SupportInquiry::create([
            'guest_account_id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'email_verified_at' => $account->email_verified_at,
            'category' => SupportInquiry::CATEGORY_GENERAL,
            'subject' => 'Existing question',
            'message' => 'I need help with a reservation.',
            'source' => SupportInquiry::SOURCE_GUEST_ACCOUNT,
        ]);
    }

    private function payload(): array
    {
        return [
            'category' => SupportInquiry::CATEGORY_GENERAL,
            'subject' => 'Question about rooms',
            'message' => 'I would like to ask about room availability for next week.',
        ];
    }
}
