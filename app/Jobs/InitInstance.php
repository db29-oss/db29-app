<?php

namespace App\Jobs;

use App\Models\Instance;
use App\Models\Machine;
use App\Models\Source;
use App\Services\SSHEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InitInstance implements ShouldQueue
{
    use Queueable;

    private Machine $machine;

    private SSHEngine $ssh;

    private Source $source;

    private array $docker_compose;

    private readonly array $version_templates;

    private readonly string $latest_version_template;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $instance_id,
        private readonly int $source_id,
        private readonly array $reg_info = []
    ) {
        $this->ssh = app('ssh');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // memory: 90MB for planka + 25MB for postgres
        // disk: 10MB planka + 1MB for postgres
        // cpu: 1% for both planka + postgres

        // determine resource needed for the source

        // get machine have enough resource
        $this->machine = Machine::whereNull('user_id')->inRandomOrder()->first(); // TODO

        Instance::whereId($this->instance_id)
            ->update([
                'status' => 'init',
                'machine_id' => $this->machine->id,
            ]);

        // dns
        $subdomain = str(str()->random(8))->lower()->toString();

        $dns_id = str(str()->random(32))->lower(); // for testing

        if (app('env') === 'production') {
            $dns_id = app('cf')
                ->addDnsRecord(
                    $subdomain,
                    $this->machine->ip_address,
                    ['comment' => $this->instance_id]
                );
        }

        $now = now();
        $sql_params = [];
        $sql =
            'update instances set '.
            'subdomain = ?, '. # $subdomain
            'dns_id = ?, '. # $dns_id
            'status = ? '. # 'dns'
            'where id = ? '. # $this->instance_id
            'returning *';

        $sql_params[] = $subdomain;
        $sql_params[] = $dns_id;
        $sql_params[] = 'dns_up';
        $sql_params[] = $this->instance_id;

        $db = app('db')->select($sql, $sql_params);

        $this->ssh
             ->to([
                 'ssh_address' => $this->machine->ip_address,
                 'ssh_port' => $this->machine->ssh_port,
             ])->compute();

        $this->source = Source::whereId($this->source_id)->first();

        $this->version_templates = json_decode($this->source->version_templates, true);

        // get deploy information
        $latest_version_template = null;
        $docker_compose = null;

        foreach ($this->version_templates as $vt_idx => $version_template) {
            if ($latest_version_template === null) {
                $latest_version_template = $version_template['tag'];

                if (array_key_exists('docker_compose', $this->version_templates[$vt_idx])) {
                    $docker_compose = $this->version_templates[$vt_idx]['docker_compose'];
                }

                continue;
            }

            if ($version_template['tag'] > $latest_version_template) {
                $latest_version_template = $version_template;
                $docker_compose = $this->version_templates[$vt_idx]['docker_compose'];
            }
        }

        $this->latest_version_template = $latest_version_template;
        $this->docker_compose = $docker_compose;

        $host_port = $this->{'setup_'.$this->source->name}($this->reg_info);

        // router
        $rule =
            [
                'match' => [
                    [
                        'host' => [$subdomain.'.'.config('app.domain')]
                    ]
                ],
                'handle' => [
                    [
                        'handler' => 'reverse_proxy',
                        'upstreams' => [
                            [
                                'dial' => '127.0.0.1:'.$host_port
                            ]
                        ]
                    ]
                ]
            ];

        app('rt', [$this->ssh])->addRule($rule);

        Instance::whereId($this->instance_id)
            ->update([
                'status' => 'rt_up', // router up
            ]);
    }

    private function setup_planka(): int
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
                $this->machine->storage_path.'instance/'.$this->instance_id.'/docker-compose.yml';
        }

        $this->ssh
             ->exec(array_merge(
                 [
                     'mkdir -p '.$this->machine->storage_path.'instance/'.$this->instance_id,
                     'rm -rf '.
                     $this->machine->storage_path.'instance/'.$this->instance_id.'/docker-compose.yml',
                     'mkdir -p '.$this->machine->storage_path.'instance/'.$this->instance_id
                 ],
                 $commands,
                 [
                     'cd '.$this->machine->storage_path.'instance/'.$this->instance_id.' \\&\\& '.
                     'podman-compose up -d',
                     'podman port '.$this->instance_id.'_planka_1'
                 ]
             ));

        $output = $this->ssh->getOutput();

        $port_mapping = $output[array_key_last($output)];

        $port_explode = explode(':', $port_mapping);

        $host_port = trim(end($port_explode));

        Instance::whereId($this->instance_id)
            ->update([
                'status' => 'ct_up', // container up
                'version_template' => [
                    'docker_compose' => $this->docker_compose,
                    'port' => $host_port,
                    'tag' => $this->latest_version_template,
                ]
            ]);

        return $host_port;
    }
}
