<?php

namespace App\Jobs\Instance;

use App\Contracts\InstanceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WordPress implements InstanceInterface, ShouldQueue
{
    use Queueable;

    public function __construct(
        private $docker_compose = null,
        private $instance = null,
        private $machine = null,
        private $plan = null,
        private $reg_info = null,
        private $ssh = null,
    ) {}

    public function setUp()
    {
        $this->docker_compose['services']['wordpress']['container_name'] = $this->instance->id;

        foreach ($this->docker_compose['services']['wordpress']['ports'] as $dcp_idx => $dcp) {
            if (str_contains($dcp, ':')) {
                $dcp_exp = explode(':', $dcp);
                $dcp_port = trim(end($dcp_exp));
                $this->docker_compose['services']['wordpress']['ports'][$dcp_idx] = $dcp_port;
                break;
            }
        }

        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        // change volumes path
        $mkdir_volume_paths = [];

        foreach ($this->docker_compose['volumes'] as $volume_name => $volume_cf) {
            $volume_path = $instance_path.$volume_name;

            $this->docker_compose['volumes'][$volume_name] = [
                'driver' => 'local',
                'driver_opts' => [
                    'type' => 'none',
                    'o' => 'bind',
                    'device' => $volume_path,
                ]
            ];

            $mkdir_volume_paths[] = 'mkdir '.$volume_path;
        }

        $dump = app('yml')->dump($this->docker_compose, 4);

        $yml_lines = explode("\n", $dump);

        $put_docker_compose_commands = [];

        foreach ($yml_lines as $yml_line) {
            $put_docker_compose_commands[] =
                'echo '.escapeshellarg($yml_line).' >> '.$instance_path.'docker-compose.yml';
        }

        $create_instance_path = 'mkdir '.$instance_path;

        if (app('env') === 'production') {
            $create_instance_path = 'btrfs subvolume create '.$instance_path;
        }

        $apply_limit_commands = $this->buildLimitCommands();

        $this->ssh
             ->exec(array_merge(
                 [
                     $create_instance_path
                 ],
                 $mkdir_volume_paths,
                 $put_docker_compose_commands,
                 [
                     'cd '.$instance_path.' && podman-compose up -d',
                 ],
                 $apply_limit_commands
             ));
    }

    public function tearDown()
    {
        $rm_instance_dir = 'cd '.$this->machine->storage_path.'instance/ && rm -rf '.$this->instance->id;

        if (app('env') === 'production') {
            $rm_instance_dir = 'btrfs subvolume delete '.
                $this->machine->storage_path.'instance/'.$this->instance->id;
        }

        $this->ssh
             ->exec(array_merge(
                 [
                     'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' && '.
                     'podman-compose down --volumes',
                 ],
                 [
                     $rm_instance_dir
                 ]
             ));
    }

    public function turnOff()
    {
        $this->ssh->exec(
            'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' && podman-compose down'
        );
    }

    public function turnOn()
    {
        $apply_limit_commands = $this->buildLimitCommands();

        $this->ssh->exec(array_merge(
             [
                 'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' && '.
                 'podman-compose up -d',
             ],
             $apply_limit_commands
        ));
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

    private function buildLimitCommands(): array
    {
        $apply_limit_commands = [];

        if (app('env') === 'production') {
            $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

            $constraint = json_decode($this->plan->constraint, true);

            if (array_key_exists('max_disk', $constraint)) {
                $apply_limit_commands[] =
                    'btrfs qgroup limit '.$constraint['max_disk'].' '.$instance_path;
            }

            if (array_key_exists('max_cpu', $constraint)) {
                $apply_limit_commands[] =
                    'podman update --cpu-shares '.$constraint['max_cpu'].' '.$this->instance->id;
            }

            if (array_key_exists('max_memory', $constraint)) {
                $apply_limit_commands[] =
                    'podman update --memory '.$constraint['max_memory'].' '.$this->instance->id;
            }
        }

        return $apply_limit_commands;
    }
}
