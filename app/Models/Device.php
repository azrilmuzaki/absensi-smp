<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'device_code',
        'device_name',
        'api_key',
        'status',
        'last_sync_at',
        'last_seen_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function unknownRfidLogs()
    {
        return $this->hasMany(UnknownRfidLog::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
