<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base' => false,
            'customized' => false,
            'price' => 1667,
            'source_id' => Source::factory(),
            'constraint' => json_encode([
                'max_cpu' => rand(40, 400), // score rating
                'max_memory' => rand(50, 250) * 1024 * 1024, // 200 MB
                'max_disk' => rand(1, 4) * 1024 * 1024 * 1024, // 1 GB
            ])
        ];
    }
}
