<?php

namespace App\Services;

use App\Models\TrafficRouter;
use Closure;
use Exception;
use Illuminate\Contracts\Cache\Lock;

class Router
{
    private Lock $lock;

    public function __construct(
        private TrafficRouter $tr,
        private SSHEngine|null $ssh = null,
    ) {
        $this->tr = $tr;
        $this->ssh = $ssh;

        if (is_null($ssh)) {
            $this->ssh = app('ssh')
                 ->to([
                     'ssh_address' => $tr->machine->ip_address,
                     'ssh_port' => $tr->machine->ssh_port,
                 ]);
        }
    }

    public function reload()
    {
        $this->ssh->exec(
            '/usr/bin/caddy reload --config /etc/caddy/db29.caddyfile --adapter caddyfile --force'
        );
    }

    public function lock(?Closure $callback = null)
    {
        if (! isset($this->lock)) {
            $this->lock = cache()->store('lock')->lock('tr_'.$this->tr->id);
        }

        $this->lock->block(5 /* 5s lock then exception */, $callback);
    }

    public function unlock()
    {
        $this->lock->release();
    }

    public function fetchRules(): string
    {
        $this->ssh->exec('curl -s localhost:2019/config/apps/http/servers/https/routes/');

        return $this->ssh->getLastLine();
    }

    public function ruleExists(array|string $rule): bool
    {
        if (is_array($rule)) {
            ksort($rule);
            $rule = json_encode($rule);
        }

        $o_f_rule_str = $this->fetchRules();

        return str_contains($o_f_rule_str, $rule);
    }

    public function setup()
    {
        $this->lock(function () {
            $ssh = $this->ssh;

            // setup apps
            while (true) {
                try {
                    $this->ssh->exec('curl -s localhost:2019/config/');
                } catch (Exception) {
                    sleep(1);
                    continue;
                }

                break;
            }

            if ($ssh->getLastLine() === 'null' || $ssh->getLastLine() === '{}') {
                $ssh->exec(
                    'curl -s -X POST '.
                    '-H '.escapeshellarg('Content-Type: application/json').' -d '.
                    escapeshellarg('{"apps": {}}').' '.
                    'localhost:2019/config/'
                );
            }

            // setup protocol
            $ssh->exec('curl -s localhost:2019/config/apps');

            $config = json_decode($ssh->getLastLine(), true);

            if (! array_key_exists('http', $config)) {
                $ssh->exec(
                    'curl -s -X POST '.
                    '-H '.escapeshellarg('Content-Type: application/json').' -d '.
                    escapeshellarg('{"http": {}}').' '.
                    'localhost:2019/config/apps/'
                );
            }

            // setup servers
            $ssh->exec('curl -s localhost:2019/config/apps/http');

            $config = json_decode($ssh->getLastLine(), true);

            if (! array_key_exists('servers', $config)) {
                $ssh->exec(
                    'curl -s -X POST '.
                    '-H '.escapeshellarg('Content-Type: application/json').' -d '.
                    escapeshellarg('{"servers": {}}').' '.
                    'localhost:2019/config/apps/http/'
                );
            }

            // http route (redirect all to https)
            $ssh->exec('curl -s localhost:2019/config/apps/http/servers');

            $config = json_decode($ssh->getLastLine(), true);

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
                                        'root' => $this->tr->machine->storage_path.'www/'
                                    ]
                                ]
                            ],
                            [
                                "match" => [
                                    [
                                        "path" => ["/ping"]
                                    ]
                                ],
                                "handle" => [
                                    [
                                        "handler" => "static_response",
                                        "status_code" => 200,
                                        "body" => $this->tr->machine->id
                                    ]
                                ]
                            ],
                            [
                                "handle" => [
                                    [
                                        "handler" => "static_response",
                                        "status_code" => 301,
                                        "headers" => [
                                            "Location" => [
                                                "https://{http.request.host}{http.request.uri}"
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                        ],
                    ];

                $ssh->exec(
                    'curl -s -X POST '.
                    '-H '.escapeshellarg('Content-Type: application/json').' -d '.
                    escapeshellarg(json_encode($http_route)).' '.
                    'localhost:2019/config/apps/http/servers/http'
                );
            }

            // https route
            $ssh->exec('curl -s localhost:2019/config/apps/http/servers');

            $config = json_decode($ssh->getLastLine(), true);

            if (! array_key_exists('https', $config)) {
                $https_route = [
                    "listen" => [
                        ":443"
                    ],
                    "routes" => []
                ];

                $ssh->exec(
                    'curl -s -X POST '.
                    '-H '.escapeshellarg('Content-Type: application/json').' -d '.
                    escapeshellarg(json_encode($https_route)).' '.
                    'localhost:2019/config/apps/http/servers/https'
                );
            }

            // extra_routes
            $ssh->exec('curl -s localhost:2019/config/apps/http/servers/https/routes/');

            $existing_routes_str = $ssh->getLastLine();

            if (is_null($this->tr->extra_routes)) {
                return;
            }

            foreach (json_decode($this->tr->extra_routes, true) as $extra_route) {
                $extra_route_json = json_encode($extra_route);

                if (str_contains($existing_routes_str, $extra_route_json)) {
                    continue;
                }

                $ssh->exec(
                    'curl -s -X POST '.
                    '-H '.escapeshellarg('Content-Type: application/json').' -d '.
                    escapeshellarg($extra_route_json).' '.
                    'localhost:2019/config/apps/http/servers/https/routes/'
                );
            }
        });
    }
}
