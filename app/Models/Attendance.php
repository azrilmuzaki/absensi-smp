<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year_id',
        'attendance_date',
        'status',
        'time',
        'device_id',
        'source',
        'idempotency_key',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function audits()
    {
        return $this->hasMany(AttendanceAudit::class);
    }
}
