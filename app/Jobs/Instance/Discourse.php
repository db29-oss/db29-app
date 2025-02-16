<?php

namespace App\Jobs\Instance;

use App\Services\AWS_SES;
use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Exception;

class Discourse extends _0Instance_
{
    public function setUp(): string
    {
        $instance_path = $this->getPath();

        $this->createInstancePath();

        $apply_limit_commands = $this->buildLimitCommands();

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

        // no need to check extra reg_info domain (we don't allow user create with custom domain yet)
        $yml['env']['DISCOURSE_HOSTNAME'] = $this->instance->subdomain.'.'.config('app.domain');

        $yml['env']['DISCOURSE_DEVELOPER_EMAILS'] = $this->reg_info['email'];
        $yml['env']['DISCOURSE_FORCE_HTTPS'] = true;

        $yml['volumes'][0]['volume']['host'] = $instance_path.'discourse_docker/shared/standalone';
        $yml['volumes'][1]['volume']['host'] = $instance_path.'discourse_docker/shared/standalone/log/var-log';

        $this->ssh->exec('mkdir -p '.$yml['volumes'][1]['volume']['host']);

        $yml['env']['DISCOURSE_SMTP_ADDRESS'] = fake()->domainName();
        $yml['env']['DISCOURSE_SMTP_PORT'] = 587; // some ISP will block port 25
        $yml['env']['DISCOURSE_SMTP_USER_NAME'] = fake()->email();
        $yml['env']['DISCOURSE_SMTP_PASSWORD'] = fake()->password();

        if (app('env') === 'production') {
            $client = new SesV2Client([
                'region' => config('services.aws.region'),
                'version' => 'latest',
                'credentials' => [
                    'key'    => config('services.aws.key'),
                    'secret' => config('services.aws.secret'),
                ],
            ]);

            if (array_key_exists('system_email', $this->reg_info)) {
                $dspk = explode(PHP_EOL, $this->reg_info['dkim_privatekey']);
                array_pop($dspk);
                array_pop($dspk);
                array_shift($dspk);
                $dspk = implode("", $dspk);

                $system_email_domain = explode('@', $this->reg_info['system_email'])[1];

                try {
                    $client->createEmailIdentity([
                        'EmailIdentity' => $system_email_domain,
                        'DkimSigningAttributes' => [
                            'DomainSigningPrivateKey' => $dspk,
                            'DomainSigningSelector' => $this->reg_info['dkim_selector'],
                        ],
                    ]);
                } catch (AwsException $e) {
                    if ($e->getAwsErrorCode() !== 'AlreadyExistsException') {
                        logger()->error('DB292018: fail create email identity', [
                            'aws_error_code' => $e->getAwsErrorCode()
                        ]);

                        throw new Exception('DB292019: fail create email identity');
                    }
                }

                try {
                    $client->putEmailIdentityMailFromAttributes([
                        'EmailIdentity' => $system_email_domain,
                        'BehaviorOnMxFailure' => 'REJECT_MESSAGE',
                        'MailFromDomain' => $this->reg_info['dkim_selector'].'.'.$system_email_domain,
                    ]);
                } catch (AwsException $e) {
                    logger()->error('DB292020: fail put mail from attribute', [
                        'aws_error_code' => $e->getAwsErrorCode()
                    ]);

                    throw new Exception('DB292021: fail put mail from attribute');
                }
            } else {
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

            $yml['env']['DISCOURSE_SMTP_ADDRESS'] = 'email-smtp.'.config('services.ses.smtp').'.amazonaws.com';
            $yml['env']['DISCOURSE_SMTP_USER_NAME'] = config('services.ses.username');
            $yml['env']['DISCOURSE_SMTP_PASSWORD'] = config('services.ses.password');

            if (array_key_exists('machine_id', $this->reg_info)) {
                $yml['env']['DISCOURSE_SMTP_ADDRESS'] =
                    'email-smtp.'.$this->reg_info['aws_ses_region'].'.amazonaws.com';
                $yml['env']['DISCOURSE_SMTP_USER_NAME'] = $this->reg_info['aws_key'];
                $yml['env']['DISCOURSE_SMTP_PASSWORD'] = AWS_SES::generateSmtpPassword(
                    $this->reg_info['aws_secret'],
                    $this->reg_info['aws_ses_region']
                );
            }
        }

        $yml['run'][] =[
            'exec' =>
            'rails runner "SiteSetting.notification_email = '.
            escapeshellarg($this->reg_info['system_email'] ?? $this->reg_info['email']).
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
                     'tee -a '.$instance_path.'discourse_docker/containers/'.$this->instance->id.'.yml'
                 );
        }

        $this->ssh
             ->exec(
                 'cd '.$instance_path.' && '.
                 'rm -rf docker && '.
                 'touch docker && chmod +x docker && '.
                 'echo '.escapeshellarg('#!/bin/bash').' | tee -a docker && '.
                 'echo '.escapeshellarg('/usr/bin/podman "$@"').' | tee -a docker'
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
        $extra = json_decode($this->instance->extra, true);

        $default_domain = $this->instance->subdomain.'.'.config('app.domain');

        $custom_domain = '';

        if (array_key_exists('domain', $extra['reg_info'])) {
            $custom_domain = $extra['reg_info']['domain'];
        }

        $effect_domain = $custom_domain ? $custom_domain : $default_domain;

        $instance_path = $this->getPath();

        $tr_config = '';

        if ($custom_domain) {
            $tr_config .= <<<CONFIG
{$default_domain} {
    redir https://{$custom_domain}{uri} 301
}

CONFIG;
        }

        $tr_config .= <<<CONFIG
{$effect_domain} {
    reverse_proxy unix/{$instance_path}discourse_docker/shared/standalone/nginx.http.sock
}
CONFIG;

        return $tr_config;
    }

    public function tearDown()
    {
        $instance_path = $this->getPath();

        $this->ssh->exec(
            'cd '.$instance_path.'discourse_docker && '.
            'export DOCKER_HOST=127.0.0.1 && '.
            'export PATH='.$instance_path.':$PATH && '.
            './launcher destroy '.$this->instance->id.' --skip-prereqs --skip-mac-address'
        );

        $this->deleteInstancePath();

        if (app('env') === 'production') {
            $client = new SesV2Client([
                'region' => config('services.aws.region'),
                'version' => 'latest',
                'credentials' => [
                    'key'    => config('services.aws.key'),
                    'secret' => config('services.aws.secret'),
                ],
            ]);

            $extra = json_decode($this->instance->extra, true);

            try {
                $client->deleteEmailIdentity([
                    'EmailIdentity' => ($extra['reg_info']['system_email'] ?? $extra['reg_info']['email'])
                ]);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== 'NotFoundException') {
                    logger()->error('DB292011: fail delete email identity', [
                        'aws_error_code' => $e->getAwsErrorCode()
                    ]);

                    throw new Exception('DB292012: fail delete email identity');
                }
            }
        }
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
    }

