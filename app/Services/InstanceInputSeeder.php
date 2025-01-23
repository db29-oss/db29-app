<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use phpseclib3\Crypt\RSA;

class InstanceInputSeeder
{
    public static function discourse()
    {
        $now = now();
        $sql_params = [];
        $sql = 'update tmp set '.
            'updated_at = ? '. # $now
            'where k = ? '. # 'discourse_dkim'
            'returning *';

        $sql_params[] = $now;
        $sql_params[] = 'discourse_dkim';

        $db = DB::select($sql, $sql_params);

        if (count($db) > 0) {
            return json_decode($db[0]->v, true);
        }

        $rsa = RSA::createKey(2048);

        $discourse_dkim = [
            'dkim_privatekey' =>
            rtrim(str_replace(["\r\n", "\n", "\r"], PHP_EOL, $rsa->toString('PKCS1')), PHP_EOL).PHP_EOL,
            'dkim_publickey' =>
            rtrim(
                str_replace(["\r\n", "\n", "\r"], PHP_EOL, $rsa->getPublicKey()->toString('PKCS8')),
                PHP_EOL
            ).PHP_EOL,
            'dkim_selector' => str(str()->random(16))->lower()
        ];

        $sql_params = [];
        $sql = 'insert into tmp (user_id, k, v, created_at, updated_at) '.
            'values ('.
                '?, '. # auth()->user()->id
                '?, '. # 'discourse_dkim'
                '?, '. # $discourse_dkim
                '?, '. # $now
                '?'. # $now
            ')';

        $sql_params[] = auth()->user()->id;
        $sql_params[] = 'discourse_dkim';
        $sql_params[] = json_encode($discourse_dkim);
        $sql_params[] = $now;
        $sql_params[] = $now;

        DB::select($sql, $sql_params);

        return $discourse_dkim;
    }
}
