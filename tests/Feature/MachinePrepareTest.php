<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Services\SSHEngine;
use Artisan;
use Tests\TestCase;

class MachinePrepareTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $m = Machine::factory()->create();
        $m->refresh();

        $ssh_port = setup_container('db29_machine_prepare', $m->id);

        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/randomdirname/';
        $m->save();

        $ssh = app('ssh')
            ->toMachine($m)
            ->exec('ls -1 /opt/');

        $this->assertEquals(0, count($ssh->getOutput()));

        $this->assertEquals(false, Machine::whereId($m->id)->first()->prepared);

        Artisan::call('app:machine-prepare');

        $this->assertEquals(true, Machine::whereId($m->id)->first()->prepared);

        $ssh->exec('ls -1 /opt/');

        $this->assertEquals(1, count($ssh->getOutput()));

        // test --force option

        $m->refresh();

        $m2 = new Machine;
        $m2->ip_address = '127.0.0.1';
        $m2->ssh_port = $ssh_port;
        $m2->storage_path = '/opt/randomdirname/';
        $m2->save();

        $m2 = $m2->fresh();

        Artisan::call('app:machine-prepare --machine_id='.$m->id);

        $this->assertEquals($m->updated_at, Machine::whereId($m->id)->first()->updated_at);

        $this->assertEquals($m2->updated_at, Machine::whereId($m2->id)->first()->updated_at);

        $this->travel(2)->seconds();

        Artisan::call('app:machine-prepare --machine_id='.$m->id.' --force');

        $this->assertNotEquals($m->updated_at, Machine::whereId($m->id)->first()->updated_at);

        $this->assertEquals($m2->updated_at, Machine::whereId($m2->id)->first()->updated_at);

        // clean up
        unset($ssh);

        cleanup_container('db29_machine_prepare', $m->id);
    }
}
