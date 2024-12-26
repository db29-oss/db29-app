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
}
