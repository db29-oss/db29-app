<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\TrafficRouter;
use Tests\TestCase;

class TrafficRouterTest extends TestCase
{
    public function test_traffic_router_store_ephemeral(): void
    {
        test_util_migrate_fresh();

        // plan:
        // start caddy without any config
        // ensure no random path exist
        // set a random path
        // ensure random path work
        // restart caddy ensure config still available

        $m = Machine::factory()->create();
        $m->refresh();
        $ssh_port = setup_container('db29_traffic_router', $m->id);

        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/randomdirname/';
        $m->save();

        $tr_r = new TrafficRouter;
        $tr_r->machine_id = $m->id;
        $tr_r->save();

        $random_path = str()->random(20);
        $random_port = rand(1025, 30000);

        // no path exist
        $ssh = app('ssh')->toMachine($m)->exec('! nc -z localhost '.$random_port);

        // set random path

        # https://caddyserver.com/docs/quick-starts/api
        $config = [
            'apps' => [
                'http' => [
                    'servers' => [
                        $random_path => [
                            'listen' => [':'.$random_port],
                            'routes' => [
                                [
                                    'handle' => [
                                        [
                                            'handler' => 'static_response',
                                            'body' => $random_path
                                        ]
                                    ]
                                ]
                            ],
                        ]
                    ]
                ]
            ]
        ];

        $json_config = json_encode($config);

        // set up caddy
        $curl_cmd = 'curl localhost:2019/load '.
            '-H '.$ssh->lbsl.'\'"Content-Type: application/json"'.$ssh->lbsl.'\' '.
            '-d '.$ssh->lbsl.'\''.
            bce($json_config, $ssh->lbsl, $ssh->hbsl).
            $ssh->lbsl.'\'';

        $ssh->exec($curl_cmd);


        // check port is open
        $ssh->exec('nc -z localhost '.$random_port);


        // kill caddy
        $ssh->exec('pkill caddy');


        // ssh: caddy run --resume run as front ground so we run as podman exec
        exec('podman exec -d db29_traffic_router caddy run --resume', $output, $exit_code);

        $this->assertEquals(0, $exit_code);

        // random path still work
        $ssh->exec('nc -z localhost '.$random_port);

        unset($ssh);

        // clean up
        cleanup_container('db29_traffic_router', $m->id);
    }
}
