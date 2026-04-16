<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de itens por loja para publicacao na 99Food.
 *
 * Mantem referencia ao produto/grade do base_erp
 * sem criar FK fisica entre bancos.
 */
return new class extends Migration
{
    /**
     * Cria tabela de itens por loja.
     */
    public function up(): void
    {
        Schema::connection('mysql_marketplace')->create('food99_shop_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_shop_id')->comment('FK logica para food99_shops.id');
            $table->unsignedBigInteger('food99_shop_category_id')->nullable()->comment('FK logica para food99_shop_categories.id');

            $table->unsignedBigInteger('id_cadastro')->nullable()->comment('Referencia da loja no base_erp');
            $table->unsignedBigInteger('id_produto')->comment('Referencia produto.id no base_erp');
            $table->unsignedBigInteger('id_grade')->nullable()->comment('Referencia grade.id_grade no base_erp');

            $table->string('app_item_id', 120)->comment('ID do item enviado para 99Food');
            $table->string('app_external_id', 255)->nullable();
            $table->string('item_name', 50);
            $table->string('short_desc', 300)->nullable();
            $table->string('head_img', 300)->nullable();

            $table->enum('price_source', ['produto', 'grade', 'override'])->default('grade');
            $table->decimal('price_amount', 12, 5)->nullable()->comment('Preco em unidade monetaria');
            $table->unsignedBigInteger('price_cents')->nullable()->comment('Preco no menor denominador para envio API');
            $table->unsignedInteger('tax_rate')->nullable()->comment('Taxa por 10000 conforme contrato da 99Food');

            $table->boolean('is_active')->default(true);
            $table->enum('publish_status', ['draft', 'queued', 'published', 'failed'])->default('draft');
            $table->timestamp('last_published_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['food99_shop_id', 'app_item_id'], 'uk_food99_shop_items_shop_app_item');
            $table->index(['food99_shop_id', 'publish_status'], 'idx_food99_shop_items_shop_status');
            $table->index(['food99_shop_category_id', 'is_active'], 'idx_food99_shop_items_category_active');
            $table->index(['id_produto', 'id_grade'], 'idx_food99_shop_items_prod_grade');
        });

        if (Schema::connection('mysql_marketplace')->hasTable('food99_shops')) {
            Schema::connection('mysql_marketplace')->table('food99_shop_items', function (Blueprint $table): void {
                $table->foreign('food99_shop_id', 'fk_food99_shop_items_shop')
                    ->references('id')
                    ->on('food99_shops')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::connection('mysql_marketplace')->hasTable('food99_shop_categories')) {
            Schema::connection('mysql_marketplace')->table('food99_shop_items', function (Blueprint $table): void {
                $table->foreign('food99_shop_category_id', 'fk_food99_shop_items_category')
                    ->references('id')
                    ->on('food99_shop_categories')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Remove tabela de itens por loja.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_shop_items');
    }
};

