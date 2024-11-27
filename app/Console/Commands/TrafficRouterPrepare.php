<?php

namespace App\Console\Commands;

use App\Models\TrafficRouter;
use Illuminate\Console\Command;
use K92\Phputils\BashCharEscape;
use K92\SshExec\SSHEngine;

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

        foreach ($traffic_routers as $traffic_router) {
            cache()->store('lock')->lock('tr_'.$traffic_router->id)->get(function() use ($traffic_router) {
                $ssh = app('ssh');
                $ssh
                    ->to([
                        'ssh_address' => $traffic_router->machine->ip_address,
                        'ssh_port' => $traffic_router->machine->ssh_port,
                    ])
                    ->exec('DEBIAN_FRONTEND=noninteractive apt install caddy curl -y');


                // on testing container env some systemd config cannot be run
                // all config applied below was test using a real machine
                // we should improve testing in the future

                if (app('env') === 'testing') {
                    $ssh->clearOutput();

                    $ssh->exec('ps aux \| grep caddy');

                    if (count($ssh->getOutput()) < 3) { // grep process and bash process
                        logger()->debug('caddy start');
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
                            $line." --resume" // add --resume
                        ];

                    foreach ($override_content_lines as $override_content_line) {
                        $commands[] =
                            'echo '.
                            $ssh->lbsl."'".
                            BashCharEscape::escape($override_content_line, $ssh->lbsl, $ssh->hbsl).
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
                            'systemctl start caddy',
                        ]
                    ));
                }

                // extra_routes
                $this->prepareExtraRoute(
                    $traffic_router,
                    $ssh,
                    json_decode($traffic_router->extra_routes, true)
                );

                TrafficRouter::whereId($traffic_router->id)->update(['prepared' => true]);
            });
        }
    }

    protected function prepareExtraRoute(TrafficRouter $traffic_router, SSHEngine $ssh, array|null $extra_routes)
    {
        // setup apps
        $ssh->clearOutput();
        $ssh->exec('curl -s localhost:2019/config/');

        if ($ssh->getOutput()[0] === 'null') {
            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                BashCharEscape::escape('{"apps": {}}', $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/'
            );
        }


        // setup protocol
        $ssh->clearOutput();
        $ssh->exec('curl -s localhost:2019/config/apps');

        $config = json_decode($ssh->getOutput()[0], true);

        if (! array_key_exists('http', $config)) {
            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                BashCharEscape::escape('{"http": {}}', $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/'
            );
        }

        // setup servers
        $ssh->clearOutput();
        $ssh->exec('curl -s localhost:2019/config/apps/http');

        $config = json_decode($ssh->getOutput()[0], true);

        if (! array_key_exists('servers', $config)) {
            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                BashCharEscape::escape('{"servers": {}}', $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/http/'
            );
        }

        // http route (redirect all to https)
        $ssh->clearOutput();
        $ssh->exec('curl -s localhost:2019/config/apps/http/servers');

        $config = json_decode($ssh->getOutput()[0], true);

        if (! array_key_exists('http', $config)) {
            $http_route =
                [
                    "listen" => [
                        ":80",
                    ],
                    "routes" => [
                        [
                            "match" => [
                                [
                                    "path" => ["/.well-known/acme-challenge/*"]
                                ]
                            ],
                            "handle" => [
                                [
                                    'handler' => 'file_server',
                                    'root' => $traffic_router->machine->storage_path.'www/'
                                ]
                            ]
                        ],
                        [
                            "handle" => [
                                [
                                    "handler" => "static_response",
                                    "status_code" => 301,
                                    "headers" => [
                                        "Location" => ["https://{http.request.host}{http.request.uri}"]
                                    ]
                                ]
                            ]
                        ],
                    ],
                ];

            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                BashCharEscape::escape(json_encode($http_route), $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/http/servers/http'
            );
        }

        // https route
        $ssh->clearOutput();
        $ssh->exec('curl -s localhost:2019/config/apps/http/servers');

        $config = json_decode($ssh->getOutput()[0], true);

        if (! array_key_exists('https', $config)) {
            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                BashCharEscape::escape('{"listen": [":443"], "routes": []}', $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/http/servers/https'
            );
        }

        if ($extra_routes === null) {
            return;
        }

        // extra_routes
        $ssh->clearOutput();
        $ssh->exec('curl -s localhost:2019/config/apps/http/servers/https/routes/');

        $existing_routes_str = $ssh->getOutput()[0];

        foreach ($extra_routes as $extra_route) {
            $extra_route_json = json_encode($extra_route);

            if (str_contains($existing_routes_str, $extra_route_json)) {
                continue;
            }

            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                BashCharEscape::escape($extra_route_json, $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/http/servers/https/routes/'
            );
        }
    }
}
