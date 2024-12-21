<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Source;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $source = Source::first();

        Plan::factory()->count(2)->create(['source_id' => $source->id]);

        Plan::factory()->create(['base' => true, 'source_id' => $source->id]);
    }
}
