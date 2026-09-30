<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelProgress extends Model
{
    protected $table = 'level_progress';

    protected $fillable = ['level_id', 'stars', 'best_moves', 'plays'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
