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
        $machine->save();
    }
}
