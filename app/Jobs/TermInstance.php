<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TermInstance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $instance_id
    ) {}

    public function handle(): void
    {
    }
}
