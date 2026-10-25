<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_heartbeats', function (Blueprint $table) {
            $table->string('name', 40)->primary();
            $table->timestampTz('seen_at');
            $table->jsonb('details');
        });
        Schema::create('pilot_checks', function (Blueprint $table) {
            $table->string('name', 40)->primary();
            $table->boolean('passed')->default(false);
            $table->integer('version')->default(1);
            $table->text('evidence')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->timestampTz('checked_at')->nullable();
        });
        foreach (['catalog_review', 'thermal_print', 'devices_network', 'external_backup', 'staff_training', 'restaurant_acceptance'] as $name) {
            DB::table('pilot_checks')->insert(['name' => $name]);
        }
        Schema::create('operations_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action', 40);
            $table->jsonb('details');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('operations_events')->exists() || DB::table('pilot_checks')->whereNotNull('actor_id')->exists()) {
            throw new RuntimeException('Há evidência operacional. Planeje compatibilidade antes de reverter.');
        }
        Schema::dropIfExists('operations_events');
        Schema::dropIfExists('pilot_checks');
        Schema::dropIfExists('operations_heartbeats');
    }
};
