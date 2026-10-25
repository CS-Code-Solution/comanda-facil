<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_access_events', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_access_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_id');
            $table->dropColumn(['before', 'after']);
        });
    }
};
