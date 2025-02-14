<?php

namespace App\Jobs\Instance;

use Exception;

class WordPress extends _0Instance_
{
    public function setUp(): string
    {
        $instance_path = $this->getPath();

        $this->createInstancePath();

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
                 'sed -i '.
                 '"/\/\* That\'s all, stop editing! Happy publishing. \*\//i define(\'DISABLE_WP_CRON\', true);" '.
                 'wp-config.php && '.

                 'cd '.$instance_path.'wordpress/wp-content/ && '.
                 'mkdir -p database && '.
                 'touch database/.ht.sqlite'
             );

        $this->ssh->clearOutput();

        $this->runContainer();

        $this->ssh->exec('podman start '.$this->instance->id);

        try {
            $this->ssh->exec($apply_limit_commands);
        } catch (Exception) {}

        // traffic rule
        return $this->buildTrafficRule();
    }

    public function runContainer()
    {
        $instance_path = $this->getPath();

        $this->ssh->clearOutput();

        $this->ssh->exec('podman ps -a -q --filter "name='.$this->instance->id.'"');

        if ($this->ssh->getLastLine() !== null) {
            return;
        }

        $this->ssh->exec(
            'cd '.$instance_path.' && '.
            'podman run -d --name='.$this->instance->id.' '.
            '-p 9000 -v '.$instance_path.'wordpress:/var/www/html/ '.
            'php:fpm-alpine'
        );

        $this->ssh->exec(
            'podman exec '.$this->instance->id.' '.
            'cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini'
        );

        $this->ssh->exec(
            'podman exec '.$this->instance->id.' '.
            'sed -i \'s#'.
            ';opcache.enable=1'.
            '#'.
            'opcache.enable=1'.
            '#\' '.
            '/usr/local/etc/php/php.ini'
        );

        $this->ssh->exec(
            'podman exec '.$this->instance->id.' '.
            'sed -i \'s#'.
            ';zend_extension=opcache'.
            '#'.
            'zend_extension=opcache'.
            '#\' '.
            '/usr/local/etc/php/php.ini'
        );

        $this->ssh->exec(
            'podman exec '.$this->instance->id.' '.
            'sed -i \'/^'.
            'upload_max_filesize'.
            '/s/.*/'.
            'upload_max_filesize = 20M/'.
            '\' '.
            '/usr/local/etc/php/php.ini'
        );

        $this->ssh->exec(
            'podman exec '.$this->instance->id.' '.
            'wget https://github.com/mlocati/docker-php-extension-installer/'.
            'releases/latest/download/install-php-extensions -O /usr/local/bin/install-php-extensions'
        );

        $this->ssh->exec(
            'podman exec '.$this->instance->id.' '.
            'chmod +x /usr/local/bin/install-php-extensions'
        );

        // gd library
        $this->ssh->exec(
            'podman exec '.$this->instance->id.' '.
            '/usr/local/bin/install-php-extensions gd'
        );

        // set permission
        $this->ssh->exec(
            'cd '.$instance_path.' && '.
            'chown -R 82:82 wordpress && '. // on alpine www-data UID is 82
            'find . -type d -exec chmod 755 {} \; && '.
            'find . -type f -exec chmod 644 {} \;'
        );

        // wp-cron.php
        $this->ssh->exec(
            'podman exec '.$this->instance->id.' '.
            'sh -c \''.
            'echo "#!/bin/sh" >> /etc/periodic/15min/wpcron && '.
            'echo "php -f /var/www/html/wp-cron.php >/dev/null 2>&1" >> /etc/periodic/15min/wpcron'.
            '\''
        );

        $this->ssh->exec('podman exec '.$this->instance->id.' chmod +x /etc/periodic/15min/wpcron');

        // php-fpm
        $this->ssh->clearOutput();

        $this->ssh->exec('podman mount '.$this->instance->id);

        $instance_mount_dir = $this->ssh->getLastLine();

        $this->ssh->exec(
            // access.format that use HTTP_X_FORWARDED_FOR
            'export FILE="'.$instance_mount_dir.'/usr/local/etc/php-fpm.d/www.conf" && '.
            'grep -qF '.
            '\'access.format = '.
            '"%{HTTP_X_FORWARDED_FOR}e - %u %t \"%m %r%Q%q\" %s %f %{milli}d %{kilo}M %C%%"\' '.
            '"'.$instance_mount_dir.'/usr/local/etc/php-fpm.d/www.conf" || '.
            'sed -i '.
            '\'/^;access\.format = .*/a '.
            'access.format = '.
            '"%{HTTP_X_FORWARDED_FOR}e - %u %t \\"%m %r%Q%q\\" %s %f %{milli}d %{kilo}M %C%%"\' '.
            '"'.$instance_mount_dir.'/usr/local/etc/php-fpm.d/www.conf"'
        );

        // restart container
        $this->ssh->exec(
            'podman stop '.$this->instance->id.' && '.
            'podman start '.$this->instance->id
        );

        // crond
        $this->ssh->exec('podman exec '.$this->instance->id.' crond');
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

        $extra = json_decode($this->instance->extra, true);

        $instance_path = $this->getPath();

        $default_domain = $this->instance->subdomain.'.'.config('app.domain');

        $custom_domain = '';

        if (array_key_exists('domain', $extra['reg_info'])) {
            $custom_domain = $extra['reg_info']['domain'];
        }

        $effect_domain = $custom_domain ? $custom_domain : $default_domain;

        $root_dir = $instance_path.'wordpress/';

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
        $this->ssh->clearOutput();

        $this->ssh->exec('podman ps -a -q --filter "name='.$this->instance->id.'"');

        if ($this->ssh->getLastLine() !== null) {
            $this->ssh->exec('podman rm -f '.$this->instance->id);
        }

        $this->deleteInstancePath();
    }

    public function turnOff()
    {
        $this->ssh->exec('podman stop '.$this->instance->id);
    }

    public function turnOn(): string
    {
        $instance_path = $this->getPath();

        $apply_limit_commands = $this->buildLimitCommands();

        $this->ssh->exec(array_merge(
            [
                'podman start '.$this->instance->id,
                'podman exec '.$this->instance->id.' crond'
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

    public function changeDomain(): string
    {
        return $this->buildTrafficRule();
    }

    public function upgrade()
    {
    }

    public function movePath(string $path)
    {
        $this->ssh->exec('rsync -r '.$this->getPath().'* '.$path);
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
