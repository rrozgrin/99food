<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garante a estrutura ERP exigida pela autenticacao e controle de acesso.
 *
 * A tabela pertence ao banco ERP (`mysql`), nunca ao `mysql_marketplace`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql');

        if (! $schema->hasTable('webc_usuario')) {
            $schema->create('webc_usuario', function (Blueprint $table): void {
                $table->unsignedBigInteger('id')->autoIncrement();
                $table->unsignedBigInteger('id_cadastro')->nullable()->index();
                $table->string('login', 20)->unique();
                $table->string('senha', 255);
                $table->char('ativo', 1)->default('A')->index();
                $table->timestamp('data_criacao')->nullable()->useCurrent();
                $table->dateTime('data_alteracao')->nullable();
            });
        }

        $this->createRbacTables();
        $this->createAuditTable();
        $this->seedRbac();
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('webc_usuario');
    }

    private function createRbacTables(): void
    {
        $schema = Schema::connection('mysql');

        if (! $schema->hasTable('webc_roles')) {
            $schema->create('webc_roles', function (Blueprint $table): void {
                $table->unsignedSmallInteger('id')->autoIncrement();
                $table->string('nome', 50)->unique();
                $table->string('descricao', 150)->nullable();
                $table->char('ativo', 1)->default('S');
            });
        }

        if (! $schema->hasTable('webc_permissoes')) {
            $schema->create('webc_permissoes', function (Blueprint $table): void {
                $table->unsignedSmallInteger('id')->autoIncrement();
                $table->string('nome', 100)->unique();
                $table->string('descricao', 200)->nullable();
                $table->string('modulo', 50);
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
    }

    private function createAuditTable(): void
    {
        $schema = Schema::connection('mysql');

        if ($schema->hasTable('webc_auditoria')) {
            return;
        }

        $schema->create('webc_auditoria', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('tabela', 100);
            $table->string('registro_id', 50);
            $table->enum('acao', ['criado', 'alterado', 'excluido']);
            $table->json('payload_antes')->nullable();
            $table->json('payload_depois')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_login', 100)->nullable();
            $table->ipAddress('ip_origem')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->index(['tabela', 'registro_id'], 'idx_auditoria_registro');
            $table->index('usuario_id', 'idx_auditoria_usuario');
            $table->index('criado_em', 'idx_auditoria_data');
            $table->index('acao', 'idx_auditoria_acao');
        });
    }

    private function seedRbac(): void
    {
        $connection = DB::connection('mysql');

        $connection->table('webc_roles')->upsert([
            ['nome' => 'admin', 'descricao' => 'Acesso total ao sistema'],
            ['nome' => 'gerente', 'descricao' => 'Gestao completa, exceto configuracoes do sistema'],
            ['nome' => 'vendedor', 'descricao' => 'Vendas, clientes, pedidos e orcamentos'],
            ['nome' => 'caixa', 'descricao' => 'Frente de caixa e formas de pagamento'],
            ['nome' => 'tecnico', 'descricao' => 'Assistencia tecnica e ordens de servico'],
            ['nome' => 'financeiro', 'descricao' => 'Relatorios financeiros e contas'],
            ['nome' => 'estoque', 'descricao' => 'Produtos, fornecedores e movimentacoes de estoque'],
        ], ['nome'], ['descricao']);

        $connection->table('webc_permissoes')->upsert([
            ['nome' => 'admin.tudo', 'descricao' => 'Permissao total', 'modulo' => 'admin'],
        ], ['nome'], ['descricao', 'modulo']);

        $roleId = $connection->table('webc_roles')->where('nome', 'admin')->value('id');
        $permissionId = $connection->table('webc_permissoes')->where('nome', 'admin.tudo')->value('id');

        if ($roleId !== null && $permissionId !== null) {
            $connection->table('webc_role_permissao')->updateOrInsert([
                'role_id' => $roleId,
                'permissao_id' => $permissionId,
            ]);
        }
    }
};
