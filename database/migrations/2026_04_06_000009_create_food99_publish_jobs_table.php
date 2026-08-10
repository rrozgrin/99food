<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log operacional de publicacoes/sincronizacoes com a 99Food.
 *
 * Serve para rastrear payload de envio, retorno e erros.
 */
return new class extends Migration
{
    /**
     * Cria tabela de jobs de publicacao.
     */
    public function up(): void
    {
        Schema::connection('mysql_marketplace')->create('food99_publish_jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('food99_shop_id')->comment('FK logica para food99_shops.id');
            $table->enum('event_type', ['upload', 'upload_v2', 'update', 'update_status', 'sync'])->default('upload');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('request_id', 120)->nullable();
            $table->string('task_id', 120)->nullable();
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['food99_shop_id', 'status'], 'idx_food99_publish_jobs_shop_status');
            $table->index('request_id', 'idx_food99_publish_jobs_request_id');
            $table->index('task_id', 'idx_food99_publish_jobs_task_id');
            $table->index('event_type', 'idx_food99_publish_jobs_event_type');
        });

        if (Schema::connection('mysql_marketplace')->hasTable('food99_shops')) {
            Schema::connection('mysql_marketplace')->table('food99_publish_jobs', function (Blueprint $table): void {
                $table->foreign('food99_shop_id', 'fk_food99_publish_jobs_shop')
                    ->references('id')
                    ->on('food99_shops')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Remove tabela de jobs de publicacao.
     */
    public function down(): void
    {
        Schema::connection('mysql_marketplace')->dropIfExists('food99_publish_jobs');
    }
};