    public function turnOn(): string
    {
        $instance_path = $this->getPath();

        $apply_limit_commands = $this->buildLimitCommands();

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

    public function changeDomain(): string
    {
        $instance_path = $this->getPath();

        $this->ssh->clearOutput();

        $this->ssh->exec('cat '.$instance_path.'discourse_docker/containers/'.$this->instance->id.'.yml');

        $yml_str = implode(PHP_EOL, $this->ssh->getOutput());

        $yml = app('yml')->parse($yml_str);

        $yml['env']['DISCOURSE_HOSTNAME'] = $this->instance->subdomain.'.'.config('app.domain');

        $extra = json_decode($this->instance->extra, true);

        if (array_key_exists('domain', $extra['reg_info'])) {
            $yml['env']['DISCOURSE_HOSTNAME'] = $extra['reg_info']['domain'];
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
                     'echo '.escapeshellarg($yml_line).' | '.
                     'tee -a '.$instance_path.'discourse_docker/containers/'.$this->instance->id.'.yml'
                 );
        }

        $this->ssh
             ->exec(
                 'cd '.$instance_path.'discourse_docker && '.
                 'export DOCKER_HOST=127.0.0.1 && '.
                 'export PATH='.$instance_path.':$PATH && '.
                 './launcher rebuild '.$this->instance->id.' --skip-prereqs --skip-mac-address'
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
