<?php

namespace App\Jobs\Instance;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * THIS IS BASE JOB SO THAT
 * WE COULD BUILD HELPER FOUNDATION
 */
class _0Instance_ implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected $docker_compose = null,
        protected $instance = null,
        protected $machine = null,
        protected $plan = null,
        protected $reg_info = null,
        protected $ssh = null,
    ) {}

    public function setUp(): string {}
    public function buildTrafficRule(): string {}
    public function tearDown() {}
    public function turnOff() {}
    public function turnOn(): string {}
    public function backUp() {}
    public function restore() {}
    public function downgrade() {}
    public function changeUrl() {}
    public function upgrade() {}
    public function buildLimitCommands(): array {}

    public function createInstancePath()
    {
        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $create_instance_path_command = 'mkdir '.$instance_path;

        $detect_filesystem_command = "df -T ".$instance_path." | awk 'NR==2 {print $2}'";

        $this->ssh->clearOutput();

        $this->ssh->exec($detect_filesystem_command);

        if (trim($this->ssh->getLastLine()) === 'btrfs') {
            $create_instance_path_command = 'btrfs subvolume create '.$instance_path;
        }

        try {
            $this->ssh->exec($create_instance_path_command);
        } catch (Exception) {}
    }

    public function deleteInstancePath()
    {
        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $extra = json_decode($this->instance->extra, true);

        if (array_key_exists('instance_path', $extra)) {
            $instance_path = $extra['instance_path'];
        }

        $delete_instance_path_command = 'rm -rf '.$instance_path;

        if ($this->getFilesystemName() === 'btrfs') {
            $delete_instance_path_command = 'btrfs subvolume delete '.$instance_path;
        }

        try {
            $this->ssh->exec($delete_instance_path_command);
        } catch (Exception) {}
    }

    public function getFilesystemName(): string
    {
        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $detect_filesystem_command = "df -T ".$instance_path." | awk 'NR==2 {print $2}'";

        $this->ssh->clearOutput();

        $this->ssh->exec($detect_filesystem_command);

        return trim($this->ssh->getLastLine());
    }
}
