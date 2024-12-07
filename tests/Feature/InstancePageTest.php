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

    public function test_show_tutorial_new_user(): void
    {
        test_util_migrate_fresh();

        $u = User::factory()->create();

        Instance::factory()
            ->for($u)
            ->for(Source::factory()->create())
            ->create();

        $u = User::first();
        $u->instance_count = 1;
        $u->save();

        auth()->login($u);

        $i = Instance::first();
        $i->status = 'queue';
        $i->save();

        $response = $this->get('/instance');

        $response->assertStatus(200);

        $response->assertSee('explain bubble color');
        $response->assertSee('reload page every 5s');

        $i = Instance::first();
        $i->status = 'dns';
        $i->save();

        $response = $this->get('/instance');

        $response->assertStatus(200);

        $response->assertSee('explain bubble color');
        $response->assertSee('reload page every 10s');

        $response->assertSee(Instance::first()->name);

        $i = Instance::first();
        $i->status = 'rt_up';
        $i->save();

        $response = $this->get('/instance');

        $response->assertStatus(200);

        $response->assertSee('↑');

        Instance::factory()
            ->for($u)
            ->for(Source::factory()->create())
            ->create();

        $u->instance_count = 2;
        $u->save();

        $response = $this->get('/instance');

        $response->assertStatus(200);

        $response->assertDontSee('↑');
    }
}
