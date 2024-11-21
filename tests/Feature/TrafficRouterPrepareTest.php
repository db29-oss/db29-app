<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\TrafficRouter;
use Artisan;
use Exception;
use K92\SshExec\SSHEngine;
use Tests\TestCase;

class TrafficRouterPrepareTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $ssh_port = setup_container('db29_traffic_router_prepare');

        $ssh_privatekey_path = sys_get_temp_dir().'/db29_traffic_router_prepare';

        $m = new Machine;
        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/randomdirname/';
        $m->save();

        $tr_r = new TrafficRouter;
        $tr_r->machine_id = $m->id;
        $tr_r->save();

        config(['services.ssh.ssh_privatekey_path' => $ssh_privatekey_path]);

        $ssh = new SSHEngine;

        $ssh
            ->from([
                'ssh_privatekey_path' => $ssh_privatekey_path
            ])
            ->to([
                'ssh_port' => $ssh_port,
            ])
            ->exec('test ! -f /usr/bin/caddy'); // no exception

        Artisan::call('app:traffic-router-prepare');

        $ssh = new SSHEngine;
        $ssh
            ->from([
                'ssh_privatekey_path' => $ssh_privatekey_path
            ])
            ->to([
                'ssh_port' => $ssh_port,
            ])
            ->exec('caddy'); // no exception

        $this->assertEquals(true, TrafficRouter::first()->prepared);

        // clean up
        cleanup_container('db29_traffic_router_prepare');
    }
}
