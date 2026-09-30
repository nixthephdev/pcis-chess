# Chess Quest

A chess learning game for students in grades 3 to 10. Students learn each piece by collecting stars, then practise captures, check and mate-in-one. Coaches create classes, add students, print login cards and follow progress.

## Stack

- Laravel 12 on PHP 8.3, MySQL (XAMPP's MariaDB)
- Inertia.js with Vue 3 and TypeScript, Tailwind CSS, Vite
- PHPUnit for the backend, Vitest for the game logic

## Running it locally

XAMPP's own PHP is 8.0, which is too old for Laravel 12, so this project uses the separate PHP 8.3 in `C:\php83`. XAMPP still provides MySQL: start MySQL in the XAMPP control panel first.

```powershell
cd C:\xampp\htdocs\chess-quest
C:\php83\php.exe artisan migrate
C:\php83\php.exe artisan db:seed --class=DemoSeeder   # optional demo class
C:\php83\php.exe artisan serve                        # http://localhost:8000
npm run dev                                           # second terminal, for live reload while editing
```

Opening the project through Apache (`http://localhost/chess-quest/public`) does not work, because Apache runs XAMPP's PHP 8.0.

To test on a tablet or phone on the same Wi-Fi, run `C:\php83\php.exe artisan serve --host=0.0.0.0` and open `http://<this PC's IP>:8000` on the device.

Composer must also run on PHP 8.3:

```powershell
C:\php83\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar install
```

### Demo accounts (after `DemoSeeder`)

- Coach: `coach@chessquest.test` / `password`
- Class code: `CHESS2`
- Students: Maya R. (PIN 1111), Liam T. (2222), Aiko S. (3333)

## Tests

```powershell
C:\php83\php.exe artisan test   # classes, student login, progress saving
npm test                        # every level is well formed and solvable
```

## How it fits together

| Where | What |
|---|---|
| `resources/js/game/levels.json` | All worlds and levels. The game and the server both read this file. Level ids are `<world>-<number>`, e.g. `rook-3`. |
| `resources/js/game/engine.ts` | Lesson rules: moves, guarded squares, check and mate, and the solver that sets each level's 3-star target. |
| `resources/js/Components/Game/` | Board (tap or drag), level player, icons. |
| `resources/js/Pages/Quest.vue` | World map, leaderboard and progress saving. |
| `app/Http/Controllers/JoinController.php` | Student login: class code, pick your name, 4-digit PIN (5 tries per minute). |
| `app/Http/Controllers/ClassroomController.php` | Coach pages: classes, students, PINs, progress. |

### Adding a level

Add an entry to a world's `levels` array in `levels.json`. Each board lists ranks `"8"` to `"1"` as 8-character rows: uppercase letters are the student's pieces, lowercase are black pieces, `*` is a star and `.` is empty. Then run `npm test`: it fails if the new level can't be solved.

A coach can link straight to a level with `/quest?level=<id>`, e.g. `/quest?level=knight-2`.

## Student privacy

Students have no email or password. The coach adds them by first name and last initial and gives each a 4-digit PIN. PINs are stored encrypted so the coach can print login cards.

## Credits

Chess piece artwork by Cburnett, [CC BY-SA 3.0](https://creativecommons.org/licenses/by-sa/3.0/), via cm-chessboard.
