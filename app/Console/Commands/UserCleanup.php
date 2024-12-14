<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserCleanup extends Command
{
    protected $signature = 'app:user-cleanup';

    protected $description = 'Cleanup unused account';

    public function handle()
    {
        User::whereInstanceCount(0)->where('updated_at', '<', now()->subDays(30))->delete();
    }
}
