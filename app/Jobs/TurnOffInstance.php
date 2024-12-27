<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class TurnOffInstance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $instance_id
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

        $source = $instance->source;

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $machine = $instance->machine;
        $traffic_router = $instance->machine->trafficRouter;

        $ssh = app('ssh')->toMachine($machine)->compute();

        $rt = app('rt', [$traffic_router, $ssh]);

        // rt_dw
        if ($instance->subdomain !== null) {
            $main_site = config('app.domain');

            $domain = config('app.domain');

            $subdomain = $instance->subdomain;

            if ($instance->subdomain) {
                $domain = $instance->subdomain.'.'.config('app.domain');
            }

            $tr_config = <<<CONFIG
{$domain} {
    respond "instance is currently off - turn instance on at $main_site" 200
}
CONFIG;
            $ssh->exec([
                'touch /etc/caddy/sites/'.$subdomain.'.caddyfile',
                'rm /etc/caddy/sites/'.$subdomain.'.caddyfile',
                'touch /etc/caddy/sites/'.$subdomain.'.caddyfile',
            ]);

            $tr_config_lines = explode(PHP_EOL, $tr_config);

            foreach ($tr_config_lines as $line) {
                $ssh->exec(
                    'echo '.escapeshellarg($line).' >> '.
                    '/etc/caddy/sites/'.$subdomain.'.caddyfile'
                );
            }

            $rt->reload();
        }

        // ct_dw
        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

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

        $sql = 'begin; '.

            'update users set '.
            'credit = credit - '.$pay_amount.', '.
            'updated_at = \''.$now->toDateTimeString().'\' '.
            'where id = \''.$instance->user->id.'\'; '.

            'update machines set '.
            'remain_cpu = remain_cpu + '.$constraint['max_cpu'].', '.
            'remain_memory = remain_memory + '.$constraint['max_memory'].', '.
            'updated_at = \''.$now->toDateTimeString().'\' '.
            'where id = \''.$machine->id.'\'; '.

            'update instances set '.
            'status = \'ct_dw\', '. # 'ct_dw'
            'queue_active = false, '. # false
            'paid_at = \''.$now->toDateTimeString().'\', '. # $now->toDateTimeString()
            'turned_off_at = \''.$now->toDateTimeString().'\', '. # $now->toDateTimeString()
            'updated_at = \''.$now->toDateTimeString().'\' '. # $now->toDateTimeString()
            'where id = \''.$instance->id.'\'; '. # $instance->id

            'commit;';

        app('db')->unprepared($sql);
    }
}
