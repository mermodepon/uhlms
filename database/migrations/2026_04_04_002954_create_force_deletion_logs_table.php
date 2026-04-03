<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('force_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number');
            $table->string('guest_name');
            $table->string('status');
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->string('reason');
            $table->unsignedBigInteger('deleted_by');
            $table->string('deleted_by_name');
            $table->json('related_counts')->nullable();
            $table->json('reservation_snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('force_deletion_logs');
    }
};
