<?php

namespace App\Support;

/**
 * Server-side view of resources/js/game/levels.json, the same file the game reads.
 * Level ids are "<world id>-<1-based index>", e.g. "rook-3".
 */
class LevelCatalog
{
    private static ?array $data = null;

    public static function data(): array
    {
        return self::$data ??= json_decode(
            file_get_contents(resource_path('js/game/levels.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<int, array{id: string, name: string, section: int, levels: int}> */
    public static function worlds(): array
    {
        return array_map(fn (array $w) => [
            'id' => $w['id'],
            'name' => $w['name'],
            'section' => $w['section'],
            'levels' => count($w['levels']),
        ], self::data()['worlds']);
    }

    /** @return list<string> */
    public static function levelIds(): array
    {
        $ids = [];
        foreach (self::data()['worlds'] as $w) {
            foreach (array_keys($w['levels']) as $i) {
                $ids[] = $w['id'].'-'.($i + 1);
            }
        }

        return $ids;
    }

    public static function exists(string $levelId): bool
    {
        return in_array($levelId, self::levelIds(), true);
    }

    public static function maxStars(): int
    {
        return count(self::levelIds()) * 3;
    }
}
