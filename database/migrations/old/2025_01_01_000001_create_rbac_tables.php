<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de controle de acesso baseado em roles (RBAC).
 *
 * Tabelas criadas:
 *   - webc_roles           — perfis de acesso (admin, gerente, vendedor, etc.)
 *   - webc_permissoes      — permissões no formato 'modulo.acao'
 *   - webc_role_permissao  — relação N:N entre roles e permissões
 *   - webc_usuario_role    — relação N:N entre usuários e roles
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webc_roles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->autoIncrement();
            $table->string('nome', 50)->unique()->comment('Ex: admin, gerente, vendedor, caixa');
            $table->string('descricao', 150)->nullable();
            $table->char('ativo', 1)->default('S')->comment('S = ativo, N = inativo');
        });

        Schema::create('webc_permissoes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->autoIncrement();
            $table->string('nome', 100)->unique()->comment('Ex: clientes.ver, vendas.cancelar');
            $table->string('descricao', 200)->nullable();
            $table->string('modulo', 50)->comment('Ex: clientes, vendas, caixa, financeiro');
            $table->index('modulo');
        });

        Schema::create('webc_role_permissao', function (Blueprint $table): void {
            $table->unsignedSmallInteger('role_id');
            $table->unsignedSmallInteger('permissao_id');
            $table->primary(['role_id', 'permissao_id']);
            $table->foreign('role_id')->references('id')->on('webc_roles')->cascadeOnDelete();
            $table->foreign('permissao_id')->references('id')->on('webc_permissoes')->cascadeOnDelete();
        });

        Schema::create('webc_usuario_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedSmallInteger('role_id');
            $table->primary(['usuario_id', 'role_id']);
            $table->foreign('role_id')->references('id')->on('webc_roles')->cascadeOnDelete();
        });

        // Roles padrão do sistema
        DB::table('webc_roles')->insert([
            ['nome' => 'admin',      'descricao' => 'Acesso total ao sistema'],
            ['nome' => 'gerente',    'descricao' => 'Gestão completa, exceto configurações do sistema'],
            ['nome' => 'vendedor',   'descricao' => 'Vendas, clientes, pedidos e orçamentos'],
            ['nome' => 'caixa',      'descricao' => 'Frente de caixa e formas de pagamento'],
            ['nome' => 'tecnico',    'descricao' => 'Assistência técnica e ordens de serviço'],
            ['nome' => 'financeiro', 'descricao' => 'Relatórios financeiros e contas'],
            ['nome' => 'estoque',    'descricao' => 'Produtos, fornecedores e movimentações de estoque'],
        ]);

        // Permissão master (permite tudo)
        DB::table('webc_permissoes')->insert([
            ['nome' => 'admin.tudo', 'descricao' => 'Permissão total — bypass de todas as verificações', 'modulo' => 'admin'],
        ]);

        // Associa permissão master ao role admin
        DB::table('webc_role_permissao')->insert([
            ['role_id' => 1, 'permissao_id' => 1],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('webc_usuario_role');
        Schema::dropIfExists('webc_role_permissao');
        Schema::dropIfExists('webc_permissoes');
        Schema::dropIfExists('webc_roles');
    }
};
