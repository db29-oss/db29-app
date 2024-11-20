<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TrafficRouterTest extends TestCase
{
    public function test_traffic_router_basic(): void
    {
        $ssh_port = setup_container('db29_traffic_router');

        // TODO

        // clean up
        cleanup_container('db29_traffic_router');
    }
}
