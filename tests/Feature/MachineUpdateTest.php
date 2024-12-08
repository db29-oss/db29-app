<?php

namespace Tests\Feature;

use App\Models\Machine;
use Artisan;
use Tests\TestCase;

class MachineUpdateTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $m = Machine::factory()->create();

        $m->refresh();

        $ip_address = $m->ip_address;
        $this->assertNull($m->last_ip_address);

        Artisan::call('app:machine-update');

        $m->refresh();

        $this->assertEquals($ip_address, $m->last_ip_address);
        $this->assertNotEquals($ip_address, $m->ip_address);
    }
}
