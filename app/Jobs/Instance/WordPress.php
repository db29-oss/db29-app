<?php

namespace App\Jobs\Instance;

use App\Contracts\InstanceInterface;
use Exception;
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

    public function setUp(): string
    {
        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $create_instance_path = 'mkdir '.$instance_path;

        if (app('env') === 'production') {
            $create_instance_path = 'btrfs subvolume create '.$instance_path;
        }

        try {
            $this->ssh->exec($create_instance_path);
        } catch (Exception) {}

        $apply_limit_commands = $this->buildLimitCommands();

        // currently wordpress do not have sqlite in core yet
        $get_sqlite_version_url =
            'https://api.github.com/repos/WordPress/sqlite-database-integration/releases/latest';

        $this->ssh->clearOutput();

        $download_sqlite_url =
            $this->ssh
                 ->exec('curl '.$get_sqlite_version_url.' | jq -M \'.tarball_url\'')
                 ->getLastLine();

        $this->ssh
             ->exec('cd '.$instance_path.' && curl -L -o latest.zip https://wordpress.org/latest.zip')
             ->exec('cd '.$instance_path.' && unzip -o latest.zip')
             ->exec(
                 'cd '.$instance_path.'wordpress/wp-content/plugins/ && '.
                 'mkdir -p sqlite-database-integration'
             )
             ->exec(
                 'cd '.$instance_path.'wordpress/wp-content/plugins/sqlite-database-integration && '.
                 'curl -L -o sqlite-database-integration.tar.gz '.$download_sqlite_url.' && '.
                 'tar --strip-components=1 -xf sqlite-database-integration.tar.gz && '.
                 'cd '.$instance_path.'wordpress/wp-content/ && '.
                 'cp plugins/sqlite-database-integration/db.copy db.php && '.

                 'sed -i \'s#'.
                 '{SQLITE_IMPLEMENTATION_FOLDER_PATH}'.
                 '#'.
                 '/var/www/html/wp-content/plugins/sqlite-database-integration'.
                 '#\' '.
                 $instance_path.'wordpress/wp-content/db.php && '.

                 'sed -i \'s#'.
                 '{SQLITE_PLUGIN}'.
                 '#'.
                 'sqlite-database-integration/load.php'.
                 '#\' '.
                 $instance_path.'wordpress/wp-content/db.php && '.

                 'cd '.$instance_path.'wordpress/ && '.
                 'cp wp-config-sample.php wp-config.php && '.

                 'cd '.$instance_path.'wordpress/wp-content/ && '.
                 'mkdir database && '.
                 'touch database/.ht.sqlite && '.
                 'cd '.$instance_path.' && '.
                 'chown -R 82:82 wordpress && '. // on alpine www-data UID is 82
                 'find . -type d -exec chmod 755 {} \; && '.
                 'find . -type f -exec chmod 644 {} \;'
             )
             ->exec(
                 'cd '.$instance_path.' && '.
                 'podman run -d --name='.$this->instance->id.' '.
                 '-p 9000 -v '.$instance_path.'wordpress:/var/www/html/ '.
                 'php:fpm-alpine'
             )
             ->exec($apply_limit_commands);

        // traffic rule
        return $this->buildTrafficRule();
    }

    public function buildTrafficRule(): string
    {
        while (true) {
            $this->ssh->clearOutput();

            $this->ssh->exec('podman port '.$this->instance->id);

            if ($this->ssh->getLastLine() !== null) {
                break;
            }

            sleep(1);
        }

        $host_port = parse_url($this->ssh->getLastLine())['port'];

        while (true) {
            $this->ssh->clearOutput();

            try {
                $this->ssh->exec('nc -zv 0.0.0.0 '.$host_port);
            } catch (Exception) {
            }

            if (str_contains($this->ssh->getLastLine(), 'succeeded')) {
                break;
            }

            sleep(1);
        }

        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $domain = config('app.domain');

        if ($this->instance->subdomain) {
            $domain = $this->instance->subdomain.'.'.config('app.domain');
        }

        $root_dir = $instance_path.'wordpress/';

        $tr_config = <<<CONFIG
{$domain} {
	root * {$root_dir}

	encode gzip
	file_server

    php_fastcgi 127.0.0.1:{$host_port} {
        root /var/www/html/
    }

	@disallowed {
		path /xmlrpc.php
		path *.sqlite
		path /wp-content/uploads/*.php
	}

	rewrite @disallowed '/index.php'
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
                     'podman rm '.$this->instance->id,
                 ],
                 [
                     $rm_instance_dir
                 ]
             ));
    }

    public function turnOff()
    {
        $this->ssh->exec(
            'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' && '.
            'podman stop '.$this->instance->id
        );
    }

    public function turnOn(): string
    {
        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $apply_limit_commands = $this->buildLimitCommands();

        $this->ssh->exec(array_merge(
             [
                 'cd '.$this->machine->storage_path.'instance/'.$this->instance->id.' && '.
                 'podman start '.$this->instance->id,
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
