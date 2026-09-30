<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Support\LevelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'classrooms' => $request->user()->classrooms()
                ->withCount('students')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $classroom = $request->user()->classrooms()->create([
            'name' => $data['name'],
            'code' => Classroom::newCode(),
        ]);

        return redirect()->route('classrooms.show', $classroom);
    }

    public function show(Request $request, Classroom $classroom): Response
    {
        $this->authorizeOwner($request, $classroom);

        $worldIds = array_column(LevelCatalog::worlds(), 'id');

        $students = $classroom->students()->with('progress:id,student_id,level_id,stars')->orderBy('name')->get()
            ->map(function (Student $s) use ($worldIds) {
                $byWorld = array_fill_keys($worldIds, 0);
                foreach ($s->progress as $p) {
                    $world = substr($p->level_id, 0, strrpos($p->level_id, '-'));
                    if (isset($byWorld[$world])) {
                        $byWorld[$world] += $p->stars;
                    }
                }

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'avatar' => $s->avatar,
                    'pin' => $s->pin,
                    'stars' => array_sum($byWorld),
                    'levels' => $s->progress->count(),
                    'worlds' => $byWorld,
                    'last_played_at' => $s->last_played_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Classrooms/Show', [
            'classroom' => $classroom->only('id', 'name', 'code'),
            'students' => $students,
            'worlds' => LevelCatalog::worlds(),
            'maxStars' => LevelCatalog::maxStars(),
            'joinUrl' => route('join'),
        ]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeOwner($request, $classroom);
        $classroom->update($request->validate(['name' => ['required', 'string', 'max:60']]));

        return back();
    }

    public function destroy(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeOwner($request, $classroom);
        $classroom->delete();

        return redirect()->route('dashboard');
    }

    public function cards(Request $request, Classroom $classroom): Response
    {
        $this->authorizeOwner($request, $classroom);

        return Inertia::render('Classrooms/Cards', [
            'classroom' => $classroom->only('id', 'name', 'code'),
            'students' => $classroom->students()->orderBy('name')->get()->map->only('id', 'name', 'avatar', 'pin'),
            'joinUrl' => route('join'),
        ]);
    }

    public function addStudents(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeOwner($request, $classroom);

        $data = $request->validate(['names' => ['required', 'string', 'max:3000']]);

        $names = collect(preg_split('/\r\n|\r|\n|,/', $data['names']))
            ->map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n)))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n))
            ->take(60);

        if ($names->contains(fn ($n) => mb_strlen($n) > 30)) {
            return back()->withErrors(['names' => 'Each name must be 30 characters or less. Use a first name and last initial, like "Maya R."']);
        }

        $existing = $classroom->students()->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $added = 0;
        foreach ($names as $name) {
            if (in_array(mb_strtolower($name), $existing, true)) {
                continue;
            }
            $classroom->students()->create(['name' => $name, 'avatar' => Student::randomAvatar(), 'pin' => Student::newPin()]);
            $added++;
        }

        $skipped = $names->count() - $added;

        return back()->with('status', "Added {$added} student".($added === 1 ? '' : 's').($skipped ? ". Skipped {$skipped} already in the class." : '.'));
    }

    public function resetPin(Request $request, Classroom $classroom, Student $student): RedirectResponse
    {
        $this->authorizeOwner($request, $classroom);
        $student->update(['pin' => Student::newPin()]);

        return back()->with('status', "{$student->name}'s new PIN is {$student->pin}.");
    }

    public function removeStudent(Request $request, Classroom $classroom, Student $student): RedirectResponse
    {
        $this->authorizeOwner($request, $classroom);
        $student->delete();

        return back()->with('status', "Removed {$student->name}.");
    }

    private function authorizeOwner(Request $request, Classroom $classroom): void
    {
        abort_unless($classroom->user_id === $request->user()->id, 403);
    }
}
