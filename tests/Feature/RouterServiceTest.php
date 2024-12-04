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
        $rt = app('rt', [$tr, $ssh]);
        $rt->setup();

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

        $rt->addRule($rule);

        $this->assertTrue($rt->ruleExists($rule));

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertTrue(str_contains($ssh->getLastLine(), $subdomain));

        $this->assertEquals(1, substr_count($ssh->getLastLine(), $subdomain));

        $rt->addRule($rule);

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertEquals(1, substr_count($ssh->getLastLine(), $subdomain));

        // find rule by domain name
        $rule_str = $rt->findRuleBySubdomainName($subdomain.'.'.config('app.domain'));

        // update rule
        $new_rule = $rule;
        $new_port = fake()->numberBetween(1025, 61000);
        $new_socket = fake()->ipv4.':'.$new_port;
        $new_domain = fake()->domainName;
        $new_subdomain = explode('.', $new_domain)[0];
        $new_rule['handle'][0]['upstreams'][0]['dial'] = $new_socket;
        $new_rule['match'][0]['host'][0] = $new_domain;

        if (fake()->boolean) {
            ksort($new_rule);
            $new_rule = json_encode($new_rule);
        }

        if (fake()->boolean) {
            ksort($rule);
            $rule = json_encode($rule);
        }

        $rt->updateRule($rule, $new_rule);

        $ssh->exec('curl -s localhost:2019/config/');
        $this->assertFalse($rt->ruleExists($rule));
        $this->assertTrue($rt->ruleExists($new_rule));

        $this->assertEquals(0, substr_count($ssh->getLastLine(), $subdomain));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), $new_domain));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), $new_socket));

        // update port by domain name
        $new_port_2 = fake()->numberBetween(1025, 61000);

        $rt->updatePortBySubdomainName($new_subdomain, $new_port_2);
        $ssh->exec('curl -s localhost:2019/config/');
        $this->assertFalse($rt->ruleExists($new_rule));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), $new_domain));
        $this->assertEquals(0, substr_count($ssh->getLastLine(), $new_socket));

        // batch update port by domain name
        $subdomain_2 = str(str()->random(8))->lower();
        $rule_2 =
            [
                'match' => [
                    [
                        'host' => [$subdomain_2.'.'.config('app.domain')]
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

        $rt->addRule($rule_2);
        $this->assertTrue($rt->ruleExists($rule_2));

        $ssh->exec('curl -s localhost:2019/config/');
        $this->assertEquals(0, substr_count($ssh->getLastLine(), '1001'));
        $this->assertEquals(0, substr_count($ssh->getLastLine(), '1002'));

        $rt->batchUpdatePortsBySubdomainNames(
            [
                $new_subdomain,
                $subdomain_2
            ],
            [
                1001,
                1002
            ]
        );

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertEquals(1, substr_count($ssh->getLastLine(), '1001'));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), '1002'));

        // delete rule
        $rt->deleteRule($new_rule); // old rule no longer exists
        $this->assertFalse($rt->ruleExists($rule));
        $this->assertFalse($rt->ruleExists($new_rule));

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertEquals(0, substr_count($ssh->getLastLine(), $subdomain));
        $this->assertEquals(0, substr_count($ssh->getLastLine(), $new_domain));
        $this->assertEquals(0, substr_count($ssh->getLastLine(), $new_socket));

        $old_last_line = $ssh->getLastLine();

        $ssh->exec('curl -s localhost:2019/config/');

        $this->assertEquals($ssh->getLastLine(), $old_last_line);

        // clean up
        cleanup_container('db29_router');
    }
}
