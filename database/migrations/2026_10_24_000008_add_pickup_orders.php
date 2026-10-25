<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_settings', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->boolean('accepting_orders')->default(false);
        });
        DB::table('pickup_settings')->insert(['id' => 1, 'accepting_orders' => false]);
        Schema::create('pickup_accounts', function (Blueprint $table) {
            $table->id();
            $table->text('phone');
            $table->timestampTz('opened_at');
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users');
        });
        Schema::create('pickup_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->unsignedInteger('auth_version')->nullable();
            $table->string('client_hash', 64)->nullable();
            $table->text('phone');
            $table->jsonb('request');
            $table->jsonb('snapshot');
            $table->bigInteger('total_cents');
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at')->index();
        });
        DB::statement('ALTER TABLE pickup_intents ADD CONSTRAINT pickup_intent_actor CHECK ((actor_id IS NOT NULL AND auth_version IS NOT NULL AND client_hash IS NULL) OR (actor_id IS NULL AND auth_version IS NULL AND client_hash IS NOT NULL))');
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->uuid('intent_id')->nullable()->change();
            $table->unsignedBigInteger('table_id')->nullable()->change();
            $table->unsignedBigInteger('session_id')->nullable()->change();
            $table->integer('table_number')->nullable()->change();
            $table->foreignId('pickup_account_id')->nullable()->unique()->constrained('pickup_accounts');
            $table->uuid('pickup_intent_id')->nullable()->unique();
            $table->foreign('pickup_intent_id')->references('id')->on('pickup_intents');
            $table->timestampTz('collected_at')->nullable();
        });
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_order_values_check');
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_order_actor_check');
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_orders_status_check');
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_order_values_check CHECK (total_cents >= 0 AND currency='BRL' AND ((modality='dining_room' AND table_id IS NOT NULL AND session_id IS NOT NULL AND table_number IS NOT NULL AND intent_id IS NOT NULL AND pickup_account_id IS NULL AND pickup_intent_id IS NULL AND status IN ('confirmed','preparing','ready','served','cancelled')) OR (modality='pickup' AND table_id IS NULL AND session_id IS NULL AND table_number IS NULL AND intent_id IS NULL AND pickup_account_id IS NOT NULL AND pickup_intent_id IS NOT NULL AND status IN ('confirmed','preparing','ready','collected','cancelled'))))");
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_order_actor_check CHECK ((source='staff' AND actor_id IS NOT NULL AND client_hash IS NULL) OR (source='qr' AND modality='dining_room' AND actor_id IS NULL AND client_hash IS NOT NULL) OR (source='web' AND modality='pickup' AND actor_id IS NULL AND client_hash IS NOT NULL))");
        foreach (['cash_payment_intents', 'cash_payments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('session_id')->nullable()->change();
                $table->foreignId('pickup_account_id')->nullable()->constrained('pickup_accounts');
            });
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_account_check CHECK ((session_id IS NOT NULL AND pickup_account_id IS NULL) OR (session_id IS NULL AND pickup_account_id IS NOT NULL))");
        }
        Schema::table('cash_payments', fn (Blueprint $table) => $table->unique('pickup_account_id'));
        Schema::table('operations_outbox', fn (Blueprint $table) => $table->string('modality', 20)->default('dining_room'));
        Schema::create('pickup_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->foreignId('order_id')->nullable()->constrained('dining_orders');
            $table->string('action', 40);
            $table->jsonb('details');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('pickup_accounts')->exists() || DB::table('pickup_intents')->exists()) {
            throw new RuntimeException('Há histórico de retirada. Planeje restauração/compatibilidade antes de reverter.');
        }
        Schema::dropIfExists('pickup_events');
        Schema::table('operations_outbox', fn (Blueprint $table) => $table->dropColumn('modality'));
        foreach (['cash_payments', 'cash_payment_intents'] as $name) {
            DB::statement("ALTER TABLE {$name} DROP CONSTRAINT {$name}_account_check");
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('pickup_account_id');
                $table->unsignedBigInteger('session_id')->nullable(false)->change();
            });
        }
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_order_values_check');
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT dining_order_actor_check');
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropForeign(['pickup_intent_id']);
            $table->dropColumn(['pickup_intent_id', 'collected_at']);
            $table->dropConstrainedForeignId('pickup_account_id');
            $table->uuid('intent_id')->nullable(false)->change();
            $table->unsignedBigInteger('table_id')->nullable(false)->change();
            $table->unsignedBigInteger('session_id')->nullable(false)->change();
            $table->integer('table_number')->nullable(false)->change();
        });
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_order_values_check CHECK (total_cents >= 0 AND modality='dining_room' AND currency='BRL')");
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_order_actor_check CHECK ((source='qr' AND actor_id IS NULL AND client_hash IS NOT NULL) OR (source='staff' AND actor_id IS NOT NULL AND client_hash IS NULL))");
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT dining_orders_status_check CHECK (status IN ('confirmed','preparing','ready','served','cancelled'))");
        Schema::dropIfExists('pickup_intents');
        Schema::dropIfExists('pickup_accounts');
        Schema::dropIfExists('pickup_settings');
    }
};
