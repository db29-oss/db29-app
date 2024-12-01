<?php

namespace App\Services;

use Exception;

class Cloudflare {
    private string $domain;

    public function __construct(
        private string $zone_id,
        private string $zone_token,
    ) {
        $this->domain = config('app.domain');
    }

    public function addDnsRecord(string $subdomain, string $hostname, array $info = []): string
    {
        $data = [];

        if (array_key_exists('comment', $info)) {
            $data['comment'] = (string) $info['comment'];
        }

        $data['name'] = $subdomain.'.'.$this->domain;

        $data['ttl'] = 60;

        $ip_address = gethostbyname($hostname);

        if ($ip_address === false) {
            throw new Exception('DB291991: unable get host ip address');
        }

        $data['content'] = $ip_address;

        $data['type'] = 'A';

        $command =
            "curl -s -X POST ".
            "https://api.cloudflare.com/client/v4/zones/".$this->zone_id."/dns_records ".
            "-H \"Content-Type: application/json\" ".
            "-H \"Authorization: Bearer ".$this->zone_token."\" ".
            "-d '".json_encode($data)."'";

        exec($command, $output, $exit_code);

        if ($exit_code !== 0) {
            throw new Exception('DB291992: curl create new dns record fail');
        }

        $result = json_decode($output[0], true);

        if ($result['success'] !== true) {
            logger()->error('DB291994: create new dns record fail', [
                'hostname' => $hostname,
                'info' => $info,
                'output' => $output[0],
                'subdomain' => $subdomain,
            ]);

            throw new Exception('DB291993: create new dns record fail');
        }

        return $result['result']['id'];
    }
}
