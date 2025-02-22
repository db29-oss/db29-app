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

        $u->credit = User::SIGN_UP_CREDIT;
        $u->save();

        Plan::factory()->for($s)->create(['base' => true]);

        $s_id = $s->id;

        $response = $this->get('source');

        foreach ($s_s as $s) {
            if ($s->id === $s_id) {
                $response->assertSee($s->name);

                $s->load(['plans' => function ($q) { $q->whereBase(true); }]);

                $response->assertSee(formatNumberShort($s->plans[0]->price));

                continue;
            }

            $response->assertDontSee($s->name);
        }

        // should not show any price
        $u->setting = json_encode(['disable_pricing' => true]);
        $u->save();

        $response = $this->get('source');

        foreach ($s_s as $s) {
            if ($s->id === $s_id) {
                $response->assertSee($s->name);

                $response->assertDontSee(formatNumberShort($s->plans[0]->price));
            }
        }
    }
}
