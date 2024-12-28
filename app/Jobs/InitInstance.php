<?php

namespace App\Jobs;

use App\Models\Instance;
use App\Models\Machine;
use App\Models\Source;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class InitInstance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $instance_id,
        private readonly array $reg_info = []
    ) {}

    public function handle(): void
    {
        $now = now();

        $instance = Instance::query()
            ->whereId($this->instance_id)
            ->with([
                'machine.trafficRouter',
                'plan',
                'source'
            ])
            ->first();

        $source = $instance->source;

        $plan = $instance->plan;

        $constraint = json_decode($plan->constraint, true);

        $version_templates = json_decode($source->version_templates, true);

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($source->name);

        $machine = $instance->machine; // sometime first time run bug and we dont want to reassign machine

        // init
        if (! $machine) {
            $sql_params = [];
            $sql = 'with '.
                'select_machine as ('.
                    'select * from machines '.
                    'where enabled = ? '. # true
                    'and user_id is null '.
                    'and remain_cpu > ? '. # $constraint['max_cpu']
                    'and remain_disk > ? '. # $constraint['max_disk']
                    'and remain_memory > ? '. # $constraint['max_memory']
                    'limit 1'.
                '), '.
                'update_machine as ('.
                    'update machines set '.
                    'remain_cpu = remain_cpu - ?, '. # $constraint['max_cpu']
                    'remain_disk = remain_disk - ?, '. # $constraint['max_disk']
                    'remain_memory = remain_memory - ?, '. # $constraint['max_memory']
                    'updated_at = ? '. # $now
                    'where id = (select id from select_machine) '.
                    'returning id'.
                ') '.
                'update instances set '.
                'status = ?, '. # 'init'
                'machine_id = (select id from select_machine), '.
                'updated_at = ? '. # $now
                'where id = ?'; # $instance->id

            $sql_params[] = true;
            $sql_params[] = $constraint['max_cpu'];
            $sql_params[] = $constraint['max_disk'];
            $sql_params[] = $constraint['max_memory'];

            $sql_params[] = $constraint['max_cpu'];
            $sql_params[] = $constraint['max_disk'];
            $sql_params[] = $constraint['max_memory'];
            $sql_params[] = $now;

            $sql_params[] = 'init';
            $sql_params[] = $now;
            $sql_params[] = $instance->id;

            DB::select($sql, $sql_params);

            $instance->refresh();

            $machine = Machine::query()
                 ->where('id', $instance->machine_id)
                 ->with('trafficRouter')
                 ->inRandomOrder() // TODO, should use resource indicator
                 ->first();
        }

        if ($machine === null) {
            throw new Exception('DB292001: machine is null - possible out of resources');
        }

        if ($machine->trafficRouter === null) {
            throw new Exception('DB292002: trafficRouter is null');
        }

        $traffic_router = $machine->trafficRouter;

        $instance->status = 'init';
        $instance->machine = $machine;

        // dns
        $subdomain = $instance->subdomain; // reuse subdomain from previous failed InitInstance

        if ($subdomain === null) {
            if (app('env') === 'production') {
                $subdomain = str(str()->random(8))->lower()->toString();
            }
        }

        $dns_id = $instance->dns_id;

        if (! $dns_id) {
            $dns_id = str(str()->random(32))->lower()->toString(); // for testing

            if (app('env') === 'production') {
                $dns_id = app('cf')
                    ->addDnsRecord(
                        $subdomain,
                        $machine->ip_address,
                        ['comment' => $this->instance_id]
                    );
            }
        }

        Instance::query()
            ->whereId($instance->id)
            ->update([
                'subdomain' => $subdomain,
                'dns_id' => $dns_id,
                'status' => 'dns',
            ]);

        $instance->subdomain = $subdomain;
        $instance->dns_id = $dns_id;
        $instance->status = 'dns';

        $ssh = app('ssh')->toMachine($machine)->compute();

        // get deploy information
        $latest_version_template = null;
        $docker_compose = null;

        foreach ($version_templates as $vt_idx => $version_template) {
            if ($latest_version_template === null) {
                $latest_version_template = $version_template['tag'];

                if (array_key_exists('docker_compose', $version_templates[$vt_idx])) {
                    $docker_compose = $version_templates[$vt_idx]['docker_compose'];
                }

                continue;
            }

            if ($version_template['tag'] > $latest_version_template) {
                $latest_version_template = $version_template;
                $docker_compose = $version_templates[$vt_idx]['docker_compose'];
            }
        }

        $tr_config = (new $job_class(
            docker_compose: $docker_compose,
            instance: $instance,
            machine: $machine,
            plan: $plan,
            reg_info: $this->reg_info,
            ssh: $ssh,
        ))->setUp();

        Instance::query()
            ->whereId($instance->id)
            ->update([
                'status' => 'rt_up', // router up
                'queue_active' => false,
                'turned_on_at' => now(),
                'version_template' => [
                    'docker_compose' => $docker_compose,
                    'tag' => $latest_version_template,
                ]
            ]);

        $ssh->exec([
            'mkdir -p /etc/caddy/sites/',
            'rm -f /etc/caddy/sites/'.$subdomain.'.caddyfile',
            'touch /etc/caddy/sites/'.$subdomain.'.caddyfile'
        ]);

        $tr_config_lines = explode(PHP_EOL, $tr_config);

        foreach ($tr_config_lines as $line) {
            $ssh->exec('echo '.escapeshellarg($line).' >> /etc/caddy/sites/'.$subdomain.'.caddyfile');
        }

        app('rt', [$machine->trafficRouter, $ssh])->reload();
    }
}
