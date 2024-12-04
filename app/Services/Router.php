<?php

namespace App\Services;

use App\Models\TrafficRouter;
use Closure;
use Exception;

class Router
{
    private string|null $lock_owner = null;

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

    public function lock(?Closure $callback = null)
    {
        if (! is_null($this->lock_owner)) {
            return;
        }

        if (is_null($this->tr)) {
            throw new Exception('DB291995: must have traffic router first');
        }

        $lock = cache()->store('lock')->lock('tr_'.$this->tr->id);

        $this->lock_owner = $lock->owner();

        $lock->get($callback);

        $this->unlock();
    }

    public function unlock()
    {
        cache()
            ->store('lock')
            ->restoreLock('tr_'.$this->tr->id, $this->lock_owner)
            ->release();

        $this->lock_owner = null;
    }

    public function fetchRule(): string
    {
        $this->ssh->exec('curl -s localhost:2019/config/apps/http/servers/https/routes/');

        return $this->ssh->getLastLine();
    }

    public function findRuleBySubdomainName(
        string $subdomain_name,
    ): string|false {
        $o_f_rule_str = $this->fetchRule();

        $strpos = strpos($o_f_rule_str, '"'.$subdomain_name.'.');

        if ($strpos === false) {
            return false;
        }

        $s_idx = $strpos + 1; // double quote
        $e_idx = $strpos + 1; // double quote
        $s_count = 0;
        $e_count = 0;

        while ($s_idx > 0) {
            $s_idx -= 1;

            if ($o_f_rule_str[$s_idx] === '{') {
                $s_count += 1;

                if ($s_count === 4) {
                    break;
                }
            }
        }

        while ($e_idx < strlen($o_f_rule_str)) {
            $e_idx += 1;

            if ($o_f_rule_str[$e_idx] === '}') {
                $e_count += 1;

                if ($e_count === 2) {
                    break;
                }
            }
        }

        return substr($o_f_rule_str, $s_idx, $e_idx);
    }

    public function updatePortBySubdomainName(
        string $subdomain_name,
        int $port,
    ) {
        $this->lock();

        $o_rule_str = $this->findRuleBySubdomainName($subdomain_name);

        $preg_replace = preg_replace(
            '/({"dial":"\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:)\d+"}/',
            '${1}'.$port.'"}',
            $o_rule_str
        );

        if (is_null($preg_replace)) {
            return;
        }

        $this->updateRule($o_rule_str, $preg_replace);

        $this->unlock();
    }

    public function batchUpdatePortsBySubdomainNames(
        array $subdomain_names,
        array $ports,
    ) {
        $this->lock();

        $o_f_rule_str = $this->fetchRule();

        $subdomain_names_count = count($subdomain_names);
        $ports_count = count($ports);

        if ($subdomain_names_count !== $ports_count) {
            throw new Exception('DB291996: elements of domain_names and ports must be the same');
        }

        $n_f_rule_str = $o_f_rule_str;

        for ($i = 0; $i < count($subdomain_names); $i++) {
            $strpos = strpos($o_f_rule_str, '"'.$subdomain_names[$i].'.');

            if ($strpos === false) {
                continue;
            }

            $s_idx = $strpos + 1; // double quote
            $e_idx = $strpos + 1; // double quote
            $s_count = 0;
            $e_count = 0;

            while ($s_idx > 0) {
                $s_idx -= 1;

                if ($o_f_rule_str[$s_idx] === '{') {
                    $s_count += 1;

                    if ($s_count === 4) {
                        break;
                    }
                }
            }

            while ($e_idx < strlen($o_f_rule_str)) {
                $e_idx += 1;

                if ($o_f_rule_str[$e_idx] === '}') {
                    $e_count += 1;

                    if ($e_count === 2) {
                        break;
                    }
                }
            }

            $substr = substr($n_f_rule_str, $s_idx, $e_idx);

            $preg_replace = preg_replace(
                '/({"dial":"\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:)\d+"}/',
                '${1}'.$ports[$i].'"}',
                $substr
            );

            if (is_null($preg_replace)) {
                continue;
            }

            $n_f_rule_str = str_replace(
                substr($n_f_rule_str, $s_idx, $e_idx),
                $preg_replace,
                $n_f_rule_str
            );
        }

        // TODO command max length is 2**16

        $command =
            'curl -s -X PATCH -H '.
            $this->ssh->lbsl.'\'Content-Type: application/json'.$this->ssh->lbsl.'\' -d '.
            $this->ssh->lbsl."'".
            bce($n_f_rule_str, $this->ssh->lbsl, $this->ssh->hbsl).
            $this->ssh->lbsl."'"." ".
            "localhost:2019/config/apps/http/servers/https/routes/";

        try {
            $this->ssh->exec($command);
        } finally {
            $this->unlock();
        }
    }

    public function addRule(array $rule)
    {
        ksort($rule);

        $o_f_rule_str = $this->fetchRule();

        $json_rule = json_encode($rule);

        if (str_contains($o_f_rule_str, $json_rule)) {
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

    public function updateRule(array|string $old_rule, array|string $rule)
    {
        $this->lock();

        $o_f_rule_str = $this->fetchRule();

        if (is_array($old_rule)) {
            ksort($old_rule);
            $old_rule = json_encode($old_rule);
        }

        if (is_array($rule)) {
            ksort($rule);
            $rule = json_encode($rule);
        }

        $n_f_rule_str = str_replace($old_rule, $rule, $o_f_rule_str);

        $command =
            'curl -s -X PATCH -H '.
            $this->ssh->lbsl.'\'Content-Type: application/json'.$this->ssh->lbsl.'\' -d '.
            $this->ssh->lbsl."'".
            bce($n_f_rule_str, $this->ssh->lbsl, $this->ssh->hbsl).
            $this->ssh->lbsl."'"." ".
            "localhost:2019/config/apps/http/servers/https/routes/";

        try {
            $this->ssh->exec($command);
        } finally {
            $this->unlock();
        }
    }

    public function ruleExists(array|string $rule): bool
    {
        if (is_array($rule)) {
            ksort($rule);
            $rule = json_encode($rule);
        }

        $o_f_rule_str = $this->fetchRule();

        return str_contains($o_f_rule_str, $rule);
    }

    public function deleteRule(array|string $rule)
    {
        if (is_array($rule)) {
            ksort($rule);
            $rule = json_encode($rule);
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

    public function deleteRuleBySubdomainName(string $subdomain_name)
    {
        $this->lock();

        $o_f_rule_str = $this->fetchRule();

        $strpos = strpos($o_f_rule_str, '"'.$subdomain_name.'.');

        if ($strpos === false) {
            return false;
        }

        $s_idx = $strpos + 1; // double quote
        $e_idx = $strpos + 1; // double quote
        $s_count = 0;
        $e_count = 0;

        while ($s_idx > 0) {
            $s_idx -= 1;

            if ($o_f_rule_str[$s_idx] === '{') {
                $s_count += 1;

                if ($s_count === 4) {
                    break;
                }
            }
        }

        while ($e_idx < strlen($o_f_rule_str)) {
            $e_idx += 1;

            if ($o_f_rule_str[$e_idx] === '}') {
                $e_count += 1;

                if ($e_count === 2) {
                    break;
                }
            }
        }

        $this->deleteRule(substr($o_f_rule_str, $s_idx, $e_idx));

        $this->unlock();
    }

    public function setup()
    {
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
                                    'root' => $this->tr->machine->storage_path.'www/'
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

        if (is_null($this->tr->extra_routes)) {
            return;
        }

        foreach (json_decode($this->tr->extra_routes, true) as $extra_route) {
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
