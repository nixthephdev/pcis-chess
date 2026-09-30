<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Support\LevelCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuestController extends Controller
{
    public function show(): Response
    {
        /** @var Student|null $student */
        $student = Auth::guard('student')->user();

        return Inertia::render('Quest', [
            'student' => $student ? [
                'name' => $student->name,
                'avatar' => $student->avatar,
                'username' => $student->username,
                'classroom' => $student->classroom?->name,
            ] : null,
            'progress' => $student?->starsByLevel(),
            'leaderboard' => $student ? $this->leaderboard($student) : null,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $data = $request->validate([
            'level_id' => ['required', 'string', Rule::in(LevelCatalog::levelIds())],
            'stars' => ['required', 'integer', 'between:1,3'],
            'moves' => ['nullable', 'integer', 'between:0,999'],
        ]);
        $data['moves'] ??= null;

        $row = $student->progress()->firstOrNew(['level_id' => $data['level_id']]);
        if ($row->exists) {
            $row->stars = max($row->stars, $data['stars']);
            $row->plays++;
            if ($data['moves'] !== null) {
                $row->best_moves = $row->best_moves === null ? $data['moves'] : min($row->best_moves, $data['moves']);
            }
        } else {
            $row->fill(['stars' => $data['stars'], 'best_moves' => $data['moves'], 'plays' => 1]);
        }
        $row->save();

        $student->forceFill(['last_played_at' => now()])->save();

        return response()->json([
            'progress' => $student->starsByLevel(),
            'leaderboard' => $this->leaderboard($student),
        ]);
    }

    /** @return array{rows: list<array>, me: int|null} */
    private function leaderboard(Student $me): array
    {
        $rows = Classroom::leaderboard($me->classroom_id);
        $rank = array_search($me->id, array_column($rows, 'id'), true);

        return [
            'rows' => array_map(
                fn (array $r) => ['name' => $r['name'], 'avatar' => $r['avatar'], 'stars' => $r['stars'], 'me' => $r['id'] === $me->id],
                array_slice($rows, 0, 10),
            ),
            'me' => $rank === false ? null : $rank + 1,
        ];
    }
}
