<?php

namespace App\Jobs\Instance;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class Planka implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private array $doc
    ) {
        $this->docker_compose = $doc['docker_compose'];
        $this->instance = $doc['instance'];
        $this->latest_version_template = $doc['latest_version_template'];
        $this->machine = $doc['machine'];
        $this->reg_info = $doc['reg_info'];
        $this->ssh = $doc['ssh'];
    }

    public static function initialResourceConsumption()
    {
        return [
            'memory' => 115 * 1000 * 1000, // bytes
            'disk' => 15 * 1000 * 1000, // bytes
            'cpu' => 300 // score from rating cpubenchmark.net
        ];
    }

    public function handle(): int
    {
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
                     'podman port '.$this->instance->id.'_planka_1'
                 ]
             ));

        $output = $this->ssh->getOutput();

        $port_mapping = $output[array_key_last($output)];

        $port_explode = explode(':', $port_mapping);

        $host_port = trim(end($port_explode));

        $this->instance->status = 'ct_up';
        $this->instance->version_template =
            [
                'docker_compose' => $this->docker_compose,
                'port' => $host_port,
                'tag' => $this->latest_version_template,
            ];

        $this->instance->save();

        return $host_port;
    }
}
