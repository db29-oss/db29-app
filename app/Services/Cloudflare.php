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

        $dns_get_record = dns_get_record($hostname, DNS_A);

        if (count($dns_get_record) === 0) {
            throw new Exception('DB291991: unable get host ip address');
        }

        $ip_address = $dns_get_record[0]['ip'];

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

    public function deleteDnsRecord(string $dns_id)
    {
        $command =
            "curl -s -X DELETE ".
            "-H \"Content-Type: application/json\" ".
            "-H \"Authorization: Bearer ".$this->zone_token."\" ".
            "https://api.cloudflare.com/client/v4/zones/".$this->zone_id."/dns_records/".$dns_id;

        exec($command, $output, $exit_code);

        if ($exit_code !== 0) {
            throw new Exception('DB292000: curl delete dns record fail');
        }
    }

    public function updateDnsRecord(string $dns_id, string $subdomain, string $hostname)
    {
        $data = [];

        $data['name'] = $subdomain.'.'.$this->domain;

        $data['ttl'] = 60;

        $dns_get_record = dns_get_record($hostname, DNS_A);

        if (count($dns_get_record) === 0) {
            throw new Exception('DB292013: unable get host ip address');
        }

        $ip_address = $dns_get_record[0]['ip'];

        $data['content'] = $ip_address;

        $data['type'] = 'A';

        $command =
            "curl -s -X PATCH ".
            "https://api.cloudflare.com/client/v4/zones/".$this->zone_id."/dns_records/".$dns_id." ".
            "-H \"Content-Type: application/json\" ".
            "-H \"Authorization: Bearer ".$this->zone_token."\" ".
            "-d '".json_encode($data)."'";

        exec($command, $output, $exit_code);

        if ($exit_code !== 0) {
            throw new Exception('DB292014: curl create new dns record fail');
        }

        $result = json_decode($output[0], true);

        if ($result['success'] !== true) {
            logger()->error('DB292015: create new dns record fail', [
                'hostname' => $hostname,
                'info' => $info,
                'output' => $output[0],
                'subdomain' => $subdomain,
            ]);

            throw new Exception('DB292016: create new dns record fail');
        }
    }

    public function subdomainExists(string $subdomain): bool
    {
        $command =
            "curl -s -X GET ".
            "-H \"Content-Type: application/json\" ".
            "-H \"Authorization: Bearer ".$this->zone_token."\" ".
            "https://api.cloudflare.com/client/v4/zones/".$this->zone_id."/dns_records".
            "?name=".$subdomain.'.'.config('app.domain');

        exec($command, $output, $exit_code);

        if ($exit_code !== 0) {
            throw new Exception('DB291997: curl subdomain exists fail');
        }

        $result = json_decode($output[0], true);

        if ($result['success'] !== true) {
            throw new Exception('DB291999: curl subdomain result fail');
        }

        if (count($result['result']) > 0) {
            return true;
        }

        return false;
    }
}
