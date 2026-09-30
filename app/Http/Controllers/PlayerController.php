<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\LevelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Players who sign up with a username and password instead of joining a class. */
class PlayerController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('quest');
        }

        return Inertia::render('Player/SignUp', ['avatars' => Student::AVATARS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/', 'unique:students,username'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'avatar' => ['required', Rule::in(Student::AVATARS)],
            // Stars earned as a guest on this device come along to the new account.
            'stars' => ['nullable', 'array', 'max:500'],
            'stars.*' => ['integer', 'between:1,3'],
        ], [
            'username.regex' => 'Use letters, numbers, - and _ only, starting with a letter or number.',
            'username.unique' => 'Someone already has that username. Try another!',
        ]);

        $student = DB::transaction(function () use ($data) {
            $student = Student::create([
                'name' => $data['username'],
                'username' => $data['username'],
                'password' => $data['password'],
                'avatar' => $data['avatar'],
            ]);

            $valid = array_flip(LevelCatalog::levelIds());
            foreach (array_intersect_key($data['stars'] ?? [], $valid) as $levelId => $stars) {
                $student->progress()->create(['level_id' => $levelId, 'stars' => $stars, 'plays' => 1]);
            }

            return $student;
        });

        Auth::guard('student')->login($student, remember: true);
        $request->session()->regenerate();

        return redirect()->route('quest');
    }

    public function signIn(): Response|RedirectResponse
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('quest');
        }

        return Inertia::render('Player/SignIn');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        $key = 'player-login:'.Str::lower($data['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'username' => 'Too many tries. Wait '.RateLimiter::availableIn($key).' seconds and try again.',
            ]);
        }

        $student = Student::where('username', $data['username'])->first();

        if (! $student || ! Hash::check($data['password'], (string) $student->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['username' => "That username and password don't match."]);
        }

        RateLimiter::clear($key);
        Auth::guard('student')->login($student, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('quest'));
    }

    public function show(string $username): Response
    {
        $student = Student::where('username', $username)->firstOrFail();

        return Inertia::render('Player/Profile', [
            'player' => [
                'username' => $student->username,
                'avatar' => $student->avatar,
                'joined' => $student->created_at->toDateString(),
                'lastPlayed' => $student->last_played_at?->toDateString(),
            ],
            'progress' => $student->starsByLevel(),
            'isMe' => Auth::guard('student')->id() === $student->id,
        ]);
    }

    public function edit(): Response
    {
        $student = $this->player();

        return Inertia::render('Player/Settings', [
            'player' => ['username' => $student->username, 'avatar' => $student->avatar],
            'avatars' => Student::AVATARS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['avatar' => ['required', Rule::in(Student::AVATARS)]]);

        $this->player()->update($data);

        return back()->with('status', 'Avatar saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $student = $this->player();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($data['current_password'], (string) $student->password)) {
            throw ValidationException::withMessages(['current_password' => "That isn't your current password."]);
        }

        $student->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $student = $this->player();

        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->string('password'), (string) $student->password)) {
            throw ValidationException::withMessages(['password' => "That isn't your password."]);
        }

        Auth::guard('student')->logout();
        $student->delete();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /** The signed-in student, who must have their own account (class students are managed by their coach). */
    private function player(): Student
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        abort_unless($student->isPlayer(), 403);

        return $student;
    }
}
