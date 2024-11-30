<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrafficRouter extends Model
{
    use HasUuids;

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
