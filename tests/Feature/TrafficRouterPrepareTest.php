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


        // after call prepare route
        // that random route above should still exists
        $tr->fresh();

        $domain = config('app.domain');

        $tr->extra_routes = <<<EXTRAROUTES
{$domain} {
    reverse_proxy 127.0.0.1:8000
}
EXTRAROUTES;

        $tr->save();

        Artisan::call('app:traffic-router-prepare --tr_id='.$tr->id.' --force');

        $output = [];

        exec(
            'podman exec db29_traffic_router_prepare '.
            'curl -s localhost:2019/config/',
            $output,
            $exit_code
        );

        // ensure new route exists
        $this->assertTrue(str_contains($output[0], $domain));
        $this->assertTrue(str_contains($output[0], 'acme-challenge'));

        unset($ssh);

        // clean up
        cleanup_container('db29_traffic_router_prepare', $m->id);
    }
}
