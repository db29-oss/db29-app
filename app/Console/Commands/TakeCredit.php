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
            ->where('queue_active', false) # already calc take credit when turn off/delete
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
