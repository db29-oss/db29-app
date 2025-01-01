<?php

namespace App\Jobs\Instance;

use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class Discourse implements ShouldQueue
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

        $this->ssh
             ->exec(
                 'cd '.$instance_path.' && '.
                 '[ ! -d "discourse_docker" ] && '.
                 'git clone https://github.com/discourse/discourse_docker.git || '.
                 'true'
             );

        $this->ssh->clearOutput();

        $this->ssh->exec('cat '.$instance_path.'discourse_docker/samples/standalone.yml');

        $yml_str = implode(PHP_EOL, $this->ssh->getOutput());

        $yml = app('yml')->parse($yml_str);

        unset($yml['expose']);

        $yml['templates'][] = 'templates/web.socketed.template.yml';

        $yml['env']['DISCOURSE_HOSTNAME'] =
            ($this->instance->subdomain ? $this->instance->subdomain.'.' : '').
            config('app.domain');

        $yml['env']['DISCOURSE_DEVELOPER_EMAILS'] = $this->reg_info['email'];

        $yml['volumes'][0]['volume']['host'] = $instance_path.'discourse_docker/shared/standalone';
        $yml['volumes'][1]['volume']['host'] = $instance_path.'discourse_docker/shared/standalone/log/var-log';

        $this->ssh->exec('mkdir -p '.$yml['volumes'][1]['volume']['host']);

        $yml['env']['DISCOURSE_SMTP_ADDRESS'] = fake()->domainName();
        $yml['env']['DISCOURSE_SMTP_USER_NAME'] = fake()->email();
        $yml['env']['DISCOURSE_SMTP_PASSWORD'] = fake()->password();

        if (app('env') === 'production') {
            $client = new SesV2Client([
                'region' => config('services.ses.region'),
                'version' => 'latest',
                'credentials' => [
                    'key'    => config('services.ses.key'),
                    'secret' => config('services.ses.secret'),
                ],
            ]);

            try {
                $client->createEmailIdentity([
                    'EmailIdentity' => $this->reg_info['email'],
                ]);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== 'AlreadyExistsException') {
                    logger()->error('DB292009: fail create email identity', [
                        'aws_error_code' => $e->getAwsErrorCode()
                    ]);

                    throw new Exception('DB292010: fail create email identity');
                }
            }
        }

        $yml_dump = app('yml')->dump($yml, 4);

        $yml_lines = explode(PHP_EOL, $yml_dump);

        $this->ssh
             ->exec(
                 'cd '.$instance_path.'discourse_docker/containers && '.
                 'rm -f '.$this->instance->id.'.yml && touch '.$this->instance->id.'.yml'
             );

        foreach ($yml_lines as $yml_line) {
            $this->ssh
                 ->exec(
                     'echo '.escapeshellarg($yml_line).' '.
                     '>> '.
                     $instance_path.'discourse_docker/containers/'.$this->instance->id.'.yml'
                 );
        }

        $this->ssh
             ->exec(
                 'cd '.$instance_path.' && '.
                 'rm -rf docker && '.
                 'touch docker && chmod +x docker && '.
                 'echo '.escapeshellarg('#!/bin/bash').' >> docker && '.
                 'echo '.escapeshellarg('/usr/bin/podman "$@"').' >> docker'
             );

        $this->ssh->clearOutput();

        $this->ssh
             ->exec(
                 'cd '.$instance_path.'discourse_docker && '.
                 'export DOCKER_HOST=127.0.0.1 && '.
                 'export PATH='.$instance_path.':$PATH && '.
                 './launcher rebuild '.$this->instance->id.' --skip-prereqs --skip-mac-address'
             );

        return $this->buildTrafficRule();
    }

    public function buildTrafficRule(): string
    {
        $domain = config('app.domain');

        if ($this->instance->subdomain) {
            $domain = $this->instance->subdomain.'.'.config('app.domain');
        }

        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $tr_config = <<<CONFIG
{$domain} {
    reverse_proxy unix/{$instance_path}discourse_docker/shared/standalone/nginx.http.sock
}
CONFIG;

        return $tr_config;
    }

    public function tearDown()
    {
        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $rm_instance_dir = 'rm -rf '.$instance_path;

        if (app('env') === 'production') {
            $rm_instance_dir = 'btrfs subvolume delete '.$instance_path;
        }

        $this->ssh
             ->exec(array_merge(
                 [
                     'cd '.$instance_path.'discourse_docker && '.
                     'export DOCKER_HOST=127.0.0.1 && '.
                     'export PATH='.$instance_path.':$PATH && '.
                     './launcher destroy '.$this->instance->id.' --skip-prereqs --skip-mac-address'
                 ],
                 [
                     $rm_instance_dir
                 ]
             ));

        if (app('env') === 'production') {
            $client = new SesV2Client([
                'region' => config('services.ses.region'),
                'version' => 'latest',
                'credentials' => [
                    'key'    => config('services.ses.key'),
                    'secret' => config('services.ses.secret'),
                ],
            ]);

            try {
                $client->deleteEmailIdentity([
                    'EmailIdentity' => json_decode($this->instance->extra, true)['reg_info']['email'],
                ]);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== 'NotFoundException') {
                    logger()->error('DB292011: fail delete email identity', [
                        'aws_error_code' => $e->getAwsErrorCode()
                    ]);

                    throw new Exception('DB292011: fail delete email identity');
                }
            }
        }
    }

    public function turnOff()
    {
        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $this->ssh->exec(
            'cd '.$instance_path.'discourse_docker && '.
            'export DOCKER_HOST=127.0.0.1 && '.
            'export PATH='.$instance_path.':$PATH && '.
            './launcher stop '.$this->instance->id.' --skip-prereqs --skip-mac-address'
        );
    }

    public function turnOn(): string
    {
        $instance_path = $this->machine->storage_path.'instance/'.$this->instance->id.'/';

        $apply_limit_commands = $this->buildLimitCommands();

        $this->ssh->exec(array_merge(
             [
                 'cd '.$instance_path.'discourse_docker && '.
                 'export DOCKER_HOST=127.0.0.1 && '.
                 'export PATH='.$instance_path.':$PATH && '.
                 './launcher start '.$this->instance->id.' --skip-prereqs --skip-mac-address'
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
        }

        return [];
    }
}
