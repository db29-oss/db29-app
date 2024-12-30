<?php

namespace App\Services;

class InstanceInputFilter
{
    public static function discourse()
    {
        // WTF??
    }

    public static function planka()
    {
        $validator = validator(request()->all(), [ 
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'alpha_num:ascii'],
            'name' => ['required', 'alpha_num:ascii'],
            'username' => ['required', 'alpha_num:ascii'],
        ]);

        $data = $validator->validated();

        $reg_info = [];

        $reg_info['email'] = request('email');
        $reg_info['password'] = request('password');
        $reg_info['name'] = request('name');
        $reg_info['username'] = request('username');
        $reg_info['secret_key'] = bin2hex(random_bytes(64));

        return $reg_info;
    }
}
