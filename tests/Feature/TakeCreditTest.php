<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TakeCreditTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $now = Carbon::parse(now()->toDateTimeString()); // to remove sub second different
        $sub_time = rand(1, 1000);
        $sub_time_unit = collect(['Seconds', 'Minutes', 'Hours', 'Days'])->random();

        $this->freezeTime(function () use ($now, $sub_time_unit, $sub_time) {
            $this->travelTo(
                (clone $now)->{'sub'.$sub_time_unit}($sub_time),
                function () use ($sub_time, $sub_time_unit, $now) {
                    Instance::factory()->create(['status' => 'rt_up', 'paid_at' => now()]);
                }
            );
        });

        $p = Plan::first();
        $i = Instance::first();

        $pay_amount = (int) ceil(Carbon::parse($i->paid_at)->diffInDays($now) * $p->price);

        $bonus_credit = rand(0, 1000);

        $this->freezeTime(function () use ($now, $bonus_credit) {
            $this->travelTo($now, function () use ($bonus_credit) {
                $u = User::first();

                $u->bonus_credit = $bonus_credit;
                $u->save();

                $this->assertEquals(0, $u->credit);

                $this->artisan('app:take-credit');
            });
        });

        $u = User::first();

        if ($u->bonus_credit > 0) {
            $this->assertEquals(0, $u->credit);
        } else {
            $this->assertTrue($u->credit <= 0);
        }

        $this->assertEquals($bonus_credit, $u->credit + $u->bonus_credit + $pay_amount);
    }
}
