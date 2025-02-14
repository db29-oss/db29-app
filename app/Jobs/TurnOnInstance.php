<?php

namespace App\Jobs;

use App\Models\Instance;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class TurnOnInstance implements ShouldQueue
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
            ])
            ->first();

        $extra = json_decode($instance->extra, true);

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
        $ssh->exec([
            'mkdir -p /etc/caddy/sites/',
            'rm -f /etc/caddy/sites/'.$instance->subdomain.'.caddyfile',
            'touch /etc/caddy/sites/'.$instance->subdomain.'.caddyfile'
        ]);

        $tr_config_lines = explode(PHP_EOL, $tr_config);

        foreach ($tr_config_lines as $line) {
            $ssh->exec(
                'echo '.escapeshellarg($line).' | tee -a /etc/caddy/sites/'.$instance->subdomain.'.caddyfile'
            );
        }

        app('rt', [$machine->trafficRouter, $ssh])->reload();

        $constraint = json_decode($instance->plan->constraint, true);

        $now = now();
        $sql_params = [];
        $sql = 'with '.
            'update_instance as ('.
                'update instances set ';

        if (count($this->chained) === 0) {
            $sql .=
                'queue_active = ?, '. # false

            $sql_params[] = false;
        }

        $sql .=
                'status = ?, '. # 'rt_up'
                'turned_on_at = ?, '. # $now
                'updated_at = ? '. # $now
                'where id = ? '. # $instance->id
                'returning id'.
            ')';

        $sql_params[] = 'rt_up';
        $sql_params[] = $now;
        $sql_params[] = $now;
        $sql_params[] = $instance->id;

        if (! array_key_exists('machine_id', $extra['reg_info'])) {
            $sql .= ', '.
                'update_machine as ('.
                    'update machines set '.
                    'remain_cpu = remain_cpu - ?, '. # $constraint['max_cpu']
                    'remain_memory = remain_memory - ?, '. # $constraint['max_memory']
                    'updated_at = ? '. # $now
                    'where id = ? '. # $machine->id
                    'returning id'.
                ')';

            $sql_params[] = $constraint['max_cpu'];
            $sql_params[] = $constraint['max_memory'];
            $sql_params[] = $now;
            $sql_params[] = $machine->id;
        }

        $sql .= ' select 1';

        DB::select($sql, $sql_params);
    }
}
