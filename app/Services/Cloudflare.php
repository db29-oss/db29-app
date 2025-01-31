<?php

namespace App\Services;

use Exception;

class Cloudflare {
    private string $domain;

    const MAX_RECORD_BATCH_ACTION = 200; // CF free plan

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

        $ip_address = null;

        if (filter_var($hostname, FILTER_VALIDATE_IP)) {
            $ip_address = $hostname;
        }

        if ($ip_address === null) {
            $dns_get_record = dns_get_record($hostname, DNS_A);

            if (count($dns_get_record) === 0) {
                throw new Exception('DB291991: unable get host ip address');
            }

            $ip_address = $dns_get_record[0]['ip'];
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

    public function batchAction(array $batch_action = []): array {
        # https://developers.cloudflare.com/dns/manage-dns-records/how-to/batch-record-changes/#example-request

        $count_deletes = 0;

        if (array_key_exists('deletes', $batch_action)) {
            $count_deletes = count($batch_action['deletes']);
        }

        $count_patches = 0;

        if (array_key_exists('patches', $batch_action)) {
            $count_patches = count($batch_action['patches']);
        }

        $count_puts = 0;

        if (array_key_exists('puts', $batch_action)) {
            $count_puts = count($batch_action['puts']);
        }

        $count_posts = 0;

        if (array_key_exists('posts', $batch_action)) {
            $count_posts = count($batch_action['posts']);
        }

        if ($count_deletes + $count_patches + $count_posts + $count_puts === 0) {
            return [
                'deletes' => null,
                'patches' => null,
                'puts' => null,
                'posts' => null,
            ];
        }

        if ($count_deletes + $count_patches + $count_posts + $count_puts > static::MAX_RECORD_BATCH_ACTION) {
            throw new Exception('DB292022: number of records cannot exceed 200');
        }

        $command =
            "curl -s ".
            "-H \"Content-Type: application/json\" ".
            "-H \"Authorization: Bearer ".$this->zone_token."\" ".
            "https://api.cloudflare.com/client/v4/zones/$this->zone_id/dns_records/batch ".
            "-d '".json_encode($batch_action)."'";

        exec($command, $output, $exit_code);

        if ($exit_code !== 0) {
            throw new Exception('DB292023: curl batch action fail');
        }

        $result = json_decode($output[0], true);

        if ($result['success'] !== true) {
            logger()->error('DB292024: batch action fail', [
                'response' => $output[0]
            ]);

            throw new Exception('DB292025: batch action fail');
        }

        return $result['result'];
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
