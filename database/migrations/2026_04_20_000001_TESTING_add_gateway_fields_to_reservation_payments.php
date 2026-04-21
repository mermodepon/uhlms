<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add online payment gateway fields to reservation_payments table.
 * 
 * TESTING MIGRATION - Can be safely rolled back.
 * Rollback: php artisan migrate:rollback --step=1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table) {
            // Gateway identification
            $table->string('gateway')->nullable()->after('payment_mode')
                ->comment('Payment gateway used: paymongo, manual, null (legacy)');
            
            // PayMongo payment tracking
            $table->string('gateway_payment_id')->nullable()->unique()->after('gateway')
                ->comment('PayMongo PaymentIntent ID');
            
            $table->string('gateway_source_id')->nullable()->after('gateway_payment_id')
                ->comment('PayMongo source ID (GCash/Card/etc)');
            
            $table->string('gateway_status')->nullable()->after('gateway_source_id')
                ->comment('Gateway payment status: pending, paid, failed, refunded');
            
            $table->json('gateway_metadata')->nullable()->after('gateway_status')
                ->comment('Full webhook payload and timestamps');
            
            // Deposit tracking
            $table->boolean('is_deposit')->default(false)->after('gateway_metadata')
                ->comment('True if this is a partial deposit payment');

            // Index for fast webhook lookups
            $table->index('gateway_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropIndex(['gateway_payment_id']);
            $table->dropColumn([
                'gateway',
                'gateway_payment_id',
                'gateway_source_id',
                'gateway_status',
                'gateway_metadata',
                'is_deposit',
            ]);
        });
    }
};
