<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class JoinController extends Controller
{
    public function code(): Response|RedirectResponse
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('quest');
        }

        return Inertia::render('Join/Code');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $code = Classroom::normalizeCode($request->string('code'));

        if (! Classroom::where('code', $code)->exists()) {
            return back()->withErrors(['code' => "We couldn't find that class code. Check it with your coach!"]);
        }

        return redirect()->route('join.class', $code);
    }

    public function pick(string $code): Response
    {
        $classroom = Classroom::where('code', Classroom::normalizeCode($code))->firstOrFail();

        return Inertia::render('Join/Pick', [
            'classroom' => ['name' => $classroom->name, 'code' => $classroom->code],
            'students' => $classroom->students()->orderBy('name')->get(['id', 'name', 'avatar']),
        ]);
    }

    public function login(Request $request, string $code): RedirectResponse
    {
        $classroom = Classroom::where('code', Classroom::normalizeCode($code))->firstOrFail();

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'pin' => ['required', 'digits:4'],
        ]);

        $student = $classroom->students()->findOrFail($data['student_id']);

        $key = 'student-login:'.$student->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $wait = RateLimiter::availableIn($key);

            return back()->withErrors(['pin' => "Too many tries. Wait {$wait} seconds, or ask your coach for your PIN."]);
        }

        if (! $student->pinMatches($data['pin'])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['pin' => "That PIN isn't right. Try again!"]);
        }

        RateLimiter::clear($key);
        Auth::guard('student')->login($student, remember: true);
        $request->session()->regenerate();

        return redirect()->route('quest');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
