<?php

namespace App\Console\Commands;

use App\Notifications\NotificationHelper;
use App\Models\User;
use Illuminate\Console\Command;

class TestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test {--type=info : Notification type (info|success|warning|error)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test notification to all staff members';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $validTypes = ['info', 'success', 'warning', 'error'];
        
        if (!in_array($type, $validTypes)) {
            $this->error("Invalid type. Must be one of: " . implode(', ', $validTypes));
            return 1;
        }

        NotificationHelper::notifyAllStaff(
            'Test Notification',
            'This is a test notification sent at ' . now()->format('g:i A') . '. Check if the toast appears and bell icon updates!',
            $type,
            'system',
            null
        );

        $this->info('✅ Test notification sent successfully!');
        $this->info('📬 Check your admin panel - you should see:');
        $this->info('   1. Bell icon count updated');
        $this->info('   2. Floating toast notification appearing');
        
        return 0;
    }
}

