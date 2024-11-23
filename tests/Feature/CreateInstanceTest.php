<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\User;
use Tests\TestCase;

class CreateInstanceTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        User::factory()->create();

        $u = User::first();

        $this->assertNotNull($u);

        auth()->login($u);

        $s = Source::factory()->create(['enabled' => true]);

        $response = $this->post('create-instance', ['source_name' => $s->name]);
    }
}
