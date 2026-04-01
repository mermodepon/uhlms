<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_in_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();
            $table->string('nationality')->nullable();
            $table->string('purpose_of_stay')->nullable();
            $table->dateTime('detailed_checkin_datetime')->nullable();
            $table->dateTime('detailed_checkout_datetime')->nullable();
            $table->string('payment_mode')->nullable();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->string('payment_or_number')->nullable();
            $table->date('or_date')->nullable();
            $table->json('additional_requests')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_in_snapshots');
    }
};
