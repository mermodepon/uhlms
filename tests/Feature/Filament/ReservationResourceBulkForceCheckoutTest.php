<?php

namespace Tests\Feature\Filament;

use Tests\TestCase;

class ReservationResourceBulkForceCheckoutTest extends TestCase
{
    public function test_bulk_force_checkout_is_super_admin_only_and_reuses_checkout_workflow(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/ReservationResource.php'));

        $this->assertStringContainsString("BulkAction::make('bulk_force_checkout')", $resource);
        $this->assertStringContainsString("->label('Force checkout selected')", $resource);
        $this->assertStringContainsString("->visible(fn () => auth()->user()->isSuperAdmin())", $resource);
        $this->assertStringContainsString("->rule('current_password')", $resource);
        $this->assertStringContainsString("Textarea::make('reason')", $resource);
        $this->assertStringContainsString("if (\$record->status !== 'checked_in')", $resource);
        $this->assertStringContainsString("'Bulk force checkout: '", $resource);
        $this->assertStringContainsString('ReservationWorkflowService::class)->checkOut($record, now(), $remarks)', $resource);
    }
}
