<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Models\TrafficRouter;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class TrafficRouterMaintain extends Command
{
    protected $signature = 'app:traffic-router-maintain';

    protected $description = 'Run as cron to batch check and fix traffic router rules';

    public function handle()
    {
        $machines = Machine::whereEnabled(true)->with('trafficRouter')->get(['id', 'ip_address']);

        $responses = Http::pool(function (Pool $pool) use ($machines) {
            foreach ($machines as $machine) {
                $pool->get('http://'.$machine->ip_address.':80/ping');
            }
        });

        foreach ($responses as $rs_idx => $response) {
            if ($response->ok() && $response->body() === $machines[$rs_idx]->id) {
                continue;
            }

            $ssh = app('ssh')->toMachine($machines[$rs_idx])->compute();

            // check if ssh still possible
            try {
                $ssh->exec('echo testing_connection');
            } catch (Exception) {
                $this->call('app:machine-ipaddress-update --machine_id='.$machines[$rs_idx]->id);
                continue;
            }

            $this->call('app:traffic-router-rebuild --tr_id='.$machines[$rs_idx]->trafficRouter->id);
        }
    }
}
