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

        $u = User::factory()->create();

        $m = Machine::factory()->create();

        $m->refresh();

        $ssh_port = setup_container('db29_route_update', $m->id);

        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->save();

        $tr = new TrafficRouter;
        $tr->machine_id = $m->id;
        $tr->save();

        $s = Source::factory()->create();

        $subdomain = str(str()->random(8))->lower();

        $inst = Instance::factory()->create([
            'user_id' => $u->id,
            'machine_id' => $m->id,
            'source_id' => $s->id,
            'subdomain' => $subdomain,
        ]);

        $inst->refresh();

        $ssh = app('ssh')->toMachine($m);

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
        unset($rt);
        unset($ssh);
        cleanup_container('db29_route_update', $m->id);
    }
}
