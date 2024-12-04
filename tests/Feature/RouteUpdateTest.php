<?php

namespace Tests\Feature;

use Artisan;
use App\Models\Instance;
use App\Models\Machine;
use App\Models\Source;
use App\Models\TrafficRouter;
use App\Models\User;
use Tests\TestCase;

class RouteUpdateTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $ssh_port = setup_container('db29_route_update');

        $ssh_privatekey_path = sys_get_temp_dir().'/db29_route_update';

        config(['services.ssh.ssh_privatekey_path' => $ssh_privatekey_path]);

        $u = User::factory()->create();

        $m = new Machine;
        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/randomdirname/';
        $m->enabled = true;
        $m->save();

        $tr = new TrafficRouter;
        $tr->machine_id = $m->id;
        $tr->save();

        $s = Source::factory()->create();

        $subdomain = str(str()->random(8))->lower();

        $inst = new Instance;
        $inst->user_id = $u->id;
        $inst->machine_id = $m->id;
        $inst->source_id = $s->id;
        $inst->subdomain = $subdomain;
        $inst->save();

        $inst->refresh();

        $ssh = app('ssh')->to([
            'ssh_address' => $m->ip_address,
            'ssh_port' => $m->ssh_port
        ]);

        $ssh->exec('podman run -p 80 -d --name '.$inst->id.' --rm alpine tail -F /dev/null');

        $ssh->exec('podman port '.$inst->id);

        $output = $ssh->getLastLine();

        $host_port = parse_url($output)['port'];

        while (true) {
            $random_port = fake()->numberBetween(1025, 61000);

            if ($random_port !== $host_port) {
                break;
            }
        }

        $rule =
            [
                'match' => [
                    [
                        'host' => [$subdomain.'.'.config('app.domain')]
                    ]
                ],
                'handle' => [
                    [
                        'handler' => 'reverse_proxy',
                        'upstreams' => [
                            [
                                'dial' => '127.0.0.1:'.$random_port
                            ]
                        ]
                    ]
                ]
            ];

        $rt = app('rt', [$tr, $ssh]);

        $rt->setup();

        $rt->addRule($rule);

        $this->assertTrue($rt->ruleExists($rule));

        Artisan::call('app:route-update');

        $this->assertFalse($rt->ruleExists($rule));

        // clean up
        cleanup_container('db29_route_update');
    }
}
