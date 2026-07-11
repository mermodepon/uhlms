<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_initial', 10)->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 30)->nullable();
            $table->string('gender', 20)->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->date('birthdate')->nullable();
            $table->text('address')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['email_verified_at', 'disabled_at']);
        });

        Schema::create('guest_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('guest_account_id')
                ->nullable()
                ->after('reference_number')
                ->constrained('guest_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['guest_account_id']);
            $table->dropColumn('guest_account_id');
        });

        Schema::dropIfExists('guest_password_reset_tokens');
        Schema::dropIfExists('guest_accounts');
    }
};
