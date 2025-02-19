<?php

namespace App\Services;

use App\Models\Source;
use App\Rules\Ipv4OrDomainARecordExists;
use App\Rules\MxRecordExactValue;
use App\Rules\TxtRecordExactValue;
use App\Rules\TxtRecordExists;
use App\Rules\UnsupportedUserOwnServer;
use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SourceInputFilter
{
    public function __construct(
        private string $source_name
    ) {
        if (! method_exists(static::class, $source_name)) {
            throw new Exception('DB292028: unimplemented source');
        }

        if (array_key_exists($this->source_name, Source::UUOS)) { // @phpstan-ignore-line
            validator(request()->all(), [
                'hostname' => new UnsupportedUserOwnServer,
            ]);
        }
    }

    public function __filter()
    {
        return $this->{$this->source_name}();
    }

    public function discourse()
    {
        $validation = [
            'email' => ['required', 'email:rfc'],
        ];

        if (request('hostname')) {
            $validation['aws_key'] = ['required'];
            $validation['aws_secret'] = ['required'];
            $validation['aws_ses_region'] = ['required'];
        }

        validator(request()->all(), $validation)->validated();

        $reg_info = [];

        $reg_info['email'] = request('email');

        if (request('hostname')) {
            $client = new SesV2Client([
                'region' => request('aws_ses_region'),
                'version' => 'latest',
                'credentials' => [
                    'key'    => request('aws_key'),
                    'secret' => request('aws_secret'),
                ],
            ]);

            try {
                $result = $client->getAccount();
            } catch (AwsException) {
                throw ValidationException::withMessages([
                    'aws_key' => [__('trans.incorrect_aws_key_secret_or_region')]
                ]);
            }

            if (
                $result->get('SendingEnabled') !== true ||
                $result->get('ProductionAccessEnabled') !== true
            ) {
                throw ValidationException::withMessages([
                    'aws_key' => [__('trans.incorrect_aws_ses_cred_or_sandbox')]
                ]);
            }

            $reg_info['aws_key'] = request('aws_key');
            $reg_info['aws_secret'] = request('aws_secret');
            $reg_info['aws_ses_region'] = request('aws_ses_region');
        }

        if (request('system_email')) {
            validator(request()->all(), [
                'system_email' => ['email:rfs'],
            ])->validated();

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

            if (app('env') === 'production') {
                $system_email_domain = explode('@', $reg_info['system_email'])[1];

                validator(
                    [
                        'dkim_txt' => $reg_info['dkim_selector'].'._domainkey.'.$system_email_domain,
                        'spf_txt' => $reg_info['dkim_selector'].'.'.$system_email_domain,
                        'dmarc_txt' => '_dmarc.'.$system_email_domain,
                        'mx_mx' => $reg_info['dkim_selector'].'.'.$system_email_domain,
                    ],
                    [
                        'dkim_txt' => new TxtRecordExactValue($reg_info['dkim_publickey']),
                        'spf_txt' => new TxtRecordExists,
                        'dmarc_txt' => new TxtRecordExists,
                        'mx_mx' => new MxRecordExactValue(
                            'feedback-smtp.'.config('services.ses.smtp').'.amazonses.com'
                        ),
                    ]
                )->validated();
            }
        }

        return $reg_info;
    }

    public function planka()
    {
        validator(request()->all(), [
            'email' => ['required', 'email:rfc'],
            'name' => ['required', 'alpha_num:ascii'],
            'password' => ['required', 'alpha_num:ascii'],
            'username' => ['required', 'alpha_num:ascii'],
        ])->validated();

        $reg_info = [];

        $reg_info['email'] = request('email');
        $reg_info['password'] = request('password');
        $reg_info['name'] = request('name');
        $reg_info['username'] = request('username');
        $reg_info['secret_key'] = bin2hex(random_bytes(64));

        return $reg_info;
    }
}
