<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_account_id')->nullable()->constrained('guest_accounts')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('category', 50);
            $table->string('subject');
            $table->text('message');
            $table->string('status', 30)->default('new');
            $table->string('priority', 30)->default('normal');
            $table->string('source', 30)->default('public');
            $table->text('internal_notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index(['guest_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_inquiries');
    }
};
