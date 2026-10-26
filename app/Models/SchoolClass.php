<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $fillable = [
        'name',
        'academic_year_id',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function placements()
    {
        return $this->hasMany(StudentClassPlacement::class);
    }

    public function waliKelas()
    {
        return $this->hasMany(User::class, 'class_id');
    }
}
