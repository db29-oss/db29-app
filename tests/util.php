<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

# workaround https://github.com/cockroachdb/cockroach/issues/46869
function test_util_migrate_fresh(): bool { #cr_46869
    $it_work = false;
    $tables = null;

    if (DB::getDriverName() === 'sqlite') {
        $it_work = true;

        Artisan::call('migrate:fresh');

        return $it_work;
    }

    if (! $it_work) {

        try { // cockroachdb

            $tables = DB::select('show tables'); // cockroachdb

            if (! empty($tables)) {
                $it_work = true;
            }

        } catch (\Throwable $throwable) {
        }
    }

    if (! $it_work) {

        try { // postgres

            $tables = DB::select(
                "select table_name from information_schema.tables ".
                "where table_catalog = '".
                config('database.connections')[config('database.default')]['database'].
                "' ".
                "and table_type = 'BASE TABLE' ".
                "and table_schema = 'public'"
            );

            if (! empty($tables)) {
                $it_work = true;
            }

        } catch (\Throwable $throwable) {
        }
    }

    if (! $it_work) {

        throw new \Exception('DB291990: unable get tables');
    }

    while (! empty($tables)) {

        try {

            $rand = rand(0, count($tables) - 1);

            ######## postgis - ignore #########
            if ($tables[$rand]->table_name === 'spatial_ref_sys') {
                unset($tables[$rand]);
                $tables = array_values($tables);
                continue;
            }
            ###################################

            if (is_null($tables[$rand])) {
                continue;
            }

            DB::select("delete from {$tables[$rand]->table_name} where 1=1");

            unset($tables[$rand]);

            $tables = array_values($tables);

        } catch (\Throwable $throwable) {
        }
    }

    return $it_work;
}

function setup_container(string $container_name): int {
    $local_image = true; // to attach some process to it, e.g: tail -F /dev/null

    if (file_exists(base_path('tests/Dockerfile/'.$container_name.'.Dockerfile'))) {
        exec(
            'podman build -t '.$container_name.' -f '.
            base_path('tests/Dockerfile/'.$container_name.'.Dockerfile').' '.
            base_path('tests/Dockerfile/')
        );
    } else {
        $local_image = false;
        exec('podman pull '.$container_name);
    }

    $ssh_privatekey_path = sys_get_temp_dir().'/'.$container_name;

    @unlink($ssh_privatekey_path);

    exec('ssh-keygen -N "" -t ed25519 -C "'.$container_name.'" -f '.$ssh_privatekey_path);

    exec('podman container exists '.$container_name, $output, $exit_code);

    if ($exit_code === 0) {
        exec('podman kill '.$container_name, $output, $exit_code);

        while (true) {
            exec('podman container exists '.$container_name, $output, $exit_code);

            if ($exit_code === 1) {
                break;
            }
        }
    }

    $podman_run_cmd =
        'podman run --privileged --rm -d --name '.$container_name.' -p 22 '.$container_name;

    if (! $local_image) {
        $podman_run_cmd .= ' tail -F /dev/null';
    }

    exec($podman_run_cmd);

    // create .ssh dir
    exec('podman exec '.$container_name.' sh -c \'mkdir -p /root/.ssh/\'');

    // copy 
    exec(
        'podman exec '.$container_name.' '.
        'sh -c \'echo "'.
        file_get_contents($ssh_privatekey_path.'.pub').
        '" >> /root/.ssh/authorized_keys\''
    );

    $output = [];

    exec('podman port '.$container_name.' 22', $output);

    return parse_url($output[0])['port'];
}

function cleanup_container(string $container_name) {
    exec('podman container exists '.$container_name.' && podman kill '.$container_name);

    unlink(sys_get_temp_dir().'/'.$container_name);
    unlink(sys_get_temp_dir().'/'.$container_name.'.pub');
}
