<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A demo coach and class for trying the app locally:
 *   php artisan db:seed --class=DemoSeeder
 * Coach login: coach@chessquest.test / password. Class code: CHESS2.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $coach = User::firstOrCreate(
            ['email' => 'coach@chessquest.test'],
            ['name' => 'Demo Coach', 'password' => 'password', 'email_verified_at' => now()],
        );

        $classroom = Classroom::firstOrCreate(['code' => 'CHESS2'], ['name' => 'Demo Chess Club', 'user_id' => $coach->id]);

        $students = [
            ['Maya R.', 'queen', '1111', ['rook-1' => 3, 'rook-2' => 3, 'rook-3' => 2, 'bishop-1' => 3]],
            ['Liam T.', 'knight', '2222', ['rook-1' => 3, 'rook-2' => 2]],
            ['Aiko S.', 'rook', '3333', []],
        ];

        foreach ($students as [$name, $avatar, $pin, $progress]) {
            $student = $classroom->students()->firstOrCreate(['name' => $name], ['avatar' => $avatar, 'pin' => $pin]);
            foreach ($progress as $level => $stars) {
                $student->progress()->updateOrCreate(['level_id' => $level], ['stars' => $stars]);
            }
            if ($progress) {
                $student->forceFill(['last_played_at' => now()->subHours(count($progress))])->save();
            }
        }
    }
}
