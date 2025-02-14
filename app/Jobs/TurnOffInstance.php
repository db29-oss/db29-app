<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TurnOffInstance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $instance_id
    ) {}

    public function handle(): void
    {
        $instance = Instance::query()
            ->whereId($this->instance_id)
            ->with([
                'machine.trafficRouter',
                'plan',
                'source',
                'user'
            ])
            ->first();

        $extra = json_decode($instance->extra, true);

        $source = $instance->source;

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $machine = $instance->machine;
        $traffic_router = $instance->machine->trafficRouter;

        $ssh = app('ssh')->toMachine($machine)->compute();

        $rt = app('rt', [$traffic_router, $ssh]);

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

        // rt_dw
        $main_site = config('app.domain');

        $domain = $instance->subdomain.'.'.config('app.domain');

        $tr_config = <<<CONFIG
{$domain} {
    respond "instance is currently off - turn instance on at $main_site" 200
}
CONFIG;
        $ssh->exec([
            'touch /etc/caddy/sites/'.$instance->subdomain.'.caddyfile',
            'rm /etc/caddy/sites/'.$instance->subdomain.'.caddyfile',
            'touch /etc/caddy/sites/'.$instance->subdomain.'.caddyfile',
        ]);

        $tr_config_lines = explode(PHP_EOL, $tr_config);

        foreach ($tr_config_lines as $line) {
            $ssh->exec(
                'echo '.escapeshellarg($line).' | '.
                'tee -a /etc/caddy/sites/'.$instance->subdomain.'.caddyfile'
            );
        }

        $rt->reload();

        // ct_dw
        (new $job_class(
            instance: $instance,
            machine: $machine,
            ssh: $ssh,
        ))->turnOff();


        // we also need to take credit from user
        // by calculate how much time instance was on/or how long since last pay
        $paid_at = Carbon::parse($instance->paid_at);

        $now = now();

        $pay_amount = (int) ceil($paid_at->diffInDays($now) * $instance->plan->price);

        $constraint = json_decode($instance->plan->constraint, true);

        $sql_params = [];
        $sql = 'with '.
            'update_instance as ('.
                'update instances set ';

        if (count($this->chained) === 0) {
            $sql .=
                'queue_active = ?, '; # false

            $sql_params[] = false;
        }

        $sql .=
                'status = ?, '. # 'ct_dw'
                'paid_at = ?, '. # $now
                'turned_off_at = ?, '. # $now
                'updated_at = ? '. # $now
                'where id = ? '. # $instance->id
                'returning id'.
            ')';

        $sql_params[] = 'ct_dw';
        $sql_params[] = $now;
        $sql_params[] = $now;
        $sql_params[] = $now;
        $sql_params[] = $instance->id;

        if (! array_key_exists('machine_id', $extra['reg_info'])) {
            $sql .= ', '.
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
                '), '.
                'update_machine as ('.
                    'update machines set '.
                    'remain_cpu = remain_cpu + ?, '. # $constraint['max_cpu']
                    'remain_memory = remain_memory + ?, '. # $constraint['max_memory']
                    'updated_at = ? '. # $now
                    'where id = ? '. # $machine->id
                    'returning id'.
                ')';

            $sql_params[] = $pay_amount;
            $sql_params[] = $pay_amount;
            $sql_params[] = $pay_amount;
            $sql_params[] = $pay_amount;
            $sql_params[] = $now;
            $sql_params[] = $instance->user->id;

            $sql_params[] = $constraint['max_cpu'];
            $sql_params[] = $constraint['max_memory'];
            $sql_params[] = $now;
            $sql_params[] = $machine->id;
        }

        $sql .= ' select 1';

        DB::select($sql, $sql_params);
    }
}
