<?php

namespace App\Jobs;

use App\Models\Instance;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TurnOnInstance implements ShouldQueue
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
            ])
            ->first();

        $source = $instance->source;

        $plan = $instance->plan;

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $machine = $instance->machine;
        $traffic_router = $instance->machine->trafficRouter;

        $ssh = app('ssh')->toMachine($machine)->compute();

        // ct_up
        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

        $tr_config = (new $job_class(
            instance: $instance,
            machine: $machine,
            plan: $plan,
            ssh: $ssh,
        ))->turnOn();

        // rt_up
        $constraint = json_decode($instance->plan->constraint, true);

        $now = now();

        $sql =
            'begin; '.

            'update instances set '.
            'status = \'rt_up\', '.
            'queue_active = false, '.
            'turned_on_at = \''.$now->toDateTimeString().'\', '.
            'updated_at = \''.$now->toDateTimeString().'\' '.
            'where id = \''.$instance->id.'\'; '.

            'update machines set '.
            'remain_cpu = remain_cpu - '.$constraint['max_cpu'].', '.
            'remain_memory = remain_memory - '.$constraint['max_memory'].', '.
            'updated_at = \''.$now->toDateTimeString().'\' '.
            'where id = \''.$machine->id.'\'; '.

            'commit;';

        app('db')->unprepared($sql);

        $ssh->exec([
            'mkdir -p /etc/caddy/sites/',
            'rm -f /etc/caddy/sites/'.$instance->subdomain.'.caddyfile',
            'touch /etc/caddy/sites/'.$instance->subdomain.'.caddyfile'
        ]);

        $tr_config_lines = explode(PHP_EOL, $tr_config);

        foreach ($tr_config_lines as $line) {
            $ssh->exec(
                'echo '.escapeshellarg($line).' >> /etc/caddy/sites/'.$instance->subdomain.'.caddyfile'
            );
        }

        app('rt', [$machine->trafficRouter, $ssh])->reload();
    }
}
