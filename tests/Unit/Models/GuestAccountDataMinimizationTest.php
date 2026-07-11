<?php

namespace Tests\Unit\Models;

use App\Models\GuestAccount;
use Tests\TestCase;

class GuestAccountDataMinimizationTest extends TestCase
{
    public function test_account_profile_keeps_age_but_not_birthdate(): void
    {
        $account = new GuestAccount;

        $this->assertContains('age', $account->getFillable());
        $this->assertNotContains('birthdate', $account->getFillable());
        $this->assertSame('integer', $account->getCasts()['age']);
        $this->assertArrayNotHasKey('birthdate', $account->getCasts());
    }
}
