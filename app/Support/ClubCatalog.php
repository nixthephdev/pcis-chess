<?php

namespace App\Support;

/**
 * Server-side view of resources/js/game/club.json, the Chess Club puzzles and lessons.
 */
class ClubCatalog
{
    public const GRADES = ['PYP 3', 'PYP 4', 'PYP 5', 'MYP 1', 'MYP 2', 'MYP 3', 'MYP 4', 'MYP 5'];

    /** XP for finishing a lesson, a puzzle on the first try, and a puzzle after a mistake. Club.vue uses the same numbers. */
    public const XP_LESSON = 50;

    public const XP_PUZZLE = 30;

    public const XP_PUZZLE_RETRY = 15;

    /** Club XP from the attendance register. */
    public const XP_PRESENT = 50;

    public const XP_LATE = 30;

    public const XP_STREAK_WEEK = 20;

    private static ?array $data = null;

    public static function data(): array
    {
        return self::$data ??= json_decode(
            file_get_contents(resource_path('js/game/club.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @return list<string> */
    public static function ids(string $kind): array
    {
        return array_column(self::data()[$kind === 'puzzle' ? 'puzzles' : 'lessons'], 'id');
    }
}
