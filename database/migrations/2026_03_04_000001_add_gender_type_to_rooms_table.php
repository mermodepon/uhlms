<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Gender designation for dormitory rooms
            $table->enum('gender_type', ['male', 'female', 'any'])
                ->default('any')
                ->after('capacity')
                ->comment('Restricts which gender may be assigned to this room');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('gender_type');
        });
    }
};
