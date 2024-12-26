<?php

namespace App\Jobs\Instance;

use App\Contracts\InstanceInterface;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class Planka implements InstanceInterface, ShouldQueue
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

    public function setUp(): string
    {
        foreach (
            $this->docker_compose['services']['planka']['environment'] as $env_idx => $environment
        ) {
            if (str_starts_with($environment, 'BASE_URL=')) {
                $this->docker_compose['services']['planka']['environment'][$env_idx] =
                    'BASE_URL=https://'.
                    ($this->instance->subdomain ? $this->instance->subdomain.'.' : '').
                    config('app.domain');

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
                break;
            }
        }

        foreach ($this->docker_compose['services']['planka']['environment'] as $dce_idx => $dce) {
            if (str_starts_with($dce, 'SECRET_KEY=')) {
                $this->docker_compose['services']['planka']['environment'][$dce_idx] =
                    'SECRET_KEY='.bin2hex(random_bytes(64));

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

        try {
            $this->ssh->exec($create_instance_path);
        } catch (Exception) {}

        $apply_limit_commands = $this->buildLimitCommands();

        $this->ssh
             ->exec(array_merge(
                 $mkdir_volume_paths,
                 $put_docker_compose_commands,
                 [
                     'cd '.$instance_path.' && podman-compose up -d',
                 ],
                 $apply_limit_commands
             ));

        // traffic rule
        return $this->buildTrafficRule();
    }

    public function buildTrafficRule(): string
    {
        $wait_seconds = 0;

        while (true) {
            $this->ssh->clearOutput();

            $this->ssh->exec('podman port '.$this->instance->id);

            if ($this->ssh->getLastLine() !== null) {
                break;
            }

            sleep(1);

            $wait_seconds += 1;

            if ($wait_seconds > 30) {
                throw new Exception('DB292006: podman port wait exceed thresh hold');
            }
        }

        $host_port = parse_url($this->ssh->getLastLine())['port'];

        $wait_seconds = 0;

        while (true) {
            $this->ssh->clearOutput();

            try {
                $this->ssh->exec('curl -o /dev/null -s -w \'%{http_code}\' -L 0.0.0.0:'.$host_port);
            } catch (Exception) {
            }

            if ($this->ssh->getLastLine() === '200') {
                break;
            }

            sleep(1);

            $wait_seconds += 1;

            if ($wait_seconds > 30) {
                throw new Exception('DB292007: curl check wait http exceed thresh hold');
            }
        }

        $domain = config('app.domain');

        if ($this->instance->subdomain) {
            $domain = $this->instance->subdomain.'.'.config('app.domain');
        }

        $tr_config = <<<CONFIG
{$domain} {
    reverse_proxy 127.0.0.1:{$host_port}
}
CONFIG;

        return $tr_config;
    }

    public function tearDown()
    {
        $rm_instance_dir =
            'cd '.$this->machine->storage_path.'instance/ && rm -rf '.$this->instance->id;

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

    public function turnOn(): string
    {
        $apply_limit_commands = $this->buildLimitCommands();

        $this->ssh->exec(array_merge(
             [
                 'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' && '.
                 'podman-compose up -d',
             ],
             $apply_limit_commands
        ));

        return $this->buildTrafficRule();
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
                $apply_limit_commands[] = 'podman update --cpu-shares '.
                    (int) ($constraint['max_cpu'] * 0.75).' '.$this->instance->id;
            }

            if (array_key_exists('max_cpu', $constraint)) {
                $apply_limit_commands[] = 'podman update --cpu-shares '.
                    (int) ($constraint['max_cpu'] * 0.25).' '.$this->instance->id.'_postgres_1';
            }

            if (array_key_exists('max_memory', $constraint)) {
                $apply_limit_commands[] = 'podman update --memory '.
                    (int) ($constraint['max_memory'] * 0.75).' '.$this->instance->id;
            }

            if (array_key_exists('max_memory', $constraint)) {
                $apply_limit_commands[] = 'podman update --memory '.
                    (int) ($constraint['max_memory'] * 0.25).' '.$this->instance->id.'_postgres_1';
            }
        }

        return $apply_limit_commands;
    }
}
