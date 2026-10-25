<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 40)->nullable()->unique();
            $table->string('pin_hash')->nullable();
            $table->unsignedInteger('auth_version')->default(1);
            $table->string('email')->nullable()->change();
        });

        Schema::create('staff_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 32);
            $table->string('source', 20);
            $table->timestampTz('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_access_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'pin_hash', 'auth_version']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
