<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_settings', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->boolean('accepting_orders')->default(false);
        });
        DB::table('dining_settings')->insert(['id' => 1, 'accepting_orders' => false]);
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->unique();
            $table->string('label', 80)->nullable();
            $table->text('qr_token');
            $table->string('qr_hash', 64)->unique();
            $table->integer('qr_version')->default(1);
            $table->bigInteger('cycle')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blocked')->default(false);
            $table->timestampsTz();
        });
        Schema::create('dining_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->bigInteger('cycle');
            $table->timestampTz('opened_at');
            $table->foreignId('opened_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unique(['table_id', 'cycle']);
            $table->unique(['id', 'table_id']);
        });
        Schema::create('dining_order_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->bigInteger('cycle');
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('client_hash', 64)->nullable();
            $table->string('source', 10);
            $table->jsonb('request');
            $table->jsonb('snapshot');
            $table->bigInteger('total_cents');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at');
        });
        Schema::create('dining_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('intent_id')->unique();
            $table->foreign('intent_id')->references('id')->on('dining_order_intents')->restrictOnDelete();
            $table->foreignId('table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->unsignedBigInteger('session_id');
            $table->foreign(['session_id', 'table_id'])->references(['id', 'table_id'])->on('dining_sessions')->restrictOnDelete();
            $table->integer('table_number');
            $table->string('modality', 20)->default('dining_room');
            $table->string('source', 10);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('client_hash', 64)->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->string('currency', 3)->default('BRL');
            $table->bigInteger('total_cents');
            $table->timestampTz('created_at');
            $table->index(['table_id', 'source', 'created_at']);
            $table->index(['session_id', 'created_at']);
        });
        Schema::create('dining_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('dining_orders')->restrictOnDelete();
            $table->integer('position');
            $table->foreignId('product_id')->constrained('catalog_products')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('catalog_variants')->restrictOnDelete();
            $table->integer('quantity');
            $table->string('note', 500)->nullable();
            $table->jsonb('snapshot');
            $table->bigInteger('total_cents');
            $table->unique(['order_id', 'position']);
        });
        Schema::create('dining_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('dining_sessions')->restrictOnDelete();
            $table->bigInteger('cycle');
            $table->string('client_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->foreignId('claimed_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->index(['status', 'created_at']);
        });
        Schema::create('dining_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->foreignId('session_id')->constrained('dining_sessions')->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('created_at');
            $table->index(['acknowledged_at', 'created_at']);
        });
        Schema::create('dining_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->nullable()->constrained('dining_tables')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('client_hash', 64)->nullable();
            $table->string('action', 40);
            $table->jsonb('details');
            $table->timestampTz('occurred_at');
            $table->index(['table_id', 'id']);
        });
        DB::statement('alter table dining_tables add constraint dining_table_values_check check (number between 1 and 9999 and cycle >= 0 and qr_version > 0)');
        DB::statement('create unique index dining_one_open_session on dining_sessions(table_id) where closed_at is null');
        DB::statement('alter table dining_sessions add constraint dining_session_close_check check ((closed_at is null and closed_by_id is null) or (closed_at >= opened_at and closed_by_id is not null))');
        DB::statement("alter table dining_order_intents add constraint dining_intent_actor_check check ((source='qr' and actor_id is null and client_hash is not null) or (source='staff' and actor_id is not null and client_hash is null))");
        DB::statement('alter table dining_order_intents add constraint dining_intent_total_check check (total_cents >= 0 and expires_at > created_at)');
        DB::statement("alter table dining_orders add constraint dining_order_actor_check check ((source='qr' and actor_id is null and client_hash is not null) or (source='staff' and actor_id is not null and client_hash is null))");
        DB::statement("alter table dining_orders add constraint dining_order_values_check check (total_cents >= 0 and modality='dining_room' and currency='BRL')");
        DB::statement('alter table dining_order_items add constraint dining_item_values_check check (total_cents >= 0 and quantity between 1 and 99 and position >= 0)');
        DB::statement("alter table dining_calls add constraint dining_call_status_check check (status in ('pending','claimed','completed'))");
        DB::statement("create unique index dining_one_open_call on dining_calls(table_id,cycle) where status in ('pending','claimed')");
    }

    public function down(): void
    {
        foreach (['dining_events', 'dining_alerts', 'dining_calls', 'dining_order_items', 'dining_orders', 'dining_order_intents', 'dining_sessions', 'dining_tables', 'dining_settings'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
