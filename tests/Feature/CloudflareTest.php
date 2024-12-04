<?php

namespace Tests\Feature;

use Tests\TestCase;

class CloudflareTest extends TestCase
{
    /**
     * All test here only run when remove not_ infront of function
     * this is to prevent automation test to run against cloudflare
     */

    public function not_test_generic(): void
    {
        $subdomain = strtolower(str()->random(20));

        $this->assertFalse(app('cf')->subdomainExists($subdomain));

        $dns_id = app('cf')->addDnsRecord($subdomain, fake()->ipv4);

        $this->assertTrue(app('cf')->subdomainExists($subdomain));

        app('cf')->deleteDnsRecord($dns_id);

        $this->assertFalse(app('cf')->subdomainExists($subdomain));
    }
}
