<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubProgress extends Model
{
    protected $table = 'club_progress';

    protected $fillable = ['kind', 'item_id', 'xp', 'first_try'];

    protected function casts(): array
    {
        return ['first_try' => 'boolean'];
    }
}
