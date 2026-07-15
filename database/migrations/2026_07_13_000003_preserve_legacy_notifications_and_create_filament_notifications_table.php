<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_TABLE = 'legacy_notifications';

    public function up(): void
    {
        /*
         * Older UHLMS installations used a custom notifications table. Its
         * structure is incompatible with Laravel's database notification
         * channel, which Filament reads from. Preserve those records before
         * creating the framework table expected by the current application.
         */
        if (Schema::hasTable('notifications') && ! Schema::hasColumn('notifications', 'data')) {
            if (Schema::hasTable(self::LEGACY_TABLE)) {
                throw new \RuntimeException('Cannot preserve the legacy notifications table because the archive table already exists.');
            }

            Schema::rename('notifications', self::LEGACY_TABLE);
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Do not drop notifications created after this corrective migration.
    }
};
