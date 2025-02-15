<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasFactory;

    const UUOS = [ // unsupported user own server
        'discourse' => true, // need mail server
    ];

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }
}
