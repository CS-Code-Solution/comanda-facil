<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['operations_outbox', 'order_history', 'cash_events'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->uuid('correlation_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        foreach (['operations_outbox', 'order_history', 'cash_events'] as $name) {
            if (DB::table($name)->whereNotNull('correlation_id')->exists()) {
                throw new RuntimeException('Há correlação operacional registrada; preserve a trilha antes de planejar downgrade.');
            }
        }
        foreach (['operations_outbox', 'order_history', 'cash_events'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('correlation_id'));
        }
    }
};
