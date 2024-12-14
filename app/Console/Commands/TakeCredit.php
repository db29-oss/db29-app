<?php

namespace App\Console\Commands;

use App\Models\Instance;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TakeCredit extends Command
{
    protected $signature = 'app:take-credit';

    protected $description = 'Take credit from user (auto pay)';

    public function handle()
    {
        $now = now();

        $instances = Instance::query()
            ->where('queue_active', false) # because we also take credit when turn off
            ->where('status', 'rt_up') # if user turn off instance - it already collect credit
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
            if ($instance->user->credit < 0) {
                continue; // let app:turn-off-free-instance calculate credit
            }

            $pay_since = $instance->paid_at;

            if ($instance->paid_at < $instance->turned_on_at) {
                $pay_since = $instance->turned_on_at;
            }

            $pay_since = Carbon::parse($pay_since);

            $pay_amount = (int) ceil($pay_since->diffInHours($now) * $instance->plan->price);

            $sql = 'begin;'.
                'update users set '.
                'credit = credit - '.$pay_amount.', '.
                'updated_at = \''.$now->toDateTimeString().'\' '.
                'where id = \''.$instance->user->id.'\'; '.
                'update instances set '.
                'paid_at = \''.$now->toDateTimeString().'\', '. # $now->toDateTimeString()
                'updated_at = \''.$now->toDateTimeString().'\' '. # $now->toDateTimeString()
                'where id = \''.$instance->id.'\';'. # $instance->id
                'commit;';

            app('db')->unprepared($sql);
        }
    }
}
