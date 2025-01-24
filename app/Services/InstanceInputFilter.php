<?php

namespace App\Services;

use App\Rules\DKIMValidation;
use Illuminate\Support\Facades\DB;

class InstanceInputFilter
{
    public static function discourse()
    {
        $validator = validator(request()->all(), [
            'email' => ['required', 'email:rfc'],
            'system_email' => ['nullable', 'email:rfc'],
        ]);

        $validator->validated();

        $reg_info = [];

        $reg_info['email'] = request('email');

        if (request('system_email')) {
            $reg_info['system_email'] = request('system_email');

            $now = now();
            $sql_params = [];
            $sql = 'select * from tmp '.
                'where user_id = ? '. # auth()->user()->id
                'and k = ?'; # 'discourse'

            $sql_params[] = auth()->user()->id;
            $sql_params[] = 'discourse';

            $db = DB::select($sql, $sql_params);

            if (count($db) === 0) { // testing
                InstanceInputSeeder::discourse();

                $db = DB::select($sql, $sql_params);
            }

            $json_decode = json_decode($db[0]->v, true);

            $reg_info['dkim_privatekey'] = $json_decode['dkim_privatekey'];
            $reg_info['dkim_publickey'] = $json_decode['dkim_publickey'];
            $reg_info['dkim_selector'] = $json_decode['dkim_selector'];
        }

        return $reg_info;
    }

    public static function planka()
    {
        $validator = validator(request()->all(), [ 
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'alpha_num:ascii'],
            'name' => ['required', 'alpha_num:ascii'],
            'username' => ['required', 'alpha_num:ascii'],
        ]);

        $validator->validated();

        $reg_info = [];

        $reg_info['email'] = request('email');
        $reg_info['password'] = request('password');
        $reg_info['name'] = request('name');
        $reg_info['username'] = request('username');
        $reg_info['secret_key'] = bin2hex(random_bytes(64));

        return $reg_info;
    }
}
