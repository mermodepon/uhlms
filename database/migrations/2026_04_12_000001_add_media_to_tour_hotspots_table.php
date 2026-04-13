<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_hotspots', function (Blueprint $table) {
            $table->string('media_type', 10)->nullable()->after('description')
                ->comment('null = none, image = image URL, video = YouTube URL');
            $table->text('media_url')->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('tour_hotspots', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_url']);
        });
    }
};
