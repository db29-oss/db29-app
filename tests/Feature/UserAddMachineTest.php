<?php

namespace Tests\Feature;

use App\Jobs\PrepareMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserAddMachineTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $u = User::factory()->create();

        auth()->login($u);

        $response = $this->get('server/add');
        $response->assertStatus(200);

        config(['queue.default' => 'database']);

        $this->assertEquals(0, DB::table('jobs')->count());

        $response = $this->post('server/add', [
            'ssh_address' => fake()->domainName(),
            'ssh_port' => fake()->numberBetween(1, 65535),
            'ssh_privatekey' => str()->random(100),
            'storage_path' => rand(0, 1) ? null : '/'.str()->random(40).'/',
        ]);

        $this->assertEquals(2, DB::table('jobs')->count());

        DB::table('jobs')->delete();
    }
}
