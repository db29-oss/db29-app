<?php

namespace App\Console\Commands;

use App\Models\TrafficRouter;
use App\Services\SSHEngine;
use Illuminate\Console\Command;

class TrafficRouterPrepare extends Command
{
    protected $signature = 'app:traffic-router-prepare {--tr_id=} {--force}';

    protected $description = 'Prepare traffic router';

    public function handle()
    {
        // we are using caddy for traffic router
        // and manually instantiate caddy
        // to use SO_REUSEPORT for zero-downtime deployment

        $traffic_routers = TrafficRouter::query();

        if ($this->option('tr_id')) {
            $traffic_routers->where('id', $this->option('tr_id'));
        }

        if (! $this->option('force')) {
            $traffic_routers->where('prepared', false);
        }

        $traffic_routers = $traffic_routers->with('machine')->get();

        foreach ($traffic_routers as $tr) {
            $ssh = app('ssh')
                ->to([
                    'ssh_address' => $tr->machine->ip_address,
                    'ssh_port' => $tr->machine->ssh_port,
                ]);

            $rt = app('rt', [$tr, $ssh]);

            $rt->lock(function () use ($rt, $ssh, $tr) {
                $ssh->exec('DEBIAN_FRONTEND=noninteractive apt install caddy curl -y');

                // on testing container env some systemd config cannot be run
                // all config applied below was test using a real machine
                // we should improve testing in the future

                if (app('env') === 'testing') {
                    $ssh->clearOutput();

                    $ssh->exec('ps aux \| grep caddy');

                    if (count($ssh->getOutput()) < 3) { // grep process and bash process
                        $ssh->exec('caddy start');
                    }
                }

                if (app('env') === 'production') {
                    // get replace ExecStart and replace it with add --resume
                    $ssh->exec('cat /lib/systemd/system/caddy.service');

                    foreach ($ssh->getOutput() as $line) {
                        if (str_starts_with($line, 'ExecStart=')) {
                            break;
                        }
                    }

                    $commands = [];

                    $override_content_lines =
                        [
                            "[Service]",
                            "ExecStart=", // reset mechanism of systemd
                            "ExecStart=/usr/bin/caddy run --resume"
                        ];

                    foreach ($override_content_lines as $override_content_line) {
                        $commands[] =
                            'echo '.
                            $ssh->lbsl."'".
                            bce($override_content_line, $ssh->lbsl, $ssh->hbsl).
                            $ssh->lbsl."'".' '.
                            $ssh->lbsl.">".$ssh->lbsl."> ".
                            '/etc/systemd/system/caddy.service.d/override.conf';
                    }

                    $ssh->exec(array_merge(
                        [
                            'mkdir -p /etc/systemd/system/caddy.service.d/',
                            'rm -rf /etc/systemd/system/caddy.service.d/override.conf',
                            'touch /etc/systemd/system/caddy.service.d/override.conf',
                        ],
                        $commands,
                        [
                            'systemctl enable caddy',
                            'systemctl daemon-reload',
                            'systemctl stop caddy',
                            'rm -rf /var/lib/caddy/.config/caddy/autosave.json',
                            'systemctl start caddy',
                        ]
                    ));
                }

                $rt->setup();

                TrafficRouter::whereId($tr->id)->update(['prepared' => true]);
            });
        }
    }
}
