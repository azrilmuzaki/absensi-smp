<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'nis',
        'nisn',
        'name',
        'gender',
        'birth_date',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function placements()
    {
        return $this->hasMany(StudentClassPlacement::class);
    }

    public function rfidCards()
    {
        return $this->hasMany(RfidCard::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
