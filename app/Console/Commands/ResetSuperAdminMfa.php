<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

class ResetSuperAdminMfa extends Command
{
    protected $signature = 'admin:mfa-recover';

    protected $description = 'Interactively reset MFA for a locked-out super administrator';

    public function handle(DisableTwoFactorAuthentication $disable, SecurityAudit $audit): int
    {
        $email = trim((string) $this->ask('Enter the exact super administrator email'));
        $user = User::query()->where('email', $email)->where('role', 'super_admin')->first();
        if (! $user) {
            $this->error('No matching super administrator was found.');

            return self::FAILURE;
        }

        $phrase = "RESET MFA FOR {$email}";
        if (! hash_equals($phrase, (string) $this->ask("Type exactly: {$phrase}"))) {
            $this->error('Confirmation phrase did not match. No changes were made.');

            return self::FAILURE;
        }

        $audit->record('mfa_console_recovery_requested', ['target_user_id' => $user->id]);
        $disable($user);
        $audit->alert(
            'mfa_console_recovery',
            'Super administrator MFA reset',
            'The local recovery command reset a super administrator MFA configuration.',
            ['target_user_id' => $user->id],
            'danger',
            true,
        );

        $this->warn('MFA was reset. The account must enroll again before sensitive operations are available.');

        return self::SUCCESS;
    }
}
