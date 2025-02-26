<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    use HasFactory;

    protected $hidden = ['ssh_privatekey'];

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function trafficRouter()
    {
        return $this->belongsTo(TrafficRouter::class);
    }
}
