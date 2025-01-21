<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\Machine;
use App\Models\Plan;
use App\Models\Source;
use App\Models\TrafficRouter;
use App\Models\User;
use App\Services\SSHEngine;
use Tests\TestCase;

class WordPressInstanceTest extends TestCase
{
    public function test_generic(): void
    {
        if (! $this->isExplicitlyRun()) {
            $this->markTestSkipped('Skipped because it takes too long - can manually run it.');
        }

        config()->set('app.domain', '127.0.0.1');

        test_util_migrate_fresh();

        $u = User::factory()->create();
        $this->assertEquals(0, $u->credit);

        auth()->login($u);

        $s = new Source;
        $s->name = 'word_press';
        $s->enabled = true;
        $s->save();

        $p = Plan::factory()->create(['base' => true, 'source_id' => $s->id]);

        $m = Machine::factory()->create();
        $m->refresh();

        $ssh_port = setup_container('db29_instance', $m->id);

        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/randomdirname/';
        $m->enabled = true;
        $m->save();

        $tr = new TrafficRouter;
        $tr->machine_id = $m->id;
        $tr->save();

        $this->artisan('app:machine-prepare');

        $this->artisan('app:traffic-router-prepare');

        $this->assertEquals(0, Instance::count());

        /**
         * SET UP
         */

        $response = $this->post('instance/register', [
            'source' => 'word_press',
        ]);

        $this->assertEquals(1, Instance::count());

        $inst = Instance::first();
        $this->assertEquals('rt_up', $inst->status);
        $this->assertNotNull($inst->dns_id);

        $ssh = app('ssh')->toMachine($m);

        while (true) {
            $output = [];

            $ssh->exec('curl localhost:2019/config/');

            if (str_contains($ssh->getLastline(), $inst->subdomain)) {
                break;
            }
        }

        $inst->refresh();

        $this->assertEquals('rt_up', $inst->status);
        $this->assertEquals(false, $inst->queue_active);

        $m->refresh();

        $this->assertEquals(
            $m->max_cpu - $m->remain_cpu,
            json_decode($inst->plan->constraint, true)['max_cpu']
        );

        $this->assertEquals(
            $m->max_disk - $m->remain_disk,
            json_decode($inst->plan->constraint, true)['max_disk']
        );

        $this->assertEquals(
            $m->max_memory - $m->remain_memory,
            json_decode($inst->plan->constraint, true)['max_memory']
        );


        /**
         * TURN OFF
         */

        $ssh->exec('curl localhost:2019/config/');

        $this->assertTrue(str_contains($ssh->getLastline(), $inst->subdomain));

        $this->assertFalse(str_contains($ssh->getLastline(), 'instance is currently off'));

        $response = $this->post('instance/turn-off', [
            'instance_id' => $inst->id,
        ]);

        $u->refresh();

        $u_credit = $u->credit;

        $this->assertTrue(0 > $u->credit);

        $inst->refresh();

        $this->assertEquals('ct_dw', $inst->status);

        $this->assertEquals(0, $inst->queue_active);

        $ssh->clearOutput();

        $ssh->exec('podman ps -q');

        $this->assertEquals([], $ssh->getOutput());

        $ssh->exec('curl localhost:2019/config/');

        $this->assertTrue(str_contains($ssh->getLastline(), $inst->subdomain));

        if ($inst->subdomain !== null) {
            $this->assertTrue(str_contains($ssh->getLastline(), 'instance is currently off'));
        }

        $m->refresh();

        $this->assertEquals(
            0,
            $m->max_cpu - $m->remain_cpu,
        );

        $this->assertEquals(
            $m->max_disk - $m->remain_disk,
            json_decode($inst->plan->constraint, true)['max_disk']
        );

        $this->assertEquals(
            0,
            $m->max_memory - $m->remain_memory,
        );

        /**
         * TURN ON
         */
        $response = $this->post('instance/turn-on', [
            'instance_id' => $inst->id,
        ]);

        $inst->refresh();

        $this->assertEquals('rt_up', $inst->status);

        $this->assertEquals(0, $inst->queue_active);

        $ssh->clearOutput();

        $ssh->exec('podman ps -q');

        $this->assertNotEquals([], $ssh->getOutput());

        $ssh->exec('curl localhost:2019/config/');

        $this->assertFalse(str_contains($ssh->getLastline(), 'instance is currently off'));

        $m->refresh();

        $this->assertEquals(
            $m->max_cpu - $m->remain_cpu,
            json_decode($inst->plan->constraint, true)['max_cpu']
        );

        $this->assertEquals(
            $m->max_disk - $m->remain_disk,
            json_decode($inst->plan->constraint, true)['max_disk']
        );

        $this->assertEquals(
            $m->max_memory - $m->remain_memory,
            json_decode($inst->plan->constraint, true)['max_memory']
        );

        /**
         * TEAR DOWN
         */

        $response = $this->post('instance/turn-off', [
            'instance_id' => $inst->id,
        ]);

        $u->refresh();

        $this->assertNotEquals($u->credit, $u_credit);

        $this->assertEquals(1, $u->instance_count);

        $this->assertEquals(1, Instance::count());

        $this->assertEquals('ct_dw', Instance::first()->status);

        $response = $this->post('instance/delete', [
            'instance_id' => $inst->id,
        ]);

        $this->assertEquals(0, Instance::count());

        $u->refresh();
        $this->assertEquals(0, $u->instance_count);

        $m->refresh();

        $this->assertEquals(
            0,
            $m->max_cpu - $m->remain_cpu,
        );

        $this->assertEquals(
            0,
            $m->max_disk - $m->remain_disk,
        );

        $this->assertEquals(
            0,
            $m->max_memory - $m->remain_memory,
        );

        // ensure no podman left
        $ssh->clearOutput();

        $ssh->exec('podman ps -q');

        $this->assertEquals([], $ssh->getOutput());

        unset($ssh);

        // clean up
        cleanup_container('db29_instance', $m->id);
    }

    private function isExplicitlyRun(): bool
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
