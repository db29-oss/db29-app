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
                'max_cpu' => 40, // score rating
                'max_memory' => 200 * 1024 * 1024, // 200 MB
                'max_disk' => 1 * 1024 * 1024 * 1024, // 1 GB
            ])
        ];
    }
}
