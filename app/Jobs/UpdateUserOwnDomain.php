<?php

namespace App\Jobs;

use App\Models\Instance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateUserOwnDomain implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $instance_id
    ) {}

    public function handle(): void
    {
        $instance = Instance::query()
            ->whereId($this->instance_id)
            ->with([
                'machine',
                'source',
            ])
            ->first();

        $source = $instance->source;

        $machine = $instance->machine;

        $ssh = app('ssh')->toMachine($machine)->compute();

        $job_class = "\\App\\Jobs\\Instance\\".str()->studly($instance->source->name);

        (new $job_class(
            instance: $instance,
            machine: $machine,
            ssh: $ssh,
        ))->changeDomain();
    }
}
