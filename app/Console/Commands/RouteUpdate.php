<?php

namespace App\Console\Commands;

use App\Models\Machine;
use Illuminate\Console\Command;

class RouteUpdate extends Command
{
    protected $signature = 'app:route-update {--instance_id=} {--machine_id=}';

    protected $description = 'Update route of an instance';

    public function handle()
    {
        $machines = Machine::query();

        if ($this->option('machine_id')) {
            $machines->whereId($this->option('machine_id'));
        }

        if ($this->option('instance_id')) {
            $machines->whereInstanceId($this->option('instance_id'));
        }

        $machines = $machines
            ->with('instances')
            ->with('trafficRouter')
            ->get();

        foreach ($machines as $machine) {
            $ssh = app('ssh')->to([
                'ssh_address' => $machine->ip_address,
                'ssh_port' => $machine->ssh_port,
            ]);

            $ssh->compute();

            $rt = app('rt', [$machine->trafficRouter, $ssh]);

            $ssh->clearOutput();

            $ssh->exec('podman ps --format {{.Names}}________{{.Ports}}');

            $instance_ids = [];

            foreach ($ssh->getOutput() as $ssh_line) {
                $preg_match =
                    preg_match(
                        '/^(\w{8}-\w{4}-\w{4}-\w{4}-\w{12})________/',
                        $ssh_line,
                        $matches
                    );

                if ($preg_match) {
                    $s_preg_match = preg_match('/________(.*)->/', $ssh_line, $s_matches);

                    if (! $s_preg_match) {
                        continue;
                    }

                    $instance_ids[$matches[1]] = [];
                    $instance_ids[$matches[1]]['port'] = parse_url($s_matches[1])['port'];
                }
            }

            foreach ($machine->instances as $instance) {
                if (! array_key_exists($instance->id, $instance_ids)) {
                    continue;
                }

                $instance_ids[$instance->id]['subdomain'] = $instance->subdomain;
            }

            $subdomain_names = [];
            $ports = [];

            foreach ($instance_ids as $arr_val) {
                $subdomain_names[] = $arr_val['subdomain'];
                $ports[] = $arr_val['port'];
            }

            $rt->batchUpdatePortsBySubdomainNames($subdomain_names, $ports);
        }
    }
}
