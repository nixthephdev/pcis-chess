<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Players can sign up on their own (username + password) instead of joining a class.
 * They are students with no classroom and no PIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->change();
            $table->text('pin')->nullable()->change();
            $table->string('username', 20)->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('pin');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'password']);
        });
    }
};
