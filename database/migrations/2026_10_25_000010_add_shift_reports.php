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
            $table->string('actor_role', 20)->nullable();
            $table->index(['cash_shift_id', 'actor_role', 'actor_id', 'table_id'], 'orders_shift_author_report');
        });
        DB::statement("ALTER TABLE dining_orders ADD CONSTRAINT order_actor_role CHECK (actor_role IS NULL OR (source='staff' AND actor_role IN ('gestor','caixa','garcom','cozinha','entregador')))");
        Schema::table('cash_shifts', fn (Blueprint $table) => $table->jsonb('report_snapshot')->nullable());
    }

    public function down(): void
    {
        if (DB::table('cash_shifts')->whereNotNull('report_snapshot')->exists() || DB::table('dining_orders')->whereNotNull('actor_role')->exists()) {
            throw new RuntimeException('Há dados históricos de relatório. Planeje compatibilidade antes de reverter.');
        }
        Schema::table('cash_shifts', fn (Blueprint $table) => $table->dropColumn('report_snapshot'));
        DB::statement('ALTER TABLE dining_orders DROP CONSTRAINT order_actor_role');
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropIndex('orders_shift_author_report');
            $table->dropColumn('actor_role');
        });
    }
};
