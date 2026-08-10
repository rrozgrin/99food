<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura de menu por loja para publicacao de itens na 99Food.
 *
 * Tabela no banco mysql_marketplace.
 */
return new class extends Migration
{
    /**
     * Cria tabela de menus por loja.
     */
    public function up(): void
    {
        Schema::connection('mysql_marketplace')->create('food99_shop_menus', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_shop_id')->comment('FK logica para food99_shops.id');
            $table->string('app_menu_id', 100)->comment('ID do menu enviado para 99Food');
            $table->string('menu_name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['food99_shop_id', 'app_menu_id'], 'uk_food99_shop_menus_shop_app_menu');
            $table->index(['food99_shop_id', 'is_active'], 'idx_food99_shop_menus_shop_active');
        });

        if (Schema::connection('mysql_marketplace')->hasTable('food99_shops')) {
            Schema::connection('mysql_marketplace')->table('food99_shop_menus', function (Blueprint $table): void {
                $table->foreign('food99_shop_id', 'fk_food99_shop_menus_shop')
                    ->references('id')
                    ->on('food99_shops')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Remove tabela de menus por loja.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_shop_menus');
    }
};
