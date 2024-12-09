<?php

namespace Tests\Feature;

use App\Jobs\TermInstance;
use App\Models\Instance;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
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

    public function test_display_on_off_delete_button(): void
    {
        test_util_migrate_fresh();

        $i = Instance::factory()->create();

        $u = User::first();

        auth()->login($u);

        $i->queue_active = true; // no button can be show
        $i->status = collect([
            'queue', 'init', 'dns_up', 'ct_up',
            'rt_up', 'rt_dw', 'dns_dw', 'ct_dw'
        ])->random();
        $i->save();

        $response = $this->get('instance');

        $response->assertDontSee(route('turn-on-instance'));
        $response->assertDontSee(route('turn-off-instance'));
        $response->assertDontSee(route('delete-instance'));
        $response->assertSee(__('trans.wait_a_sec'));
    }

    public function test_on_off_delete_out_of_flow(): void
    {
        test_util_migrate_fresh();

        $i = Instance::factory()->create();

        $u = User::first();

        $this->assertEquals($u->id, $i->user_id);

        auth()->login($u);


        // delete instance not allowed when queue_active
        Queue::fake();

        $i->refresh();
        $i->queue_active = true;
        $i->status = collect([
            'queue', 'init', 'dns_up', 'ct_up',
            'rt_up', 'rt_dw', 'dns_dw', 'ct_dw'
        ])->random();
        $i->save();

        $this->post('instance/delete', [
            'instance_id' => $i->id,
        ]);

        Queue::assertNothingPushed();

        // delete instance allowed when instance down
        Queue::fake();

        $i->refresh();
        $i->queue_active = false;
        $i->status = 'ct_dw';
        $i->save();

        $this->post('instance/delete', [
            'instance_id' => $i->id,
        ]);

        Queue::assertPushed(TermInstance::class);

        // delete instance while allowed even when instance up
        Queue::fake();

        $i->refresh();
        $i->queue_active = collect([true, false])->random();
        $i->status = 'rt_up';
        $i->save();

        $this->post('instance/delete', [
            'instance_id' => $i->id,
        ]);

        Queue::assertNothingPushed();

        // turn off instance not allowed when status is not rt_up
        Queue::fake();

        $i->refresh();
        $i->queue_active = collect([true, false])->random();
        $i->status = collect([ // missing rt_up
            'queue', 'init', 'dns_up', 'ct_up',
            'rt_dw', 'dns_dw', 'ct_dw'
        ])->random();
        $i->save();

        $this->post('instance/turn-off', [
            'instance_id' => $i->id,
        ]);

        Queue::assertNothingPushed();

        // turn off instance not allowed when queue_active
        Queue::fake();

        $i->refresh();
        $i->queue_active = true;
        $i->status = collect([ // missing rt_up
            'queue', 'init', 'dns_up', 'ct_up',
            'rt_dw', 'dns_dw', 'ct_dw'
        ])->random();
        $i->save();

        $this->post('instance/turn-off', [
            'instance_id' => $i->id,
        ]);

        Queue::assertNothingPushed();

        // turn on instance not allowed when queue_active
        Queue::fake();

        $i->refresh();
        $i->queue_active = true;
        $i->status = collect([
            'queue', 'init', 'dns_up', 'ct_up',
            'rt_up', 'rt_dw', 'dns_dw', 'ct_dw'
        ])->random();
        $i->save();

        $this->post('instance/turn-on', [
            'instance_id' => $i->id,
        ]);

        Queue::assertNothingPushed();

        // turn on instance not allowed when status is not ct_dw
        Queue::fake();

        $i->refresh();
        $i->queue_active = collect([true, false])->random();
        $i->status = collect([
            'queue', 'init', 'dns_up', 'ct_up',
            'rt_up', 'rt_dw', 'dns_dw' // missing ct_dw
        ])->random();
        $i->save();

        $this->post('instance/turn-on', [
            'instance_id' => $i->id,
        ]);

        Queue::assertNothingPushed();
    }
}
