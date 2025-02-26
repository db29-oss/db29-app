<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instance extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
