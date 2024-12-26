<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeleteUserKeepRechargeNumberTest extends TestCase
{
    public function test_generic(): void
    {
        DB::table('recharge_number_holes')->delete();
        DB::table('users')->delete();

        User::factory()->count(3)->create();

        $u = User::first();

        $recharge_number = $u->recharge_number;

        $u->delete();

        $this->assertEquals(1, DB::table('recharge_number_holes')->count());
        $this->assertEquals($recharge_number, DB::table('recharge_number_holes')->first()->recharge_number);

        $u_2 = User::first();
        $recharge_number_2 = $u->recharge_number;
        $u_2->delete();

        $this->assertEquals(2, DB::table('recharge_number_holes')->count());
    }
}
