<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_settings', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->boolean('accepting_orders')->default(false);
        });
        DB::table('delivery_settings')->insert(['id' => 1, 'accepting_orders' => false]);
        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('city', 120);
            $table->string('city_key', 120);
            $table->char('state', 2);
            $table->string('neighborhood', 120)->nullable();
            $table->string('neighborhood_key', 120)->nullable();
            $table->char('cep_start', 8)->nullable();
            $table->char('cep_end', 8)->nullable();
            $table->bigInteger('fee_cents');
            $table->bigInteger('minimum_cents');
            $table->boolean('is_active')->default(false);
            $table->integer('version')->default(1);
            $table->timestampsTz();
            $table->index(['is_active', 'city_key', 'state']);
        });
        DB::statement("ALTER TABLE delivery_areas ADD CONSTRAINT delivery_area_values CHECK (fee_cents BETWEEN 0 AND 100000000 AND minimum_cents BETWEEN 0 AND 100000000 AND version > 0 AND (neighborhood_key IS NOT NULL OR cep_start IS NOT NULL) AND ((cep_start IS NULL AND cep_end IS NULL) OR (cep_start ~ '^[0-9]{8}$' AND cep_end ~ '^[0-9]{8}$' AND cep_start <= cep_end)))");
        Schema::create('delivery_accounts', function (Blueprint $table) {
            $table->id();
            $table->text('phone');
            $table->text('address');
            $table->jsonb('coverage');
            $table->bigInteger('fee_cents');
            $table->bigInteger('minimum_cents');
            $table->foreignId('courier_id')->nullable()->constrained('users');
            $table->timestampTz('opened_at');
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users');
            $table->index(['courier_id', 'closed_at']);
        });
        DB::statement('ALTER TABLE delivery_accounts ADD CONSTRAINT delivery_account_values CHECK (fee_cents BETWEEN 0 AND 100000000 AND minimum_cents BETWEEN 0 AND 100000000)');
        Schema::create('delivery_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->unsignedInteger('auth_version')->nullable();
            $table->string('client_hash', 64)->nullable();
            $table->text('phone');
            $table->text('address');
            $table->jsonb('coverage');
            $table->jsonb('request');
            $table->jsonb('snapshot');
            $table->bigInteger('total_cents');
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at')->index();
        });
        DB::statement('ALTER TABLE delivery_intents ADD CONSTRAINT delivery_intent_actor CHECK ((actor_id IS NOT NULL AND auth_version IS NOT NULL AND client_hash IS NULL) OR (actor_id IS NULL AND auth_version IS NULL AND client_hash IS NOT NULL))');
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->foreignId('delivery_account_id')->nullable()->unique()->constrained('delivery_accounts');
            $table->uuid('delivery_intent_id')->nullable()->unique();
            $table->foreign('delivery_intent_id')->references('id')->on('delivery_intents');
            $table->timestampTz('waiting_courier_at')->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
        });
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_order_values_check');
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_order_actor_check');
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_order_values_check CHECK (total_cents >= 0 AND currency='BRL' AND ((modality='dining_room' AND table_id IS NOT NULL AND session_id IS NOT NULL AND table_number IS NOT NULL AND intent_id IS NOT NULL AND pickup_account_id IS NULL AND pickup_intent_id IS NULL AND delivery_account_id IS NULL AND delivery_intent_id IS NULL AND status IN ('confirmed','preparing','ready','served','cancelled')) OR (modality='pickup' AND table_id IS NULL AND session_id IS NULL AND table_number IS NULL AND intent_id IS NULL AND pickup_account_id IS NOT NULL AND pickup_intent_id IS NOT NULL AND delivery_account_id IS NULL AND delivery_intent_id IS NULL AND status IN ('confirmed','preparing','ready','collected','cancelled')) OR (modality='delivery' AND table_id IS NULL AND session_id IS NULL AND table_number IS NULL AND intent_id IS NULL AND pickup_account_id IS NULL AND pickup_intent_id IS NULL AND delivery_account_id IS NOT NULL AND delivery_intent_id IS NOT NULL AND status IN ('confirmed','preparing','ready','waiting_courier','dispatched','delivered','cancelled'))))");
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_order_actor_check CHECK ((source='staff' AND actor_id IS NOT NULL AND client_hash IS NULL) OR (source='qr' AND modality='dining_room' AND actor_id IS NULL AND client_hash IS NOT NULL) OR (source='web' AND modality IN ('pickup','delivery') AND actor_id IS NULL AND client_hash IS NOT NULL))");
        foreach (['cash_payment_intents', 'cash_payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('delivery_account_id')->nullable()->constrained('delivery_accounts'));
            DB::statement("ALTER TABLE {$name} DROP CONSTRAINT {$name}_account_check");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_account_check CHECK (num_nonnulls(session_id, pickup_account_id, delivery_account_id) = 1)");
        }
        Schema::table('cash_payments', fn (Blueprint $table) => $table->unique('delivery_account_id'));
        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->foreignId('order_id')->nullable()->constrained('dining_orders');
            $table->string('action', 40);
            $table->uuid('operation_id')->nullable()->unique();
            $table->string('fingerprint', 64)->nullable();
            $table->jsonb('details');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('delivery_accounts')->exists() || DB::table('delivery_intents')->exists() || DB::table('delivery_areas')->exists() || DB::table('delivery_events')->exists()) {
            throw new RuntimeException('Há histórico de entrega. Planeje restauração/compatibilidade antes de reverter.');
        }
        Schema::dropIfExists('delivery_events');
        foreach (['cash_payments', 'cash_payment_intents'] as $name) {
            DB::statement("ALTER TABLE {$name} DROP CONSTRAINT {$name}_account_check");
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('delivery_account_id'));
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_account_check CHECK (num_nonnulls(session_id, pickup_account_id) = 1)");
        }
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_order_values_check');
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_order_actor_check');
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_intent_id']);
            $table->dropColumn(['delivery_intent_id', 'waiting_courier_at', 'dispatched_at', 'delivered_at']);
            $table->dropConstrainedForeignId('delivery_account_id');
        });
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_order_values_check CHECK (total_cents >= 0 AND currency='BRL' AND ((modality='dining_room' AND table_id IS NOT NULL AND session_id IS NOT NULL AND table_number IS NOT NULL AND intent_id IS NOT NULL AND pickup_account_id IS NULL AND pickup_intent_id IS NULL AND status IN ('confirmed','preparing','ready','served','cancelled')) OR (modality='pickup' AND table_id IS NULL AND session_id IS NULL AND table_number IS NULL AND intent_id IS NULL AND pickup_account_id IS NOT NULL AND pickup_intent_id IS NOT NULL AND status IN ('confirmed','preparing','ready','collected','cancelled'))))");
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_order_actor_check CHECK ((source='staff' AND actor_id IS NOT NULL AND client_hash IS NULL) OR (source='qr' AND modality='dining_room' AND actor_id IS NULL AND client_hash IS NOT NULL) OR (source='web' AND modality='pickup' AND actor_id IS NULL AND client_hash IS NOT NULL))");
        Schema::dropIfExists('delivery_intents');
        Schema::dropIfExists('delivery_accounts');
        Schema::dropIfExists('delivery_areas');
        Schema::dropIfExists('delivery_settings');
    }
};
