<?php

namespace Tests\Feature;

use App\Jobs\PrepareMachine;
use App\Models\Instance;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserOwnMachineTest extends TestCase
{
    public function test_add_machine(): void
    {
        test_util_migrate_fresh();

        $u = User::factory()->create();

        auth()->login($u);

        $response = $this->get('server/add');
        $response->assertStatus(200);

        config(['queue.default' => 'database']);

        $this->assertEquals(0, DB::table('jobs')->count());

        $response = $this->post('server/add', [
            'ssh_username' => fake()->username(),
            'ssh_address' => fake()->domainName(),
            'ssh_port' => fake()->numberBetween(1, 65535),
            'ssh_privatekey' => str()->random(100),
            'storage_path' => rand(0, 1) ? null : '/'.str()->random(40).'/',
        ]);

        $this->assertEquals(2, DB::table('jobs')->count());

        DB::table('jobs')->delete();
    }

    public function test_delete_machine(): void
    {
        $this->assertEquals(0, Instance::count());

        $u = User::first();

        $this->assertNotNull($u);

        auth()->login($u);

        $m = Machine::first();

        $this->assertNotNull($m);

        $this->assertEquals(1, Machine::count());

        Instance::factory()->create(['machine_id' => $m->id, 'user_id' => $u->id]);

        $this->assertEquals(1, Instance::count());

        $m2 = Machine::factory()->create();

        $this->assertEquals(2, Machine::count());

        $response = $this->post('server/delete', [
            'machine_id' => $m2->id
        ]);

        $this->assertEquals(2, Machine::count());

        $response = $this->post('server/delete', [
            'machine_id' => $m->id
        ]);

        $this->assertEquals(2, Machine::count());

        Instance::query()->delete();

        $response = $this->post('server/delete', [
            'machine_id' => $m->id
        ]);

        $this->assertEquals(1, Machine::count());
    }
}
