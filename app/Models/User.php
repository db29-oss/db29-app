<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    public const FREE_CREDIT = 50_000;

    public function sources()
    {
        return $this->hasMany(Source::class);
    }

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function subdomains()
    {
        return $this->hasMany(Subdomain::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $sql_params = [];

            $sql = 'insert into recharge_number_holes (recharge_number) values (?)';
            $sql_params[] = $user->recharge_number;

            DB::insert($sql, $sql_params);
        });
    }
}
