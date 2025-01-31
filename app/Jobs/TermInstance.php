<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TermInstance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $instance_id
    ) {}

    public function handle(): void
    {
        $instance = Instance::query()
            ->whereId($this->instance_id)
            ->with('user')
            ->with('source')
            ->with('machine.trafficRouter')
            ->first();

        $machine = $instance->machine;
        $traffic_router = $instance->machine->trafficRouter;

        $ssh = app('ssh')->toMachine($machine)->compute();

        // rt_dw
        $ssh->exec('rm -f /etc/caddy/sites/'.$instance->subdomain.'.caddyfile');
        app('rt', [$machine->trafficRouter, $ssh])->reload();

        // dns_dw
        if (app('env') === 'production') {
            app('cf')->deleteDnsRecord($instance->dns_id);
        }

        // ct_dw
        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

        (new $job_class(
            instance: $instance,
            machine: $machine,
            ssh: $ssh,
        ))->tearDown();

        $paid_at = Carbon::parse($instance->paid_at);

        $now = now();

        $pay_amount = (int) ceil($paid_at->diffInDays($now) * $instance->plan->price);

        $constraint = json_decode($instance->plan->constraint, true);

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
                'instance_count = instance_count - 1, '.
                'updated_at = ? '. # $now
                'where id = ? '. # $instance->user->id
                'returning id'.
            '), '.
            'update_machine as ('.
                'update machines set '.
                'remain_disk = remain_disk + ?, '. # $constraint['max_disk']
                'updated_at = ? '. # $now
                'where id = ? '. # $machine->id
                'returning id'.
            ') '.
            'delete from instances '.
            'where id = ?'; # $instance->id

        $sql_params[] = $pay_amount;
        $sql_params[] = $pay_amount;
        $sql_params[] = $pay_amount;
        $sql_params[] = $pay_amount;
        $sql_params[] = $now;
        $sql_params[] = $instance->user->id;

        $sql_params[] = $constraint['max_disk'];
        $sql_params[] = $now;
        $sql_params[] = $machine->id;

        $sql_params[] = $instance->id;

        DB::select($sql, $sql_params);
    }
}
