<?php

namespace App\Rules;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;

class SSHConnectionWorks implements ValidationRule
{
    public function __construct(
        private string $ssh_address,
        private string $ssh_port,
        private string $ssh_privatekey,
        private string $ssh_username,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ssh_privatekey_path = '/dev/shm/'.hash('sha512', $this->ssh_privatekey);

        $file_put_contents = file_put_contents($ssh_privatekey_path, $this->ssh_privatekey);

        if ($file_put_contents === false) {
            throw new Exception('DB292026: unable write ssh privatekey to /dev/shm/');
        }

        $chmod = chmod($ssh_privatekey_path, 0600);

        if (! $chmod) {
            @unlink($ssh_privatekey_path);
            throw new Exception('DB292027: unable chmod ssh private key');
        }

        $ssh = app('ssh')->from([
            'ssh_privatekey_path' => $ssh_privatekey_path
        ])->to([
            'ssh_address' => $this->ssh_address,
            'ssh_port' => $this->ssh_port,
            'ssh_username' => $this->ssh_username,
        ]);

        try {
            $ssh->exec('echo testing_connection');
        } catch (Exception) {
            @unlink($ssh_privatekey_path);
            $fail(__('trans.ssh_unreachable'));
            return;
        }

        @unlink($ssh_privatekey_path);
    }
}
