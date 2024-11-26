<?php

namespace App\Jobs;

use App\Models\Instance;
use App\Models\Machine;
use App\Models\Source;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InitInstance implements ShouldQueue
{
    use Queueable;

    private readonly int $instance_id;

    private readonly int $source_id;

    private readonly array $reg_info;

    /**
     * Create a new job instance.
     */
    public function __construct(int $instance_id, int $source_id, array $reg_info = [])
    {
        $this->instance_id = $instance_id;

        $this->source_id = $source_id;

        $this->reg_info = $reg_info;
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
        $machine = Machine::whereNull('user_id')->inRandomOrder()->first(); // TODO

        while (true) {
            $subdomain = str(str()->random(8))->lower()->toString();

            $now = now();
            $sql_params = [];
            $sql =
                'update instances set '.
                'subdomain = ? '. # $subdomain
                'where id = ? '. # $this->instance_id
                'returning *';

            $sql_params[] = $subdomain;
            $sql_params[] = $this->instance_id;

            $db = app('db')->select($sql, $sql_params);

            if ($db[0]->subdomain === $subdomain) {
                break;
            }
        }

        $ssh = app('ssh');
        $ssh
            ->to([
                'ssh_address' => $machine->ip_address,
                'ssh_port' => $machine->ssh_port,
            ])
            ->exec('mkdir -p '.$machine->storage_path.'instance/'.$this->instance_id);

        $instance = Instance::whereId($this->instance_id)->first();

        $version_templates = json_decode($instance->version_templates, true);

        $latest_version_template = $this->getLatest($version_templates);

        // TODO

        $this->{$db[0]->name}($this->reg_info);
    }

    private function getLatest(array $version_templates)
    {
        $latest_version_template = null;

        foreach ($version_templates as $version_template) {
            if ($latest_version_template === null) {
                $latest_version_template = $version_template['tag'];
                continue;
            }

            if ($version_template['tag'] > $latest_version_template) {
                $latest_version_template = $version_template;
            }
        }
    }
}
