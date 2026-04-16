<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria vinculo entre lojas do ERP Hub e 99Food.
 *
 * Tabela no banco mysql_marketplace por representar mapeamento de integracao.
 */
return new class extends Migration
{
    /**
     * Cria a tabela de lojas mapeadas com a 99Food.
     */
    public function up(): void
    {
        Schema::connection('mysql_marketplace')->create('food99_shops', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_app_credential_id');
            $table->unsignedBigInteger('id_cadastro')->comment('ID da loja/cadastro no ERP Hub');
            $table->string('app_shop_id');
            $table->string('food99_shop_id')->nullable();
            $table->string('name')->nullable();
            $table->string('binding_status')->default('pending');
            $table->string('auth_status')->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('food99_app_credential_id', 'idx_food99_shops_credential_id');
            $table->index('id_cadastro', 'idx_food99_shops_id_cadastro');
            $table->unique(['food99_app_credential_id', 'app_shop_id'], 'uk_food99_shops_credential_app_shop');

            $table->foreign('food99_app_credential_id', 'fk_food99_shops_app_credential')
                ->references('id')
                ->on('food99_app_credentials')
                ->cascadeOnDelete();
        });
    }

    /**
     * Remove a tabela de lojas mapeadas com a 99Food.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_shops');
    }
};
