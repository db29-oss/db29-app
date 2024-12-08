<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Models\TrafficRouter;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class TrafficRouterMaintain extends Command
{
    protected $signature = 'app:traffic-router-maintain';

    protected $description = 'Run as cron to batch check and fix traffic router rules';

    public function handle()
    {
        $machines = Machine::whereEnabled(true)->get(['id', 'ip_address']);

        $responses = Http::pool(function (Pool $pool) use ($machines) {
            foreach ($machines as $machine) {
                $pool->get('http://'.$machine->ip_address.':80/ping');
            }
        });

        // TODO
    }
}
