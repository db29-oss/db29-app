<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    public function test_update_email(): void
    {
        test_util_migrate_fresh();

        User::factory()->create();

        $u = User::first();

        auth()->login($u);

        $this->assertNull($u->email);

        $response = $this->post('account-update', [
            'email' => fake()->word
        ]);

        $this->assertNull(User::whereId($u->id)->first()->email);

        $response = $this->post('account-update', [
            'email' => fake()->email
        ]);

        $this->assertNotNull(User::whereId($u->id)->first()->email);
    }
}
