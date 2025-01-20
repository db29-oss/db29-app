<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UpdatePrefillTest extends TestCase
{
    public function test_update_email(): void
    {
        test_util_migrate_fresh();

        User::factory()->create();

        $u = User::first();

        auth()->login($u);

        $this->assertNull($u->email);

        $response = $this->post('prefill', [
            'email' => fake()->word()
        ]);

        $this->assertNull(User::whereId($u->id)->first()->email);

        $response = $this->post('prefill', [
            'email' => fake()->email,
            'name' => fake()->word(),
            'username' => fake()->word(),
        ]);

        $this->assertNotNull(User::whereId($u->id)->first()->email);
    }
}
