<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $username = 'magnus_jr'): Student
    {
        return Student::create(['name' => $username, 'username' => $username, 'password' => 'secret123', 'avatar' => 'rook']);
    }

    public function test_player_signs_up_and_brings_guest_stars(): void
    {
        $this->post('/signup', [
            'username' => 'Magnus_Jr',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'avatar' => 'queen',
            'stars' => ['rook-1' => 3, 'rook-2' => 2, 'made-up-9' => 3],
        ])->assertRedirect('/quest');

        $student = Student::where('username', 'Magnus_Jr')->firstOrFail();
        $this->assertAuthenticatedAs($student, 'student');
        $this->assertNull($student->classroom_id);
        $this->assertTrue($student->isPlayer());
        $this->assertSame(['rook-1' => 3, 'rook-2' => 2], $student->starsByLevel());
    }

    public function test_usernames_are_unique_and_checked(): void
    {
        $this->player('taken');

        $base = ['password' => 'secret123', 'password_confirmation' => 'secret123', 'avatar' => 'pawn'];
        $this->post('/signup', ['username' => 'taken'] + $base)->assertSessionHasErrors('username');
        $this->post('/signup', ['username' => 'no spaces'] + $base)->assertSessionHasErrors('username');
        $this->post('/signup', ['username' => '_edge'] + $base)->assertSessionHasErrors('username');
        $this->assertGuest('student');
    }

    public function test_player_signs_in_and_is_locked_out_after_five_misses(): void
    {
        $student = $this->player();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/signin', ['username' => 'magnus_jr', 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        $this->post('/signin', ['username' => 'magnus_jr', 'password' => 'secret123'])->assertSessionHasErrors('username');
        $this->assertGuest('student');

        \RateLimiter::clear('player-login:magnus_jr|127.0.0.1');
        $this->post('/signin', ['username' => 'magnus_jr', 'password' => 'secret123'])->assertRedirect('/quest');
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_class_students_cannot_sign_in_with_a_password(): void
    {
        $classroom = User::factory()->create()->classrooms()->create(['name' => 'Club', 'code' => 'ABC234']);
        $classroom->students()->create(['name' => 'Maya', 'avatar' => 'knight', 'pin' => '0421']);

        $this->post('/signin', ['username' => 'Maya', 'password' => ''])->assertSessionHasErrors();
        $this->post('/signin', ['username' => 'Maya', 'password' => '0421'])->assertSessionHasErrors('username');
        $this->assertGuest('student');
    }

    public function test_players_share_a_leaderboard_separate_from_classes(): void
    {
        $me = $this->player('me');
        $this->player('other')->progress()->create(['level_id' => 'rook-1', 'stars' => 3]);
        $classroom = User::factory()->create()->classrooms()->create(['name' => 'Club', 'code' => 'ABC234']);
        $classroom->students()->create(['name' => 'Maya', 'avatar' => 'knight', 'pin' => '0421'])
            ->progress()->create(['level_id' => 'rook-1', 'stars' => 3]);

        $this->actingAs($me, 'student')->postJson('/quest/progress', ['level_id' => 'rook-1', 'stars' => 1])
            ->assertOk()
            ->assertJsonCount(2, 'leaderboard.rows')
            ->assertJsonPath('leaderboard.rows.0.name', 'other')
            ->assertJsonPath('leaderboard.me', 2);
    }

    public function test_profile_is_public(): void
    {
        $this->player()->progress()->create(['level_id' => 'rook-1', 'stars' => 2]);

        $this->get('/@/magnus_jr')->assertOk()->assertSee('magnus_jr');
        $this->get('/@/nobody')->assertNotFound();
    }

    public function test_player_changes_avatar_and_password(): void
    {
        $student = $this->player();

        $this->actingAs($student, 'student')->patch('/account', ['avatar' => 'king'])->assertSessionHasNoErrors();
        $this->assertSame('king', $student->fresh()->avatar);

        $this->actingAs($student, 'student')->put('/account/password', [
            'current_password' => 'nope', 'password' => 'newpass1', 'password_confirmation' => 'newpass1',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($student, 'student')->put('/account/password', [
            'current_password' => 'secret123', 'password' => 'newpass1', 'password_confirmation' => 'newpass1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('newpass1', $student->fresh()->password));
    }

    public function test_player_closes_account(): void
    {
        $student = $this->player();

        $this->actingAs($student, 'student')->delete('/account', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($student, 'student')->delete('/account', ['password' => 'secret123'])->assertRedirect('/');

        $this->assertModelMissing($student);
        $this->assertGuest('student');
    }

    public function test_account_pages_need_a_player(): void
    {
        $this->get('/account')->assertRedirect('/signin');

        $classroom = User::factory()->create()->classrooms()->create(['name' => 'Club', 'code' => 'ABC234']);
        $classStudent = $classroom->students()->create(['name' => 'Maya', 'avatar' => 'knight', 'pin' => '0421']);
        $this->actingAs($classStudent, 'student')->get('/account')->assertForbidden();
    }
}
