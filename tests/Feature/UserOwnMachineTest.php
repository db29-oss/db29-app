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
    public function test_add_delete_machine(): void
    {
        test_util_migrate_fresh();

        $u = User::factory()->create();

        auth()->login($u);

        $response = $this->get('server/add');
        $response->assertStatus(200);

        config(['queue.default' => 'database']);

        $this->assertEquals(0, DB::table('jobs')->count());

        $this->assertEquals(0, User::whereId($u->id)->first()->machine_count);

        $response = $this->post('server/add', [
            'ssh_username' => fake()->username(),
            'ssh_address' => 'example.com',
            'ssh_port' => fake()->numberBetween(1, 65535),
            'ssh_privatekey' => str()->random(100),
            'storage_path' => rand(0, 1) ? null : '/'.str()->random(40).'/',
        ]);

        $this->assertEquals(1, User::whereId($u->id)->first()->machine_count);

        $this->assertEquals(1, Machine::count());

        $this->assertEquals(1, DB::table('jobs')->count());

        DB::table('jobs')->delete();

        // delete machine

        $response = $this->post('server/delete', [
            'machine_id' => Machine::first()->id
        ]);

        $this->assertEquals(0, User::whereId($u->id)->first()->machine_count);
    }
}
