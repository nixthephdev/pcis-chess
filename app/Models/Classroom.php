<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Classroom extends Model
{
    protected $fillable = ['name', 'code'];

    // No 0/O or 1/I/L, so young students can copy the code from the board without mix-ups.
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * A null classroom ranks the players who signed up on their own.
     *
     * @return list<array{id: int, name: string, avatar: string, stars: int}> best first
     */
    public static function leaderboard(?int $classroomId): array
    {
        return Student::where('classroom_id', $classroomId)
            ->withSum('progress', 'stars')
            ->get(['id', 'name', 'avatar'])
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'avatar' => $s->avatar,
                'stars' => (int) $s->progress_sum_stars,
            ])
            ->sortBy([['stars', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    public static function normalizeCode(string $input): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    }
}
