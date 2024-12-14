<?php

namespace Tests\Feature;

use App\Models\Instance;
use Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TurnOffFreeInstanceTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        Instance::factory()->count(10)->create([
            'status' => 'rt_up',
            'queue_active' => false,
            'paid_at' => now()->subDays(2),
            'turned_on_at' => now()->subDays(2),
        ]);

        $i = Instance::inRandomOrder()->first();
        $i->paid_at = now(); // will not be subject to turn-off-free-instance
        $i->save();

        $i2 = Instance::whereNotIn('id', [$i->id])->inRandomOrder()->first();
        $i2->paid_at = now()->subDays(2);
        $i2->turned_on_at = now()->subHours(23); // will not be subject to turn-off-free-instance
        $i2->save();

        config()->set('queue.default', 'database');

        $this->assertEquals(0, app('db')->table('jobs')->count());

        $this->artisan('app:turn-off-free-instance');

        $this->assertEquals(8, app('db')->table('jobs')->count());

        test_util_migrate_fresh(); // prevent composer run dev kick in
    }
}
