<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_feedback', function (Blueprint $table) {
            $table->boolean('public_display_consent')->default(false)->after('visibility_status');
            $table->index(['visibility_status', 'public_display_consent', 'reviewed_at'], 'feedback_public_testimonials_index');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_feedback', function (Blueprint $table) {
            $table->dropIndex('feedback_public_testimonials_index');
            $table->dropColumn('public_display_consent');
        });
    }
};
