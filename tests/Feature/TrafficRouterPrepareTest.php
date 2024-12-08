<?php

namespace Tests\Feature;

use App\Models\Machine;

use App\Models\TrafficRouter;
use App\Services\SSHEngine;
use Artisan;
use Exception;
use Tests\TestCase;

class TrafficRouterPrepareTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $m = Machine::factory()->create();
        $m->refresh();

        $ssh_port = setup_container('db29_traffic_router_prepare', $m->id);

        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/randomdirname/';
        $m->save();

        $tr = new TrafficRouter;
        $tr->machine_id = $m->id;
        $tr->save();

        $ssh = app('ssh')->toMachine($m)->exec('test ! -f /usr/bin/caddy'); // no exception

        Artisan::call('app:traffic-router-prepare');

        while (true) {
            $output = [];

            exec(
                'podman exec db29_traffic_router_prepare '.
                'curl -s localhost:2019/config/',
                $output,
                $exit_code
            );

            // it take some time to populate config
            if (str_contains($output[0], 'acme-challenge')) {
                break;
            }
        }

        $this->assertTrue(str_contains($output[0], $m->id));

        $ssh->exec('caddy'); // no exception

        $this->assertEquals(true, TrafficRouter::first()->prepared);

        // test traffic router will
        // populate some special route
        // for machine traffic router
        // add some random route to caddy first
        $random_routes =
            [
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

        // apply random config
        $ssh->clearOutput();

        foreach ($random_routes as $random_route) {
            $ssh->exec(
                'curl -s -X POST -H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' -d '.
                $ssh->lbsl.'\''.
                bce(json_encode($random_route), $ssh->lbsl, $ssh->hbsl).
                $ssh->lbsl.'\' '.
                'localhost:2019/config/apps/http/servers/https/routes/',
            );
        }

        // ensure config do exists
        while (true) {
            $output = [];

            exec(
                'podman exec db29_traffic_router_prepare '.
                'curl -s localhost:2019/config/',
                $output,
                $exit_code
            );

            // take sometime to populate config
            if (str_contains($output[0], 'random.org')) {
                break;
            }
        }
        
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

        // ensure new route exists
        $this->assertTrue(str_contains($output[0], 'db29.ovh'));
        $this->assertTrue(str_contains($output[0], 'acme-challenge'));

        unset($ssh);

        // clean up
        cleanup_container('db29_traffic_router_prepare', $m->id);
    }
}
