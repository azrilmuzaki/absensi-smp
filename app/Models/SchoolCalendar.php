<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCalendar extends Model
{
    protected $fillable = [
        'date',
        'status',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
