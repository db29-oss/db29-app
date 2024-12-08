<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\EC;

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
        $uuid = str()->uuid()->toString();

        $key = EC::createKey('Ed25519');

        $comment = 'db29_app '.$uuid;

        $ssh_privatekey = $key->toString('OpenSSH', ['comment' => $comment]);

        $file_put_contents = file_put_contents(storage_path('app/private/'.$uuid), $ssh_privatekey);

        chmod(storage_path('app/private/'.$uuid), 0600);

        $ssh_publickey = $key->getPublicKey()->toString('OpenSSH', ['comment' => $comment]);

        $file_put_contents = file_put_contents(storage_path('app/private/'.$uuid.'.pub'), $ssh_publickey);

        return [
            'id' => $uuid,
            'ssh_privatekey' => $ssh_privatekey,
            'hostname' => fake()->domainName,
            'ip_address' => fake()->ipv4,
            'ssh_port' => fake()->numberBetween(1, 65535),
            'storage_path' => '/opt/',
            'enabled' => true,
        ];
    }
}
