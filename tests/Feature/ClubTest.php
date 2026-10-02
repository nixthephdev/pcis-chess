<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClubAttendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClubTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private Classroom $classroom;

    private Student $maya;

    protected function setUp(): void
    {
        parent::setUp();
        // A Thursday, so "this week" starts on Monday 2026-09-28.
        Carbon::setTestNow('2026-10-01 10:00:00');

        $this->coach = User::factory()->create();
        $this->classroom = $this->coach->classrooms()->create(['name' => 'Chess Club', 'code' => 'ABC234']);
        $this->maya = $this->classroom->students()->create(['name' => 'Maya R.', 'avatar' => 'knight', 'pin' => '0421', 'grade' => 'MYP 3']);
    }

    private function mark(Student $s, string $week, string $mark): void
    {
        ClubAttendance::create(['student_id' => $s->id, 'week' => $week, 'mark' => $mark]);
    }

    public function test_guests_can_open_the_club_without_a_register(): void
    {
        $this->get('/club')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Club')
            ->where('weeks', ['2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28'])
            ->where('roster', null)
            ->where('board', null)
            ->where('me', null));
    }

    public function test_coach_sees_their_register(): void
    {
        $this->mark($this->maya, '2026-09-28', 'P');

        $this->actingAs($this->coach)->get('/club')->assertInertia(fn (Assert $p) => $p
            ->where('roster.classroomId', $this->classroom->id)
            ->where('roster.members.0.name', 'Maya R.')
            ->where('roster.members.0.grade', 'MYP 3')
            ->where('roster.members.0.attendance.2026-09-28', 'P')
            ->where('board.title', 'Chess Club'));
    }

    public function test_coach_marks_and_clears_attendance(): void
    {
        $this->actingAs($this->coach)->post("/club/students/{$this->maya->id}/attendance", ['week' => '2026-09-28', 'mark' => 'P'])->assertRedirect();
        $this->actingAs($this->coach)->post("/club/students/{$this->maya->id}/attendance", ['week' => '2026-09-28', 'mark' => 'L']);
        $this->assertSame(['2026-09-28' => 'L'], ClubAttendance::pluck('mark', 'week')->mapWithKeys(fn ($m, $w) => [substr($w, 0, 10) => $m])->all());

        $this->actingAs($this->coach)->post("/club/students/{$this->maya->id}/attendance", ['week' => '2026-09-28', 'mark' => null]);
        $this->assertSame(0, ClubAttendance::count());
    }

    public function test_attendance_must_be_a_past_monday(): void
    {
        $this->actingAs($this->coach)->post("/club/students/{$this->maya->id}/attendance", ['week' => '2026-09-29', 'mark' => 'P'])->assertSessionHasErrors('week');
        $this->actingAs($this->coach)->post("/club/students/{$this->maya->id}/attendance", ['week' => '2026-10-05', 'mark' => 'P'])->assertSessionHasErrors('week');
        $this->assertSame(0, ClubAttendance::count());
    }

    public function test_coach_cannot_touch_another_coachs_students(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)->post("/club/students/{$this->maya->id}/attendance", ['week' => '2026-09-28', 'mark' => 'P'])->assertForbidden();
        $this->actingAs($other)->patch("/club/students/{$this->maya->id}", ['grade' => 'PYP 3'])->assertForbidden();
        $this->actingAs($other)->get("/club?classroom={$this->classroom->id}")->assertInertia(fn (Assert $p) => $p->where('roster.classroomId', null));
    }

    public function test_coach_sets_grade_and_registration(): void
    {
        $this->actingAs($this->coach)->patch("/club/students/{$this->maya->id}", ['grade' => 'PYP 5', 'registered' => false])->assertRedirect();
        $this->assertSame('PYP 5', $this->maya->fresh()->grade);
        $this->assertFalse($this->maya->fresh()->registered);

        $this->actingAs($this->coach)->patch("/club/students/{$this->maya->id}", ['grade' => 'Year 9'])->assertSessionHasErrors('grade');
    }

    public function test_adding_a_student_from_the_club_sets_their_grade(): void
    {
        $this->actingAs($this->coach)->post("/classrooms/{$this->classroom->id}/students", ['names' => 'Levi A.', 'grade' => 'PYP 5']);

        $this->assertSame('PYP 5', $this->classroom->students()->where('name', 'Levi A.')->value('grade'));
    }

    public function test_puzzle_xp_is_given_once_with_a_first_try_top_up(): void
    {
        $as = $this->actingAs($this->maya, 'student');

        $as->postJson('/club/progress', ['kind' => 'puzzle', 'id' => 'gm-skewer', 'first_try' => false])
            ->assertOk()->assertJsonPath('xp', 15)->assertJsonPath('weekly.puzzles', 1);
        $as->postJson('/club/progress', ['kind' => 'puzzle', 'id' => 'gm-skewer', 'first_try' => false])->assertJsonPath('xp', 15);
        $as->postJson('/club/progress', ['kind' => 'puzzle', 'id' => 'gm-skewer', 'first_try' => true])
            ->assertJsonPath('xp', 30)->assertJsonPath('solved.gm-skewer.firstTry', true);
        $as->postJson('/club/progress', ['kind' => 'lesson', 'id' => 'sicilian', 'first_try' => true])
            ->assertJsonPath('xp', 80)->assertJsonPath('lessons', ['sicilian'])->assertJsonPath('weekly.xp', 80);
        $as->postJson('/club/progress', ['kind' => 'lesson', 'id' => 'sicilian', 'first_try' => true])->assertJsonPath('xp', 80);
    }

    public function test_progress_rejects_unknown_items_and_guests(): void
    {
        $this->actingAs($this->maya, 'student')->postJson('/club/progress', ['kind' => 'puzzle', 'id' => 'free-xp', 'first_try' => true])->assertStatus(422);
        $this->actingAs($this->maya, 'student')->postJson('/club/progress', ['kind' => 'puzzle', 'id' => 'sicilian', 'first_try' => true])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->postJson('/club/progress', ['kind' => 'puzzle', 'id' => 'gm-skewer', 'first_try' => true])->assertUnauthorized();
    }

    public function test_leaderboard_adds_attendance_streaks_and_puzzle_xp(): void
    {
        $liam = $this->classroom->students()->create(['name' => 'Liam T.', 'avatar' => 'rook', 'pin' => '1111']);
        // Maya: present the last three weeks (this week not marked yet) = 3 × 50 + streak 3 × 20 = 210.
        foreach (['2026-09-07', '2026-09-14', '2026-09-21'] as $w) {
            $this->mark($this->maya, $w, 'P');
        }
        // Liam: late this week, absent last week = 30 + streak 1 × 20 = 50, plus a first-try puzzle (30) = 80.
        $this->mark($liam, '2026-09-28', 'L');
        $this->mark($liam, '2026-09-21', 'A');
        $liam->clubProgress()->create(['kind' => 'puzzle', 'item_id' => 'gm-skewer', 'xp' => 30, 'first_try' => true]);

        $this->actingAs($liam, 'student')->get('/club')->assertInertia(fn (Assert $p) => $p
            ->where('me.name', 'Liam T.')
            ->where('myProgress.xp', 30)
            ->where('roster', null)
            ->where('board.rows.0.name', 'Maya R.')
            ->where('board.rows.0.xp', 210)
            ->where('board.rows.0.streak', 3)
            ->where('board.rows.1.xp', 80)
            ->where('board.rows.1.me', true));
    }

    public function test_players_without_a_class_see_top_players(): void
    {
        $player = Student::create(['name' => 'magnus_jr', 'username' => 'magnus_jr', 'password' => 'secret123', 'avatar' => 'rook']);

        $this->actingAs($player, 'student')->get('/club')->assertInertia(fn (Assert $p) => $p
            ->where('board.title', 'Top players')
            ->has('board.rows', 1)
            ->where('board.rows.0.me', true));
    }
}
