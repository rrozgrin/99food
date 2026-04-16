<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model de perfil de acesso (Role) do ERP.
 *
 * Roles padrão do sistema:
 *   - admin        — acesso total ao sistema
 *   - gerente      — gestão completa, exceto configurações do sistema
 *   - vendedor     — vendas, clientes, pedidos, orçamentos
 *   - caixa        — frente de caixa e formas de pagamento
 *   - tecnico      — assistência técnica e serviços
 *   - financeiro   — relatórios financeiros e contas a pagar/receber
 *   - estoque      — produtos, fornecedores e movimentações
 *
 * Tabela: webc_roles
 */
class Role extends Model
{
    protected $table = 'webc_roles';

    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
    ];

    public $timestamps = false;

    /**
     * Permissões associadas a este role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Permission::class,
            table: 'webc_role_permissao',
            foreignPivotKey: 'role_id',
            relatedPivotKey: 'permissao_id',
        );
    }

    /**
     * Usuários com este role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            related: User::class,
            table: 'webc_usuario_role',
            foreignPivotKey: 'role_id',
            relatedPivotKey: 'usuario_id',
        );
    }

    /**
     * Verifica se este role possui uma permissão específica.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('nome', $permission);
    }
}
