<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria credenciais da aplicacao 99Food por ambiente.
 *
 * Tabela no banco mysql_marketplace por ser integracao externa.
 */
return new class extends Migration
{
    /**
     * Cria a tabela de credenciais da 99Food.
     */
    public function up(): void
    {
        if (Schema::connection('mysql_marketplace')->hasTable('food99_app_credentials')) {
            return;
        }

        Schema::connection('mysql_marketplace')->create('food99_app_credentials', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('environment')->default('sandbox');
            $table->string('country_code', 8)->nullable();
            $table->string('app_id');
            $table->text('app_secret');
            $table->string('base_url')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('environment', 'idx_food99_app_credentials_environment');
            $table->unique(['environment', 'app_id'], 'uk_food99_app_credentials_env_app_id');
        });
    }

    /**
     * Remove a tabela de credenciais da 99Food.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_app_credentials');
    }
};
