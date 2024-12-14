<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Source;
use App\Models\User;
use Tests\TestCase;

class ShowSupportedSourceTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        User::factory()->create();

        $u = User::first();

        auth()->login($u);

        $response = $this->get('source');

        $response->assertStatus(200);

        $response->assertDontSee(str()->random(32));

        $this->assertEquals(0, Source::count());

        Source::factory()->count(10)->hasPlans(3)->create();

        $s_s = Source::whereEnabled(false)->get();

        $response = $this->get('source');
        $response->assertOk();

        foreach ($s_s as $s) {
            $response->assertDontSee($s->name);
        }

        $s = Source::first();
        $s->enabled = true;
        $s->save();

        Plan::factory()->for($s)->create();

        $s_id = $s->id;

        $response = $this->get('source');

        foreach ($s_s as $s) {
            if ($s->id === $s_id) {
                $response->assertSee($s->name);
                continue;
            }

            $response->assertDontSee($s->name);
        }
    }
}
