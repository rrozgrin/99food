<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura de categorias por loja/menu para publicacao de itens na 99Food.
 *
 * Tabela no banco mysql_marketplace.
 */
return new class extends Migration
{
    /**
     * Cria tabela de categorias por loja/menu.
     */
    public function up(): void
    {
        Schema::connection('mysql_marketplace')->create('food99_shop_categories', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_shop_id')->comment('FK logica para food99_shops.id');
            $table->unsignedBigInteger('food99_shop_menu_id')->comment('FK logica para food99_shop_menus.id');
            $table->string('app_category_id', 100)->comment('ID da categoria enviado para 99Food');
            $table->string('category_name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['food99_shop_id', 'app_category_id'], 'uk_food99_shop_categories_shop_app_category');
            $table->index(['food99_shop_menu_id', 'sort_order'], 'idx_food99_shop_categories_menu_sort');
            $table->index(['food99_shop_id', 'is_active'], 'idx_food99_shop_categories_shop_active');
        });

        if (Schema::connection('mysql_marketplace')->hasTable('food99_shops')) {
            Schema::connection('mysql_marketplace')->table('food99_shop_categories', function (Blueprint $table): void {
                $table->foreign('food99_shop_id', 'fk_food99_shop_categories_shop')
                    ->references('id')
                    ->on('food99_shops')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::connection('mysql_marketplace')->hasTable('food99_shop_menus')) {
            Schema::connection('mysql_marketplace')->table('food99_shop_categories', function (Blueprint $table): void {
                $table->foreign('food99_shop_menu_id', 'fk_food99_shop_categories_menu')
                    ->references('id')
                    ->on('food99_shop_menus')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Remove tabela de categorias por loja/menu.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_shop_categories');
    }
};

