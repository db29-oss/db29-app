<?php

namespace App\Console\Commands;

use App\Models\TrafficRouter;
use Illuminate\Console\Command;

class TrafficRouterPrepare extends Command
{
    protected $signature = 'app:traffic-router-prepare';

    protected $description = 'Prepare traffic router';

    public function handle()
    {
        // we are using caddy for traffic router
        // and manually instantiate caddy
        // to use SO_REUSEPORT for zero-downtime deployment

        $traffic_routers = TrafficRouter::where('prepared', false)->with('machine')->get();

        foreach ($traffic_routers as $traffic_router) {
            $ssh = app('ssh');
            $ssh
                ->to([
                    'ssh_address' => $traffic_router->machine->ip_address,
                    'ssh_port' => $traffic_router->machine->ssh_port,
                ])
                ->exec('apt install caddy -y');

            if (app('env') !== 'testing') {
                $ssh->exec('systemctl enable --now caddy');
            }

            TrafficRouter::whereId($traffic_router->id)->update(['prepared' => true]);
        }
    }
}
