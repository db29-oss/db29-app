<?php

namespace App\Services;

use App\Models\TrafficRouter;
use Closure;
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

        $this->ec_ssh= $existing_connection_ssh;
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
        }

        $this->ssh->exec('curl localhost:2019/config/apps/http/servers/https/routes/');

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
            $this->ssh->lbsl.'\'"Content-Type: application/json"'.$this->ssh->lbsl.'\' -d '.
            $this->ssh->lbsl."'".
            bce(json_encode($rule), $this->ssh->lbsl, $this->ssh->hbsl).
            $this->ssh->lbsl."'"." ".
            "localhost:2019/config/apps/http/servers/https/routes/";

        $this->ssh->exec($command);
    }
}
