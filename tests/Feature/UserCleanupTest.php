<?php

namespace Tests\Feature;

use App\Models\User;
use Artisan;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class UserCleanupTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        Artisan::call('app:user-cleanup');

        User::factory()->count(10)->create();

        $this->assertEquals(10, User::count());

        $u = User::inRandomOrder()->first();
        $u->last_logged_in_at = now()->subDays(31);
        $u->save();

        $u2 = User::whereNotIn('id', [$u->id])->inRandomOrder()->first();
        $u2->instance_count = rand(1, 10);
        $u2->last_logged_in_at = now()->subDays(31);
        $u2->save();

        $this->assertEquals(0, DB::table('recharge_number_holes')->count());

        Artisan::call('app:user-cleanup');

        $this->assertEquals(9, User::count());

        $this->assertNull(User::whereId($u->id)->first());
        $this->assertNotNull(User::whereId($u2->id)->first());

        $this->assertEquals(1, DB::table('recharge_number_holes')->count());

        $this->assertEquals(
            $u->recharge_number,
            DB::table('recharge_number_holes')->first()->recharge_number
        );
    }
}
