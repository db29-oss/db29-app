<?php

namespace Tests\Feature;

use App\Models\Machine;

use App\Models\TrafficRouter;
use Artisan;
use Exception;
use K92\Phputils\BashCharEscape;
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

        $tr = new TrafficRouter;
        $tr->machine_id = $m->id;
        $tr->save();

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

        // test traffic router will
        // populate some special route
        // for machine traffic router
        // add some random route to caddy first
        $random_route = 
            [
                'apps' => [
                    'http' => [
                        'servers' => [
                            'random_server' => [
                                'listen' => [':80'],
                                'routes' => [
                                    [
                                        'match' => [
                                            [
                                                'host' => ['random.org']
                                            ]
                                        ],
                                        'handle' => [
                                            [
                                                'handler' => 'reverse_proxy',
                                                'upstreams' => [
                                                    [
                                                        'dial' => '127.0.0.1:8000'
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

        // ensure config do not exists
        $output = [];

        exec(
            'podman exec db29_traffic_router_prepare '.
            'curl -s localhost:2019/config/',
            $output,
            $exit_code
        );

        $this->assertFalse(str_contains($output[0], 'random.org'));
        $this->assertFalse(str_contains($output[0], 'random_server'));

        // apply random config
        $ssh->clearOutput();
        $ssh->exec(
            'curl -s -X POST -H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' -d '.
            $ssh->lbsl.'\''.
            BashCharEscape::escape(json_encode($random_route), $ssh->lbsl, $ssh->hbsl).
            $ssh->lbsl.'\' '.
            'localhost:2019/config/',
        );

        // ensure config do exists
        $output = [];

        exec(
            'podman exec db29_traffic_router_prepare '.
            'curl -s localhost:2019/config/',
            $output,
            $exit_code
        );

        $this->assertTrue(str_contains($output[0], 'random.org'));
        $this->assertTrue(str_contains($output[0], 'random_server'));
        
        // after call prepare route
        // that random route above should still exists
        $tr->fresh();

        $tr->extra_routes =
            [
                [
                    'match' => [
                        [
                            'host' => ['db29.ovh']
                        ]
                    ],
                    'handle' => [
                        [
                            'handler' => 'reverse_proxy',
                            'upstreams' => [
                                [
                                    'dial' => '127.0.0.1:8000'
                                ]
                            ]
                        ]
                    ]
                ]
            ];

        $tr->save();

        Artisan::call('app:traffic-router-prepare --tr_id='.$tr->id.' --force');

        $output = [];

        exec(
            'podman exec db29_traffic_router_prepare '.
            'curl -s localhost:2019/config/',
            $output,
            $exit_code
        );

        // ensure random route still exists
        $this->assertTrue(str_contains($output[0], 'random.org'));
        $this->assertTrue(str_contains($output[0], 'random_server'));

        // ensure new route exists
        $this->assertTrue(str_contains($output[0], 'db29.ovh'));
        $this->assertTrue(str_contains($output[0], 'extra_routes'));

        // clean up
        cleanup_container('db29_traffic_router_prepare');
    }
}
