<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\Plan;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Instance>
 */
class InstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dns_id' => bin2hex(random_bytes(16)),
            'machine_id' => Machine::factory(),
            'plan_id' => Plan::factory(),
            'source_id' => Source::factory(),
            'subdomain' => bin2hex(random_bytes(8)),
            'user_id' => User::factory(),
            'status' => fake()->randomElement([
                'queue', 'init', 'dns_up', 'ct_up', 'rt_up', 'ct_dw', 'rt_dw', 'dns_dw'
            ]),
            'version_template' => '[]',
        ];
    }
}
