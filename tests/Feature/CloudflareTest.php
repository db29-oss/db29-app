<?php

namespace Tests\Feature;

use Tests\TestCase;

class CloudflareTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (! static::isExplicitlyRun()) {
            static::markTestSkipped('Skipped because it takes too long - can manually run it.');
        }
    }

    public function test_generic(): void
    {
        $subdomain = strtolower(str()->random(20));

        $this->assertFalse(app('cf')->subdomainExists($subdomain));

        $dns_id = app('cf')->addDnsRecord($subdomain, fake()->ipv4);

        $this->assertTrue(app('cf')->subdomainExists($subdomain));

        app('cf')->deleteDnsRecord($dns_id);

        $this->assertFalse(app('cf')->subdomainExists($subdomain));
    }

    public function test_batch_action(): void
    {
        // 1. create 2 subdomain

        $subdomain_1 = strtolower(str()->random(20));
        $subdomain_2 = strtolower(str()->random(20));

        $this->assertFalse(app('cf')->subdomainExists($subdomain_1));
        $this->assertFalse(app('cf')->subdomainExists($subdomain_2));

        $dns_id_1 = app('cf')->addDnsRecord($subdomain_1, fake()->ipv4());

        $this->assertTrue(app('cf')->subdomainExists($subdomain_1));

        $dns_id_2 = app('cf')->addDnsRecord($subdomain_2, fake()->ipv4());

        $this->assertTrue(app('cf')->subdomainExists($subdomain_2));

        $update_content_dns_id_2 =  fake()->ipv4();

        $subdomain_3 = strtolower(str()->random(20));

        // 2. batch action delete 1 domain, update one, and create one
        $batch_action = [
            'deletes' => [
                [
                    'id' => $dns_id_1,
                ]
            ],
            'patches' => [
                [
                    'id' => $dns_id_2,
                    'content' => $update_content_dns_id_2,
                ]
            ],
            'puts' => [],
            'posts' => [
                [
                    'name' => $subdomain_3,
                    'type' => 'A',
                    'content' => fake()->ipv4(),
                ]
            ]
        ];

        $batch_result = app('cf')->batchAction($batch_action);

        // 3. remove new domain in 1. and 2.
        $batch_result_2 = app('cf')->batchAction([
            'deletes' => [
                [
                    'id' => $batch_result['posts'][0]['id']
                ],
                [
                    'id' => $dns_id_2,
                ],
            ]
        ]);

        $this->assertEquals(2, count($batch_result_2['deletes']));
    }

    private static function isExplicitlyRun(): bool
    {
        $class_arr = explode('\\', __CLASS__);
        $class_name = end($class_arr);

        foreach ($_SERVER['argv'] as $server_argv) {
            if (str_contains($server_argv, $class_name)) {
                return true;
            }
        }

        return false;
    }
}
