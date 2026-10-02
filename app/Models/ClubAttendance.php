<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubAttendance extends Model
{
    public const MARKS = ['P', 'L', 'A'];

    // `week` is left as a plain "Y-m-d" string: a date cast would store a time too and break lookups on SQLite.
    protected $fillable = ['student_id', 'week', 'mark'];
}
