<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    public function trafficRouter()
    {
        return $this->hasOne(TrafficRouter::class);
    }
}
