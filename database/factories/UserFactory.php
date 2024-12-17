<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $str = str()->random(31);

        return [
            'login_id' => $str,
            'username' => $str,
            'recharge_number' => DB::raw(
                '(select coalesce(max(recharge_number), 0) + 1 as recharge_number from users)'
            ),
            'last_logged_in_at' => now()->toDateTimeString(),
        ];
    }
}
