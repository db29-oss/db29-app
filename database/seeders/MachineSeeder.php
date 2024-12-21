<?php

namespace Database\Seeders;

use App\Models\Machine;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $machine = new Machine;
        $machine->ip_address = 'localhost';
        $machine->ssh_port = 22;
        $machine->storage_path = '/tmp/';
        $machine->enabled = true;
        $machine->max_cpu = rand(4_000, 32_000);
        $machine->remain_cpu = rand(4_000, 32_000);
        $machine->max_disk = rand(10, 20) * 1024 * 1024 * 1024;
        $machine->remain_disk = rand(10, 20) * 1024 * 1024 * 1024;
        $machine->max_memory = rand(2, 10) * 1024 * 1024 * 1024;
        $machine->remain_memory = rand(2, 10) * 1024 * 1024 * 1024;
        $machine->save();
    }
}
