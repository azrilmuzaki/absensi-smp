<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfidCard extends Model
{
    protected $fillable = [
        'student_id',
        'uid',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
