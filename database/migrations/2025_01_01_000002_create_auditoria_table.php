<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela de auditoria do ERP.
 *
 * Registra automaticamente todas as operações de criação, alteração e exclusão
 * em models que utilizam a trait Auditable.
 *
 * Campos JSON (payload_antes/payload_depois) armazenam o estado antes e após
 * a operação, com campos sensíveis automaticamente removidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql');

        if ($schema->hasTable('webc_auditoria')) {
            return;
        }

        $schema->create('webc_auditoria', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('tabela', 100)->comment('Nome da tabela auditada');
            $table->string('registro_id', 50)->comment('ID (PK) do registro alterado');
            $table->enum('acao', ['criado', 'alterado', 'excluido']);
            $table->json('payload_antes')->nullable()->comment('Estado anterior à operação');
            $table->json('payload_depois')->nullable()->comment('Estado após a operação');
            $table->unsignedBigInteger('usuario_id')->nullable()->comment('ID do usuário responsável');
            $table->string('usuario_login', 100)->nullable();
            $table->ipAddress('ip_origem')->nullable();
            $table->dateTime('criado_em')->useCurrent();

            // Índices para consultas frequentes
            $table->index(['tabela', 'registro_id'], 'idx_auditoria_registro');
            $table->index('usuario_id', 'idx_auditoria_usuario');
            $table->index('criado_em', 'idx_auditoria_data');
            $table->index('acao', 'idx_auditoria_acao');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('webc_auditoria');
    }
};
