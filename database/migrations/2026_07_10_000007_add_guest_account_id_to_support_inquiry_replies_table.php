<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_inquiry_replies', function (Blueprint $table) {
            $table->foreignId('guest_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('guest_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_inquiry_replies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_account_id');
        });
    }
};
