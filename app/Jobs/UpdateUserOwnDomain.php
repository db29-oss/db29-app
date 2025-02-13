<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class UpdateUserOwnDomain implements ShouldQueue
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
                'machine',
                'source',
            ])
            ->first();

        $source = $instance->source;

        $machine = $instance->machine;

        $ssh = app('ssh')->toMachine($machine)->compute();

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

        $tr_config = (new $job_class(
            instance: $instance,
            machine: $machine,
            ssh: $ssh,
        ))->changeDomain();

        if (count($this->chained) === 0) {
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

            $now = now();
            $sql_params = [];
            $sql = 'update instances set '.
                'queue_active = ?, '. # false
                'updated_at = ?, '. # $now
                'where id = ?'; # $instance->id

            $sql_params[] = false;
            $sql_params[] = $now;
            $sql_params[] = $instance->id;

            DB::select($sql, $sql_params);
        }
    }
}
