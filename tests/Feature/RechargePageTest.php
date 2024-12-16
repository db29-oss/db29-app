<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

class RechargePageTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $u = User::factory()->create();

        $u->refresh();

        auth()->login($u);

        $s = new Setting;
        $s->k = 'banking_details';
        $banking_details = [
            [
                'bank_name' => fake()->name,
                'account_number' => fake()->numberBetween(1000, 20000),
            ],
            [
                'bank_name' => fake()->name,
                'account_number' => fake()->numberBetween(1000, 20000),
            ],
            [
                'bank_name' => fake()->name,
                'account_number' => fake()->numberBetween(1000, 20000),
            ],
        ];
        $s->v = json_encode($banking_details);
        $s->save();

        $response = $this->get('recharge');

        $response->assertStatus(200);

        $response->assertSee($banking_details[0]['bank_name']);
        $response->assertSee($banking_details[1]['bank_name']);
        $response->assertDontSee(fake()->name);
    }
}
