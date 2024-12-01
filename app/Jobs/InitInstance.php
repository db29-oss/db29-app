<?php

namespace App\Jobs;

use App\Models\Instance;
use App\Models\Machine;
use App\Models\Source;
use App\Services\SSHEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class InitInstance implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

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
        private readonly string $instance_id,
        private readonly array $reg_info = []
    ) {
        $this->ssh = app('ssh');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $instance = Instance::whereId($this->instance_id)->with('source')->first();

        $this->source = $instance->source;

        $this->version_templates = json_decode($this->source->version_templates, true);

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($this->source->name);

        $resources = $job_class::initialResourceConsumption();

        $this->machine = Machine::whereNull('user_id')->inRandomOrder()->first(); // TODO

        // init
        $instance->status = 'init';
        $instance->machine_id = $this->machine->id;
        $instance->save();

        // dns
        $subdomain = $instance->subdomain;

        if ($subdomain === null) {
            $subdomain = str(str()->random(8))->lower()->toString();
        }

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
            'status = ?, '. # 'dns'
            'updated_at = ? '. # $now
            'where id = ? '. # $this->instance_id
            'returning *';

        $sql_params[] = $subdomain;
        $sql_params[] = $dns_id;
        $sql_params[] = 'dns_up';
        $sql_params[] = $now;
        $sql_params[] = $this->instance_id;

        $db = app('db')->select($sql, $sql_params);

        $this->ssh
             ->to([
                 'ssh_address' => $this->machine->ip_address,
                 'ssh_port' => $this->machine->ssh_port,
             ])->compute();

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


        $host_port = (new $job_class([
            'docker_compose' => $this->docker_compose,
            'instance' => $instance,
            'latest_version_template' => $this->latest_version_template,
            'machine' => $this->machine,
            'reg_info' => $this->reg_info,
            'ssh' => $this->ssh,
        ]))->handle();

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

        $instance->status = 'rt_up'; // router up
        $instance->save();
    }
}
