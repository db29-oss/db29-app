<?php

namespace App\Jobs\Instance;

use Aws\Exception\AwsException;
use Exception;

class Discourse extends _0Instance_
{
    public function setUp(): string
    {
        $instance_path = $this->getPath();

        $this->createInstancePath();

        $apply_limit_commands = $this->buildLimitCommands();

        $system_email_domain = explode('@', $this->reg_info['system_email'])[1];

        $this->ssh
             ->exec(
                 'cd '.$instance_path.' && '.
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
        $yml['env']['DISCOURSE_FORCE_HTTPS'] = true;

        $yml['volumes'][0]['volume']['host'] = $instance_path.'discourse_docker/shared/standalone';
        $yml['volumes'][1]['volume']['host'] = $instance_path.'discourse_docker/shared/standalone/log/var-log';

        $this->ssh->exec('mkdir -p '.$yml['volumes'][1]['volume']['host']);

        $yml['env']['DISCOURSE_SMTP_ADDRESS'] = $this->instance->id.'-postfix';
        $yml['env']['DISCOURSE_SMTP_PORT'] = 25;

        $yml['run'][] =[
            'exec' =>
            'rails runner "SiteSetting.notification_email = '.
            escapeshellarg($this->reg_info['system_email']).
            '"'
        ];

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
                     'echo '.escapeshellarg($yml_line).' | '.
                     'sudo tee -a '.$instance_path.'discourse_docker/containers/'.$this->instance->id.'.yml'
                 );
        }

        $this->ssh
             ->exec(
                 'cd '.$instance_path.' && '.
                 'rm -rf docker && '.
                 'touch docker && chmod +x docker && '.
                 'echo '.escapeshellarg('#!/bin/bash').' | sudo tee -a docker && '.
                 'echo '.escapeshellarg('/usr/bin/podman "$@"').' | sudo tee -a docker'
             );

        $this->ssh->clearOutput();

        $this->ssh->exec('podman network create '.$this->instance->id);

        // postfix
        $this->ssh->exec('mkdir '.$instance_path.'dkim_privatekeys');

        foreach (explode("\n", $this->reg_info['dkim_privatekey']) as $line) {
            if ($line === '') {
                continue;
            }

            $this->ssh->exec(
                'echo '.escapeshellarg($line).' | '.
                'sudo tee -a '.$instance_path.'dkim_privatekeys/'.$this->instance->id);
        }

        $this->ssh
             ->exec(
                 'podman run -d --name '.$this->instance->id.'-postfix --network '.$this->instance->id.' '.
                 '-e "ALLOWED_SENDER_DOMAINS='.$system_email_domain.'" '.
                 '-e DKIM_SELECTOR='.$this->reg_info['dkim_selector'].' '.
                 '-v '.$instance_path.'dkim_privatekeys:/etc/opendkim/keys '.
                 'boky/postfix'
             );

        // discourse
        $this->ssh
             ->exec(
                 'cd '.$instance_path.'discourse_docker && '.
                 'export DOCKER_HOST=127.0.0.1 && '.
                 'export PATH='.$instance_path.':$PATH && '.
                 './launcher rebuild '.$this->instance->id.' '.
                 '--skip-prereqs --skip-mac-address --docker-args \'--network '.$this->instance->id.'\''
             );

        return $this->buildTrafficRule();
    }

    public function buildTrafficRule(): string
    {
        $domain = config('app.domain');

        if ($this->instance->subdomain) {
            $domain = $this->instance->subdomain.'.'.config('app.domain');
        }

        $instance_path = $this->getPath();

        $tr_config = <<<CONFIG
{$domain} {
    reverse_proxy unix/{$instance_path}discourse_docker/shared/standalone/nginx.http.sock
}
CONFIG;

        return $tr_config;
    }

    public function tearDown()
    {
        $instance_path = $this->getPath();

        $could_destroy = true;

        try {
            $this->ssh->exec('test -d '.$instance_path.'discourse_docker');
        } catch (Exception) {
            $could_destroy = false;
        }

        if ($could_destroy) {
            $this->ssh->exec(
                'cd '.$instance_path.'discourse_docker && '.
                'export DOCKER_HOST=127.0.0.1 && '.
                'export PATH='.$instance_path.':$PATH && '.
                './launcher destroy '.$this->instance->id.' --skip-prereqs --skip-mac-address'
            );
        }

        $could_delete = true;

        try {
            $this->ssh->exec('test -d '.$instance_path);
        } catch (Exception) {
            $could_delete = false;
        }

        if ($could_delete) {
            $this->deleteInstancePath();
        }

        $this->ssh->exec(
            'podman network ls -q | grep -w '.$this->instance->id.' | xargs -r podman network rm'
        );
    }

    public function turnOff()
    {
        $instance_path = $this->getPath();

        $this->ssh->exec(
            'cd '.$instance_path.'discourse_docker && '.
            'export DOCKER_HOST=127.0.0.1 && '.
            'export PATH='.$instance_path.':$PATH && '.
            './launcher stop '.$this->instance->id.' --skip-prereqs --skip-mac-address'
        );

        $this->ssh->exec('podman ps -q -f name='.$this->instance->id.'-postfix | xargs -r podman stop');
    }

    public function turnOn(): string
    {
        $instance_path = $this->getPath();

        $apply_limit_commands = $this->buildLimitCommands();

        $this->ssh->exec('podman start '.$this->instance->id.'-postfix');

        $this->ssh->exec([
            'cd '.$instance_path.'discourse_docker && '.
             'export DOCKER_HOST=127.0.0.1 && '.
             'export PATH='.$instance_path.':$PATH && '.
             './launcher start '.$this->instance->id.' --skip-prereqs --skip-mac-address'
        ]);

        try {
            $this->ssh->exec($apply_limit_commands);
        } catch (Exception) {}

        return $this->buildTrafficRule();
    }

    public function changeUrl(): string
    {
        $instance_path = $this->getPath();

        $this->ssh->clearOutput();

        $this->ssh->exec('cat '.$instance_path.'discourse_docker/containers/'.$this->instance->id.'.yml');

        $yml_str = implode(PHP_EOL, $this->ssh->getOutput());

        $yml = app('yml')->parse($yml_str);

        $yml['env']['DISCOURSE_HOSTNAME'] =
            ($this->instance->subdomain ? $this->instance->subdomain.'.' : '').
            config('app.domain');

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
                     'echo '.escapeshellarg($yml_line).' | '.
                     'sudo tee -a '.$instance_path.'discourse_docker/containers/'.$this->instance->id.'.yml'
                 );
        }

        $this->ssh
             ->exec(
                 'cd '.$instance_path.'discourse_docker && '.
                 'export DOCKER_HOST=127.0.0.1 && '.
                 'export PATH='.$instance_path.':$PATH && '.
                 './launcher rebuild '.$this->instance->id.' '.
                 '--skip-prereqs --skip-mac-address --docker-args \'--network '.$this->instance->id.'\''
             );

        return $this->buildTrafficRule();
    }

    public function buildLimitCommands(): array
    {
        $apply_limit_commands = [];

        if ($this->getFilesystemName() === 'btrfs') {
            $instance_path = $this->getPath();

            $constraint = json_decode($this->plan->constraint, true);

            if (array_key_exists('max_disk', $constraint)) {
                $apply_limit_commands[] =
                    'btrfs qgroup limit '.$constraint['max_disk'].' '.$instance_path;
            }
        }

        return $apply_limit_commands;
    }
}
