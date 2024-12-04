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
        $new_rule_1 = $rule;
        $new_port_1 = fake()->numberBetween(1025, 61000);
        $new_socket_1 = fake()->ipv4.':'.$new_port_1;
        $new_domain_1 = fake()->domainName;
        $new_subdomain_1 = explode('.', $new_domain_1)[0];
        $new_rule_1['handle'][0]['upstreams'][0]['dial'] = $new_socket_1;
        $new_rule_1['match'][0]['host'][0] = $new_domain_1;

        if (fake()->boolean) {
            ksort($new_rule_1);
            $new_rule_1 = json_encode($new_rule_1);
        }

        if (fake()->boolean) {
            ksort($rule);
            $rule = json_encode($rule);
        }

        $rt->updateRule($rule, $new_rule_1);

        $ssh->exec('curl -s localhost:2019/config/');
        $this->assertFalse($rt->ruleExists($rule));
        $this->assertTrue($rt->ruleExists($new_rule_1));

        $this->assertEquals(0, substr_count($ssh->getLastLine(), $subdomain));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), $new_subdomain_1));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), $new_domain_1));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), $new_socket_1));

        // update port by domain name
        $new_port_2 = fake()->numberBetween(1025, 61000);

        $rt->updatePortBySubdomainName($new_subdomain_1, $new_port_2);
        $ssh->exec('curl -s localhost:2019/config/');
        $this->assertFalse($rt->ruleExists($new_rule_1));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), $new_port_2));
        $this->assertEquals(1, substr_count($ssh->getLastLine(), $new_domain_1));
        $this->assertEquals(0, substr_count($ssh->getLastLine(), $new_socket_1));

        // batch update port by domain name
        $new_subdomain_2 = str(str()->random(8))->lower();
        $new_rule_2 =
            [
                'match' => [
                    [
                        'host' => [$new_subdomain_2.'.'.config('app.domain')]
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

        $rt->addRule($new_rule_2);
        $this->assertTrue($rt->ruleExists($new_rule_2));

        $ssh->exec('curl -s localhost:2019/config/');
        $this->assertEquals(0, substr_count($ssh->getLastLine(), '1001'));
        $this->assertEquals(0, substr_count($ssh->getLastLine(), '1002'));

        $rt->batchUpdatePortsBySubdomainNames(
            [
                $new_subdomain_1,
                $new_subdomain_2
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
        $subdomain_3 = str(str()->random(12))->lower();
        $new_rule_3 =
            [
                'match' => [
                    [
                        'host' => [$subdomain_3.'.'.config('app.domain')]
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

        $subdomain_4 = str(str()->random(12))->lower();
        $new_rule_4 =
            [
                'match' => [
                    [
                        'host' => [$subdomain_4.'.'.config('app.domain')]
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

        $rt->addRule($new_rule_3);
        $rt->addRule($new_rule_4);
        $this->assertTrue($rt->ruleExists($new_rule_3));
        $this->assertTrue($rt->ruleExists($new_rule_4));

        $rt->deleteRule($new_rule_3);
        $this->assertFalse($rt->ruleExists($new_rule_3));
        $this->assertTrue($rt->ruleExists($new_rule_4));

        $rt->deleteAllRules(); // old rule no longer exists
        $this->assertFalse($rt->ruleExists($new_rule_3));
        $this->assertFalse($rt->ruleExists($new_rule_4));

        $ssh->exec('curl -s localhost:2019/config/apps/http/servers/https/routes/');

        $this->assertEquals('[]', $ssh->getLastLine());

        // clean up
        cleanup_container('db29_router');
    }
}
