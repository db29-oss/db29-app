<?php

namespace Tests\Feature;

use App\Models\User;
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

        $this->assertEquals(1, User::count());

        $u = User::first();

        $this->assertNotNull($u);

        $this->assertNull($u->last_logged_in_at);
    }

    public function test_login_logout(): void
    {
        $this->assertGuest();

        $this->assertEquals(1, User::count());

        $u = User::first();

        $this->assertNull($u->last_logged_in_at);

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
}
