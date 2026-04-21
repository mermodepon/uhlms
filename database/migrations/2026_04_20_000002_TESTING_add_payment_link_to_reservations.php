<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add payment link token and deposit configuration to reservations table.
 * 
 * TESTING MIGRATION - Can be safely rolled back.
 * Rollback: php artisan migrate:rollback --step=1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Payment link security token
            $table->uuid('payment_link_token')->nullable()->unique()->after('payment_status')
                ->comment('Secure UUID for guest payment URL');
            
            $table->timestamp('payment_link_expires_at')->nullable()->after('payment_link_token')
                ->comment('Token expiry (typically 48hr from created_at)');
            
            // Deposit configuration
            $table->decimal('deposit_percentage', 5, 2)->nullable()->after('payment_link_expires_at')
                ->comment('Override default deposit % for this reservation (0-100)');
            
            // Index for token lookups
            $table->index('payment_link_token');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['payment_link_token']);
            $table->dropColumn([
                'payment_link_token',
                'payment_link_expires_at',
                'deposit_percentage',
            ]);
        });
    }
};
