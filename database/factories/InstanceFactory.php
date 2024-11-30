<?php

namespace Database\Factories;

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
            'version_template' => '{}',
            'status' => fake()->randomElement([
                'queue', 'init', 'dns_up', 'ct_up', 'rt_up', 'ct_dw', 'rt_dw', 'dns_dw'
            ]),
            'subdomain' => bin2hex(random_bytes(8)),
            'dns_id' => bin2hex(random_bytes(16)),
        ];
    }
}
