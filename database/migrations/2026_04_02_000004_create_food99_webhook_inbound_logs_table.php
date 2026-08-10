<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria logs de entrada dos webhooks da 99Food.
 *
 * Tabela no banco mysql_marketplace para observabilidade da integracao.
 */
return new class extends Migration
{
    /**
     * Cria tabela de logs de webhook recebidos.
     */
    public function up(): void
    {
        if (Schema::connection('mysql_marketplace')->hasTable('food99_webhook_inbound_logs')) {
            return;
        }

        Schema::connection('mysql_marketplace')->create('food99_webhook_inbound_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_app_credential_id')->nullable();
            $table->unsignedBigInteger('food99_shop_id')->nullable();
            $table->string('event_name')->nullable();
            $table->string('request_id')->nullable();
            $table->string('status')->default('received');
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('food99_app_credential_id', 'idx_food99_webhook_logs_credential_id');
            $table->index('food99_shop_id', 'idx_food99_webhook_logs_shop_id');
            $table->index('request_id', 'idx_food99_webhook_logs_request_id');

            $table->foreign('food99_app_credential_id', 'fk_food99_webhook_logs_credential')
                ->references('id')
                ->on('food99_app_credentials')
                ->nullOnDelete();

            $table->foreign('food99_shop_id', 'fk_food99_webhook_logs_shop')
                ->references('id')
                ->on('food99_shops')
                ->nullOnDelete();
        });
    }

    /**
     * Remove tabela de logs de webhook.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_webhook_inbound_logs');
    }
};
