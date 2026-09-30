<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomFlowTest extends TestCase
{
    use RefreshDatabase;

    private function classroomWithStudent(): array
    {
        $teacher = User::factory()->create();
        $classroom = $teacher->classrooms()->create(['name' => 'Grade 4', 'code' => 'ABC234']);
        $student = $classroom->students()->create(['name' => 'Maya R.', 'avatar' => 'knight', 'pin' => '0421']);

        return [$teacher, $classroom, $student];
    }

    public function test_teacher_creates_a_class_and_adds_students(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->post('/classrooms', ['name' => 'Chess Club'])->assertRedirect();
        $classroom = Classroom::firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $classroom->code);

        $this->actingAs($teacher)
            ->post("/classrooms/{$classroom->id}/students", ['names' => "Maya R.\nLiam T.\nmaya r.\n\n"])
            ->assertSessionHas('status', 'Added 2 students.');

        $this->assertSame(['Liam T.', 'Maya R.'], $classroom->students()->orderBy('name')->pluck('name')->all());
        $this->assertMatchesRegularExpression('/^\d{4}$/', $classroom->students()->first()->pin);
    }

    public function test_teacher_cannot_open_another_teachers_class(): void
    {
        [, $classroom] = $this->classroomWithStudent();

        $this->actingAs(User::factory()->create())->get("/classrooms/{$classroom->id}")->assertForbidden();
    }

    public function test_pin_is_encrypted_at_rest(): void
    {
        [, , $student] = $this->classroomWithStudent();

        $raw = \DB::table('students')->where('id', $student->id)->value('pin');
        $this->assertNotSame('0421', $raw);
        $this->assertSame('0421', $student->fresh()->pin);
    }

    public function test_unknown_class_code_shows_a_friendly_error(): void
    {
        $this->post('/join', ['code' => 'nope99'])->assertSessionHasErrors('code');
    }

    public function test_class_code_is_case_and_space_insensitive(): void
    {
        $this->classroomWithStudent();

        $this->post('/join', ['code' => ' abc-234 '])->assertRedirect('/join/ABC234');
    }

    public function test_student_logs_in_with_pin_and_is_locked_out_after_five_misses(): void
    {
        [, , $student] = $this->classroomWithStudent();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/join/ABC234', ['student_id' => $student->id, 'pin' => '9999'])->assertSessionHasErrors('pin');
        }
        $this->post('/join/ABC234', ['student_id' => $student->id, 'pin' => '0421'])->assertSessionHasErrors('pin');
        $this->assertGuest('student');

        \RateLimiter::clear('student-login:'.$student->id.'|127.0.0.1');
        $this->post('/join/ABC234', ['student_id' => $student->id, 'pin' => '0421'])->assertRedirect('/quest');
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_student_cannot_log_in_through_another_class(): void
    {
        [, , $student] = $this->classroomWithStudent();
        User::factory()->create()->classrooms()->create(['name' => 'Other', 'code' => 'XYZ789']);

        $this->post('/join/XYZ789', ['student_id' => $student->id, 'pin' => '0421'])->assertNotFound();
    }

    public function test_progress_keeps_the_best_result(): void
    {
        [, , $student] = $this->classroomWithStudent();

        $this->actingAs($student, 'student')->postJson('/quest/progress', ['level_id' => 'rook-2', 'stars' => 3, 'moves' => 3])
            ->assertOk()
            ->assertJsonPath('progress.rook-2', 3)
            ->assertJsonPath('leaderboard.me', 1);

        $this->actingAs($student, 'student')->postJson('/quest/progress', ['level_id' => 'rook-2', 'stars' => 1, 'moves' => 9])
            ->assertJsonPath('progress.rook-2', 3);

        $row = $student->progress()->firstOrFail();
        $this->assertSame(3, (int) $row->best_moves);
        $this->assertSame(2, (int) $row->plays);
    }

    public function test_progress_rejects_unknown_levels_and_guests(): void
    {
        [, , $student] = $this->classroomWithStudent();

        $this->actingAs($student, 'student')->postJson('/quest/progress', ['level_id' => 'rook-99', 'stars' => 3])
            ->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->postJson('/quest/progress', ['level_id' => 'rook-1', 'stars' => 3])->assertUnauthorized();
    }

    public function test_guests_can_open_the_quest(): void
    {
        $this->get('/quest')->assertOk();
    }
}
