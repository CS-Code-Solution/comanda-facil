<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by_id')->constrained('users');
            $table->foreignId('closed_by_id')->nullable()->constrained('users');
            $table->bigInteger('opening_cents');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->jsonb('closing')->nullable();
            $table->text('closing_reason')->nullable();
            $table->index('opened_at');
        });
        DB::statement('ALTER TABLE cash_shifts ADD CONSTRAINT cash_shift_values CHECK (opening_cents >= 0 AND version > 0 AND ((closed_at IS NULL AND closed_by_id IS NULL AND closing IS NULL) OR (closed_at >= opened_at AND closed_by_id IS NOT NULL AND closing IS NOT NULL)))');
        DB::statement('CREATE UNIQUE INDEX cash_one_open_shift ON cash_shifts ((1)) WHERE closed_at IS NULL');
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->foreignId('cash_shift_id')->nullable()->constrained('cash_shifts');
        });
        Schema::create('cash_payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('session_id')->constrained('dining_sessions');
            $table->foreignId('shift_id')->constrained('cash_shifts');
            $table->foreignId('actor_id')->constrained('users');
            $table->unsignedInteger('auth_version');
            $table->string('fingerprint', 64);
            $table->jsonb('snapshot');
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at');
        });
        Schema::create('cash_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->uuid('intent_id')->unique();
            $table->foreign('intent_id')->references('id')->on('cash_payment_intents');
            $table->foreignId('session_id')->unique()->constrained('dining_sessions');
            $table->foreignId('shift_id')->constrained('cash_shifts');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('method', 10);
            $table->bigInteger('amount_cents');
            $table->bigInteger('received_cents')->nullable();
            $table->bigInteger('change_cents')->default(0);
            $table->string('external_reference', 100)->nullable();
            $table->jsonb('snapshot');
            $table->timestampTz('created_at');
            $table->index(['shift_id', 'method']);
            $table->boolean('has_pending_refund')->default(false)->index();
        });
        DB::statement("ALTER TABLE cash_payments ADD CONSTRAINT cash_payment_values CHECK (amount_cents >= 0 AND method IN ('cash','pix','card') AND ((method='cash' AND received_cents IS NOT NULL AND received_cents >= amount_cents AND change_cents=received_cents-amount_cents) OR (method IN ('pix','card') AND received_cents IS NULL AND change_cents=0)))");
        Schema::create('cash_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('payment_id')->constrained('cash_payments');
            $table->foreignId('shift_id')->constrained('cash_shifts');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('method', 10);
            $table->bigInteger('amount_cents');
            $table->text('reason');
            $table->string('external_reference', 100)->nullable();
            $table->timestampTz('created_at');
            $table->index(['shift_id', 'method']);
        });
        DB::statement("ALTER TABLE cash_refunds ADD CONSTRAINT cash_refund_values CHECK (amount_cents > 0 AND method IN ('cash','pix','card') AND length(trim(reason)) >= 3)");
        Schema::create('cash_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 30);
            $table->string('fingerprint', 64);
            $table->foreignId('actor_id')->constrained('users');
            $table->unsignedBigInteger('result_id');
            $table->timestampTz('created_at');
        });
        Schema::create('cash_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('cash_shifts');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 40);
            $table->jsonb('details');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('cash_shifts')->exists()) {
            throw new RuntimeException('Há histórico financeiro. Planeje restauração/compatibilidade antes de reverter o caixa.');
        }
        foreach (['cash_events', 'cash_operations', 'cash_refunds', 'cash_payments', 'cash_payment_intents'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_shift_id');
        });
        Schema::dropIfExists('cash_shifts');
    }
};
