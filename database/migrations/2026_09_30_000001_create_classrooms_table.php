<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('code', 8)->unique();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('avatar', 12)->default('knight');
            $table->text('pin');
            $table->timestamp('last_played_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['classroom_id', 'name']);
        });

        Schema::create('level_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('level_id', 40);
            $table->unsignedTinyInteger('stars');
            $table->unsignedSmallInteger('best_moves')->nullable();
            $table->unsignedInteger('plays')->default(1);
            $table->timestamps();

            $table->unique(['student_id', 'level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_progress');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classrooms');
    }
};
