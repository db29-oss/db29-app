<?php

namespace App\Services;

use App\Models\TrafficRouter;
use Closure;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;

class Router
{
    private readonly bool $ec_ssh;

    public function __construct(
        private SSHEngine|null $ssh = null
    ) {
        $this->ssh = $ssh;

        $existing_connection_ssh = true;

        if (is_null($this->ssh)) {
            $this->ssh = app('ssh');
            $existing_connection_ssh = false;
        }

        $this->ec_ssh = $existing_connection_ssh;
    }

    public function lock(TrafficRouter $tr, Closure $callback = null)
    {
        return cache()->store('lock')->lock('tr_'.$tr->id)->get($callback);
    }

    public function fetchRule(array|null $endpoint = null): string
    {
        if (! $this->ec_ssh) {
            $this->ssh->to([
                'ssh_address' => $endpoint['ip_address'],
                'ssh_port' => $endpoint['ssh_port']
            ]);

            $this->ec_ssh = true;
        }

        $this->ssh->exec('curl -s localhost:2019/config/apps/http/servers/https/routes/');

        return $this->ssh->getLastLine();
    }

    public function addRule(array $rule, array|null $endpoint = null)
    {
        $o_rule_str = $this->fetchRule($endpoint);

        if (str_contains($o_rule_str, $rule['match'][0]['host'][0])) {
            return;
        }

        $command =
            'curl -s -X POST -H '.
            $this->ssh->lbsl.'\'Content-Type: application/json'.$this->ssh->lbsl.'\' -d '.
            $this->ssh->lbsl."'".
            bce(json_encode($rule), $this->ssh->lbsl, $this->ssh->hbsl).
            $this->ssh->lbsl."'"." ".
            "localhost:2019/config/apps/http/servers/https/routes/";

        $this->ssh->exec($command);
    }

    public function delRule(array $rule, array|null $endpoint = null)
    {
        ksort($rule);

        $o_rule_str = $this->fetchRule($endpoint);

        $json_rule = json_encode($rule);

        $strpos = strpos($o_rule_str, $json_rule);

        if ($strpos === false) {
            return;
        }

        if ($o_rule_str[$strpos - 1] === ',') {
            $n_rule_str =
                substr($o_rule_str, 0, $strpos - 1).
                substr($o_rule_str, $strpos);
        } elseif ($o_rule_str[$strpos + strlen($json_rule)] === ',') {
            $n_rule_str =
                substr($o_rule_str, 0, $strpos).
                substr($o_rule_str, $strpos + strlen($json_rule) + 1);
        } else {
            $n_rule_str =
                substr($o_rule_str, $strpos).
                substr($o_rule_str, $strpos + strlen($json_rule));
        }

        $command =
            'curl -s -X DELETE -H '.
            $this->ssh->lbsl.'\'Content-Type: application/json'.$this->ssh->lbsl.'\' -d '.
            $this->ssh->lbsl."'".
            bce(json_encode($rule), $this->ssh->lbsl, $this->ssh->hbsl).
            $this->ssh->lbsl."'"." ".
            "localhost:2019/config/apps/http/servers/https/routes/";

        $this->ssh->exec($command);
    }

    public function setup(TrafficRouter $traffic_router, array|null $endpoint = null)
    {
        if (! $this->ec_ssh) {
            $this->ssh->to([
                'ssh_address' => $endpoint['ip_address'],
                'ssh_port' => $endpoint['ssh_port']
            ]);

            $this->ec_ssh = true;
        }

        $ssh = $this->ssh;

        // setup apps
        $this->ssh->exec('curl -s localhost:2019/config/');

        if ($ssh->getLastLine() === 'null') {
            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'Content-Type: application/json'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl."'".
                bce('{"apps": {}}', $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl."'".' '.
                'localhost:2019/config/'
            );
        }

        // setup protocol
        $ssh->exec('curl -s localhost:2019/config/apps');

        $config = json_decode($ssh->getLastLine(), true);

        if (! array_key_exists('http', $config)) {
            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'Content-Type: application/json'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                bce('{"http": {}}', $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/'
            );
        }

        // setup servers
        $ssh->exec('curl -s localhost:2019/config/apps/http');

        $config = json_decode($ssh->getLastLine(), true);

        if (! array_key_exists('servers', $config)) {
            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'Content-Type: application/json'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                bce('{"servers": {}}', $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
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
                'curl -s -X POST -H '.$ssh->lbsl.'\'Content-Type: application/json'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                bce(json_encode($http_route), $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
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
                'curl -s -X POST -H '.$ssh->lbsl.'\'Content-Type: application/json'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                bce(json_encode($https_route), $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/http/servers/https'
            );
        }

        // extra_routes
        $ssh->exec('curl -s localhost:2019/config/apps/http/servers/https/routes/');

        $existing_routes_str = $ssh->getLastLine();

        foreach (json_decode($traffic_router->extra_routes, true) as $extra_route) {
            $extra_route_json = json_encode($extra_route);

            if (str_contains($existing_routes_str, $extra_route_json)) {
                continue;
            }

            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'Content-Type: application/json'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                bce($extra_route_json, $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/http/servers/https/routes/'
            );
        }
    }
}
