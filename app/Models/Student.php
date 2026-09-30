<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Student extends Authenticatable
{
    public const AVATARS = ['king', 'queen', 'rook', 'bishop', 'knight', 'pawn'];

    protected $fillable = ['name', 'username', 'avatar', 'pin', 'password', 'last_played_at'];

    protected $hidden = ['pin', 'password', 'remember_token'];

    protected function casts(): array
    {
        return [
            // Encrypted rather than hashed so the teacher can print login cards.
            'pin' => 'encrypted',
            'password' => 'hashed',
            'last_played_at' => 'datetime',
        ];
    }

    /** Signed up on their own, rather than added to a class by a coach. */
    public function isPlayer(): bool
    {
        return $this->username !== null;
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LevelProgress::class);
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password;
    }

    public function pinMatches(string $pin): bool
    {
        return $this->pin !== null && hash_equals((string) $this->pin, $pin);
    }

    public static function newPin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public static function randomAvatar(): string
    {
        return self::AVATARS[array_rand(self::AVATARS)];
    }

    /** @return array<string, int> level id => stars */
    public function starsByLevel(): array
    {
        return $this->progress()->pluck('stars', 'level_id')->map(fn ($s) => (int) $s)->all();
    }
}
