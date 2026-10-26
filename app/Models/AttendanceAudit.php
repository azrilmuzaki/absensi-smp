<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceAudit extends Model
{
    protected $fillable = [
        'attendance_id',
        'old_status',
        'new_status',
        'changed_by',
        'reason',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
