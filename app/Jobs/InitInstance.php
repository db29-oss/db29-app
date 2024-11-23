<?php

namespace App\Jobs;

use App\Models\Machine;
use App\Models\Source;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InitInstance implements ShouldQueue
{
    use Queueable;

    private readonly Source $source;

    private readonly array $reg_info;

    /**
     * Create a new job instance.
     */
    public function __construct(Source $source, array $reg_info = [])
    {
        $this->source = $source;
        $this->reg_info = $reg_info;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // determine resource needed for the source

        // get machine have enough resource

        // start pushing

        $this->{$source->name}($reg_info);
    }

    protected function planka(array $reg_info)
    {
        // memory: 90MB for planka + 25MB for postgres
        // disk: 10MB planka + 1MB for postgres
        // cpu: 1% for both planka + postgres

        // TODO
    }
}
