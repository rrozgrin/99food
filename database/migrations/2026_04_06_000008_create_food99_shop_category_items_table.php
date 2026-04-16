<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot entre categorias e itens da 99Food por loja.
 *
 * Permite ordenar itens por categoria no momento de publicar.
 */
return new class extends Migration
{
    /**
     * Cria tabela pivot categoria-item.
     */
    public function up(): void
    {
        Schema::connection('mysql_marketplace')->create('food99_shop_category_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_shop_category_id')->comment('FK logica para food99_shop_categories.id');
            $table->unsignedBigInteger('food99_shop_item_id')->comment('FK logica para food99_shop_items.id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['food99_shop_category_id', 'food99_shop_item_id'],
                'uk_food99_shop_category_items_category_item',
            );
            $table->index(['food99_shop_category_id', 'sort_order'], 'idx_food99_shop_category_items_sort');
        });

        if (Schema::connection('mysql_marketplace')->hasTable('food99_shop_categories')) {
            Schema::connection('mysql_marketplace')->table('food99_shop_category_items', function (Blueprint $table): void {
                $table->foreign('food99_shop_category_id', 'fk_food99_shop_category_items_category')
                    ->references('id')
                    ->on('food99_shop_categories')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::connection('mysql_marketplace')->hasTable('food99_shop_items')) {
            Schema::connection('mysql_marketplace')->table('food99_shop_category_items', function (Blueprint $table): void {
                $table->foreign('food99_shop_item_id', 'fk_food99_shop_category_items_item')
                    ->references('id')
                    ->on('food99_shop_items')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Remove tabela pivot categoria-item.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_shop_category_items');
    }
};

