<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnknownRfidLog extends Model
{
    protected $fillable = [
        'uid',
        'device_id',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
