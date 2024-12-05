<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\TrafficRouter;
use Illuminate\Database\Seeder;

class TrafficRouterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tr = new TrafficRouter;
        $tr->machine_id = Machine::first()->id;
        $tr->save();
    }
}
