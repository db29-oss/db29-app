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
        private $subdomain = null,
    ) {}

    public function setUp(): array
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
             ->exec($apply_limit_commands)
             ->exec('cd '.$instance_path.'wordpress && cp wp-config-sample.php wp-config.php')
             ->exec(
                 'cd '.$instance_path.'wordpress/wp-content/plugins/ && '.
                 'mkdir sqlite-database-integration'
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

                 'cd '.$instance_path.'wordpress/wp-content/ && '.
                 'mkdir database && '.
                 'touch database/.ht.sqlite && '.
                 'chmod 640 database/.ht.sqlite'
             )
             ->exec(
                 'cd '.$instance_path.' && '.
                 'podman run -d --name='.$this->instance->id.' '.
                 '-p 9000 -v '.$instance_path.'wordpress:/var/www/html/ '.
                 'php:fpm-alpine'
             );

        // traffic rule
        $this->ssh->clearOutput();

        while (true) {
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

        $rule =
            [
                'match' => [
                    [
                        'host' => [$this->subdomain.'.'.config('app.domain')]
                    ]
                ],
                'handle' => [
                    [
                        'handler' => 'subroute',
                        'routes' => [
                            [
                                'match' => [
                                    ['path' => ['*.php']]
                                ],
                                'handle' => [
                                    [
                                        'handler' => 'reverse_proxy',
                                        'upstreams' => [['dial' => '127.0.0.1:'.$host_port]],
                                        'transport' => [
                                            'protocol' => 'fastcgi',
                                            'root' => '/var/www/html/',
                                            'split_path' => ['.php']
                                        ]
                                    ]
                                ],
                                'handle' => [
                                    [
                                        'handler' => 'file_server',
                                        'root' => $instance_path.'wordpress'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

        return $rule;
    }

    public function tearDown()
    {
        $rm_instance_dir = 'cd '.$this->machine->storage_path.'instance/ && rm -rf '.$this->instance->id;

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

    public function turnOn(): array
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

        while (true) {
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

        $rule =
            [
                'match' => [
                    [
                        'host' => [$this->instance->subdomain.'.'.config('app.domain')]
                    ]
                ],
                'handle' => [
                    [
                        'handler' => 'subroute',
                        'routes' => [
                            [
                                'match' => [
                                    ['path' => ['*.php']]
                                ],
                                'handle' => [
                                    [
                                        'handler' => 'reverse_proxy',
                                        'upstreams' => [['dial' => '127.0.0.1:'.$host_port]],
                                        'transport' => [
                                            'protocol' => 'fastcgi',
                                            'root' => '/var/www/html/',
                                            'split_path' => ['.php']
                                        ]
                                    ]
                                ],
                                'handle' => [
                                    [
                                        'handler' => 'file_server',
                                        'root' => $instance_path.'wordpress'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

        return $rule;
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
