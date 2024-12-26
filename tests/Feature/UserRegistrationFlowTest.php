<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRegistrationFlowTest extends TestCase
{
    public function test_register_new_user(): void
    {
        test_util_migrate_fresh();

        $response = $this->get('login');

        $response->assertStatus(200);

        $this->assertEquals(0, User::count());

        $response = $this->post('register');
        $response->assertOk();

        $this->assertEquals(1, User::count());

        $u = User::first();

        $this->assertNotNull($u);
    }

    public function test_login_logout(): void
    {
        $this->assertGuest();

        $this->assertEquals(1, User::count());

        $u = User::first();

        $response = $this->get('dashboard');

        $response->assertRedirect('login');

        $response = $this->post('login', ['login_id' => $u->login_id]);

        $response->assertRedirect('dashboard');

        $this->assertNotNull(User::whereId($u->id)->first()->last_logged_in_at);

        $this->assertAuthenticated();

        $this->assertAuthenticatedAs($u);

        $this->post('logout');

        $this->assertGuest();
    }

    public function test_reuse_recharge_number(): void
    {
        $user_count = rand(1, 10);

        User::query()->delete();

        User::factory()->count($user_count)->create();

        $u = User::inRandomOrder()->first();

        $recharge_number = $u->recharge_number;

        $this->assertEquals(0, count(DB::select('select * from recharge_number_holes')));

        $u->delete();

        $this->assertEquals(1, count(DB::select('select * from recharge_number_holes')));

        $this->assertNull(User::whereRechargeNumber($recharge_number)->first());

        $response = $this->post('register');

        $response->assertOk();

        $this->assertNotNull(User::whereRechargeNumber($recharge_number)->first());

        $this->assertEquals(0, count(DB::select('select * from recharge_number_holes')));

        $this->assertNull(User::whereRechargeNumber($user_count + 1)->first());

        $response = $this->post('register');

        $response->assertOk();

        $this->assertNotNull(User::whereRechargeNumber($user_count + 1)->first());
    }
}
