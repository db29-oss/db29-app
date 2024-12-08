<?php

namespace App\Console\Commands;

use App\Models\TrafficRouter;
use Artisan;
use Illuminate\Console\Command;

class TrafficRouterRebuild extends Command
{
    protected $signature = 'app:traffic-router-rebuild {--tr_id=}';

    protected $description = 'Wipe out config and rebuild all rules on traffic router';

    public function handle()
    {
        $trs = TrafficRouter::query();

        if ($this->option('tr_id')) {
            $trs->whereId($this->option('tr_id'));
        }

        $trs = $trs->with('machine.instances')->get();

        foreach ($trs as $tr) {
            $ssh = app('ssh')->toMachine($tr->machine)->compute();
            $rt = app('rt', [$tr, $ssh]);

            $rt->wipecf();

            $rt->setup();

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
