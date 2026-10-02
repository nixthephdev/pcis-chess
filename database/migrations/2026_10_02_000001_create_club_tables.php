<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('grade', 6)->nullable()->after('avatar');
            $table->boolean('registered')->default(true)->after('grade');
        });

        // One row per student per club week; the week is that week's Monday.
        Schema::create('club_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('week');
            $table->char('mark', 1);
            $table->timestamps();

            $table->unique(['student_id', 'week']);
        });

        // A Chess Club puzzle or lesson a student has finished. created_at is when they first finished it.
        Schema::create('club_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('item_id', 40);
            $table->unsignedSmallInteger('xp');
            $table->boolean('first_try')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'kind', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_progress');
        Schema::dropIfExists('club_attendances');
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['grade', 'registered']);
        });
    }
};
