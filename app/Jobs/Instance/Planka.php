<?php

namespace App\Jobs\Instance;

use App\Contracts\InstanceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class Planka implements InstanceInterface, ShouldQueue
{
    use Queueable;

    public function __construct(
        private $docker_compose = null,
        private $instance = null,
        private $latest_version_template = null,
        private $machine = null,
        private $reg_info = null,
        private $ssh = null,
    ) {}

    public static function initialResourceConsumption()
    {
        return [
            'memory' => 115 * 1000 * 1000, // bytes
            'disk' => 15 * 1000 * 1000, // bytes
            'cpu' => 300 // score from rating cpubenchmark.net
        ];
    }

    public function setUp()
    {
        foreach (
            $this->docker_compose['services']['planka']['environment'] as $env_idx => $environment
        ) {
            if (str_starts_with($environment, 'BASE_URL=')) {
                $this->docker_compose['services']['planka']['environment'][$env_idx] =
                    'BASE_URL=https://'.$this->instance->subdomain.'.'.config('app.domain');

                break;
            }
        }

        $this->docker_compose['services']['planka']['container_name'] = $this->instance->id;

        $this->docker_compose['services']['planka']['environment'][] =
            'DEFAULT_ADMIN_EMAIL='.$this->reg_info['email'];

        $this->docker_compose['services']['planka']['environment'][] =
            'DEFAULT_ADMIN_PASSWORD='.$this->reg_info['password'];

        $this->docker_compose['services']['planka']['environment'][] =
            'DEFAULT_ADMIN_NAME='.$this->reg_info['name'];

        $this->docker_compose['services']['planka']['environment'][] =
            'DEFAULT_ADMIN_USERNAME='.$this->reg_info['username'];

        foreach ($this->docker_compose['services']['planka']['ports'] as $dcp_idx => $dcp) {
            if (str_contains($dcp, ':')) {
                $dcp_exp = explode(':', $dcp);
                $dcp_port = trim(end($dcp_exp));
                $this->docker_compose['services']['planka']['ports'][$dcp_idx] = $dcp_port;
            }
        }

        foreach ($this->docker_compose['services']['planka']['environment'] as $dce_idx => $dce) {
            if (str_starts_with($dce, 'SECRET_KEY=')) {
                $this->docker_compose['services']['planka']['environment'][$dce_idx] =
                    'SECRET_KEY='.bin2hex(random_bytes(64));
            }
        }

        $dump = app('yml')->dump($this->docker_compose, 4);

        $yml_lines = explode("\n", $dump);

        $commands = [];

        foreach ($yml_lines as $yml_line) {
            $commands[] = 'echo '.
                $this->ssh->lbsl.'\''.
                bce($yml_line, $this->ssh->lbsl, $this->ssh->hbsl).
                $this->ssh->lbsl.'\''.' '.
                $this->ssh->lbsl.">".$this->ssh->lbsl."> ".
                $this->machine->storage_path.'instance/'.$this->instance->id.'/docker-compose.yml';
        }

        $this->ssh
             ->exec(array_merge(
                 [
                     'mkdir -p '.$this->machine->storage_path.'instance/'.$this->instance->id,
                     'rm -rf '.
                     $this->machine->storage_path.'instance/'.$this->instance->id.'/docker-compose.yml',
                     'mkdir -p '.$this->machine->storage_path.'instance/'.$this->instance->id
                 ],
                 $commands,
                 [
                     'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' \\&\\& '.
                     'podman-compose up -d',
                 ]
             ));
    }

    public function tearDown()
    {
        $this->ssh
             ->exec([
                 'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' \\&\\& '.
                 'podman-compose down --volumes',
                 'cd '.$this->machine->storage_path.'instance/ \\&\\& rm -rf '.$this->instance->id,
             ]);
    }

    public function turnOff()
    {
        $this->ssh->exec(
            'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' \\&\\& podman-compose down'
        );
    }

    public function turnOn()
    {

        $this->ssh->exec(
             [
                 'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' \\&\\& '.
                 'podman-compose up -d',
             ]
        );
    }

    public function backUp()
    {
    }

    public function restore()
    {
    }

    public function downgrade()
    {
    }

    public function upgrade()
    {
    }
}
