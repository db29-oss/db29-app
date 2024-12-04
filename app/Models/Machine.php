<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    use HasFactory, HasUuids;

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function trafficRouter()
    {
        return $this->hasOne(TrafficRouter::class);
    }
}
