<?php

namespace Tests\Feature;

use App\Jobs\TermInstance;
use App\Models\Instance;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InstanceCleanupTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $this->freezeTime(function () {
            Instance::factory()->count(10)->create([
                'turned_off_at' => now()->subDays(rand(20, 40))
            ]);

            $instances = Instance::query()
                ->whereStatus('ct_dw')
                ->where('queue_active', false)
                ->where('turned_off_at', '<', now()->subDays(30))
                ->get();

            Queue::fake();

            $this->artisan('app:instance-cleanup');

            if (count($instances)) {
                Queue::assertPushed(TermInstance::class, count($instances));
            } else {
                Queue::assertNothingPushed();
            }
        });
    }
}
