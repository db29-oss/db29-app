<?php

namespace App\Console\Commands;

use App\Models\TrafficRouter;
use Illuminate\Console\Command;
use K92\Phputils\BashCharEscape;

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
                ->exec('DEBIAN_FRONTEND=noninteractive apt install caddy -y');


            // on testing container env some systemd config cannot be run
            // all config applied below was test using a real machine
            // we should improve testing in the future

            if (app('env') !== 'testing') {
                // get replace ExecStart and replace it with add --resume
                $ssh->exec('cat /lib/systemd/system/caddy.service');

                foreach ($ssh->getOutput() as $line) {
                    if (str_starts_with($line, 'ExecStart=')) {
                        break;
                    }
                }

                $ssh->exec('mkdir -p /etc/systemd/system/caddy.service.d/')
                    ->exec('rm -rf /etc/systemd/system/caddy.service.d/override.conf')
                    ->exec('touch /etc/systemd/system/caddy.service.d/override.conf');

                $override_content_lines =
                    [
                        "[Service]",
                        "ExecStart=", // reset mechanism of systemd
                        $line." --resume" // add --resume
                    ];

                foreach ($override_content_lines as $override_content_line) {
                    $ssh->exec(
                        'echo '.
                        $ssh->lbsl."'".
                        BashCharEscape::escape($override_content_line, $ssh->lbsl, $ssh->hbsl).
                        $ssh->lbsl."'".' '.
                        $ssh->lbsl.">".$ssh->lbsl."> ".
                        '/etc/systemd/system/caddy.service.d/override.conf'
                    );
                }

                $ssh->exec('systemctl enable caddy')
                    ->exec('systemctl daemon-reload')
                    ->exec('systemctl start caddy');
            }

            TrafficRouter::whereId($traffic_router->id)->update(['prepared' => true]);
        }
    }
}
