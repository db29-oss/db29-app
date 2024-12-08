<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\Machine;
use App\Models\Source;
use App\Models\TrafficRouter;
use App\Models\User;
use App\Services\SSHEngine;
use Artisan;
use Tests\TestCase;

class SetUpTearDownInstanceQueueTest extends TestCase
{
    public function test_generic(): void
    {
        if (! $this->isExplicitlyRun()) {
            $this->markTestSkipped('Skipped because it takes too long - can manually run it.');
        }

        test_util_migrate_fresh();

        $u = User::factory()->create();

        auth()->login($u);

        $s = new Source;
        $s->name = 'planka';
        $s->enabled = true;
        $s->version_templates = '[{"commit": "617246ec407353cd69c875baff5524b5e0c852dd", "docker_compose": {"services": {"planka": {"depends_on": {"postgres": {"condition": "service_healthy"}}, "environment": ["BASE_URL=http://localhost:3000", "DATABASE_URL=postgresql://postgres@postgres/planka", "SECRET_KEY=notsecretkey"], "image": "ghcr.io/plankanban/planka:latest", "ports": ["3000:1337"], "restart": "on-failure", "volumes": ["user-avatars:/app/public/user-avatars", "project-background-images:/app/public/project-background-images", "attachments:/app/private/attachments"]}, "postgres": {"environment": ["POSTGRES_DB=planka", "POSTGRES_HOST_AUTH_METHOD=trust"], "healthcheck": {"interval": "10s", "retries": 5, "test": ["CMD-SHELL", "pg_isready -U postgres -d planka"], "timeout": "5s"}, "image": "postgres:16-alpine", "restart": "on-failure", "volumes": ["db-data:/var/lib/postgresql/data"]}}, "version": "3", "volumes": {"attachments": null, "db-data": null, "project-background-images": null, "user-avatars": null}}, "tag": "v1.24.2"}]';
        $s->save();

        $m = Machine::factory()->create();
        $m->refresh();

        $ssh_port = setup_container('db29_set_up_tear_down_instance_queue', $m->id);

        $m->ip_address = '127.0.0.1';
        $m->ssh_port = $ssh_port;
        $m->storage_path = '/opt/randomdirname/';
        $m->enabled = true;
        $m->save();

        $tr = new TrafficRouter;
        $tr->machine_id = $m->id;
        $tr->save();

        Artisan::call('app:machine-prepare');

        Artisan::call('app:traffic-router-prepare');

        $this->assertEquals(0, Instance::count());

        /**
         * SET UP INSTANCE TEST
         */

        $response = $this->post('instance/register', [
            'source' => 'planka',
            'email' => fake()->email,
            'name' => str()->random(8),
            'password' => str()->random(25),
            'username' => str()->random(11),
        ]);

        $this->assertEquals(1, Instance::count());

        $inst = Instance::first();
        $this->assertEquals('rt_up', $inst->status);
        $this->assertNotNull($inst->dns_id);
        $this->assertNotNull($inst->subdomain);

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

        /**
         * TEAR DOWN INSTANCE TEST
         */

        $u->refresh();
        $this->assertEquals(1, $u->instance_count);

        $this->assertEquals(1, Instance::count());

        $response = $this->delete('instance', [
            'instance_id' => $inst->id,
        ]);

        $this->assertEquals(0, Instance::count());

        $u->refresh();
        $this->assertEquals(0, $u->instance_count);

        // ensure no podman left
        $ssh->clearOutput();

        $ssh->exec('podman ps -q');

        $this->assertEquals([], $ssh->getOutput());

        unset($ssh);

        // clean up
        cleanup_container('db29_set_up_tear_down_instance_queue', $m->id);
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
