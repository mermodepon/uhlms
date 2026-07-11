<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_alternative_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('reservation_room_request_id')->nullable();
            $table->unsignedBigInteger('offered_room_type_id');
            $table->json('room_ids');
            $table->decimal('original_total', 10, 2);
            $table->decimal('quoted_total', 10, 2);
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->unsignedBigInteger('proposed_by')->nullable();
            $table->timestamps();
            $table->foreign('reservation_id', 'alt_offer_reservation_fk')->references('id')->on('reservations')->cascadeOnDelete();
            $table->foreign('reservation_room_request_id', 'alt_offer_request_fk')->references('id')->on('reservation_room_requests')->nullOnDelete();
            $table->foreign('offered_room_type_id', 'alt_offer_room_type_fk')->references('id')->on('room_types')->restrictOnDelete();
            $table->foreign('proposed_by', 'alt_offer_proposer_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['reservation_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `reservations` MODIFY COLUMN `status` ENUM('pending','awaiting_alternative_confirmation','approved','confirmed','pending_payment','declined','cancelled','checked_in','checked_out') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("UPDATE reservations SET status = 'pending' WHERE status = 'awaiting_alternative_confirmation'");
            DB::statement("ALTER TABLE `reservations` MODIFY COLUMN `status` ENUM('pending','approved','confirmed','pending_payment','declined','cancelled','checked_in','checked_out') NOT NULL DEFAULT 'pending'");
        }

        Schema::dropIfExists('reservation_alternative_offers');
    }
};
