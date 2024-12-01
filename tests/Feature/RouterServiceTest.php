<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\TrafficRouter;
use App\Services\SSHEngine;
use Tests\TestCase;

class RouterServiceTest extends TestCase
{
    public function test_generic(): void
    {
        $ssh_port = setup_container('db29_router');

        $ssh_privatekey_path = sys_get_temp_dir().'/db29_router';

        config(['services.ssh.ssh_privatekey_path' => $ssh_privatekey_path]);

        $ssh = app('ssh')
            ->to([
                'ssh_port' => $ssh_port,
            ]);

        $m = Machine::factory()->create();

        $tr = new TrafficRouter;
        $tr->machine_id = $m->id;
        $tr->save();

        $tr->refresh();

        // setup
        app('rt', [$ssh])->setup($tr);

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertTrue(str_contains($ssh->getLastLine(), '80'));
        $this->assertTrue(str_contains($ssh->getLastLine(), '443'));

        // add rule
        $subdomain = str(str()->random(8))->lower();
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
                                'dial' => '127.0.0.1:'.fake()->numberBetween(1025, 61000)
                            ]
                        ]
                    ]
                ]
            ];

        app('rt', [$ssh])->addRule($rule);

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertTrue(str_contains($ssh->getLastLine(), $subdomain));

        $this->assertEquals(1, substr_count($ssh->getLastLine(), $subdomain));

        app('rt', [$ssh])->addRule($rule);

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertEquals(1, substr_count($ssh->getLastLine(), $subdomain));

        // delete rule
        app('rt', [$ssh])->delRule($rule);

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertEquals(0, substr_count($ssh->getLastLine(), $subdomain));

        $old_last_line = $ssh->getLastLine();

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertEquals($ssh->getLastLine(), $old_last_line);

        // clean up
        cleanup_container('db29_router');
    }
}
