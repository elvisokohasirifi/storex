<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakePlatformAdmin extends Command
{
    protected $signature = 'store:make-admin {email : Email of an existing account}';

    protected $description = 'Grant platform moderation access to an existing registered account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Register this account first.');

            return self::FAILURE;
        }
        $user->forceFill(['is_platform_admin' => true])->save();
        $this->info('Platform admin access granted. This account cannot edit shop or product details.');

        return self::SUCCESS;
    }
}
