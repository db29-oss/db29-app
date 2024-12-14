<?php

namespace Tests\Feature;

use App\Models\User;
use Artisan;
use Tests\TestCase;

class UserCleanupTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        User::factory()->count(10)->create();

        $this->assertEquals(10, User::count());

        $u = User::inRandomOrder()->first();
        $u->updated_at = now()->subDays(31);
        $u->save();

        $u2 = User::whereNotIn('id', [$u->id])->inRandomOrder()->first();
        $u2->instance_count = rand(1, 10);
        $u2->updated_at = now()->subDays(31);
        $u2->save();

        Artisan::call('app:user-cleanup');

        $this->assertEquals(9, User::count());

        $this->assertNull(User::whereId($u->id)->first());
        $this->assertNotNull(User::whereId($u2->id)->first());
    }
}
