<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('catalog_categories')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 240)->unique();
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->jsonb('composition')->default('[]');
            $table->string('source_ref')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_available')->default(true);
            $table->timestampsTz();
        });
        Schema::create('catalog_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('catalog_products')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('code', 140);
            $table->string('unit', 20)->default('presentation');
            $table->integer('measure_value')->nullable();
            $table->string('pizza_size', 1)->nullable();
            $table->bigInteger('price_cents');
            $table->boolean('is_reviewed')->default(false);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_available')->default(true);
            $table->timestampsTz();
            $table->unique(['product_id', 'code']);
        });
        Schema::create('catalog_option_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->integer('min_selections')->default(0);
            $table->integer('max_selections')->default(1);
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();
        });
        Schema::create('catalog_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('catalog_option_groups')->restrictOnDelete();
            $table->string('name', 120);
            $table->bigInteger('price_cents')->nullable();
            $table->integer('max_quantity')->default(1);
            $table->boolean('is_reviewed')->default(false);
            $table->boolean('is_available')->default(false);
            $table->timestampsTz();
        });
        Schema::create('catalog_product_option_group', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('catalog_products')->restrictOnDelete();
            $table->foreignId('group_id')->constrained('catalog_option_groups')->restrictOnDelete();
            $table->primary(['product_id', 'group_id']);
        });
        Schema::create('catalog_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('scope', 20);
            $table->foreignId('category_id')->nullable()->constrained('catalog_categories')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('catalog_products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('catalog_variants')->restrictOnDelete();
            $table->string('discount_type', 20);
            $table->bigInteger('value');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();
        });
        Schema::create('catalog_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source', 20);
            $table->string('entity', 30);
            $table->bigInteger('entity_id');
            $table->string('action', 30);
            $table->jsonb('before')->nullable();
            $table->jsonb('after');
            $table->timestampTz('occurred_at');
            $table->index(['entity', 'entity_id', 'id']);
        });
        Schema::create('catalog_state', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->bigInteger('revision')->default(0);
        });
        DB::table('catalog_state')->insert(['id' => 1, 'revision' => 0]);
        DB::statement("alter table catalog_products add constraint catalog_product_type_check check (type in ('simple','pizza','combo'))");
        DB::statement('alter table catalog_products add constraint catalog_product_review_check check (not is_active or is_reviewed)');
        DB::statement('alter table catalog_variants add constraint catalog_variant_price_check check (price_cents > 0 and price_cents <= 100000000)');
        DB::statement('alter table catalog_variants add constraint catalog_variant_review_check check (not is_active or is_reviewed)');
        DB::statement("alter table catalog_variants add constraint catalog_pizza_size_check check (pizza_size is null or pizza_size in ('p','g','f'))");
        DB::statement('create unique index catalog_pizza_size_unique on catalog_variants(product_id, pizza_size) where pizza_size is not null');
        DB::statement('alter table catalog_option_groups add constraint catalog_group_selection_check check (min_selections >= 0 and max_selections >= min_selections and max_selections <= 20)');
        DB::statement('alter table catalog_options add constraint catalog_option_price_check check (price_cents is null or price_cents between 0 and 100000000)');
        DB::statement('alter table catalog_options add constraint catalog_option_review_check check (not is_available or (is_reviewed and price_cents is not null))');
        DB::statement('alter table catalog_options add constraint catalog_option_quantity_check check (max_quantity between 1 and 20)');
        DB::statement("alter table catalog_promotions add constraint catalog_discount_check check (discount_type in ('fixed','percentage') and value > 0 and value <= 100000000 and (discount_type <> 'percentage' or value <= 10000))");
        DB::statement('alter table catalog_promotions add constraint catalog_promotion_dates_check check (ends_at > starts_at)');
        DB::statement("alter table catalog_promotions add constraint catalog_promotion_scope_check check ((scope='all' and category_id is null and product_id is null and variant_id is null) or (scope='category' and category_id is not null and product_id is null and variant_id is null) or (scope='product' and product_id is not null and category_id is null and variant_id is null) or (scope='variant' and variant_id is not null and category_id is null and product_id is null))");
    }

    public function down(): void
    {
        foreach (['catalog_state', 'catalog_events', 'catalog_promotions', 'catalog_product_option_group', 'catalog_options', 'catalog_option_groups', 'catalog_variants', 'catalog_products', 'catalog_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
