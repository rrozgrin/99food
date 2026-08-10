<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $schema = Schema::connection('mysql');

        if (! $schema->hasTable('webc_roles')) {
            $schema->create('webc_roles', function (Blueprint $table): void {
                $table->unsignedSmallInteger('id')->autoIncrement();
                $table->string('nome', 50)->unique()->comment('Ex: admin, gerente, vendedor, caixa');
                $table->string('descricao', 150)->nullable();
                $table->char('ativo', 1)->default('S')->comment('S = ativo, N = inativo');
            });
        }

        if (! $schema->hasTable('webc_permissoes')) {
            $schema->create('webc_permissoes', function (Blueprint $table): void {
                $table->unsignedSmallInteger('id')->autoIncrement();
                $table->string('nome', 100)->unique()->comment('Ex: clientes.ver, vendas.cancelar');
                $table->string('descricao', 200)->nullable();
                $table->string('modulo', 50)->comment('Ex: clientes, vendas, caixa, financeiro');
                $table->index('modulo');
            });
        }

        if (! $schema->hasTable('webc_role_permissao')) {
            $schema->create('webc_role_permissao', function (Blueprint $table): void {
                $table->unsignedSmallInteger('role_id');
                $table->unsignedSmallInteger('permissao_id');
                $table->primary(['role_id', 'permissao_id']);
                $table->foreign('role_id')->references('id')->on('webc_roles')->cascadeOnDelete();
                $table->foreign('permissao_id')->references('id')->on('webc_permissoes')->cascadeOnDelete();
            });
        }

        if (! $schema->hasTable('webc_usuario_role')) {
            $schema->create('webc_usuario_role', function (Blueprint $table): void {
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedSmallInteger('role_id');
                $table->primary(['usuario_id', 'role_id']);
                $table->foreign('role_id')->references('id')->on('webc_roles')->cascadeOnDelete();
            });
        }

        // Roles padrão do sistema
        DB::connection('mysql')->table('webc_roles')->upsert([
            ['nome' => 'admin',      'descricao' => 'Acesso total ao sistema', 'ativo' => 'S'],
            ['nome' => 'gerente',    'descricao' => 'Gestão completa, exceto configurações do sistema', 'ativo' => 'S'],
            ['nome' => 'vendedor',   'descricao' => 'Vendas, clientes, pedidos e orçamentos', 'ativo' => 'S'],
            ['nome' => 'caixa',      'descricao' => 'Frente de caixa e formas de pagamento', 'ativo' => 'S'],
            ['nome' => 'tecnico',    'descricao' => 'Assistência técnica e ordens de serviço', 'ativo' => 'S'],
            ['nome' => 'financeiro', 'descricao' => 'Relatórios financeiros e contas', 'ativo' => 'S'],
            ['nome' => 'estoque',    'descricao' => 'Produtos, fornecedores e movimentações de estoque', 'ativo' => 'S'],
        ], ['nome'], ['descricao', 'ativo']);

        // Permissão master (permite tudo)
        DB::connection('mysql')->table('webc_permissoes')->upsert([
            ['nome' => 'admin.tudo', 'descricao' => 'Permissão total — bypass de todas as verificações', 'modulo' => 'admin'],
        ], ['nome'], ['descricao', 'modulo']);

        // Associa permissão master ao role admin
        $connection = DB::connection('mysql');
        $roleId = $connection->table('webc_roles')->where('nome', 'admin')->value('id');
        $permissionId = $connection->table('webc_permissoes')->where('nome', 'admin.tudo')->value('id');

        if ($roleId !== null && $permissionId !== null) {
            $connection->table('webc_role_permissao')->updateOrInsert([
                'role_id' => $roleId,
                'permissao_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql');
        $schema->dropIfExists('webc_usuario_role');
        $schema->dropIfExists('webc_role_permissao');
        $schema->dropIfExists('webc_permissoes');
        $schema->dropIfExists('webc_roles');
    }
};
