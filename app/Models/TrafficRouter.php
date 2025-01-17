<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

# Apache/Caddy/Nginx/etc...
class TrafficRouter extends Model
{
    use HasFactory;

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
