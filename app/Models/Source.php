<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasFactory, HasUuids;

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }
}
