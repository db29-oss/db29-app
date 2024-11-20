<?php

namespace Tests\Feature;

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

        $response = $this->get('supported-source');

        $response->assertStatus(200);

        $response->assertDontSee(str()->random(32));

        $this->assertEquals(0, Source::count());

        Source::factory()->count(10)->create();

        $source = Source::first();

        $response = $this->get('supported-source');

        $response->assertSee($source->name);
    }
}
