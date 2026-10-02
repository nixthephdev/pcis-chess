<?php

namespace App\Http\Controllers;

use App\Models\ClubAttendance;
use App\Models\ClubProgress;
use App\Models\Student;
use App\Support\ClubCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school Chess Club page. Coaches keep the register for their classes;
 * signed-in students and players have their puzzle and lesson XP saved to their account.
 */
class ClubController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var Student|null $me */
        $me = Auth::guard('student')->user();
        $coach = Auth::guard('web')->user();
        $weeks = self::weeks(4);

        $roster = null;
        $board = null;

        if ($coach) {
            $classrooms = $coach->classrooms()->orderBy('name')->get(['id', 'name']);
            $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();

            $roster = [
                'classrooms' => $classrooms->map->only('id', 'name')->values(),
                'classroomId' => $classroom?->id,
                'members' => $classroom ? $this->members($classroom->students(), $weeks) : [],
            ];
            if ($classroom) {
                $board = ['title' => $classroom->name, 'rows' => $this->leaderboard($classroom->students(), $me)];
            }
        }

        if (! $board && $me) {
            $board = $me->classroom_id
                ? ['title' => $me->classroom->name, 'rows' => $this->leaderboard(Student::where('classroom_id', $me->classroom_id), $me)]
                : ['title' => 'Top players', 'rows' => array_slice($this->leaderboard(Student::whereNull('classroom_id'), $me), 0, 50)];
        }

        return Inertia::render('Club', [
            'weeks' => $weeks,
            'me' => $me ? ['name' => $me->name, 'grade' => $me->grade, 'classroom' => $me->classroom?->name] : null,
            'myProgress' => $me ? self::summary($me) : null,
            'roster' => $roster,
            'board' => $board,
        ]);
    }

    /** Saves a finished puzzle or lesson for the signed-in student. XP is only given the first time. */
    public function progress(Request $request): JsonResponse
    {
        /** @var Student $me */
        $me = Auth::guard('student')->user();

        $data = $request->validate([
            'kind' => ['required', Rule::in(['puzzle', 'lesson'])],
            'id' => ['required', 'string', 'max:40'],
            'first_try' => ['required', 'boolean'],
        ]);
        abort_unless(in_array($data['id'], ClubCatalog::ids($data['kind']), true), 422, 'Unknown puzzle or lesson.');

        $row = $me->clubProgress()->firstOrNew(['kind' => $data['kind'], 'item_id' => $data['id']]);

        if ($data['kind'] === 'lesson') {
            $row->exists || $row->fill(['xp' => ClubCatalog::XP_LESSON, 'first_try' => true]);
        } elseif (! $row->exists) {
            $row->fill([
                'xp' => $data['first_try'] ? ClubCatalog::XP_PUZZLE : ClubCatalog::XP_PUZZLE_RETRY,
                'first_try' => $data['first_try'],
            ]);
        } elseif ($data['first_try'] && ! $row->first_try) {
            // Solved cleanly on a later try: top up to the full first-try XP.
            $row->fill(['xp' => ClubCatalog::XP_PUZZLE, 'first_try' => true]);
        }
        $row->save();

        $me->forceFill(['last_played_at' => now()])->save();

        return response()->json(self::summary($me));
    }

    public function mark(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeCoach($request, $student);

        $data = $request->validate([
            'week' => ['required', 'date_format:Y-m-d'],
            'mark' => ['nullable', Rule::in(ClubAttendance::MARKS)],
        ]);

        $week = Carbon::parse($data['week']);
        if (! $week->isMonday() || $week->gt(self::thisMonday()) || $week->lt(self::thisMonday()->subYear())) {
            return back()->withErrors(['week' => 'Attendance is kept by week, starting on a Monday, within the last year.']);
        }

        $key = ['student_id' => $student->id, 'week' => $week->toDateString()];
        if ($data['mark'] === null) {
            ClubAttendance::where($key)->delete();
        } else {
            ClubAttendance::updateOrCreate($key, ['mark' => $data['mark']]);
        }

        return back();
    }

    public function updateStudent(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeCoach($request, $student);

        $student->update($request->validate([
            'grade' => ['sometimes', 'nullable', Rule::in(ClubCatalog::GRADES)],
            'registered' => ['sometimes', 'boolean'],
        ]));

        return back();
    }

    // ------------------------------------------------------------------ helpers

    private static function thisMonday(): Carbon
    {
        return now()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /** @return list<string> the last $n Mondays as "Y-m-d", oldest first */
    private static function weeks(int $n): array
    {
        $monday = self::thisMonday();

        return array_map(fn (int $back) => $monday->copy()->subWeeks($back)->toDateString(), range($n - 1, 0));
    }

    /** @return array{xp: int, solved: object, lessons: list<string>, weekly: array{puzzles: int, lessons: int, xp: int}} */
    public static function summary(Student $student): array
    {
        $rows = $student->clubProgress()->get();
        $thisWeek = $rows->filter(fn (ClubProgress $r) => $r->created_at->gte(self::thisMonday()));

        return [
            'xp' => (int) $rows->sum('xp'),
            'solved' => (object) $rows->where('kind', 'puzzle')->mapWithKeys(fn (ClubProgress $r) => [$r->item_id => ['firstTry' => $r->first_try]])->all(),
            'lessons' => $rows->where('kind', 'lesson')->pluck('item_id')->values()->all(),
            'weekly' => [
                'puzzles' => $thisWeek->where('kind', 'puzzle')->count(),
                'lessons' => $thisWeek->where('kind', 'lesson')->count(),
                'xp' => (int) $thisWeek->sum('xp'),
            ],
        ];
    }

    /** Register rows with marks for the given weeks. */
    private function members(HasMany|Builder $students, array $weeks): array
    {
        return $students->with(['attendance' => fn ($q) => $q->whereIn('week', $weeks)])
            ->orderBy('name')
            ->get()
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'grade' => $s->grade,
                'registered' => $s->registered,
                'attendance' => (object) $s->attendance->mapWithKeys(fn (ClubAttendance $a) => [self::day($a->week) => $a->mark])->all(),
            ])
            ->all();
    }

    /**
     * Club XP = attendance XP (present, late, current streak) + puzzle and lesson XP. Best first.
     *
     * @return list<array{id: int, name: string, grade: ?string, xp: int, streak: int, sessions: int, me: bool}>
     */
    private function leaderboard(HasMany|Builder $students, ?Student $me): array
    {
        return $students->with('attendance:id,student_id,week,mark')
            ->withSum('clubProgress', 'xp')
            ->get()
            ->map(function (Student $s) use ($me) {
                $marks = $s->attendance->mapWithKeys(fn (ClubAttendance $a) => [self::day($a->week) => $a->mark]);
                $present = $marks->filter(fn ($m) => $m === 'P')->count();
                $late = $marks->filter(fn ($m) => $m === 'L')->count();
                $streak = self::streak($marks->all());

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'grade' => $s->grade,
                    'xp' => $present * ClubCatalog::XP_PRESENT + $late * ClubCatalog::XP_LATE + $streak * ClubCatalog::XP_STREAK_WEEK
                        + (int) $s->club_progress_sum_xp,
                    'streak' => $streak,
                    'sessions' => $present + $late,
                    'me' => $me?->id === $s->id,
                ];
            })
            ->sortBy([['xp', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /** Weeks in a row attended, counting back from this week (or from last week if this week isn't marked yet). */
    private static function streak(array $marks): int
    {
        $monday = self::thisMonday();
        $n = 0;
        for ($back = isset($marks[$monday->toDateString()]) ? 0 : 1; $back < 52; $back++) {
            $mark = $marks[$monday->copy()->subWeeks($back)->toDateString()] ?? null;
            if ($mark !== 'P' && $mark !== 'L') {
                break;
            }
            $n++;
        }

        return $n;
    }

    /** MySQL returns "Y-m-d" for a date column; SQLite may add a time. */
    private static function day(string $week): string
    {
        return substr($week, 0, 10);
    }

    private function authorizeCoach(Request $request, Student $student): void
    {
        abort_unless($student->classroom && $student->classroom->user_id === $request->user()->id, 403);
    }
}
