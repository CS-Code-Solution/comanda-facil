<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('content_revision')->default(1);
            $table->timestampTz('status_changed_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('served_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->index(['status', 'created_at']);
        });
        // Install the complete set of dining-room fulfillment states.
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT IF EXISTS dining_orders_status_check');
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_orders_status_check CHECK (status IN ('confirmed','preparing','ready','served','cancelled'))");
        DB::statement('ALTER TABLE dining_orders ADD CONSTRAINT dining_orders_versions_check CHECK (version > 0 AND content_revision > 0)');
        Schema::create('order_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('dining_orders');
            $table->unsignedInteger('version');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action', 40);
            $table->text('reason')->nullable();
            $table->jsonb('before');
            $table->jsonb('after');
            $table->timestampTz('created_at');
            $table->unique(['order_id', 'version']);
        });
        Schema::create('order_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->constrained('dining_orders');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('fingerprint', 64);
            $table->unsignedInteger('result_version');
            $table->timestampTz('created_at');
        });
        Schema::create('order_change_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->constrained('dining_orders');
            $table->foreignId('actor_id')->constrained('users');
            $table->unsignedInteger('auth_version');
            $table->unsignedInteger('expected_version');
            $table->text('reason');
            $table->jsonb('request');
            $table->jsonb('snapshot');
            $table->timestampTz('expires_at');
        });
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('dining_orders');
            $table->unsignedInteger('revision');
            $table->string('target', 16);
            $table->string('kind', 20);
            $table->unsignedInteger('copy')->default(1);
            $table->string('status', 20)->default('pending');
            $table->jsonb('snapshot');
            $table->uuid('request_id')->nullable()->unique();
            $table->unsignedBigInteger('origin_job_id')->nullable();
            $table->foreignId('requested_by_id')->nullable()->constrained('users');
            $table->text('reason')->nullable();
            $table->foreignId('claimed_by_id')->nullable()->constrained('users');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['order_id', 'revision', 'target', 'copy']);
            $table->index(['target', 'status', 'created_at']);
        });
        DB::statement("ALTER TABLE print_jobs ADD CONSTRAINT print_jobs_values_check CHECK (target IN ('kitchen','cashier') AND status IN ('pending','printing','printed','failed','superseded') AND copy > 0)");
        Schema::create('print_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('print_jobs');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action', 30);
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');
        });
        Schema::create('operations_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_reference')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('leased_until')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestampTz('created_at');
            $table->index(['delivered_at', 'available_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('dining_orders')->where('status', '!=', 'confirmed')->exists()) {
            throw new RuntimeException('Não reverter preparo/cancelamento existente. Planeje restauração a partir de backup.');
        }
        foreach (['operations_outbox', 'print_events', 'print_jobs', 'order_change_intents', 'order_operations', 'order_history'] as $table) {
            Schema::dropIfExists($table);
        }
        // Downgrade cannot silently discard fulfillment state.
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_orders_versions_check');
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_orders_status_check');
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['version', 'content_revision', 'status_changed_at', 'started_at', 'ready_at', 'served_at', 'cancelled_at']);
        });
    }
};
