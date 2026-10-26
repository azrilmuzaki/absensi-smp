<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentClassPlacement extends Model
{
    protected $fillable = [
        'student_id',
        'school_class_id',
        'academic_year_id',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
