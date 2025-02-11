<?php

namespace App\Console\Commands;

use App\Models\Instance;
use App\Models\Machine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TakeCredit extends Command
{
    protected $signature = 'app:take-credit';

    protected $description = 'Take credit from user (auto pay)';

    public function handle()
    {
        $now = now();

        $instances = Instance::query()
            ->where('queue_active', false) # already calc take credit when turn off/delete
            ->whereIn('machine_id', Machine::whereNull('user_id')->pluck('id'))
            ->with([
                'plan',
                'user'
            ])
            ->get([
                'id',
                'paid_at',
                'plan_id',
                'turned_on_at',
                'user_id',
            ]);

        foreach ($instances as $instance) {
            $paid_at = Carbon::parse($instance->paid_at);

            $pay_amount = (int) ceil($paid_at->diffInDays($now) * $instance->plan->price);

            $sql_params = [];
            $sql = 'with '.
                'update_user as ('.
                    'update users set '.
                    'bonus_credit = case '.
                        'when bonus_credit >= ? '. # $pay_amount
                        'then bonus_credit - ? '. # $pay_amount
                        'else 0 '.
                    'end, '.
                    'credit = case '.
                        'when bonus_credit >= ? '. # $pay_amount
                        'then credit '.
                        'else credit - (? - bonus_credit) '. # $pay_amount
                    'end, '.
                    'updated_at = ? '. # $now
                    'where id = ? '. # $instance->user->id
                    'returning id'.
                ') '.
                'update instances set '.
                'paid_at = ?, '. # $now
                'updated_at = ? '. # $now
                'where id = ?'; # $instance->id

            $sql_params[] = $pay_amount;
            $sql_params[] = $pay_amount;
            $sql_params[] = $pay_amount;
            $sql_params[] = $pay_amount;
            $sql_params[] = $now;
            $sql_params[] = $instance->user->id;

            $sql_params[] = $now;
            $sql_params[] = $now;
            $sql_params[] = $instance->id;

            DB::select($sql, $sql_params);
        }
    }
}
