<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserCleanup extends Command
{
    protected $signature = 'app:user-cleanup';

    protected $description = 'Cleanup unused user after 30 days';

    public function handle()
    {
        $users = User::query()
            ->whereInstanceCount(0)
            ->whereSubdomainCount(0)
            ->whereMachineCount(0)
            ->where('last_logged_in_at', '<', now()->subDays(30))
            ->where('credit', '<=', 0)
            ->get();

        if (count($users) === 0) {
            return;
        }

        $sql_params = [];
        $sql = 'insert into recharge_number_holes (recharge_number) values ';

        foreach ($users as $user) {
            $sql .= '(?), ';
            $sql_params[] = $user->recharge_number;
        }

        $sql = substr($sql, 0, -2);

        User::whereIn('id', $users->pluck('id'))->delete();

        DB::insert($sql, $sql_params);
    }
}
