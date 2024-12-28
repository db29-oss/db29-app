<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }
}
