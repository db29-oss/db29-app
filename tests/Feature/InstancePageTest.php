<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\Source;
use App\Models\User;
use Tests\TestCase;

class InstancePageTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $u = User::factory()->create();

        Instance::factory()
            ->count(fake()->numberBetween(2, 6))
            ->for($u)
            ->for(Source::factory()->create())
            ->create();

        Instance::factory()
            ->count(fake()->numberBetween(1, 5))
            ->for($u)
            ->for(Source::factory()->create())
            ->create();

        $u = User::first();

        auth()->login($u);

        $response = $this->get('/instance');

        $response->assertStatus(200);

        $response->assertSee(Instance::first()->name);
    }
}
