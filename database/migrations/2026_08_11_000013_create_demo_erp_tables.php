<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! (bool) config('services.food99.demo_mode', false)) {
            return;
        }

        $schema = Schema::connection('mysql');

        if (! $schema->hasTable('cliente')) {
            $schema->create('cliente', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_cadastro')->index();
                $table->char('tipo_pessoa', 1)->default('F');
                $table->string('nome', 120);
                $table->string('email', 150)->nullable();
                $table->string('telefone', 20)->nullable();
                $table->string('celular', 20)->nullable();
                $table->char('ativo', 1)->default('A');
                $table->index(['id_cadastro', 'nome', 'telefone'], 'idx_demo_cliente_lookup');
            });
        }

        if (! $schema->hasTable('produto')) {
            $schema->create('produto', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('descricao', 255);
                $table->unsignedBigInteger('id_cadastro')->index();
                $table->unsignedBigInteger('id_usuario')->nullable();
                $table->dateTime('data_cadastro')->nullable();
                $table->boolean('ativo')->default(true);
                $table->string('codigo_barra', 64)->nullable()->index();
                $table->string('barra', 64)->nullable();
                $table->string('ean', 64)->nullable();
                $table->string('identificacao_interna', 60)->nullable();
                $table->decimal('custo', 12, 2)->default(0);
                $table->decimal('custo_medio_venda', 12, 2)->default(0);
                $table->decimal('custo_medio_venda_atacado', 12, 2)->default(0);
                $table->decimal('qtd_minima', 12, 3)->default(0);
                $table->decimal('locacao_quantidade', 12, 3)->default(0);
            });
        }

        if (! $schema->hasTable('grade')) {
            $schema->create('grade', function (Blueprint $table): void {
                $table->bigIncrements('id_grade');
                $table->unsignedBigInteger('id_cadastro')->index();
                $table->unsignedBigInteger('id_produto')->index();
                $table->string('codigo_barra', 64)->nullable();
                $table->string('codigo_interno', 64)->nullable();
                $table->decimal('valor_custo', 12, 2)->default(0);
                $table->decimal('valor_varejo_aprazo', 12, 2)->default(0);
                $table->char('ativo', 1)->default('S');
            });
        }

        if (! $schema->hasTable('venda')) {
            $schema->create('venda', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_tipo_venda')->default(7);
                $table->unsignedBigInteger('id_cadastro')->index();
                $table->unsignedBigInteger('id_usuario')->nullable();
                $table->unsignedBigInteger('id_cliente')->nullable();
                $table->date('data_venda')->nullable();
                $table->time('hora_venda')->nullable();
                $table->char('situacao', 1)->default('A');
                $table->unsignedInteger('tipo_pgto')->nullable();
                $table->string('origem_venda', 30)->default('B2W');
                $table->char('pago', 1)->default('N');
                $table->unsignedBigInteger('id_placa')->default(0);
                $table->string('descricao_venda', 255)->nullable();
                $table->string('observacao', 255)->nullable();
                $table->index(['id_cadastro', 'origem_venda', 'descricao_venda'], 'idx_demo_venda_food99_marker');
            });
        }

        if (! $schema->hasTable('venda_itens')) {
            $schema->create('venda_itens', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->decimal('qtd', 12, 3)->default(1);
                $table->unsignedBigInteger('id_venda')->index();
                $table->unsignedBigInteger('id_produto')->nullable();
                $table->unsignedBigInteger('id_grade')->nullable();
                $table->string('nome_produto', 255)->nullable();
                $table->decimal('preco_tabela', 12, 2)->default(0);
                $table->decimal('preco_venda', 12, 2)->default(0);
                $table->string('codigo_barra', 64)->nullable();
                $table->unsignedBigInteger('id_cadastro')->index();
            });
        }

        if (! $schema->hasTable('venda_informacoes')) {
            $schema->create('venda_informacoes', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_venda')->index();
                $table->decimal('valor_frete', 12, 2)->default(0);
                $table->unsignedInteger('volumes')->default(1);
                $table->unsignedBigInteger('id_cadastro')->index();
                $table->string('info_adicional', 255)->nullable();
            });
        }

        if (! $schema->hasTable('venda_pagamento')) {
            $schema->create('venda_pagamento', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_venda')->index();
                $table->unsignedInteger('id_forma_pgto')->default(210);
                $table->decimal('valor_pgto', 12, 2)->default(0);
                $table->unsignedInteger('qtd_parcela')->default(1);
                $table->unsignedBigInteger('id_cadastro')->index();
            });
        }
    }

    public function down(): void
    {
        if (! (bool) config('services.food99.demo_mode', false)) {
            return;
        }

        $schema = Schema::connection('mysql');
        $schema->dropIfExists('venda_pagamento');
        $schema->dropIfExists('venda_informacoes');
        $schema->dropIfExists('venda_itens');
        $schema->dropIfExists('venda');
        $schema->dropIfExists('grade');
        $schema->dropIfExists('produto');
        $schema->dropIfExists('cliente');
    }
};
