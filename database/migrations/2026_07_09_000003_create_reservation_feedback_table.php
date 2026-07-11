<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_account_id')->constrained('guest_accounts')->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('cleanliness_rating')->nullable();
            $table->unsignedTinyInteger('comfort_rating')->nullable();
            $table->unsignedTinyInteger('service_rating')->nullable();
            $table->unsignedTinyInteger('value_rating')->nullable();
            $table->unsignedTinyInteger('booking_experience_rating')->nullable();
            $table->boolean('would_stay_again')->nullable();
            $table->text('comments')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('status', 30)->default('new');
            $table->string('visibility_status', 30)->default('internal');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('reservation_id');
            $table->index(['guest_account_id', 'submitted_at']);
            $table->index(['status', 'overall_rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_feedback');
    }
};
