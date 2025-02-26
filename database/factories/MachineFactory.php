<?php

namespace Database\Factories;

use App\Models\TrafficRouter;
use Illuminate\Database\Eloquent\Factories\Factory;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Machine>
 */
class MachineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = random_int(1000000000000000000, PHP_INT_MAX);

        $key = EC::createKey('Ed25519');

        $comment = 'db29_app '.$id;

        $ssh_privatekey = $key->toString('OpenSSH', ['comment' => $comment]);

        $file_put_contents = file_put_contents(storage_path('app/private/'.$id), $ssh_privatekey);

        chmod(storage_path('app/private/'.$id), 0600);

        $ssh_publickey = $key->getPublicKey()->toString('OpenSSH', ['comment' => $comment]);

        $file_put_contents = file_put_contents(storage_path('app/private/'.$id.'.pub'), $ssh_publickey);

        $max_cpu = rand(4_000, 32_000);
        $max_disk = rand(10, 20) * 1024 * 1024 * 1024;
        $max_memory = rand(2, 10) * 1024 * 1024 * 1024;

        return [
            'id' => $id,
            'ssh_privatekey' => $ssh_privatekey,
            'hostname' => fake()->domainName,
            'ip_address' => fake()->ipv4,
            'ssh_port' => fake()->numberBetween(1, 65535),
            'storage_path' => '/opt/',
            'enabled' => true,
            'max_cpu' => $max_cpu,
            'remain_cpu' => $max_cpu,
            'max_disk' => $max_disk,
            'remain_disk' => $max_disk,
            'max_memory' => $max_memory,
            'remain_memory' => $max_memory,
            'traffic_router_id' => TrafficRouter::factory()->create(['machine_id' => $id])->id,
        ];
    }
}
