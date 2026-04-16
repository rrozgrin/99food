<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria tokens de autenticacao por loja da 99Food.
 *
 * Tabela no banco mysql_marketplace por armazenar credenciais externas.
 */
return new class extends Migration
{
    /**
     * Cria tabela de token por loja.
     */
    public function up(): void
    {
        Schema::connection('mysql_marketplace')->create('food99_shop_tokens', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_shop_id')->nullable()->unique()->comment('FK para food99_shops.id');
            $table->string('app_shop_id')->nullable()->unique()->comment('ID da loja na app da 99Food, util para lookup direto');
            $table->text('auth_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('last_retrieved_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('food99_shop_id', 'fk_food99_shop_tokens_food99_shop')
                ->references('id')
                ->on('food99_shops')
                ->nullOnDelete();
        });
    }

    /**
     * Remove tabela de tokens por loja.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_shop_tokens');
    }
};
